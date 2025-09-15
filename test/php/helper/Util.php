<?php

namespace DanaUat\Helper;

use Dana\Utils\SnapHeader;
use Dana\ApiException;
use DanaUat\Helper\Exception;

class Util
{   
    /**
     * Function to generate timestamp for Dana API
     * 
     * @param bool $invalidTimestamp Whether to generate an invalid timestamp format
     * @return array [timestamp, timestampForSignature]
     */
    private static function generateTimestamp(bool $invalidTimestamp = false): array
    {
        // Generate timestamp with proper format
        // Set timezone to Jakarta (UTC+7)
        $date = new \DateTime('now', new \DateTimeZone('+0700'));

        // Generate timestamp value for signature or headers
        $timestamp = $invalidTimestamp
            ? $date->format('Y-m-d H:i:sP') // Invalid format (space instead of T)
            : $date->format('Y-m-d\TH:i:sP'); // Valid format

        // Always add timestamp for signature calculation
        $timestampForSignature = $date->format('Y-m-d\TH:i:sP');

        return [$timestamp, $timestampForSignature];
    }

    /**
     * Get request data from a JSON file
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @return array Decoded JSON data
     */
    public static function getRequest(string $jsonPathFile, string $titleCase, string $caseName): array
    {
        $jsonPath = dirname(dirname(dirname(__DIR__))) . '/' . $jsonPathFile;

        if (!file_exists($jsonPath)) {
            throw new \Exception("JSON file not found: {$jsonPath}");
        }

        $jsonString = file_get_contents($jsonPath);
        $jsonData = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error parsing JSON file: ' . json_last_error_msg());
        }

        if (!isset($jsonData[$titleCase][$caseName]['request'])) {
            throw new \Exception("Request data not found for {$titleCase}.{$caseName}");
        }

        if (isset($jsonData[$titleCase][$caseName]['request']['merchantId'])) {
            $jsonData[$titleCase][$caseName]['request']['merchantId'] = getenv('MERCHANT_ID');
        }

