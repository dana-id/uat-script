package payment_gateway_test

import (
	"context"
	"encoding/json"
	"testing"
	"time"

	pg "github.com/dana-id/go_client/payment_gateway/v1"
	"github.com/google/uuid"

	"self_testing_scenario/helper"
)

const (
	queryPaymentTitleCase        = "QueryPayment"
	queryPaymentJsonPath         = "../../../resource/request/components/PaymentGateway.json"
	createOrderForQueryTitleCase = "CreateOrder"
)

// createTestOrder creates a test order to be queried
func createTestOrder() (string, error) {
	// Get the request data from the JSON file
	caseName := "CreateOrderApi"
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, createOrderForQueryTitleCase, caseName)
	if err != nil {
		return "", err
	}

	// Set a unique partner reference number
	partnerReferenceNo := uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderRequest object and populate it with JSON data
	createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(jsonBytes, createOrderByApiRequest)
	if err != nil {
		return "", err
	}

	// Make the API call
	ctx := context.Background()
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderByApiRequest,
	}
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
	if err != nil {
		return "", err
	}
	defer httpResponse.Body.Close()

	return partnerReferenceNo, nil
}

// TestQueryPaymentValidFormat tests the query payment API with valid format
func TestQueryPaymentValidFormat(t *testing.T) {
	// Create an order first
	partnerReferenceNo, err := createTestOrder()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now query the payment
	caseName := "QueryPaymentCreatedOrder"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Create the QueryPaymentRequest object and populate it with JSON data
	queryPaymentRequest := &pg.QueryPaymentRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, queryPaymentRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.QueryPayment(ctx).QueryPaymentRequest(*queryPaymentRequest).Execute()
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// Create variable dictionary for dynamic values
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	// Assert the API response with variable substitution
	err = helper.AssertResponse(queryPaymentJsonPath, queryPaymentTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

// TestQueryPaymentInvalidFormat tests the query payment API with invalid format
func TestQueryPaymentInvalidFormat(t *testing.T) {
	// Create an order first
	partnerReferenceNo, err := createTestOrder()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now query the payment
	caseName := "QueryPaymentInvalidFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Create the QueryPaymentRequest object and populate it with JSON data
	queryPaymentRequest := &pg.QueryPaymentRequest{}
	// Use the new helper function to execute the request and assert error response
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm"
	resourcePath := "/payment-gateway/v1.0/debit/status.htm"

	// Custom headers with incorrect timestamp format to trigger validation error
	customHeaders := map[string]string{
		"X-TIMESTAMP": time.Now().Format("2006-01-02 15:04:05+07:00"), // Incorrect timestamp format
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	err = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		queryPaymentRequest,
		"POST",
		endpoint,
		resourcePath,
		queryPaymentJsonPath,
		queryPaymentTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

// TestQueryPaymentInvalidMandatoryField tests the query payment API with invalid mandatory field
func TestQueryPaymentInvalidMandatoryField(t *testing.T) {
	// Create an order first
	partnerReferenceNo, err := createTestOrder()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now query the payment
	caseName := "QueryPaymentInvalidMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Create the QueryPaymentRequest object and populate it with JSON data
	queryPaymentRequest := &pg.QueryPaymentRequest{}
	// Use the new helper function to execute the request and assert error response
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm"
	resourcePath := "/payment-gateway/v1.0/debit/status.htm"

	// Set custom headers with empty X-TIMESTAMP to trigger mandatory field error
	customHeaders := map[string]string{
		"X-TIMESTAMP": "", // Missing mandatory field
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	err = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		queryPaymentRequest,
		"POST",
		endpoint,
		resourcePath,
		queryPaymentJsonPath,
		queryPaymentTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

// TestQueryPaymentTransactionNotFound tests the query payment API with transaction not found
func TestQueryPaymentTransactionNotFound(t *testing.T) {
	// Create an order first
	partnerReferenceNo, err := createTestOrder()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now query the payment
	caseName := "QueryPaymentTransactionNotFound"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the partner reference number with a modification to ensure it's not found
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo + "test"

	// Create the QueryPaymentRequest object and populate it with JSON data
	queryPaymentRequest := &pg.QueryPaymentRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, queryPaymentRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.QueryPayment(ctx).QueryPaymentRequest(*queryPaymentRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(queryPaymentJsonPath, queryPaymentTitleCase, caseName, httpResponse, nil)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// // TestQueryPaymentGeneralError tests the query payment API with general error
// func TestQueryPaymentGeneralError(t *testing.T) {
// 	// Create an order first
// 	partnerReferenceNo, err := createTestOrder()
// 	if err != nil {
// 		t.Fatalf("Failed to create test order: %v", err)
// 	}

// 	// Give time for the order to be processed
// 	time.Sleep(2 * time.Second)

// 	// Now query the payment
// 	caseName := "QueryPaymentGeneralError"

// 	// Get the request data from the JSON file
// 	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, caseName)
// 	if err != nil {
// 		t.Fatalf("Failed to get request data: %v", err)
// 	}

// 	// Set the correct partner reference number
// 	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

// 	// Create the QueryPaymentRequest object and populate it with JSON data
// 	queryPaymentRequest := &pg.QueryPaymentRequest{}
// 	jsonBytes, err := json.Marshal(jsonDict)
// 	if err != nil {
// 		t.Fatalf("Failed to marshal JSON: %v", err)
// 	}

// 	err = json.Unmarshal(jsonBytes, queryPaymentRequest)
// 	if err != nil {
// 		t.Fatalf("Failed to unmarshal JSON: %v", err)
// 	}

// 	// Make the API call
// 	ctx := context.Background()
// 	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.QueryPayment(ctx).QueryPaymentRequest(*queryPaymentRequest).Execute()
// 	if err != nil {
// 		// Assert the API error response
// 		err = helper.AssertFailResponse(queryPaymentJsonPath, queryPaymentTitleCase, caseName, httpResponse, nil)
// 		if err != nil {
// 			t.Fatal(err)
// 		}
// 	} else {
// 		httpResponse.Body.Close()
// 		t.Fatal("Expected error but got successful response")
// 	}
// }
