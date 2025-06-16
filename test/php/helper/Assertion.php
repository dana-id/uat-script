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
     * @param array $variableDict Optional dictionary of variables to replace in expected response
     * @throws \Exception If assertion fails
     */
    public static function assertResponse(string $jsonPathFile, string $titleCase, string $caseName, string $actualResponse, array $variableDict = []): void
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
        
        echo "✅ Assertion passed for {$titleCase}.{$caseName} (fail case): " . substr($actualResponse, 0, 200) . (strlen($actualResponse) > 200 ? '...' : '') . "\n";
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
    
    private static function assertArrayContains(array $expected, array $actual, string $path = 'root'): void
    {
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
}
