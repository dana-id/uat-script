<?php

namespace DanaUat\Helper;

class Assertion
{
    /**
     * Assert response against expected response from JSON file
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @param string $actualResponse JSON string of the actual response
     * @throws \Exception If assertion fails
     */
    public static function assertResponse(string $jsonPathFile, string $titleCase, string $caseName, string $actualResponse): void
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
            throw new \Exception("Expected response data not found for {$titleCase}.{$caseName}");
        }
        
        $expectedResponse = $jsonData[$titleCase][$caseName]['response'];
        $actualResponseArray = json_decode($actualResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error parsing actual response JSON: ' . json_last_error_msg());
        }
        
        // Assert expected fields are in the actual response
        self::assertArrayContains($expectedResponse, $actualResponseArray, "{$titleCase}.{$caseName}");
        
        echo "✅ Assertion passed for {$titleCase}.{$caseName}\n";
    }
    
    /**
     * Assert fail response against expected fail response from JSON file
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @param string $actualResponse JSON string of the actual response
     * @throws \Exception If assertion fails
     */
    public static function assertFailResponse(string $jsonPathFile, string $titleCase, string $caseName, string $actualResponse): void
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
            throw new \Exception("Expected response data not found for {$titleCase}.{$caseName}");
        }
        
        $expectedResponse = $jsonData[$titleCase][$caseName]['response'];
        $actualResponseArray = json_decode($actualResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error parsing actual response JSON: ' . json_last_error_msg());
        }
        
        // Assert expected fields are in the actual response
        self::assertArrayContains($expectedResponse, $actualResponseArray, "{$titleCase}.{$caseName} (fail)");
        
        echo "✅ Assertion passed for {$titleCase}.{$caseName} (fail case)\n";
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
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                throw new \Exception("Expected key '{$key}' not found in response at path: {$path}");
            }
            
            if (is_array($value) && is_array($actual[$key])) {
                self::assertArrayContains($value, $actual[$key], "{$path}.{$key}");
            } else if ($value !== null && $actual[$key] !== $value) {
                throw new \Exception("Value mismatch for '{$key}' at path: {$path}. Expected: " . json_encode($value) . ", Actual: " . json_encode($actual[$key]));
            }
        }
    }
}
