package payment_gateway_test

import (
	"context"
	"encoding/json"
	"testing"

	pg "github.com/dana-id/go_client/payment_gateway/v1"

	"self_testing_scenario/helper"
)

const (
	consultPayTitleCase = "ConsultPay"
	consultPayJsonPath  = "../../../resource/request/components/PaymentGateway.json"
)

// TestConsultPayWithStrPrivateKeySuccess tests the consult pay API with a private key string
func TestConsultPayWithStrPrivateKeySuccess(t *testing.T) {
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

	// Assert the API response
	err = helper.AssertResponse(consultPayJsonPath, consultPayTitleCase, caseName, string(responseJSON), nil)
	if err != nil {
		t.Fatal(err)
	}
}
