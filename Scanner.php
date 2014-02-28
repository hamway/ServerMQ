<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 27.02.14
 * Time: 15:41
 */

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Scanner {
	private $domain;
	private $path;

	/** MQ Config */
	private $mqConfig = array(
		'host' => 'localhost',
		'port' => 5672,
		'user' => 'scanner',
		'pass' => 'scanner',
		'vhost' => '/',
		'exchange' => 'router',
		'queue' => 'msgs',
		'consumer_tag' => 'consumer'
	);
	/** @var \PhpAmqpLib\Connection\AMQPConnection */
	private $mqHandler = false;
	/** @var  \PhpAmqpLib\Channel\AMQPChannel */
	private $mqChannel;
	private $mqDebug = true;
	/** MQ Config End */

	/** @var array List parsed link. */
	private $sitemap = array();
	/** @var array list need parse link. */
	private $needParse = array();

	/** @var mixed Curl handler. */
	private $curl;

	public function __construct($domain, $path = "", $fork = false) {
		$this->domain = $domain;
		$this->path = $path;

		$url = "http://". $domain;

		if ($this->path) {
			$url .= $path;
		}

		if (!$this->mqHandler)
			$this->_connectMQ();

		if (!$fork)
			$this->_addMessageToMQ($url);

		$this->mqChannel->basic_consume(
			$this->mqConfig['queue'],
			$this->mqConfig['consumer_tag'],
			false,
			false,
			false,
			false,
			function($msg) {
				$this->process_message($msg);
			});


		$this->curl = curl_init();
		while(count($this->mqChannel->callbacks)) {
			$this->mqChannel->wait();
		}
	}

	private function process_message($msg) {

		if ($msg->body === 'quit') {
			$msg->delivery_info['channel']->
				basic_cancel($msg->delivery_info['consumer_tag']);
		}

		/** @var $msg \PhpAmqpLib\Message\AMQPMessage */
		echo $msg->body, PHP_EOL;
		$this->scan($msg->body);
		$this->writeParsedLink($msg->body);

		$msg->delivery_info['channel']->
			basic_ack($msg->delivery_info['delivery_tag']);


	}

	private function _connectMQ() {
		$conn = new AMQPConnection(
			$this->mqConfig['host'],
			$this->mqConfig['port'],
			$this->mqConfig['user'],
			$this->mqConfig['pass'],
			$this->mqConfig['vhost']
		);

		if ($conn) {
			$this->mqHandler = $conn;
			$ch = $conn->channel();
			if ($ch) {
				$this->mqChannel = $ch;
				$ch->queue_declare($this->mqConfig['queue'], false, true, false, false);
				$ch->exchange_declare($this->mqConfig['exchange'], 'direct', false, true, false);
				$ch->queue_bind($this->mqConfig['queue'], $this->mqConfig['exchange']);
			}
		}
	}

	private function _addMessageToMQ($message) {
		$msg = new AMQPMessage(trim($message), array('content_type' => 'text/plain', 'delivery_mode' => 2));
		$this->mqChannel->basic_publish($msg, $this->mqConfig['exchange']);
	}

	/** Scanner worker */

	/**
	 * Send curl request to get html by url.
	 * @param $url
	 * @return mixed
	 */
	private function _getUrl($url) {

		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
		return curl_exec($this->curl);
	}

	private function scan($url) {

		$data = $this->_getUrl($url);

		if ($data) {

			$this->addSiteMapUrl($url);

			if(preg_match_all('/<a [^<>]*href=[\'"]([^\'"]+)[\'"][^<>]*>/', $data, $m)) {
				foreach ($m[1] as $links) {
					if(preg_match('/\?reply-to=|#answers|#comments|BanksAutocreditSearchForm|BanksCreditSearchForm|BanksProgrammSearchForm|BanksHypothecSearchForm|\?page=/', $links)) {
						continue;
					}
					$link = false;
					if (preg_match("~^".$this->path.".*?$~", $links))
						$link =  'http://'. $this->domain. $links;
					else if (preg_match('~^http://www.'.$this->domain.$this->path.'.*?$~', $links)) {
						$link = str_replace('www.', '', $links);
					} else if (preg_match('~^http://'.$this->domain.$this->path.'.*?$~', $links)) {
						$link = $links;
					}
					if ($link) {
						$this->addNeedParseUrl(trim($link));
					}
				}
			}
		}
	}

	/**
	 * Add url to list parsed urls.
	 * @param $url
	 */
	private function addSiteMapUrl($url) {
		// Get list already parsed urls
		$this->sitemap = ScannerStorage::get('sitemap');

		if (!in_array($url,$this->sitemap)) {
			$this->sitemap[] = $url;
			ScannerStorage::set('sitemap', $this->sitemap);
		}
	}

	/**
	 * Add url to list need parse url.
	 * @param $url
	 */
	private function addNeedParseUrl($url) {
		// Get parsing list for validation
		$this->needParse = ScannerStorage::get('parsing');

		if (!in_array($url,$this->needParse) && !in_array($url,$this->sitemap)) {
			$this->needParse[] = $url;
			ScannerStorage::set('parsing', $this->needParse);
			// Add link to rabbit
			$this->_addMessageToMQ($url);
		}
	}

	/**
	 * Write link to list parsed link.
	 * @param $url
	 */
	private function writeParsedLink($url) {
		$f = fopen('links.txt', "a+");
		fwrite($f, $url.PHP_EOL);
		fclose($f);
	}

	public function __destruct() {
		$this->mqChannel->close();
		$this->mqHandler->close();
		curl_close($this->curl);
	}
} 