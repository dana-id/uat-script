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

// TestCreateOrderRedirectScenario tests creating an order using redirect scenario and pay with DANA
func TestCreateOrderRedirectScenario(t *testing.T) {
	caseName := "CreateOrderRedirect"

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

	// Unmarshal directly into CreateOrderByRedirectRequest
	createOrderByRedirectRequest := &pg.CreateOrderByRedirectRequest{}
	err = json.Unmarshal(jsonBytes, createOrderByRedirectRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Create the final request object
	createOrderReq := pg.CreateOrderRequest{
		CreateOrderByRedirectRequest: createOrderByRedirectRequest,
	}

	// Make the API call with retry on inconsistent request
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		ctx := context.Background()
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()
		responseJSON, err := apiResponse.MarshalJSON()
		if err != nil {
			return nil, err
		}
		return string(responseJSON), nil
	}, 3, 2*time.Second)
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, result.(string), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderApiScenario tests creating an order using API scenario with BALANCE payment method
func TestCreateOrderApiScenario(t *testing.T) {
	caseName := "CreateOrderApi"

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

	// Make the API call with retry on inconsistent request
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		ctx := context.Background()
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()
		responseJSON, err := apiResponse.MarshalJSON()
		if err != nil {
			return nil, err
		}
		return string(responseJSON), nil
	}, 3, 2*time.Second)
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, result.(string), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderNetworkPayPgQris tests creating an order using API scenario with QRIS payment method
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

	// Make the API call with retry on inconsistent request
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		ctx := context.Background()
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()
		responseJSON, err := apiResponse.MarshalJSON()
		if err != nil {
			return nil, err
		}
		return string(responseJSON), nil
	}, 3, 2*time.Second)
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, result.(string), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderNetworkPayPgOtherWallet tests creating an order using API scenario with wallet payment method
func TestCreateOrderNetworkPayPgOtherWallet(t *testing.T) {
	caseName := "CreateOrderNetworkPayPgOtherWallet"

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

	// Make the API call with retry on inconsistent request
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		ctx := context.Background()
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()
		responseJSON, err := apiResponse.MarshalJSON()
		if err != nil {
			return nil, err
		}
		return string(responseJSON), nil
	}, 3, 2*time.Second)
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, result.(string), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderNetworkPayPgOtherVaBank tests creating an order with API scenario using VA bank payment method
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

	// Make the API call with retry on inconsistent request
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		ctx := context.Background()
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()
		responseJSON, err := apiResponse.MarshalJSON()
		if err != nil {
			return nil, err
		}
		return string(responseJSON), nil
	}, 3, 2*time.Second)
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}

	// Assert the API response with the partner reference number as a variable
	err = helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, caseName, result.(string), map[string]interface{}{"partnerReferenceNo": partnerReferenceNo})
	if err != nil {
		t.Fatal(err)
	}
}

// TestCreateOrderInvalidFieldFormat tests failing when field format is invalid (ex: amount without decimal)
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
