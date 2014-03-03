<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 28.02.14
 * Time: 16:27
 */

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class QueueServer {

	protected static $instance;

	const HOST = 'localhost';
	const PORT = 5672;
	const USER = 'scanner';
	const PASS = 'scanner';
	const VHOST = '/';

	private static $config = array(
		'exchange' => 'router',
		'queue' => 'msgs',
		'consumer_tag' => 'consumer'
	);

	private static $defaultParams = array(
		'content-type' => 'text/plain'
	);

	/** @var \PhpAmqpLib\Connection\AMQPConnection */
	private static $connection = false;
	/** @var  \PhpAmqpLib\Channel\AMQPChannel */
	private static $channel;
	//private $debug = true;

	private static $callback;

	public static function getInstance() {
		return (self::$instance === null) ?
			self::$instance = new self() :
			self::$instance;
	}

	private function _connect() {
		if (self::$connection) return true;

		$conn = new AMQPConnection(
			self::HOST,
			self::PORT,
			self::USER,
			self::PASS,
			self::VHOST
		);

		$config = self::$config;

		if ($conn) {
			self::$connection = $conn;
			$ch = $conn->channel();
			if ($ch) {
				self::$channel = $ch;
				$ch->queue_declare($config['queue'], false, true, false, false);
				$ch->exchange_declare($config['exchange'], 'direct', false, true, false);
				$ch->queue_bind($config['queue'], $config['exchange']);

				$ch->basic_consume(
					$config['queue'],
					$config['consumer_tag'],
					false,
					false,
					false,
					false,
					function($msg) {
						$queue = QueueServer::getInstance();
						$queue->process($msg);
					});
			}
		}
	}

	/**
	 * @param $message \PhpAmqpLib\Message\AMQPMessage
	 */
	public function process($message) {
		if ($message->body === 'quit') {
			$message->delivery_info['channel']->
				basic_cancel($message->delivery_info['consumer_tag']);
		}

		if(self::$callback) {
			$scanner = Scanner::getInstance();
			$scanner->{self::$callback}($message->body);
		}
		$message->delivery_info['channel']->
			basic_ack($message->delivery_info['delivery_tag']);
	}

	public static function addMessage($text, $params = array()) {
		self::_connect();

		$params = array_merge(self::$defaultParams, $params);

		$msg = new AMQPMessage(trim($text), $params);
		self::$channel->basic_publish($msg, self::$config['exchange']);
	}

	public static function getChannel() {
		return self::$channel;
	}

	public static function setCallback($func) {
		self::$callback = $func;
	}

	public function __destruct() {
		self::$channel->close();
		self::$connection->close();
	}
}