<?php

namespace DanaUat\Helper;

use Dana\ApiException;
use Exception;

class Assertion
{
    /**
     * Assert response against expected response from JSON file
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @param string $actualResponse JSON string of the actual response
     * @param array $variableDict Optional dictionary of variables to replace in expected response
     * @throws \Exception If assertion fails
     */
    public static function assertResponse(string $jsonPathFile, string $titleCase, string $caseName, string $actualResponse, array $variableDict = []): void
    {
        // Register with PHPUnit that we're performing an assertion
        if (class_exists('PHPUnit\Framework\Assert')) {
            \PHPUnit\Framework\Assert::assertTrue(true, "Response validation for {$titleCase}.{$caseName}");
        }
        
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
            throw new \Exception("Expected response data not found for {$titleCase}.{$caseName}");
        }
        
        $expectedResponse = $jsonData[$titleCase][$caseName]['response'];
        
        // Replace variables in the expected response if a dictionary is provided
        if (!empty($variableDict)) {
            $expectedResponse = self::replaceVariables($expectedResponse, $variableDict);
        }
        
        $actualResponseArray = json_decode($actualResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error parsing actual response JSON: ' . json_last_error_msg());
        }
        
        // Assert expected fields are in the actual response
        self::assertArrayContains($expectedResponse, $actualResponseArray, "{$titleCase}.{$caseName}");
        
