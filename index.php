<?php

/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 27.02.14
 * Time: 15:01
 */
declare(strict_types=1);

use ServerMQ\Scanner;
use ServerMQ\ScannerStorage;
use ServerMQ\ScannerConfig;

require_once __DIR__ .'/vendor/autoload.php';

class Server {
    /** @var null|Scanner */
    protected $scanner = null;
    /** @var null|ScannerConfig  */
    protected $config = null;

    protected function init() {
        if ($this->scanner === null) {
            $this->scanner = new Scanner();
        }

        $this->initConfig();
    }

    protected function initConfig() {
        if ($this->config === null) {
            $filename = (!isset($argv[2])) ? $argv[2] : 'default.json';

            $config = json_decode(file_get_contents($filename));

            if  ($config) {
                $this->config = new ScannerConfig();

                foreach ($config as $key => $item) {
                    $this->config->$key = $item;
                }
            }
        }
    }

    protected function getCapacity() : string {
        return var_export(ScannerStorage::capacity(), true).PHP_EOL;
    }

    protected function getHelp() {
        echo
        "Usage: php index.php [COMMAND]", PHP_EOL,
        "\tserver - Run as first command", PHP_EOL,
        "\tclient - add worker to server", PHP_EOL,
        "\tcount - show count parsed links", PHP_EOL;
    }

    protected function runClient() {
        $this->init();

        if ($this->config === null || empty($this->config)) {
            $this->getHelp();
            return;
        }

        $this->scanner->start($this->config->host, $this->config->path, true);
    }

    protected function runServer() {
        $this->init();

        if ($this->config === null || empty($this->config)) {
            $this->getHelp();
            return;
        }

        $this->scanner->start($this->config->host, $this->config->path );
    }


    public function run($command) {
        switch($command) {
            case "count":
                echo $this->getCapacity();
                break;
            case "client":
                $this->runClient();
                break;
            case "server":
                $this->runServer();
                break;
            default:
                $this->getHelp();
        }
   }
}

(new Server())->run($argv[1]);