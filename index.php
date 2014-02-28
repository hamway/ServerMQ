<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 27.02.14
 * Time: 15:01
 */

require_once 'php-amqplib/vendor/autoload.php';
require_once 'ScannerStorage.php';
require_once 'QueueServer.php';
require_once 'Scanner.php';

if (!isset($argv[1]))
	$argv[1] = null;

switch($argv[1]) {
	case "count":
		print_r(ScannerStorage::capacity());
		break;
	case "client":
		new Scanner('7gw.ru', '/', true);
		break;
	case "server":
		new Scanner('7gw.ru', '/');
		break;
	default:
		echo "Usage: php index.php [COMMAND]", PHP_EOL, "\tserver - Run as first command", PHP_EOL,
		"\tclient - add worker to server", PHP_EOL, "\tcount - show count parsed links", PHP_EOL;
}

