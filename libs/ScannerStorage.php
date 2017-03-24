<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 28.02.14
 * Time: 11:29
 */
namespace ServerMQ;

class ScannerStorage implements Storage {

	const HOST = 'localhost';
	const PORT = 6379;
	const TIMEOUT = 0.5;
	const FORMAT = 1;

	const JSON = 1;

	/** @var  \Redis */
	private static $redis;

	private static $prefix = 'ServerMQ';

	public static function _connect() {
		if (!self::$redis) {
			self::$redis = new \Redis();
			self::$redis->connect(self::HOST,self::PORT,self::TIMEOUT);
//			self::$redis->_prefix(self::$prefix);
		}
	}

	public  static function clean() {
		self::_connect();

		self::$redis->flushDB();
	}

	public static function get($name) {
		self::_connect();

		$result = self::$redis->get($name);

		if (self::FORMAT == self::JSON) {
			$result = json_decode($result);
		}

		return $result;
	}

	public static function set($name, $value) {
		self::_connect();

		if (self::FORMAT == self::JSON) {
			$value = json_encode($value);
		}

		self::$redis->set($name, $value);
	}

	public static function capacity() {
		self::_connect();

		$keys = self::$redis->keys(self::$prefix . '*');
		$result = array();
		foreach ($keys as $key) {
			$data = self::$redis->get($key);

			if (self::FORMAT == self::JSON) {
				$data = json_decode($data);
			}

			$result[$key] = count($data);
		}

		return $result;
	}

	public static function getParams(string $name) : string {
		$params = self::get('params::'.$name);

		var_dump('params::'.$name, $params);
		if ($params === null) {
		    die;
            return null;
        } else {
            return $params;
        }
	}

	public static function setParams($name, $value) {
		self::set('params::'.$name, $value);
	}

	public function __destruct() {
		if (self::$redis) {
			self::$redis->close();
		}
	}
} 