<?php

// Set error reporting to show all errors except notices and strict standards
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

// Define test root constant
define('TEST_ROOT', __DIR__);

// Include the Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file if dotenv is available
if (class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname(dirname(__DIR__)));
    $dotenv->safeLoad();
}

// Explicitly require helper files to ensure they're loaded in any environment
require_once __DIR__ . '/helper/Util.php';
require_once __DIR__ . '/helper/Assertion.php';
require_once __DIR__ . '/payment_gateway/Scripts/WebAutomation.php';

// Clear any opcache issues that might prevent class reloading
if (function_exists('opcache_reset')) {
    opcache_reset();
}
