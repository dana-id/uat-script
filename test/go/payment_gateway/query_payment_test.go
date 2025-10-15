package payment_gateway_test

import (
	"context"
	"encoding/json"
	"os"
	"testing"
	"time"

	pg "github.com/dana-id/dana-go/payment_gateway/v1"
	"github.com/google/uuid"

	"uat-script/helper"
	payment "uat-script/payment_gateway"
)

const (
	queryPaymentTitleCase        = "QueryPayment"
	queryPaymentJsonPath         = "../../../resource/request/components/PaymentGateway.json"
	createOrderForQueryTitleCase = "CreateOrder"
	cancelOrderForQueryTitleCase = "CancelOrder"
)

// createOrder creates a test order for querying with INIT status
func createOrder() (string, string, error) {
	var partnerReferenceNo string
	var webRedirectUrl string
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		// Get the request data from the JSON file
		caseName := "CreateOrderApi"
		jsonDict, err := helper.GetRequest(queryPaymentJsonPath, createOrderForQueryTitleCase, caseName)
		if err != nil {
			return "", err
		}

		// Set a unique partner reference number
		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo
		jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")
		jsonDict["validUpTo"] = helper.GenerateFormattedDate(30, 7)

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

		webRedirectUrl, err = helper.GetValueFromResponseBody(httpResponse, "webRedirectUrl")
		if err != nil {
			return "", err
		}

		defer httpResponse.Body.Close()

		return partnerReferenceNo, nil
	}, 3, 2*time.Second)
	if err != nil {
	}
	return result.(string), webRedirectUrl, nil
}

// createOrderCancelQuery creates a test order and then cancels it to achieve canceled status
func createOrderCancelQuery() (string, error) {
	var partnerReferenceNo string
	// Create the order first
	partnerReferenceNo, _, err := createOrder()

	// Now cancel the order
	caseName := "CancelOrderValidScenario"
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, cancelOrderForQueryTitleCase, caseName)
	if err != nil {
		return "", err
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

	// Create the CancelOrderRequest object and populate it with JSON data
	cancelOrderRequest := &pg.CancelOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		return "", err
	}

	err = json.Unmarshal(jsonBytes, cancelOrderRequest)
	if err != nil {
		return "", err
	}

	// Make the API call to cancel the order
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CancelOrder(ctx).CancelOrderRequest(*cancelOrderRequest).Execute()
	if err != nil {
		return "", err
	}
	httpResponse.Body.Close()
	return partnerReferenceNo, nil
}

func createOrderPaidQuery(phoneNumber, pin string) (string, error) {
	partnerReferenceNo, webRedirectUrl, err := createOrder()
	payment.PayOrder(phoneNumber, pin, webRedirectUrl)
	return partnerReferenceNo, err
}

// TestQueryPaymentCreatedOrder tests query the payment with status created but not paid (INIT)
func TestQueryPaymentCreatedOrder(t *testing.T) {
	// Create an order first
	partnerReferenceNo, _, err := createOrder()
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
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

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

func TestQueryPaymentPaidOrder(t *testing.T) {
	helper.RetryTest(t, 3, 1, func() error {
		// Create an order first
		partnerReferenceNo, err := createOrderPaidQuery(helper.TestConfig.PhoneNumber, helper.TestConfig.PIN)
		if err != nil {
			t.Fatalf("Failed to create test order: %v", err)
		}

		// Now query the payment
		caseName := "QueryPaymentPaidOrder"

		// Get the request data from the JSON file
		jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, caseName)
		if err != nil {
			t.Fatalf("Failed to get request data: %v", err)
		}

		// Set the correct partner reference number
		jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
		jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

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
		return nil
	})
}

// TestQueryPaymentCanceledOrder tests query the payment with status canceled (CANCELLED)
func TestQueryPaymentCanceledOrder(t *testing.T) {
	// Create an order first with short expiry time
	partnerReferenceNo, err := createOrderCancelQuery()
	if err != nil {
		t.Fatalf("Failed to create test order with canceled status: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now query the payment
	caseName := "QueryPaymentCanceledOrder"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

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

// TestQueryPaymentInvalidFormat tests if the query fails when using invalid format (ex: X-TIMESTAMP header format not correct)
func TestQueryPaymentInvalidFormat(t *testing.T) {
	// Create an order first
	partnerReferenceNo, _, err := createOrder()
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
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

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

	_ = helper.ExecuteAndAssertErrorResponse(
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

// TestQueryPaymentInvalidMandatoryField tests if the query fails when missing mandatory field (ex: request without X-TIMESTAMP header)
func TestQueryPaymentInvalidMandatoryField(t *testing.T) {
	// Create an order first
	partnerReferenceNo, _, err := createOrder()
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
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

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

	_ = helper.ExecuteAndAssertErrorResponse(
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

// TestQueryPaymentUnauthorized tests if the query fails when unauthorized due to invalid signature
func TestQueryPaymentUnauthorized(t *testing.T) {
	// Create an order first
	partnerReferenceNo, _, err := createOrder()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now query the payment
	caseName := "QueryPaymentUnauthorized"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(queryPaymentJsonPath, queryPaymentTitleCase, "QueryPaymentCreatedOrder")
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

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

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm"
	resourcePath := "/payment-gateway/v1.0/debit/status.htm"

	// Set custom headers with invalid signature to trigger authorization error
	customHeaders := map[string]string{
		"X-SIGNATURE": "invalid_signature", // Invalid signature
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	_ = helper.ExecuteAndAssertErrorResponse(
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

// TestQueryPaymentTransactionNotFound tests if the query fails when transaction is not found
func TestQueryPaymentTransactionNotFound(t *testing.T) {
	// Create an order first
	partnerReferenceNo, _, err := createOrder()
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
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")

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
		t.Fatal("Expected error but got successful response ")
	}
}

// TestQueryPaymentGeneralError tests the query payment API with general error
func TestQueryPaymentGeneralError(t *testing.T) {
	// Create an order first
	partnerReferenceNo, _, err := createOrder()
	if err != nil {
		t.Fatalf("Failed to create test order: %v", err)
	}

	// Give time for the order to be processed
	time.Sleep(2 * time.Second)

	// Now query the payment
	caseName := "QueryPaymentGeneralError"

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
