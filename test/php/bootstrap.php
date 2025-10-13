<?php

// Set error reporting to show all errors except notices and strict standards
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

// Define test root constant
define('TEST_ROOT', __DIR__);

// Include the Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file if dotenv is available
if (class_exists('\Dotenv\Dotenv')) {
    $projectRoot = dirname(dirname(__DIR__));
    $envFile = $projectRoot . '/.env';
    
    if (file_exists($envFile)) {
        try {
            $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
            $dotenv->safeLoad();
        } catch (\Dotenv\Exception\InvalidFileException $e) {
            $envContent = file_get_contents($envFile);
            $lines = explode("\n", $envContent);
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    if (strpos($key, 'PRIVATE_KEY') !== false) {
                        $value = str_replace('\\n', "\n", $value);
                    }
                    
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
}

// Explicitly require helper files to ensure they're loaded in any environment
require_once __DIR__ . '/helper/Util.php';
require_once __DIR__ . '/helper/Assertion.php';
require_once __DIR__ . '/payment_gateway/Scripts/WebAutomation.php';

// Clear any opcache issues that might prevent class reloading
if (function_exists('opcache_reset')) {
    opcache_reset();
}
