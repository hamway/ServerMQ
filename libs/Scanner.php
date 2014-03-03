<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 27.02.14
 * Time: 15:41
 */

class Scanner {
	private $domain;
	private $path;
	protected static $instance;

	private $sitemap = array();
	private $needParse = array();

	private $curl;

	public static function getInstance() {
		return  (self::$instance === null) ?
			self::$instance = new self() :
			self::$instance;
	}

	public function __construct() {
		$this->domain = ScannerStorage::getParams('domain');
		$this->path = ScannerStorage::getParams('path');
	}

	public function start($domain = false, $path = "", $fork = false) {
		if(!$fork)
			ScannerStorage::clean();

		$this->domain = $domain;
		$this->path = $path;

		ScannerStorage::setParams('domain', $domain);
		ScannerStorage::setParams('path', $path);


		$url = "http://". $domain;

		if ($this->path) {
			$url .= $path;
		}

		if (!$fork)
			QueueServer::addMessage($url);

		$channel = QueueServer::getChannel();
		QueueServer::setCallback('scan');

		while(count($channel->callbacks)) {
			$channel->wait();
		}
	}

	/** Scanner worker */

	/**
	 * Send curl request to get html by url.
	 * @param $url
	 * @return mixed
	 */
	private function _getUrl($url) {

		if(!$this->curl) {
			$this->curl = curl_init();
		}

		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
		return curl_exec($this->curl);
	}

	public function scan($url=null) {

		if (self::$instance === null) new Exception('Call without instance', 1);

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
		if($this->sitemap === null) {
			$this->sitemap = array();
		};
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
		if($this->needParse === null) {
			$this->needParse = array();
		};
		if (!in_array($url,$this->needParse) && !in_array($url,$this->sitemap)) {
			$this->needParse[] = $url;
			ScannerStorage::set('parsing', $this->needParse);
			// Add link to rabbit
			QueueServer::addMessage($url);
		}
	}

	public function __destruct() {
		if ($this->curl)
			curl_close($this->curl);
	}
} 