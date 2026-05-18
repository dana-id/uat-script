<?php

namespace DanaUat\Helper;

/**
 * Bootstrap Selenium server when tests run outside runners/run-test-php.sh.
 */
class SeleniumUtil
{
    private const SELENIUM_DOWNLOAD_URL =
        'https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.10.0/selenium-server-4.10.0.jar';

    public static function jarPath(): string
    {
        $fromEnv = getenv('SELENIUM_JAR');
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }

        $home = getenv('HOME');
        if ($home === false || $home === '') {
            $home = '/root';
        }

        return $home . '/.selenium/selenium-server.jar';
    }

    public static function ensureJar(): bool
    {
        $jar = self::jarPath();
        if (file_exists($jar)) {
            return true;
        }

        $dir = dirname($jar);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            echo "Failed to create Selenium directory: {$dir}" . PHP_EOL;
            return false;
        }

        echo "Selenium JAR not found, downloading to {$jar}..." . PHP_EOL;
        $context = stream_context_create(['http' => ['timeout' => 120]]);
        $data = @file_get_contents(self::SELENIUM_DOWNLOAD_URL, false, $context);
        if ($data === false) {
            echo "Failed to download Selenium server. Run tests via ./runners/run-test-php.sh" . PHP_EOL;
            return false;
        }

        if (file_put_contents($jar, $data) === false) {
            echo "Failed to write Selenium JAR to {$jar}" . PHP_EOL;
            return false;
        }

        return true;
    }

    public static function attemptStart(): bool
    {
        if (!self::ensureJar()) {
            return false;
        }

        exec('which java', $output, $returnVar);
        if ($returnVar !== 0) {
            echo "Java not found. Cannot start Selenium server." . PHP_EOL;
            return false;
        }

        exec('pgrep -f "selenium-server"', $output, $returnVar);
        if ($returnVar === 0) {
            return true;
        }

        $jar = self::jarPath();
        echo "Starting Selenium server from {$jar}..." . PHP_EOL;
        exec('java -jar ' . escapeshellarg($jar) . ' standalone > /dev/null 2>&1 &');
        sleep(5);

        return true;
    }

    public static function isReady(string $seleniumUrl): bool
    {
        self::attemptStart();

        $statusUrl = rtrim($seleniumUrl, '/') . '/status';
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && !empty($response)) {
            $json = json_decode($response, true);
            if (isset($json['value']['ready']) && $json['value']['ready'] === true) {
                return true;
            }
        }

        echo "Selenium server not available or not responding correctly. HTTP code: {$httpCode}" . PHP_EOL;
        return false;
    }
}
