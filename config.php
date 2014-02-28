<?php
/**
 * Created by PhpStorm.
 * User: hamway
 * Date: 27.02.14
 * Time: 15:01
 */

require_once __DIR__.'/php-amqplib-master/vendor/autoload.php';

define('HOST', 'localhost');
define('PORT', 5672);
define('USER', 'guest');
define('PASS', 'guest');
define('VHOST', '/');

//If this is enabled you can see AMQP output on the CLI
define('AMQP_DEBUG', true);