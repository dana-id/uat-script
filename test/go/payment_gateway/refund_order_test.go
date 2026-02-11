package payment_gateway_test

import (
	"context"
	"encoding/json"
	"fmt"
	"testing"
	"time"

	pg "github.com/dana-id/dana-go/v2/payment_gateway/v1"
	"github.com/google/uuid"

	"uat-script/helper"
	payment "uat-script/payment_gateway"
)

const (
	refundOrderTitleCase = "RefundOrder"
	refundOrderJsonPath  = "../../../resource/request/components/PaymentGateway.json"
)

// createOrderInit creates a test order for querying with INIT status
func createOrderInit() (string, string, error) {
	var partnerReferenceNo string
	var webRedirectUrl string
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		// Get the request data from the JSON file
		jsonDict, err := helper.GetRequest(refundOrderJsonPath, "CreateOrder", "CreateOrderApi")
		if err != nil {
			return "", err
		}

		// Set a unique partner reference number
		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo

		jsonDict["validUpTo"] = helper.GenerateFormattedDate(360, 7)

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
		return "", "", fmt.Errorf("failed to create order after retries: %w", err)
	}

	// Check if result is nil before type assertion
	if result == nil {
		return "", "", fmt.Errorf("createOrderInit returned nil result")
	}

	return result.(string), webRedirectUrl, nil
}

func createPaidOrder(phoneNumber, pin string) (string, error) {
	partnerReferenceNo, webRedirectUrl, err := createOrderInit()
	if err != nil {
		return "", fmt.Errorf("failed to create order: %w", err)
	}

	// Execute payment - PayOrder returns interface{}, not error
	payment.PayOrder(phoneNumber, pin, webRedirectUrl)

	return partnerReferenceNo, nil
}

// TestRefundOrderInProgress tests refunding an order that is in progress
func TestRefundOrderInProgress(t *testing.T) {
	// Use a specific case for in-progress order refund
	caseName := "RefundOrderInProgress"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
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
		"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	// Assert the API response with variable substitution
	err = helper.AssertResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestRefundOrderValid(t *testing.T) {
	// Prevent parallel execution due to createPaidOrder using Playwright
	t.Setenv("FORCE_SEQUENTIAL", "true")
	helper.RetryTest(t, 3, 1, func() error {
		// Create a paid order to get the original partner reference number
		partnerReferenceNo, err := createPaidOrder(helper.TestConfig.PhoneNumber, helper.TestConfig.PIN)
		if err != nil {
			t.Fatalf("Failed to create paid order: %v", err)
		}

		// Use a specific case for in-progress order refund
		caseName := "RefundOrderValidScenario"

		// Get the request data from the JSON file
		jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
		if err != nil {
			t.Fatalf("Failed to get request data: %v", err)
		}

		jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
		jsonDict["partnerRefundNo"] = partnerReferenceNo

		// Create the RefundOrderRequest object and populate it with JSON data
		refundOrderRequest := &pg.RefundOrderRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			t.Fatalf("Failed to marshal JSON: %v", err)
		}

		err = json.Unmarshal(jsonBytes, refundOrderRequest)
		if err != nil {
			t.Fatalf("Failed to unmarshal JSON: %v", err)
		}

		// Make the API call
		ctx := context.Background()
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
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
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the API response with variable substitution
		err = helper.AssertResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, string(responseJSON), variableDict)
		if err != nil {
			t.Fatal(err)
		}
		return nil
	})
}

