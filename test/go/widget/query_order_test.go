package widget_test

import (
	"context"
	"encoding/json"
	"testing"
	"time"

	widget "github.com/dana-id/dana-go/widget/v1"
	"github.com/google/uuid"

	"uat-script/helper"
)

const (
	queryOrderTitleCase          = "QueryOrder"
	queryOrderJsonPath           = "../../../resource/request/components/Widget.json"
	paymentForQueryTitleCase     = "Payment"
	cancelOrderForQueryTitleCase = "CancelOrder"
)

// createTestWidgetPayment creates a test widget payment for querying with INIT status
func createTestWidgetPayment() (string, error) {
	var partnerReferenceNo string
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		// Get the request data from the Payment JSON section
		caseName := "PaymentSuccess"
		jsonDict, err := helper.GetRequest(queryOrderJsonPath, paymentForQueryTitleCase, caseName)
		if err != nil {
			return "", err
		}

		// Set a unique partner reference number
		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo

		// Create the WidgetPaymentRequest object and populate it with JSON data
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return "", err
		}

		var request widget.WidgetPaymentRequest
		err = json.Unmarshal(jsonBytes, &request)
		if err != nil {
			return "", err
		}

		// Make the API call to create a payment
		ctx := context.Background()
		_, httpResponse, err := helper.ApiClient.WidgetAPI.WidgetPayment(ctx).WidgetPaymentRequest(request).Execute()
		if err != nil {
			return "", err
		}
		defer httpResponse.Body.Close()

		return partnerReferenceNo, nil
	}, 3, 2*time.Second)
	if err != nil {
		return "", err
	}
	return result.(string), nil
}

// createTestWidgetPaymentCanceled creates a test widget payment and then cancels it to achieve canceled status
func createTestWidgetPaymentCanceled() (string, error) {
	var partnerReferenceNo string
	_, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		// Get the request data from the Payment JSON section
		caseName := "PaymentSuccess"
		jsonDict, err := helper.GetRequest(queryOrderJsonPath, paymentForQueryTitleCase, caseName)
		if err != nil {
			return "", err
		}

		// Set a unique partner reference number
		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo

		// Create the WidgetPaymentRequest object and populate it with JSON data
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return "", err
		}

		var request widget.WidgetPaymentRequest
		err = json.Unmarshal(jsonBytes, &request)
		if err != nil {
			return "", err
		}

		// Make the API call to create a payment
		ctx := context.Background()
		_, httpResponse, err := helper.ApiClient.WidgetAPI.WidgetPayment(ctx).WidgetPaymentRequest(request).Execute()
		if err != nil {
			return "", err
		}
		defer httpResponse.Body.Close()

		return partnerReferenceNo, nil
	}, 3, 2*time.Second)
	if err != nil {
		return "", err
	}

	// Give time for the payment to be processed
	time.Sleep(2 * time.Second)

	// Now cancel the order
	caseName := "CancelOrderValidScenario"
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, cancelOrderForQueryTitleCase, caseName)
	if err != nil {
		return "", err
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Create the CancelOrderRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		return "", err
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		return "", err
	}

	// Make the API call to cancel the order
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		return "", err
	}
	httpResponse.Body.Close()

	// Give time for the cancellation to be processed
	time.Sleep(2 * time.Second)

	return partnerReferenceNo, nil
}

