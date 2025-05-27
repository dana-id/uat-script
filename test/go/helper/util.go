package helper

import (
	"encoding/json"
	"fmt"
	"os"
	"strings"
	"time"
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

// RetryOnInconsistentRequest retries a function for inconsistent request scenarios (e.g., duplicate partnerReferenceNo with different payloads).
// Retries on error or if the returned error/message indicates a general/internal error.
//
// Example usage:
//   result, err := RetryOnInconsistentRequest(func() (interface{}, error) {
//       return client.CreateOrder(requestObj) // or any API call
//   }, 3, 2*time.Second)
//
//   if err != nil {
//       // handle error
//   }
func RetryOnInconsistentRequest(apiCall func() (interface{}, error), maxAttempts int, delay time.Duration) (interface{}, error) {
	var lastErr error
	for attempt := 1; attempt <= maxAttempts; attempt++ {
		result, err := apiCall()
		if err == nil {
			// Check for error message in result if it's a map
			if respMap, ok := result.(map[string]interface{}); ok {
				msg, _ := respMap["message"].(string)
				code, _ := respMap["status"].(float64)
				if (code == 500 || strings.Contains(msg, "General Error") || strings.Contains(msg, "Internal Server Error")) && attempt < maxAttempts {
					// Retry on general/internal error
					time.Sleep(delay)
					continue
				}
			}
			return result, nil
		}
		// If error is retryable
		errMsg := err.Error()
		if (strings.Contains(errMsg, "General Error") || strings.Contains(errMsg, "Internal Server Error")) && attempt < maxAttempts {
			// Retry on general/internal error
			time.Sleep(delay)
			continue
		}
		lastErr = err
		break
	}
	return nil, lastErr
}