func TestRefundOrderNotPaid(t *testing.T) {
	// Create a paid order to get the original partner reference number
	partnerReferenceNo, _, err := createOrderInit()
	if err != nil {
		t.Fatalf("Failed to create order: %v", err)
	}

	// Use a specific case for not allowed refund
	caseName := "RefundOrderNotPaid"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
	jsonDict["partnerRefundNo"] = partnerReferenceNo

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestRefundOrderDuplicateRefund(t *testing.T) {
	// Prevent parallel execution due to createPaidOrder using Playwright
	t.Setenv("FORCE_SEQUENTIAL", "true")
	helper.RetryTest(t, 3, 1, func() error {
		// Create a paid order to get the original partner reference number
		partnerReferenceNo, err := createPaidOrder(helper.TestConfig.PhoneNumber, helper.TestConfig.PIN)
		if err != nil {
			t.Fatalf("Failed to create paid order: %v", err)
		}

		// Use a specific case for in-progress order refund
		caseName := "RefundOrderDuplicateRequest"

		// Get the request data from the JSON file
		jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
		if err != nil {
			t.Fatalf("Failed to get request data: %v", err)
		}

		jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
		jsonDict["partnerRefundNo"] = partnerReferenceNo

		// Create the RefundOrderRequest object and populate it with JSON data
		refundOrderRequest := &pg.RefundOrderRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			t.Fatalf("Failed to marshal JSON: %v", err)
		}

		err = json.Unmarshal(jsonBytes, refundOrderRequest)
		if err != nil {
			t.Fatalf("Failed to unmarshal JSON: %v", err)
		}

		// Make the API call
		ctx := context.Background()
		helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()

		refundOrderRequest.RefundAmount.Value = "10000.00"
		refundOrderRequest.RefundAmount.Currency = "IDR"
		_, httpResponse, _ := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()

		defer httpResponse.Body.Close()

		// Create variable dictionary for dynamic values
		variableDict := map[string]interface{}{
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the API response with variable substitution

		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
		return nil
	})
}

// TestRefundOrderNotAllowed tests if the refund fails when refund is not allowed by agreement
func TestRefundOrderNotAllowed(t *testing.T) {
	// Use a specific case for not allowed refund
	caseName := "RefundOrderNotAllowed"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, map[string]interface{}{
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

// TestRefundOrderIllegalParameter tests if the refund fails with illegal parameter
func TestRefundOrderIllegalParameter(t *testing.T) {
	// Use a specific case for illegal parameter
	caseName := "RefundOrderIllegalParameter"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, map[string]interface{}{
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

// TestRefundOrderInvalidMandatoryField tests if the refund fails when mandatory field is invalid
func TestRefundOrderInvalidMandatoryField(t *testing.T) {
	// Use a specific case for invalid mandatory field
	caseName := "RefundOrderInvalidMandatoryParameter"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/refund.htm"
	resourcePath := "/payment-gateway/v1.0/debit/refund.htm"

	// Set custom headers with missing timestamp to trigger mandatory field error
	customHeaders := map[string]string{
		"X-SIGNATURE": "", // Empty timestamp
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		refundOrderRequest,
		"POST",
		endpoint,
		resourcePath,
		refundOrderJsonPath,
		refundOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

// TestRefundOrderInsufficientFunds tests if the refund fails with insufficient funds
func TestRefundOrderInsufficientFunds(t *testing.T) {
	// Use a specific case for insufficient funds
	caseName := "RefundOrderInsufficientFunds"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, map[string]interface{}{
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

// TestRefundOrderUnauthorized tests if the refund fails when unauthorized
func TestRefundOrderUnauthorized(t *testing.T) {
	// Use a specific case for unauthorized refund
	caseName := "RefundOrderUnauthorized"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/refund.htm"
	resourcePath := "/payment-gateway/v1.0/debit/refund.htm"

	// Set custom headers with invalid signature to trigger unauthorized error
	customHeaders := map[string]string{
		"X-SIGNATURE": "invalid_signature", // Invalid signature
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		refundOrderRequest,
		"POST",
		endpoint,
		resourcePath,
		refundOrderJsonPath,
		refundOrderTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

// TestRefundOrderMerchantStatusAbnormal tests if the refund fails when merchant status is abnormal
func TestRefundOrderMerchantStatusAbnormal(t *testing.T) {
	// Use a specific case for merchant status abnormal
	caseName := "RefundOrderMerchantStatusAbnormal"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, map[string]interface{}{
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

// TestRefundOrderTimeout tests if the refund fails with timeout
func TestRefundOrderTimeout(t *testing.T) {
	// Use a specific case for timeout
	caseName := "RefundOrderTimeout"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestRefundOrderIdempotent(t *testing.T) {
	// Prevent parallel execution due to createPaidOrder using Playwright
	t.Setenv("FORCE_SEQUENTIAL", "true")
	helper.RetryTest(t, 3, 1, func() error {
		// Create a paid order to get the original partner reference number
		partnerReferenceNo, err := createPaidOrder(helper.TestConfig.PhoneNumber, helper.TestConfig.PIN)
		if err != nil {
			t.Fatalf("Failed to create paid order: %v", err)
		}

		// Use a specific case for in-progress order refund
		caseName := "RefundOrderIdempotent"

		// Get the request data from the JSON file
		jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
		if err != nil {
			t.Fatalf("Failed to get request data: %v", err)
		}

		jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
		jsonDict["partnerRefundNo"] = partnerReferenceNo

		// Create the RefundOrderRequest object and populate it with JSON data
		refundOrderRequest := &pg.RefundOrderRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			t.Fatalf("Failed to marshal JSON: %v", err)
		}

		err = json.Unmarshal(jsonBytes, refundOrderRequest)
		if err != nil {
			t.Fatalf("Failed to unmarshal JSON: %v", err)
		}

		// Make the API call
		ctx := context.Background()
		// First call to refund the order
		helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
		// Second call to refund the order (idempotent)
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
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
			"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the API response with variable substitution
		err = helper.AssertResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, string(responseJSON), variableDict)
		if err != nil {
			t.Fatal(err)
		}
		return nil
	})
}

func TestRefundOrderNotExist(t *testing.T) {
	// Use a specific case for in-progress order refund
	caseName := "RefundOrderInvalidBill"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	partnerReferenceNo := "7fcd0a69"
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
	jsonDict["partnerRefundNo"] = partnerReferenceNo

	// Create the RefundOrderRequest object and populate it with JSON data
	refundOrderRequest := &pg.RefundOrderRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, refundOrderRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, map[string]interface{}{
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