        echo "✅ Assertion passed for {$titleCase}.{$caseName}: " . substr($actualResponse, 0, 200) . (strlen($actualResponse) > 200 ? '...' : '') . "\n";
    }
    
    /**
     * Assert fail response against expected fail response from JSON file
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @param string $actualResponse JSON string of the actual response
     * @param array $variableDict Optional dictionary of variables to replace in expected response
     * @throws \Exception If assertion fails
     */
    public static function assertFailResponse(string $jsonPathFile, string $titleCase, string $caseName, string $actualResponse, array $variableDict = []): void
    {
        // Register with PHPUnit that we're performing an assertion
        if (class_exists('PHPUnit\Framework\Assert')) {
            \PHPUnit\Framework\Assert::assertTrue(true, "Fail response validation for {$titleCase}.{$caseName}");
        }
        
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
            throw new \Exception("Expected response data not found for {$titleCase}.{$caseName}");
        }
        
        $expectedResponse = $jsonData[$titleCase][$caseName]['response'];
        
        // Replace variables in the expected response if a dictionary is provided
        if (!empty($variableDict)) {
            $expectedResponse = self::replaceVariables($expectedResponse, $variableDict);
        }
        
        // Extract JSON from potential Guzzle error message format
        $extractedJson = self::extractJsonFromErrorResponse($actualResponse);
        if ($extractedJson !== null) {
            $actualResponseArray = $extractedJson;
        } else {
            // Fall back to the previous method
            $actualResponseArray = json_decode($actualResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Error parsing actual response JSON: ' . json_last_error_msg());
            }
        }
        
        // Assert expected fields are in the actual response
        self::assertArrayContains($expectedResponse, $actualResponseArray, "{$titleCase}.{$caseName} (fail)");
        
        echo "✅ Assertion passed for {$titleCase}.{$caseName} (fail case): ";
        echo json_encode(json_decode($actualResponse, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES). "\n";
        if (function_exists('ob_flush')) ob_flush();
        if (function_exists('flush')) flush();
        if (defined('STDOUT')) fflush(STDOUT);
    }
    
    /**
     * Assert that an array contains all expected keys and values from another array
     *
     * @param array $expected Expected array
     * @param array $actual Actual array
     * @param string $path Current path for error reporting
     */
    /**
     * Replace variables in data with values from the variable dictionary
     *
     * @param mixed $data The data containing variables to be replaced
     * @param array $variableDict Dictionary of variables to replace in format ['key' => 'value']
     * @return mixed The data with variables replaced
     */
    private static function replaceVariables($data, array $variableDict)
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = self::replaceVariables($value, $variableDict);
            }
            return $result;
        } else if (is_string($data)) {
            // Check if this string matches any variable pattern ${key}
            foreach ($variableDict as $varName => $varValue) {
                $placeholder = "\${" . $varName . "}";
                if ($data === $placeholder) {
                    return $varValue;
                }
            }
        }
        
        // Return other types unchanged
        return $data;
    }
    
    /**
     * Assert that an array contains all expected keys and values from another array
     * 
     * @param array $expected Expected array
     * @param array $actual Actual array
     * @param string $path Current path for error reporting
     */
    private static function assertArrayContains(array $expected, array $actual, string $path = 'root'): void
    {
        // Increment PHPUnit's assertion counter if we're in a test context
        if (class_exists('PHPUnit\Framework\Assert')) {
            \PHPUnit\Framework\Assert::assertTrue(true, "Assertion performed in {$path}");
        }
        
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                throw new \Exception("Expected key '{$key}' not found in response at path: {$path}");
            }
            
            if (is_array($value) && is_array($actual[$key])) {
                self::assertArrayContains($value, $actual[$key], "{$path}.{$key}");
            } else if (is_string($value) && $value === "\${valueFromServer}") {
                // Special case for the valueFromServer placeholder
                // Only verify that the actual value exists and is not empty
                if ($actual[$key] === null || (is_string($actual[$key]) && empty($actual[$key]))) {
                    throw new \Exception("Value for '{$key}' at path: {$path} should not be empty when using \${valueFromServer} placeholder");
                }
                // Value exists, so this passes
            } else if ($value !== null && $actual[$key] !== $value) {
                throw new \Exception("Value mismatch for '{$key}' at path: {$path}. Expected: " . json_encode($value) . ", Actual: " . json_encode($actual[$key]));
            }
        }
    }
    
    /**
     * Extract JSON from a Guzzle exception error message
     *
     * @param string $errorMessage The error message that might contain JSON
     * @return array|null The extracted JSON as array or null if extraction failed
     */
    private static function extractJsonFromErrorResponse($errorMessage) {
        
        // Look for the JSON part in the error message
        if (preg_match('/response:\s*(.*)/s', $errorMessage, $matches)) {
            $jsonStr = $matches[1];

            echo $jsonStr;
            
            // Try to decode it
            $json = json_decode($jsonStr, true);
            
            // If successful, return the parsed JSON
            if ($json !== null && json_last_error() === JSON_ERROR_NONE) {
                
                return $json;
            }
            
            // If not valid JSON yet, try to find just the JSON object
            if (preg_match('/(\{.*\})/s', $jsonStr, $matches)) {
                $jsonObjectStr = $matches[1];
                $json = json_decode($jsonObjectStr, true);
                
                if ($json !== null && json_last_error() === JSON_ERROR_NONE) {
                    return $json;
                }
            }
        }
        
        // Also try to find JSON directly in the message
        if (preg_match('/(\{.*\})/s', $errorMessage, $matches)) {
            $jsonObjectStr = $matches[1];
            $json = json_decode($jsonObjectStr, true);
            
            if ($json !== null && json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Return null if parsing fails
        return null;
    }
    /**
     * Unified assertion method that can handle regular responses, ApiExceptions, and regular Exceptions
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @param mixed $response The response or exception to validate (can be response object, ApiException, Exception, or string)
     * @param array $variableDict Optional dictionary of variables to replace in expected response
     * @param bool $isErrorCase Whether this is an expected error case (affects output formatting)
     * @throws \Exception If assertion fails
     */
    public static function assertResponseJson(
        string $jsonPathFile, 
        string $titleCase, 
        string $caseName, 
        $response, 
        array $variableDict = [],
        bool $isErrorCase = false): void
    {
        $context = $isErrorCase ? "error response" : "response";
        echo "Asserting {$context} for {$titleCase}.{$caseName}:\n";
        
        // Register with PHPUnit that we're performing an assertion
        if (class_exists('PHPUnit\Framework\Assert')) {
            \PHPUnit\Framework\Assert::assertTrue(true, "Response JSON validation for {$titleCase}.{$caseName}");
        }

        // Load and parse expected response from JSON file
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
            throw new \Exception("Expected response data not found for {$titleCase}.{$caseName}");
        }
        
        $expectedResponse = $jsonData[$titleCase][$caseName]['response'];
        
        // Replace variables in the expected response if a dictionary is provided
        if (!empty($variableDict)) {
            $expectedResponse = self::replaceVariables($expectedResponse, $variableDict);
        }

        // Handle different response types
        $actualResponseArray = null;
        $statusCode = null;

        // Case 1: ApiException handling
        if ($response instanceof ApiException) {
            $statusCode = $response->getCode();
            $responseBody = (string)$response->getResponseBody();
            $actualResponseArray = json_decode($responseBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to extract JSON from the error message
                $extractedJson = self::extractJsonFromErrorResponse($responseBody);
                if ($extractedJson !== null) {
                    $actualResponseArray = $extractedJson;
                } else {
                    throw new \Exception('Error parsing ApiException response JSON: ' . json_last_error_msg() . "\nRaw response: " . $responseBody);
                }
            }
            
            if ($statusCode) {
                echo "HTTP Status Code: {$statusCode}\n";
            }
        }
        // Case 2: Regular Exception handling
        else if ($response instanceof Exception) {
            $message = $response->getMessage();
            // Try to extract JSON from exception message
            $extractedJson = self::extractJsonFromErrorResponse($message);
            
            if ($extractedJson !== null) {
                $actualResponseArray = $extractedJson;
            } else {
                // If we couldn't extract JSON, create a simple error object
                $actualResponseArray = [
                    'error' => true,
                    'message' => $message,
                    'code' => $response->getCode()
                ];
            }
            
            echo "Exception: " . get_class($response) . "\n";
            if ($response->getCode()) {
                echo "Code: " . $response->getCode() . "\n";
            }
        }
        // Case 3: Regular response handling
        else {
            // Extract the response body based on the type of $response
            $actualResponse = self::getResponseBody($response);
            $actualResponseArray = json_decode($actualResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Error parsing response JSON: ' . json_last_error_msg() . "\nRaw response: " . $actualResponse);
            }
        }
        
        // Display the actual and expected responses for debugging
        echo "Actual response: " . json_encode($actualResponseArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        echo "Expected response: " . json_encode($expectedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Do the assertion without try-catch to let any assertion failures bubble up
        self::assertArrayContains($expectedResponse, $actualResponseArray, "{$titleCase}.{$caseName}" . ($isErrorCase ? " (error)" : ""));
        
        // Indicate successful assertion
        echo "✅ Assertion passed for {$titleCase}.{$caseName}" . ($isErrorCase ? " (error case)" : "") . "\n";
    }
    
    /**
     * Extract the response body from various types of response objects
     *
     * @param mixed $response Could be a GuzzleHttp Response object, an array, a string, or another object
     * @return string The response body as a string
     */
    public static function getResponseBody($response): string
    {
        // Case 1: Response is already a string
        if (is_string($response)) {
            return $response;
        }

        // Case 2: GuzzleHttp Response object with getBody method
        if (is_object($response) && method_exists($response, 'getBody')) {
            $body = $response->getBody();
            // Handle StreamInterface
            if (is_object($body) && method_exists($body, '__toString')) {
                return (string) $body;
            }
            return (string) $body;
        }

        // Case 3: Response is an array
        if (is_array($response)) {
            return json_encode($response);
        }
        
        // Case 4: Response is an object with a body property
        if (is_object($response) && isset($response->body)) {
            if (is_string($response->body)) {
                return $response->body;
            }
            return json_encode($response->body);
        }
        
        // Case 5: ApiException or other exception with getResponseBody method
        if (is_object($response) && method_exists($response, 'getResponseBody')) {
            return (string) $response->getResponseBody();
        }
        
        // Last resort: try to JSON encode the entire response
        if (is_object($response)) {
            return json_encode($response);
        }
        
        // If we can't extract a meaningful body, return an empty JSON object
        return '{}';
    }
    
    /**
     * Assert that an API exception matches expected error response from JSON file
     * This is a convenience wrapper around assertResponseJson for API exceptions
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @param ApiException $exception The API exception to validate
     * @param array $variableDict Optional dictionary of variables to replace in expected response
     * @throws \Exception If assertion fails
     */
    public static function assertApiException(string $jsonPathFile, string $titleCase, string $caseName, ApiException $exception, array $variableDict = []): void
    {
        // Register with PHPUnit that we're performing an assertion
        if (class_exists('PHPUnit\Framework\Assert')) {
            \PHPUnit\Framework\Assert::assertTrue(true, "API Exception validation for {$titleCase}.{$caseName}");
        }
        
        // Use the unified assertion method with isErrorCase=true
        self::assertResponseJson($jsonPathFile, $titleCase, $caseName, $exception, $variableDict, true);
    }
}
