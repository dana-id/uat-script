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
	cancelOrderTitleCase          = "CancelOrder"
	cancelOrderJsonPath           = "../../../resource/request/components/PaymentGateway.json"
	createOrderForCancelTitleCase = "CreateOrder"
)

// createTestOrderForCancel creates a test order to be canceled
func createTestOrderForCancel() (string, error) {
	var partnerReferenceNo string
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		// Get the request data from the JSON file
		caseName := "CreateOrderApi"
		jsonDict, err := helper.GetRequest(cancelOrderJsonPath, createOrderForCancelTitleCase, caseName)
		if err != nil {
			return nil, err
		}

		// Set a unique partner reference number
		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo

		// Create the CreateOrderRequest object and populate it with JSON data
		createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return nil, err
		}

		err = json.Unmarshal(jsonBytes, createOrderByApiRequest)
		if err != nil {
			return nil, err
		}

		// Make the API call
		ctx := context.Background()
		createOrderReq := pg.CreateOrderRequest{
			CreateOrderByApiRequest: createOrderByApiRequest,
		}
		_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()

		return partnerReferenceNo, nil
	}, 3, 2*time.Second)
	if err != nil {
		return "", err
	}
	return result.(string), nil
}

// TestCancelOrder tests canceling the order
func TestCancelOrder(t *testing.T) {
	// Create an order first
	partnerReferenceNo, err := createTestOrderForCancel()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now cancel the order
	caseName := "CancelOrderValidScenario"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
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
	err = helper.AssertResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

// TestCancelOrderInProgress tests canceling the order when in progress
func TestCancelOrderInProgress(t *testing.T) {
	// Use a specific case for in-progress order cancellation
	caseName := "CancelOrderInProgress"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
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
		"partnerReferenceNo": "2025700",
	}

	// Assert the API response with variable substitution
	err = helper.AssertResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

// TestCancelOrderWithUserStatusAbnormal tests if the cancel fails when user status is abnormal
func TestCancelOrderWithUserStatusAbnormal(t *testing.T) {
	// Use a specific case for user status abnormal
	caseName := "CancelOrderUserStatusAbnormal"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": "4035717",
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// TestCancelOrderWithMerchantStatusAbnormal tests if the cancel fails when merchant status is abnormal
func TestCancelOrderWithMerchantStatusAbnormal(t *testing.T) {
	// Use a specific case for merchant status abnormal
	caseName := "CancelOrderMerchantStatusAbnormal"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": "4035703",
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// TestCancelOrderInvalidMandatoryField tests if the cancel fails when mandatory field is invalid (ex: request without X-TIMESTAMP header)
func TestCancelOrderInvalidMandatoryField(t *testing.T) {
	// Create an order first
	partnerReferenceNo, err := createTestOrderForCancel()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now cancel the order with an invalid request
	caseName := "CancelOrderInvalidMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
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

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		cancelOrderRequest,
		"POST",
		endpoint,
		resourcePath,
		cancelOrderJsonPath,
		cancelOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

// TestCancelOrderTransactionNotFound tests if the cancel fails when transaction is not found
func TestCancelOrderTransactionNotFound(t *testing.T) {
	// Use a specific case for transaction not found
	caseName := "CancelOrderTransactionNotFound"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// TestCancelOrderWithExpiredTransaction tests if the cancel fails when transaction is expired
func TestCancelOrderWithExpiredTransaction(t *testing.T) {
	// Use a specific case for expired transaction
	caseName := "CancelOrderTransactionExpired"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// TestCancelOrderWithAccountStatusAbnormal tests if the cancel fails when account status is abnormal
func TestCancelOrderWithAccountStatusAbnormal(t *testing.T) {
	// Use a specific case for account status abnormal
	caseName := "CancelOrderAccountStatusAbnormal"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// TestCancelOrderWithInsufficientFunds tests if the cancel fails when there are insufficient funds
func TestCancelOrderWithInsufficientFunds(t *testing.T) {
	// Use a specific case for insufficient funds
	caseName := "CancelOrderInsufficientFunds"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

// TestCancelOrderUnauthorized tests if the cancel fails with unauthorized error
func TestCancelOrderUnauthorized(t *testing.T) {
	// Create an order first
	partnerReferenceNo, err := createTestOrderForCancel()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now cancel the order with an unauthorized request
	caseName := "CancelOrderUnauthorized"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/payment-host-to-host.htm"
	resourcePath := "/payment-gateway/v1.0/debit/payment-host-to-host.htm"

	// Set custom headers with invalid authorization to trigger unauthorized error
	customHeaders := map[string]string{
		"X-SIGNATURE": "invalid_signature",
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		cancelOrderRequest,
		"POST",
		endpoint,
		resourcePath,
		cancelOrderJsonPath,
		cancelOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

// TestCancelOrderTimeout tests if the cancel handles timeout correctly
func TestCancelOrderTimeout(t *testing.T) {
	caseName := "CancelOrderRequestTimeout"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(cancelOrderJsonPath, cancelOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(cancelOrderJsonPath, cancelOrderTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}
