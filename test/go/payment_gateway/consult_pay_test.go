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

	// Parse the response JSON to check the paymentInfos array
	var responseMap map[string]interface{}
	if err := json.Unmarshal(responseJSON, &responseMap); err != nil {
		t.Fatalf("Failed to parse response JSON: %v", err)
	}

	// Verify response code and message
	if responseCode, ok := responseMap["responseCode"].(string); !ok || responseCode != "2005700" {
		t.Fatalf("Expected response code 2005700, got %v", responseMap["responseCode"])
	}

	if responseMsg, ok := responseMap["responseMessage"].(string); !ok || responseMsg != "Successful" {
		t.Fatalf("Expected response message 'Successful', got %v", responseMap["responseMessage"])
	}

	// Only check if paymentInfos array has at least one item
	paymentInfos, ok := responseMap["paymentInfos"].([]interface{})
	if !ok {
		t.Fatal("paymentInfos is not an array or is missing")
	}

	if len(paymentInfos) == 0 {
		t.Fatal("Expected at least one payment info item")
	}
}
