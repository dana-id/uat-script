package helper

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"reflect"
	"strings"
)

// valueFromServer is a special placeholder in expected data that only verifies the field exists
const valueFromServer = "${valueFromServer}"

// ReplaceVariables recursively replaces placeholders in the format ${key} with values from variableDict
func ReplaceVariables(data interface{}, variableDict map[string]interface{}) interface{} {
	switch v := data.(type) {
	case map[string]interface{}:
		result := make(map[string]interface{})
		for key, value := range v {
			result[key] = ReplaceVariables(value, variableDict)
		}
		return result

	case []interface{}:
		result := make([]interface{}, len(v))
		for i, item := range v {
			result[i] = ReplaceVariables(item, variableDict)
		}
		return result

	case string:
		// Check if this string matches any variable pattern ${key}
		for varName, varValue := range variableDict {
			placeholder := fmt.Sprintf("${%s}", varName)
			if v == placeholder {
				return varValue
			}
		}
		return v

	default:
		// Return other types unchanged
		return v
	}
}

// AssertResponse asserts that the API response matches the expected data from a JSON file
func AssertResponse(jsonPathFile, title, data string, apiResponseJson string, variableDict map[string]interface{}) error {
	// Fetch the expected data from the JSON file
	expectedData, err := GetResponse(jsonPathFile, title, data)
	if err != nil {
		return fmt.Errorf("failed to get expected response: %w", err)
	}

	// Replace variables in the expected data if a dictionary is provided
	if variableDict != nil {
		expectedData = ReplaceVariables(expectedData, variableDict).(map[string]interface{})
	}

	// Parse the API response JSON
	var actualResponse map[string]interface{}
	if err := json.Unmarshal([]byte(apiResponseJson), &actualResponse); err != nil {
		return fmt.Errorf("failed to parse API response JSON: %w", err)
	}

	// Recursively compare actual and expected
	diffPaths := []string{}
	compareJsonObjects(expectedData, actualResponse, "", &diffPaths)

	// If there are differences, return an error
	if len(diffPaths) > 0 {
		errorMsg := "Assertion failed. Differences found at:\n"
		for _, diff := range diffPaths {
			errorMsg += diff + "\n"
		}
		// Print verbose output only on failure
		fmt.Printf("Actual response: %v\n", actualResponse)
		return fmt.Errorf(errorMsg)
	}

	// Print success message
	actualJSON, _ := json.Marshal(actualResponse)
	fmt.Printf("Assertion passed: API response matches the expected data %s\n", string(actualJSON))
	return nil
}

// AssertFailResponse asserts that the API error response matches the expected data
func AssertFailResponse(jsonPathFile, title, data string, errorInfo interface{}, variableDict map[string]interface{}) error {
	// Fetch the expected data from the JSON file
	expectedData, err := GetResponse(jsonPathFile, title, data)
	if err != nil {
		return fmt.Errorf("failed to get expected response: %w", err)
	}

	// Replace variables in the expected data if a dictionary is provided
	if variableDict != nil {
		expectedData = ReplaceVariables(expectedData, variableDict).(map[string]interface{})
	}

	var bodyContent []byte

	// Handle different types of error info
	switch v := errorInfo.(type) {
	case string:
		// Extract the relevant part of the error string
		tempErrorString := strings.Replace(v, "HTTP response body: ", "", 1)
		tempErrorLines := strings.Split(tempErrorString, "\n")

		tempError := ""
		for _, line := range tempErrorLines {
			if strings.Contains(line, "{") && strings.Contains(line, "}") {
				tempError = line
				break
			}
		}

		if tempError == "" && len(tempErrorLines) > 3 {
			tempError = tempErrorLines[3] // Default as in Python
		}

		bodyContent = []byte(tempError)

	case *http.Response:
		// Read the response body
		var readErr error
		bodyContent, readErr = io.ReadAll(v.Body)
		if readErr != nil {
			return fmt.Errorf("failed to read response body: %w", readErr)
		}

		// Reset the response body for further use
		v.Body.Close()

	default:
		return fmt.Errorf("unsupported error info type: %T", v)
	}

	// Parse the error response JSON
	var actualResponse map[string]interface{}
	if err := json.Unmarshal(bodyContent, &actualResponse); err != nil {
		return fmt.Errorf("failed to parse error response JSON: %w\nRaw body: %s", err, string(bodyContent))
	}

	// Recursively compare actual and expected
	diffPaths := []string{}
	compareJsonObjects(expectedData, actualResponse, "", &diffPaths)

	// If there are differences, return an error
	if len(diffPaths) > 0 {
		errorMsg := "Assertion failed. Differences found in error response:\n"
		for _, diff := range diffPaths {
			errorMsg += diff + "\n"
		}
		// Print verbose output only on failure
		fmt.Printf("Actual error response: %v\n", actualResponse)
		return fmt.Errorf(errorMsg)
	}

	// Print success message
	actualJSON, _ := json.Marshal(actualResponse)
	fmt.Printf("Assertion passed: API error response matches the expected data %s\n", string(actualJSON))
	return nil
}

// compareJsonObjects recursively compares JSON objects with special handling for "${valueFromServer}" placeholder
func compareJsonObjects(expected, actual interface{}, path string, diffPaths *[]string) {
	// Handle nil values
	if expected == nil && actual == nil {
		return
	}

	// Different types
	if reflect.TypeOf(expected) != reflect.TypeOf(actual) {
		*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s\n  Expected: %v\n  Actual: %v", path, expected, actual))
		return
	}

	switch exp := expected.(type) {
	case map[string]interface{}:
		// Type assertion for actual
		act, ok := actual.(map[string]interface{})
		if !ok {
			*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s\n  Expected: map\n  Actual: %v", path, actual))
			return
		}

		// Check that all expected keys exist in actual
		for key, expValue := range exp {
			newPath := key
			if path != "" {
				newPath = path + "." + key
			}

			actValue, exists := act[key]
			if !exists {
				*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s\n  Expected: %v\n  Actual: MISSING", newPath, expValue))
				continue
			}

			// Recursively compare nested values
			compareJsonObjects(expValue, actValue, newPath, diffPaths)
		}

	case []interface{}:
		// Type assertion for actual
		act, ok := actual.([]interface{})
		if !ok {
			*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s\n  Expected: array\n  Actual: %v", path, actual))
			return
		}

		// Check array length
		if len(exp) != len(act) {
			*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s[length]\n  Expected: %d\n  Actual: %d", path, len(exp), len(act)))
			return
		}

		// Compare each item in the array
		for i := range exp {
			newPath := fmt.Sprintf("%s[%d]", path, i)
			compareJsonObjects(exp[i], act[i], newPath, diffPaths)
		}

	case string:
		// Special handling for valueFromServer placeholder
		if exp == valueFromServer {
			// Only verify that the actual value exists (is not nil or empty string)
			if actual == nil || (reflect.TypeOf(actual).Kind() == reflect.String && actual.(string) == "") {
				*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s\n  Expected: %v\n  Actual: %v", path, exp, actual))
			}
		} else if exp != actual {
			*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s\n  Expected: %v\n  Actual: %v", path, exp, actual))
		}

	default:
		// For all other cases, do an exact comparison
		if !reflect.DeepEqual(expected, actual) {
			*diffPaths = append(*diffPaths, fmt.Sprintf("Path: %s\n  Expected: %v\n  Actual: %v", path, expected, actual))
		}
	}
}
