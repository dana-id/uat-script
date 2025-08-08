package helper

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"os"
	"testing"
	"time"

	"github.com/dana-id/dana-go/config"
	"github.com/dana-id/dana-go/utils"
	"github.com/google/uuid"
)

// ExecuteAPIRequestWithCustomHeaders executes an API request with the provided custom headers
// It returns the HTTP response and error
func ExecuteAPIRequestWithCustomHeaders(
	ctx context.Context,
	requestObj interface{},
	method string,
	endpoint string,
	resourcePath string,
	customHeaders map[string]string,
) (*http.Response, error) {
	// Setup API key config
	headerParams := make(map[string]string)
	apiKey := &config.APIKey{
		ENV:          config.ENV_SANDBOX,
		X_PARTNER_ID: os.Getenv("X_PARTNER_ID"),
		CHANNEL_ID:   os.Getenv("CHANNEL_ID"),
		PRIVATE_KEY:  os.Getenv("PRIVATE_KEY"),
		ORIGIN:       os.Getenv("ORIGIN"),
	}

	// Prepare request data for signature
	var dataForSnapStr string
	dst := &bytes.Buffer{}
	dataForSnap, _ := json.Marshal(requestObj)
	_ = json.Compact(dst, dataForSnap)
	dataForSnapStr = dst.String()

	// Generate headers with signature
	utils.SetSnapHeaders(headerParams, apiKey, dataForSnapStr, method, resourcePath)

	// Prepare final headers
	headers := map[string]string{
		"X-PARTNER-ID":  os.Getenv("X_PARTNER_ID"),
		"CHANNEL-ID":    os.Getenv("CHANNEL_ID"),
		"Content-Type":  "application/json",
		"ORIGIN":        os.Getenv("ORIGIN"),
		"X-EXTERNAL-ID": uuid.New().String(),
	}

	// Add timestamp if not explicitly provided in custom headers
	if _, found := customHeaders["X-TIMESTAMP"]; !found {
		headers["X-TIMESTAMP"] = time.Now().Format("2006-01-02T15:04:05-07:00")
	}

	if _, found := customHeaders["X-SIGNATURE"]; !found {
		headers["X-SIGNATURE"] = headerParams["X-SIGNATURE"]
	}

	// Apply any custom headers (these will override defaults)
	if customHeaders != nil {
		for k, v := range customHeaders {
			headers[k] = v
		}
	}

	// Execute the request
	return ApiClient.ExecuteRequest(ctx, endpoint, method, requestObj, headers, nil, nil, nil, nil)
}

// ExecuteAndAssertErrorResponse executes an API request and asserts that an error response is received
// It returns an error if the assertion fails
func ExecuteAndAssertErrorResponse(
	t *testing.T,
	ctx context.Context,
	requestObj interface{},
	method string,
	endpoint string,
	resourcePath string,
	jsonPathFile string,
	titleCase string,
	caseName string,
	customHeaders map[string]string,
	variableDict map[string]interface{},
) error {
	// Execute the request
	httpResponse, err := ExecuteAPIRequestWithCustomHeaders(ctx, requestObj, method, endpoint, resourcePath, customHeaders)

	if err != nil {
		// Assert the API error response
		err = AssertFailResponse(jsonPathFile, titleCase, caseName, httpResponse, variableDict)
		if err != nil {
			return err
		}
		return nil
	}

	// If we got here, we expected an error but got a success
	t.Fatal("Expected error but got successful response")
	return nil
}
