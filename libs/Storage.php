<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 03.03.14
 * Time: 17:48
 */

interface Storage {
	public static function _connect();

	public static function clean();

	public static function get($name);

	public static function set($name, $value);

	public static function capacity();

	public static function getParams($name);

	public static function setParams($name, $value) ;

	public function __destruct();
} 