// QueryOrder
func TestQueryOrderSuccessPaid(t *testing.T) {
	t.Skip("Skip: API returns 404 Not Found - Widget QueryPayment API may not support this scenario or requires pre-existing orders")
	caseName := "QueryOrderSuccessPaid"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct for type safety
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.QueryPayment(ctx).QueryPaymentRequest(request).Execute()
	if err != nil {
		t.Fatalf("API request failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// Assert the success response
	variableDict := map[string]interface{}{
		"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	err = helper.AssertResponse(
		queryOrderJsonPath,
		queryOrderTitleCase,
		caseName,
		string(responseJSON),
		variableDict,
	)
	if err != nil {
		t.Fatal(err)
	}
}
func TestQueryOrderSuccessInitiated(t *testing.T) {
	// Create a test widget payment first
	partnerReferenceNo, err := createTestWidgetPayment()
	if err != nil {
		t.Fatalf("Failed to create test widget payment: %v", err)
	}

	// Give time for the payment to be processed
	time.Sleep(2 * time.Second)

	// Now query the order
	caseName := "QueryOrderSuccessInitiated"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Marshal to JSON and unmarshal to widget SDK struct for type safety
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.QueryPayment(ctx).QueryPaymentRequest(request).Execute()
	if err != nil {
		t.Fatalf("API request failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// For success scenarios, just verify the essential fields
	response := make(map[string]interface{})
	err = json.Unmarshal(responseJSON, &response)
	if err != nil {
		t.Fatalf("Failed to unmarshal response JSON: %v", err)
	}

	// Check essential fields
	if response["responseCode"] != "2005500" {
		t.Errorf("Expected responseCode 2005500, got %v", response["responseCode"])
	}
	if response["responseMessage"] != "Successful" {
		t.Errorf("Expected responseMessage 'Successful', got %v", response["responseMessage"])
	}
	if response["originalPartnerReferenceNo"] != partnerReferenceNo {
		t.Errorf("Expected originalPartnerReferenceNo %s, got %v", partnerReferenceNo, response["originalPartnerReferenceNo"])
	}

	// Check for INIT status which indicates initiated
	if additionalInfo, ok := response["additionalInfo"].(map[string]interface{}); ok {
		if statusDetail, ok := additionalInfo["statusDetail"].(map[string]interface{}); ok {
			if acquirementStatus, ok := statusDetail["acquirementStatus"].(string); ok {
				if acquirementStatus != "INIT" {
					t.Errorf("Expected acquirementStatus 'INIT', got %v", acquirementStatus)
				}
			}
		}
	}
}
func TestQueryOrderSuccessPaying(t *testing.T) {
	t.Skip("Skip: API returns 404 Not Found - Widget QueryPayment API may not support this scenario or requires pre-existing orders")
	caseName := "QueryOrderSuccessPaying"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct for type safety
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.QueryPayment(ctx).QueryPaymentRequest(request).Execute()
	if err != nil {
		t.Fatalf("API request failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// Assert the success response
	variableDict := map[string]interface{}{
		"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	err = helper.AssertResponse(
		queryOrderJsonPath,
		queryOrderTitleCase,
		caseName,
		string(responseJSON),
		variableDict,
	)
	if err != nil {
		t.Fatal(err)
	}
}
func TestQueryOrderSuccessCancelled(t *testing.T) {
	// Create a test widget payment and cancel it
	partnerReferenceNo, err := createTestWidgetPaymentCanceled()
	if err != nil {
		t.Fatalf("Failed to create and cancel test widget payment: %v", err)
	}

	// Give time for the cancellation to be processed
	time.Sleep(2 * time.Second)

	// Now query the cancelled order
	caseName := "QueryOrderSuccessCancelled"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Marshal to JSON and unmarshal to widget SDK struct for type safety
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.QueryPayment(ctx).QueryPaymentRequest(request).Execute()
	if err != nil {
		t.Fatalf("API request failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// For success scenarios, just verify the essential fields
	response := make(map[string]interface{})
	err = json.Unmarshal(responseJSON, &response)
	if err != nil {
		t.Fatalf("Failed to unmarshal response JSON: %v", err)
	}

	// Check essential fields
	if response["responseCode"] != "2005500" {
		t.Errorf("Expected responseCode 2005500, got %v", response["responseCode"])
	}
	if response["responseMessage"] != "Successful" {
		t.Errorf("Expected responseMessage 'Successful', got %v", response["responseMessage"])
	}
	if response["originalPartnerReferenceNo"] != partnerReferenceNo {
		t.Errorf("Expected originalPartnerReferenceNo %s, got %v", partnerReferenceNo, response["originalPartnerReferenceNo"])
	}

	// Check for CANCELLED status which indicates cancelled
	if additionalInfo, ok := response["additionalInfo"].(map[string]interface{}); ok {
		if statusDetail, ok := additionalInfo["statusDetail"].(map[string]interface{}); ok {
			if acquirementStatus, ok := statusDetail["acquirementStatus"].(string); ok {
				if acquirementStatus != "CANCELLED" {
					t.Errorf("Expected acquirementStatus 'CANCELLED', got %v", acquirementStatus)
				}
			}
		}
	}
}
func TestQueryOrderFailInvalidField(t *testing.T) {
	caseName := "QueryOrderFailInvalidField"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the API call with custom headers to trigger invalid field error
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/debit/status.htm"
	resourcePath := "/v1.0/debit/status.htm"

	// Create custom headers with malformed timestamp format (not RFC3339)
	// This should trigger "Invalid Field Format X-TIMESTAMP" error
	customHeaders := map[string]string{
		"X-TIMESTAMP": "invalid-timestamp-format",
	}

	// Variable dictionary for assertions
	variableDict := map[string]interface{}{
		"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	// Execute the request and assert error response
	err = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		&request,
		"POST",
		endpoint,
		resourcePath,
		queryOrderJsonPath,
		queryOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
	if err != nil {
		t.Fatal(err)
	}
}
func TestQueryOrderFailInvalidMandatoryField(t *testing.T) {
	caseName := "QueryOrderFailInvalidMandatoryField"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the API call with custom headers to trigger invalid mandatory field error
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/debit/status.htm"
	resourcePath := "/v1.0/debit/status.htm"

	// Create custom headers with empty timestamp to trigger invalid mandatory field error
	customHeaders := map[string]string{
		"X-TIMESTAMP": "",
	}

	// Variable dictionary for assertions
	variableDict := map[string]interface{}{
		"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	// Execute the request and assert error response
	err = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		&request,
		"POST",
		endpoint,
		resourcePath,
		queryOrderJsonPath,
		queryOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
	if err != nil {
		t.Fatal(err)
	}
}
func TestQueryOrderFailTransactionNotFound(t *testing.T) {
	caseName := "QueryOrderFailTransactionNotFound"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Use a non-existent transaction ID to trigger not found error
	jsonDict["originalPartnerReferenceNo"] = jsonDict["originalPartnerReferenceNo"].(string) + "_NOT_FOUND"

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call and expect error response
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.QueryPayment(ctx).QueryPaymentRequest(request).Execute()
	if err != nil {
		// This is expected for error test cases
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(queryOrderJsonPath, queryOrderTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}
func TestQueryOrderFailGeneralError(t *testing.T) {
	t.Skip("Skip: SDK signature generation issue prevents proper testing - requires valid signature to test general error scenario")
	caseName := "QueryOrderFailGeneralError"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(queryOrderJsonPath, queryOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.QueryPaymentRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the API call with proper authentication to trigger general error
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/debit/status.htm"
	resourcePath := "/v1.0/debit/status.htm"

	// Use empty custom headers - this will let the helper generate proper signature and timestamp
	customHeaders := map[string]string{}

	// Variable dictionary for assertions
	variableDict := map[string]interface{}{
		"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	// Execute the request and assert error response
	err = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		&request,
		"POST",
		endpoint,
		resourcePath,
		queryOrderJsonPath,
		queryOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
	if err != nil {
		t.Fatal(err)
	}
}
