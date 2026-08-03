package helper

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
)

type jsonMarshaler interface {
	MarshalJSON() ([]byte, error)
}

// AssertSdkErrorResponse asserts disbursement/PG error fixtures against SDK Execute results.
// Handles HTTP transport errors, HTTP 2xx business errors, and enriched error bodies.
func AssertSdkErrorResponse(
	jsonPathFile, title, caseName string,
	apiResponse jsonMarshaler,
	httpResponse *http.Response,
	execErr error,
	variableDict map[string]interface{},
) error {
	if execErr != nil {
		if !isNilInterface(apiResponse) {
			responseJSON, err := apiResponse.MarshalJSON()
			if err == nil {
				return AssertResponse(jsonPathFile, title, caseName, string(responseJSON), variableDict)
			}
		}
		if httpResponse != nil {
			return AssertFailResponse(jsonPathFile, title, caseName, httpResponse, variableDict)
		}
		return fmt.Errorf("expected API error but got err without response: %w", execErr)
	}

	if isNilInterface(apiResponse) {
		return fmt.Errorf("expected error response but got nil")
	}

	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		return fmt.Errorf("failed to marshal API response: %w", err)
	}

	var body map[string]interface{}
	if err := json.Unmarshal(responseJSON, &body); err != nil {
		return fmt.Errorf("failed to parse API response JSON: %w", err)
	}

	responseCode, _ := body["responseCode"].(string)
	if strings.HasPrefix(responseCode, "200") {
		return fmt.Errorf("expected business error but got success responseCode %s", responseCode)
	}

	return AssertResponse(jsonPathFile, title, caseName, string(responseJSON), variableDict)
}
