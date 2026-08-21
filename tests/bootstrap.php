<?php

use Dotenv\Dotenv;

$loader = require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');
error_reporting(E_ALL ^ E_NOTICE);

const TEST_ROOT = __DIR__;

if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createUnsafeImmutable(__DIR__)->safeLoad();
}
