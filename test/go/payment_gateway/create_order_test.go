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
	createOrderTitleCase = "CreateOrder"
	createOrderJsonPath  = "../../../resource/request/components/PaymentGateway.json"
)

// generatePartnerReferenceNo generates a unique partner reference number
func generatePartnerReferenceNo() string {
	return uuid.New().String()
}

// TestCreateOrderBalance tests the create order API with balance payment method
func TestCreateOrderBalance(t *testing.T) {
	caseName := "CreateOrderBalance"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set a unique partner reference number
	partnerReferenceNo := generatePartnerReferenceNo()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	// Unmarshal directly into CreateOrderByApiRequest
	createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
	err = json.Unmarshal(jsonBytes, createOrderByApiRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Create the final request object
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderByApiRequest,
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, string(responseJSON), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderNetworkPayPgOtherVaBank tests the create order API with VA Bank payment method
func TestCreateOrderNetworkPayPgOtherVaBank(t *testing.T) {
	caseName := "CreateOrderNetworkPayPgOtherVaBank"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set a unique partner reference number
	partnerReferenceNo := generatePartnerReferenceNo()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	// Unmarshal directly into CreateOrderByApiRequest
	createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
	err = json.Unmarshal(jsonBytes, createOrderByApiRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Create the final request object
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderByApiRequest,
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, string(responseJSON), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderNetworkPayPgQris tests the create order API with QRIS payment method
func TestCreateOrderNetworkPayPgQris(t *testing.T) {
	caseName := "CreateOrderNetworkPayPgQris"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set a unique partner reference number
	partnerReferenceNo := generatePartnerReferenceNo()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	// Unmarshal directly into CreateOrderByApiRequest
	createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
	err = json.Unmarshal(jsonBytes, createOrderByApiRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Create the final request object
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderByApiRequest,
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, string(responseJSON), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderInvalidFieldFormat tests the create order API with invalid field format
func TestCreateOrderInvalidFieldFormat(t *testing.T) {
	caseName := "CreateOrderInvalidFieldFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set a unique partner reference number
	partnerReferenceNo := generatePartnerReferenceNo()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	// Unmarshal directly into CreateOrderByApiRequest
	createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
	err = json.Unmarshal(jsonBytes, createOrderByApiRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Create the final request object
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderByApiRequest,
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
	if err != nil {
		// Assert the API error response with the partner reference number as a variable
		err = helper.AssertFailResponse(createOrderJsonPath, createOrderTitleCase, caseName, httpResponse, map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// TestCreateOrderInconsistentRequest tests the create order API with inconsistent request (e.g., duplicate partner reference number)
func TestCreateOrderInconsistentRequest(t *testing.T) {
	caseName := "CreateOrderInconsistentRequest"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set a unique partner reference number
	partnerReferenceNo := generatePartnerReferenceNo()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderByApiRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	createOrderRequest := &pg.CreateOrderByApiRequest{}
	err = json.Unmarshal(jsonBytes, createOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the first API call
	ctx := context.Background()
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderRequest,
	}
	_, firstResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
	if err != nil {
		t.Fatalf("First API call failed: %v", err)
	}
	// Close the first response body immediately
	firstResponse.Body.Close()

	// Wait briefly
	time.Sleep(2 * time.Second)

	// Make the second API call with the same partner reference number but different amount

	createOrderRequest.Amount.Value = "10000.00"
	createOrderRequest.PayOptionDetails[0].TransAmount.Value = "10000.00"
	createOrderSecondReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderRequest,
	}

	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderSecondReq).Execute()
	if err != nil {
		// Assert the API error response with the partner reference number as a variable
		err = helper.AssertFailResponse(createOrderJsonPath, createOrderTitleCase, caseName, httpResponse, map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		t.Fatal("Expected error but got successful response on duplicate partner reference number")
	}
}

// TestCreateOrderInvalidMandatoryField tests the create order API with invalid mandatory field
func TestCreateOrderInvalidMandatoryField(t *testing.T) {
	caseName := "CreateOrderInvalidMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set a unique partner reference number
	partnerReferenceNo := generatePartnerReferenceNo()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	// Unmarshal directly into CreateOrderByApiRequest
	createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
	err = json.Unmarshal(jsonBytes, createOrderByApiRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Create the final request object
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderByApiRequest,
	}

	// Use the new helper function to execute the request and assert error response
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/payment-host-to-host.htm"
	resourcePath := "/payment-gateway/v1.0/debit/payment-host-to-host.htm"

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
		createOrderReq,
		"POST",
		endpoint,
		resourcePath,
		createOrderJsonPath,
		createOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

// TestCreateOrderUnauthorized tests the create order API with unauthorized access
func TestCreateOrderUnauthorized(t *testing.T) {
	caseName := "CreateOrderUnauthorized"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set a unique partner reference number
	partnerReferenceNo := generatePartnerReferenceNo()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the CreateOrderByApiRequest object and populate it with JSON data
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	createOrderRequest := &pg.CreateOrderByApiRequest{}
	err = json.Unmarshal(jsonBytes, createOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call with invalid auth header
	ctx := context.Background()
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByApiRequest: createOrderRequest,
	}

	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/payment-host-to-host.htm"
	resourcePath := "/payment-gateway/v1.0/debit/payment-host-to-host.htm"

	// Set custom headers with invalid signature to trigger unauthorized error
	customHeaders := map[string]string{
		"X-SIGNATURE": "abcde", // Invalid signature
		"X-TIMESTAMP": time.Now().In(time.FixedZone("GMT+7", 7*60*60)).Format("2006-01-02T15:04:05+07:00"),
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	err = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		createOrderReq,
		"POST",
		endpoint,
		resourcePath,
		createOrderJsonPath,
		createOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}
