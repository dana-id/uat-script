package helper

import (
	"encoding/json"
	"fmt"
	"os"
)

// GetRequest loads request data from a JSON file based on the title case and case name
func GetRequest(jsonPath, titleCase, caseName string) (map[string]interface{}, error) {
	// Read the JSON file
	content, err := os.ReadFile(jsonPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read JSON file: %v", err)
	}

	// Parse the JSON content
	var data map[string]interface{}
	if err := json.Unmarshal(content, &data); err != nil {
		return nil, fmt.Errorf("failed to parse JSON content: %v", err)
	}

	// First try direct access to caseName
	if titleMap, ok := data[titleCase].(map[string]interface{}); ok {
		// Try finding the case directly
		if caseMap, ok := titleMap[caseName].(map[string]interface{}); ok {
			// Look for request section
			if request, ok := caseMap["request"].(map[string]interface{}); ok {
				return request, nil
			}
			return nil, fmt.Errorf("case %s not found in request", caseName)
		}
		return nil, fmt.Errorf("case %s not found in %s", caseName, titleCase)
	}

	return nil, fmt.Errorf("case %s not found in any structure", caseName)
}

// GetResponse loads expected response data from a JSON file based on the title case and case name
func GetResponse(jsonPath, titleCase, caseName string) (map[string]interface{}, error) {
	// Read the JSON file
	content, err := os.ReadFile(jsonPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read JSON file: %v", err)
	}

	// Parse the JSON content
	var data map[string]interface{}
	if err := json.Unmarshal(content, &data); err != nil {
		return nil, fmt.Errorf("failed to parse JSON content: %v", err)
	}

	// First try direct access to caseName
	if titleMap, ok := data[titleCase].(map[string]interface{}); ok {
		// Try finding the case directly
		if caseMap, ok := titleMap[caseName].(map[string]interface{}); ok {
			// Look for response section
			if response, ok := caseMap["response"].(map[string]interface{}); ok {
				return response, nil
			}
			return nil, fmt.Errorf("case %s not found in response", caseName)
		}
		return nil, fmt.Errorf("case %s not found in %s", caseName, titleCase)
	}

	return nil, fmt.Errorf("case %s not found in any structure", caseName)
}
