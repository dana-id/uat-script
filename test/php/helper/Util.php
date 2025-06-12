<?php

namespace DanaUat\Helper;

class Util
{
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
        
        return $jsonData[$titleCase][$caseName]['request'];
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
     * Creates a model object from JSON request data
     *
     * @param string $jsonPathFile Path to the JSON file
     * @param string $titleCase The title case for the request
     * @param string $caseName The case name for the request
     * @param string $modelClass Fully qualified name of the model class
     * @param array $overrides Optional key-value pairs to override in the request data
     * @return object The instantiated model object
     */
    public static function createModelFromJsonRequest(string $jsonPathFile, string $titleCase, string $caseName, string $modelClass, array $overrides = []): object
    {
        // Get the request data from the JSON file
        $jsonDict = self::getRequest($jsonPathFile, $titleCase, $caseName);
        
        // Apply any overrides
        foreach ($overrides as $key => $value) {
            $jsonDict[$key] = $value;
        }
        
        // Create a new instance of the model class
        $modelObject = new $modelClass();
        
        // Use reflection to get the model properties and setters
        $reflectionClass = new \ReflectionClass($modelClass);
        
        // Set simple properties first
        foreach ($jsonDict as $key => $value) {
            $setterMethod = 'set' . self::camelize($key);
            
            if ($reflectionClass->hasMethod($setterMethod)) {
                if (is_array($value) && !self::isAssociativeArray($value)) {
                    // Handle array of objects
                    continue; // Skip arrays for now, handle complex objects first
                } else if (is_array($value) && self::isAssociativeArray($value)) {
                    // This is likely a nested object - get the param type
                    $method = $reflectionClass->getMethod($setterMethod);
                    $params = $method->getParameters();
                    if (isset($params[0]) && $params[0]->hasType()) {
                        $type = $params[0]->getType();
                        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                            $nestedClass = $type->getName();
                            $nestedObject = new $nestedClass();
                            
                            // Set properties on the nested object
                            $nestedReflection = new \ReflectionClass($nestedClass);
                            foreach ($value as $nestedKey => $nestedValue) {
                                $nestedSetter = 'set' . self::camelize($nestedKey);
                                if ($nestedReflection->hasMethod($nestedSetter)) {
                                    $nestedObject->$nestedSetter($nestedValue);
                                }
                            }
                            
                            // Set the nested object on the parent
                            $modelObject->$setterMethod($nestedObject);
                            continue;
                        }
                    }
                }
                
                // For simple types or if we couldn't determine the complex type
                $modelObject->$setterMethod($value);
            }
        }
        
        // Now handle array properties
        foreach ($jsonDict as $key => $value) {
            if (is_array($value) && !self::isAssociativeArray($value)) {
                $setterMethod = 'set' . self::camelize($key);
                
                if ($reflectionClass->hasMethod($setterMethod)) {
                    // Try to determine the type of objects in the array
                    $method = $reflectionClass->getMethod($setterMethod);
                    $params = $method->getParameters();
                    
                    // Create array of objects
                    $objects = [];
                    foreach ($value as $item) {
                        if (is_array($item) && isset($params[0]) && $params[0]->hasType()) {
                            $type = $params[0]->getType();
                            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                                // Extract the base type from the array type hint
                                $itemClass = preg_replace('/\\\[]$/', '', $type->getName());
                                if (class_exists($itemClass)) {
                                    $itemObject = new $itemClass();
                                    $itemReflection = new \ReflectionClass($itemClass);
                                    
                                    foreach ($item as $itemKey => $itemValue) {
                                        $itemSetter = 'set' . self::camelize($itemKey);
                                        if ($itemReflection->hasMethod($itemSetter)) {
                                            $itemObject->$itemSetter($itemValue);
                                        }
                                    }
                                    
                                    $objects[] = $itemObject;
                                    continue;
                                }
                            }
                        }
                        
                        // Fallback: just add the raw value
                        $objects[] = $item;
                    }
                    
                    $modelObject->$setterMethod($objects);
                }
            }
        }
        
        return $modelObject;
    }
    
    /**
     * Convert a string to camelCase
     * 
     * @param string $string String to convert
     * @return string Camelized string
     */
    private static function camelize(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }
    
    /**
     * Check if an array is associative (has string keys)
     * 
     * @param array $array Array to check
     * @return bool True if associative
     */
    private static function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
