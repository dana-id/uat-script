package payment_gateway_test

import (
	"context"
	"encoding/json"
	"testing"

	pg "github.com/dana-id/dana-go/payment_gateway/v1"

	"uat-script/helper"
)

const (
	consultPayTitleCase = "ConsultPay"
	consultPayJsonPath  = "../../../resource/request/components/PaymentGateway.json"
)

// TestConsultPayWithStrPrivateKeySuccess tests the consult pay API with a private key string
func TestConsultPayBalancedSuccess(t *testing.T) {
	caseName := "ConsultPayBalancedSuccess"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(consultPayJsonPath, consultPayTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the ConsultPayRequest object and populate it with JSON data
	consultPayRequest := &pg.ConsultPayRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, consultPayRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.ConsultPay(ctx).ConsultPayRequest(*consultPayRequest).Execute()
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// Assert the API response with variable substitution
	err = helper.AssertResponse(consultPayJsonPath, consultPayTitleCase, caseName, string(responseJSON), nil)
	if err != nil {
		t.Fatal(err)
	}
}

// TestConsultPayInvalidFieldFormat tests the consult pay API with invalid field format
func TestConsultPayInvalidFieldFormat(t *testing.T) {
	caseName := "ConsultPayBalancedInvalidFieldFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(consultPayJsonPath, consultPayTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonDict["merchantId"] = "" // Set invalid format for merchantId

	// Create the ConsultPayRequest object and populate it with JSON data
	consultPayRequest := &pg.ConsultPayRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, consultPayRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call - expecting it to fail
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.ConsultPay(ctx).ConsultPayRequest(*consultPayRequest).Execute()
	if httpResponse != nil {
		defer httpResponse.Body.Close()
	}

	// Expecting an error for invalid field format
	if err == nil {
		t.Fatal("Expected an error for invalid field format, but got none")
	}

	// Validate the error response
	err = helper.AssertFailResponse(consultPayJsonPath, consultPayTitleCase, caseName, err.Error(), nil)
	if err != nil {
		t.Fatalf("Assertion failed: %v", err)
	}
}

// TestConsultPayInvalidMandatoryField tests the consult pay API with missing mandatory field
func TestConsultPayInvalidMandatoryField(t *testing.T) {
	caseName := "ConsultPayBalancedInvalidMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(consultPayJsonPath, consultPayTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the ConsultPayRequest object and populate it with JSON data
	consultPayRequest := &pg.ConsultPayRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, consultPayRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Use the helper function to execute the request and assert error response
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/payment-gateway/consult-pay.htm"
	resourcePath := "/v1.0/payment-gateway/consult-pay.htm"

	// Custom headers with empty X-TIMESTAMP to trigger mandatory field error
	customHeaders := map[string]string{
		"X-TIMESTAMP": "", // Empty timestamp will cause validation error
	}

	// Execute manual API call and assert error response
	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		consultPayRequest,
		"POST",
		endpoint,
		resourcePath,
		consultPayJsonPath,
		consultPayTitleCase,
		caseName,
		customHeaders,
		nil,
	)
}
