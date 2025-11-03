package helper

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"strings"
	"testing"
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
				// Check for merchantId or mid and replace with value from .env if present
				// BUT only if the current value is not explicitly empty (for invalid field format tests)
				merchantIdEnv := os.Getenv("MERCHANT_ID")
				if merchantIdEnv != "" {
					if merchantId, ok := request["merchantId"]; ok {
						// Only replace if the current value is not an empty string (preserve empty strings for invalid tests)
						if merchantIdStr, isString := merchantId.(string); !isString || merchantIdStr != "" {
							request["merchantId"] = merchantIdEnv
						}
					}
					if mid, ok := request["mid"]; ok {
						// Only replace if the current value is not an empty string (preserve empty strings for invalid tests)
						if midStr, isString := mid.(string); !isString || midStr != "" {
							request["mid"] = merchantIdEnv
						}
					}
				}
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
//
//	result, err := RetryOnInconsistentRequest(func() (interface{}, error) {
//	    return client.CreateOrder(requestObj) // or any API call
//	}, 3, 2*time.Second)
//
//	if err != nil {
//	    // handle error
//	}
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
	// Return empty string instead of nil to prevent panic in type assertion
	if lastErr != nil {
		return "", lastErr
	}
	return nil, lastErr
}

func GetValueFromResponseBody(response *http.Response, key string) (string, error) {
	defer response.Body.Close()
	bodyBytes, err := io.ReadAll(response.Body)
	// Get the full response body as string
	responseBodyStr := string(bodyBytes)
	if err != nil {
		return "", fmt.Errorf("failed to read response body: %v", err)
	}

	// Parse the response body as JSON
	var responseData map[string]interface{}
	if err := json.Unmarshal([]byte(responseBodyStr), &responseData); err != nil {
		return "", fmt.Errorf("failed to parse JSON response: %v", err)
	}

	// Check if the key exists
	value, exists := responseData[key]
	if !exists {
		return "", fmt.Errorf("key '%s' not found in response", key)
	}

	return value.(string), nil
}

// RetryTest attempts to run a test function multiple times until it succeeds
func RetryTest(t *testing.T, attempts int, delay time.Duration, testFunc func() error) {
	t.Helper() // Mark as helper for better error reporting

	if attempts == 0 {
		attempts = 1 // Default to 1 attempt if not specified
	}

	if delay == 0 {
		delay = 1 * time.Second // Default to 1 second if not specified
	}

	var err error
	for i := 0; i < attempts; i++ {
		if i > 0 {
			t.Logf("Retry attempt %d/%d after %v delay...", i+1, attempts, delay)
			time.Sleep(delay)
		}

		err = testFunc()
		if err == nil {
			// Test passed
			return
		}

		t.Logf("Attempt %d failed: %v", i+1, err)
	}

	// All attempts failed
	t.Fatalf("Test failed after %d attempts. Last error: %v", attempts, err)
}

// GenerateFormattedDate generates a formatted date string in ISO format with timezone offset and optional second offset
// Parameters:
//   - offsetSeconds: Number of seconds to add/subtract from current time (can be negative for past dates)
//   - timezoneOffset: Timezone offset in hours (defaults to +7 for Jakarta/Asia timezone)
//
// Returns: Formatted date string in format: 2030-05-01T00:46:43+07:00
//
// Examples:
//
//	GenerateFormattedDate(0, 7)     // Current time: "2025-09-10T14:30:15+07:00"
//	GenerateFormattedDate(3600, 7)  // 1 hour from now: "2025-09-10T15:30:15+07:00"
//	GenerateFormattedDate(-1800, 7) // 30 minutes ago: "2025-09-10T14:00:15+07:00"
//	GenerateFormattedDate(0, -5)    // Current time with EST timezone: "2025-09-10T14:30:15-05:00"
func GenerateFormattedDate(offsetSeconds int, timezoneOffset int) string {
	// Indonesia timezone (WIB = UTC+7)
	indonesiaLocation := time.FixedZone("WIB", 7*60*60)

	// Create date with offset seconds applied using Indonesia timezone
	targetTime := time.Now().In(indonesiaLocation).Add(time.Duration(offsetSeconds) * time.Second)

	// Create timezone offset
	var offsetSign string
	absOffset := timezoneOffset
	if timezoneOffset >= 0 {
		offsetSign = "+"
	} else {
		offsetSign = "-"
		absOffset = -timezoneOffset
	}

	// Format timezone offset as +HH:MM or -HH:MM
	timezoneStr := fmt.Sprintf("%s%02d:00", offsetSign, absOffset)

	// Format the date in the required format: 2030-05-01T00:46:43+07:00
	return fmt.Sprintf("%04d-%02d-%02dT%02d:%02d:%02d%s",
		targetTime.Year(),
		targetTime.Month(),
		targetTime.Day(),
		targetTime.Hour(),
		targetTime.Minute(),
		targetTime.Second(),
		timezoneStr,
	)
}