        return $jsonData[$titleCase][$caseName]['request'];
    }

    /**
     * Get response data from a JSON file
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the response
     * @param string $caseName The case name for the response
     * @return array Decoded JSON response data
     */
    public static function getResponse(string $jsonPathFile, string $titleCase, string $caseName): array
    {
        $jsonPath = dirname(dirname(dirname(__DIR__))) . '/' . $jsonPathFile;

        if (!file_exists($jsonPath)) {
            throw new \Exception("JSON file not found: {$jsonPath}");
        }

        $jsonString = file_get_contents($jsonPath);
        $jsonData = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error parsing JSON file: ' . json_last_error_msg());
        }

        if (!isset($jsonData[$titleCase][$caseName]['response'])) {
            throw new \Exception("Response data not found for {$titleCase}.{$caseName}");
        }

        return $jsonData[$titleCase][$caseName]['response'];
    }

    /**
     * Function to add delay between test executions
     *
     * @param callable $callback The function to execute
     * @param int $seconds Delay in seconds
     * @return mixed Result of the callback
     */
    public static function withDelay(callable $callback, int $seconds = 1)
    {
        sleep($seconds);
        return $callback();
    }

    /**
     * Returns standard headers used in API requests
     *
     * @param bool $withTimestamp Whether to include X-TIMESTAMP (defaults to true)
     * @return array Dict of standard headers
     */
    public static function getStandardHeaders(bool $withTimestamp = true): array
    {
        $headers = [
            'X-PARTNER-ID' => getenv('X_PARTNER_ID'),
            'CHANNEL-ID' => getenv('CHANNEL_ID'),
            'ORIGIN' => getenv('ORIGIN'),
            'X-EXTERNAL-ID' => uniqid(),
            'Content-Type' => 'application/json'
        ];

        if ($withTimestamp) {
            // Set timezone to Jakarta (UTC+7)
            $date = new \DateTime('now', new \DateTimeZone('+0700'));
            $headers['X-TIMESTAMP'] = $date->format('Y-m-d\TH:i:sP');
        }

        return $headers;
    }

    /**
     * Execute an API request and handle the response, especially for testing purposes
     * This wrapper makes it easier to access error responses and verify them in tests
     *
     * @param string $method HTTP method (GET, POST, etc)
     * @param string $url Full URL to the API endpoint
     * @param array $headers Headers to send with the request
     * @param mixed $requestBody Request body (will be converted to JSON)
     * @param bool $throwOnError Whether to throw exceptions for non-2XX status codes (default: false)
     * @return array Associative array with 'statusCode', 'headers', 'body' and 'jsonBody' (if applicable)
     * @throws \Exception For unexpected errors or if $throwOnError=true and status code is outside 200-299 range
     */
    public static function executeApiRequest(string $method, string $url, array $headers, $requestBody = null): array
    {
        // Create GuzzleHttp client (avoiding dependency on specific SDK implementation)
        $client = new \GuzzleHttp\Client(['http_errors' => false]); // Important: don't throw exceptions on HTTP errors

        $options = [
            'headers' => $headers
        ];

        // Add request body if provided
        if ($requestBody !== null) {
            $options['json'] = $requestBody;
        }

        try {
            $response = $client->request($method, $url, $options);

            $statusCode = $response->getStatusCode();
            $responseHeaders = $response->getHeaders();
            $responseBody = (string)$response->getBody();

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s): %s',
                        $statusCode,
                        $url,
                        $responseBody
                    ),
                    $statusCode,
                    $responseHeaders,
                    $responseBody
                );
            }

            // Try to parse JSON response
            $jsonBody = null;
            if (!empty($responseBody)) {
                try {
                    $jsonBody = json_decode($responseBody, true);
                } catch (\Exception $e) {
                    // Failed to parse JSON, leave $jsonBody as null
                }
            }

            return [
                'statusCode' => $statusCode,
                'headers' => $responseHeaders,
                'body' => $responseBody,
                'jsonBody' => $jsonBody
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Creates headers including the signature, handling all the signature generation internally
     *
     * @param string $method HTTP method (e.g., "POST", "GET")
     * @param string $resourcePath API resource path (e.g., "/payment-gateway/v1.0/debit/status.htm")
     * @param array|object $requestObj Request object or array
     * @param bool $withTimestamp Whether to include X-TIMESTAMP (defaults to true)
     * @param bool $invalidTimestamp If true, uses an invalid timestamp format
     * @param bool $invalidSignature If true, uses an invalid signature
     * @return array Dict of headers including the signature
     * @throws \Exception If validation fails
     */
    public static function getHeadersWithSignature(
        ?string $method = null,
        ?string $resourcePath = null,
        $requestObj = null,
        bool $withTimestamp = true,
        bool $invalidTimestamp = false,
        bool $invalidSignature = false
    ): array {
        // Get the standard headers first
        $headers = self::getStandardHeaders(false); // We'll handle timestamp specially

        // Add the signature
        if ($invalidSignature) {
            $headers['X-SIGNATURE'] = '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'; // Invalid signature

            $generatedTimestamp = self::generateTimestamp($invalidTimestamp);
            if ($withTimestamp) {
                $headers['X-TIMESTAMP'] = $generatedTimestamp[0];
                echo "Setting X-TIMESTAMP to {$generatedTimestamp[0]} for invalid signature test\n";
            } else {
                $headers['X-TIMESTAMP'] = "";
                echo "Setting X-TIMESTAMP to empty string for invalid signature test\n";
            }
        } else {
            if ($method === null || $resourcePath === null || $requestObj === null) {
                throw new \Exception('Method, resourcePath, and requestObj are required unless invalidSignature=true');
            }

            // Convert request object to array if needed
            $requestArray = $requestObj;
            if (is_object($requestObj) && method_exists($requestObj, 'toArray')) {
                $requestArray = $requestObj->toArray();
            }

            $generatedTimestamp = self::generateTimestamp($invalidTimestamp);
            $timestampForSignature = $generatedTimestamp[1];

            if ($withTimestamp) {
                $headers['X-TIMESTAMP'] = $generatedTimestamp[0];
            }

            // Generate signature based on what's available in the Dana SDK
            try {
                $headers['X-SIGNATURE'] = SnapHeader::generateSignature(
                    $method,
                    $resourcePath,
                    json_encode($requestArray),
                    $timestampForSignature,
                    SnapHeader::getPrivateKey(getenv('PRIVATE_KEY'))
                );
            } catch (\Exception $e) {
                throw new \Exception("Failed to generate signature: " . $e->getMessage());
            }
        }

        return $headers;
    }

    public static function execFileWithParseParam(
        string $fileScript,
        string $params
    ): string {
        $scriptPath = dirname(__DIR__) . $fileScript;

        // Check if the script exists
        if (!file_exists($scriptPath)) {
            throw new \Exception("Script not found at: $scriptPath");
        }

        $command = sprintf(
            'node %s %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($params)
        );

        echo "Running Node.js script: $command\n";
        $output = shell_exec($command);

        // Check for output and parse results
        if (empty($output)) {
            throw new \Exception("No output received from Node.js script");
        }

        return $output;
    }

    public static function execFileAutomate(
        string $fileScript,
        string $params
    ): void {
        $scriptPath = dirname(__DIR__) . $fileScript;

        // Check if the script exists
        if (!file_exists($scriptPath)) {
            throw new \Exception("Script not found at: $scriptPath");
        }

        $command = sprintf(
            'node %s %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($params)
        );

        echo "Running Node.js script: $command\n";
        shell_exec($command);
    }

    public static function generatePartnerReferenceNo(): string
    {
        // Generate a UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Generates a formatted date string in ISO format with timezone offset and optional second offset
     * 
     * @param int $offsetSeconds Number of seconds to add/subtract from current time (can be negative for past dates)
     * @param int $timezoneOffset Timezone offset in hours (defaults to +7 for Jakarta/Asia timezone)
     * @return string Formatted date string in format: 2030-05-01T00:46:43+07:00
     * 
     * Examples:
     * - generateFormattedDate(0, 7)     // Current time: "2025-09-10T14:30:15+07:00"
     * - generateFormattedDate(3600, 7)  // 1 hour from now: "2025-09-10T15:30:15+07:00"
     * - generateFormattedDate(-1800, 7) // 30 minutes ago: "2025-09-10T14:00:15+07:00"
     * - generateFormattedDate(0, -5)    // Current time with EST timezone: "2025-09-10T14:30:15-05:00"
     */
    public static function generateFormattedDate(int $offsetSeconds = 0, int $timezoneOffset = 7): string
    {
        // Create DateTime object with offset seconds applied
        $targetDateTime = new \DateTime();
        $targetDateTime->modify(sprintf('%+d seconds', $offsetSeconds));
        
        // Format timezone offset
        $offsetSign = $timezoneOffset >= 0 ? '+' : '-';
        $absOffset = abs($timezoneOffset);
        $timezoneStr = sprintf('%s%02d:00', $offsetSign, $absOffset);
        
        // Format the date in the required format: 2030-05-01T00:46:43+07:00
        return sprintf(
            '%04d-%02d-%02dT%02d:%02d:%02d%s',
            $targetDateTime->format('Y'),
            $targetDateTime->format('m'),
            $targetDateTime->format('d'),
            $targetDateTime->format('H'),
            $targetDateTime->format('i'),
            $targetDateTime->format('s'),
            $timezoneStr
        );
    }

    public static function runWithRetry(
        callable $testCallback, 
        int $maxRetries = 3, 
        int $delay = 1000, 
        array $retryableExceptions = [\Exception::class]
    ) {
        $lastException = null;
        $attempt = 0;
        
        while ($attempt <= $maxRetries) {
            try {
                if ($attempt > 0) {
                    echo "\nRetry attempt {$attempt}/{$maxRetries}...\n";
                }
                
                return $testCallback();
            } catch (\Exception $e) {
                $lastException = $e;
                $shouldRetry = false;
                
                // Check if this exception type should trigger a retry
                foreach ($retryableExceptions as $retryableException) {
                    if ($e instanceof $retryableException) {
                        $shouldRetry = true;
                        break;
                    }
                }
                
                if (!$shouldRetry || $attempt >= $maxRetries) {
                    throw $e;
                }
                
                echo "\nAttempt " . ($attempt + 1) . " failed: " . $e->getMessage() . "\n";
                echo "Waiting " . ($delay / 1000) . " seconds before next retry...\n";
                
                usleep($delay * 1000);
                // Increase delay with exponential backoff
                $delay = (int)($delay * 1.5);
                $attempt++;
            }
        }
        
        throw $lastException;
    }
}
