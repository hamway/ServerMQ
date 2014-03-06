<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 27.02.14
 * Time: 15:01
 */

require_once 'php-amqplib/vendor/autoload.php';

function _autoload($class) {
	require_once 'libs/'.$class.'.php';
}
spl_autoload_register('_autoload');

function getHelp() {
	echo
	"Usage: php index.php [COMMAND]", PHP_EOL,
	"\tserver - Run as first command", PHP_EOL,
	"\tclient - add worker to server", PHP_EOL,
	"\tcount - show count parsed links", PHP_EOL;
}

$loadConfig = false;
$scanner = new Scanner();

if (!isset($argv[1]))
	$argv[1] = null;

if (!isset($argv[2]))
	$argv[2] = 'default.json';

$config = json_decode(file_get_contents($argv[2]));

if (!empty($config)) {
	$loadConfig = true;
} else {
	getHelp();
}

switch($argv[1]) {
	case "count":
		print_r(ScannerStorage::capacity());
		break;
	case "client":
			$scanner->start($config->host, $config->path, true);
		break;
	case "server":
		$scanner->start($config->host, $config->path );
		break;
	default:
		getHelp();
}

