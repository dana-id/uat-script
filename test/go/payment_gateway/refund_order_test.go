package payment_gateway_test

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"sync"
	"testing"
	"time"

	pg "github.com/dana-id/dana-go/payment_gateway/v1"
	"github.com/google/uuid"

	"uat-script/helper"
	payment "uat-script/payment_gateway"
)

const (
	refundOrderTitleCase          = "RefundOrder"
	refundOrderJsonPath           = "../../../resource/request/components/PaymentGateway.json"
	createOrderForRefundTitleCase = "CreateOrder"
)

var merchantId = os.Getenv("MERCHANT_ID")
var phoneNumber = "0811742234"
var pin = "123321"

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
		jsonDict["merchantId"] = merchantId

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

func createPaidOrder(phoneNumber, pin string) (string, error) {
	partnerReferenceNo, webRedirectUrl, err := createOrderInit()
	payment.PayOrder(phoneNumber, pin, webRedirectUrl)
	return partnerReferenceNo, err
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
	// Create a paid order to get the original partner reference number
	partnerReferenceNo, err := createPaidOrder(phoneNumber, pin)
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
	jsonDict["merchantId"] = merchantId

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
	jsonDict["merchantId"] = merchantId

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
	// Create a paid order to get the original partner reference number
	partnerReferenceNo, err := createPaidOrder(phoneNumber, pin)
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
	jsonDict["merchantId"] = merchantId

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
	_, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).RefundOrderRequest(*refundOrderRequest).Execute()
	if err != nil {
		t.Fatalf("API call failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// // Convert the response to JSON for assertion
	// responseJSON, err := apiResponse.MarshalJSON()
	// if err != nil {
	// 	t.Fatalf("Failed to convert response to JSON: %v", err)
	// }

	// Create variable dictionary for dynamic values
	variableDict := map[string]interface{}{
		"partnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
	}

	// Assert the API response with variable substitution

	err = helper.AssertFailResponse(refundOrderJsonPath, refundOrderTitleCase, caseName, httpResponse, variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestRefundOrderNotExist(t *testing.T) {
	// Use a specific case for in-progress order refund
	caseName := "RefundOrderInvalidBill"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	partnerReferenceNo := "7fcd0a69-60a4-470c-88a2-fe28869e941f"
	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
	jsonDict["partnerRefundNo"] = partnerReferenceNo
	jsonDict["merchantId"] = merchantId

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

func TestRefundOrderExceedTransactionLimit(t *testing.T) {
	// Create a paid order to get the original partner reference number
	partnerReferenceNo, err := createPaidOrder(phoneNumber, pin)
	if err != nil {
		t.Fatalf("Failed to create paid order: %v", err)
	}

	// Use a specific case for in-progress order refund
	caseName := "RefundOrderExceedsTransactionAmountLimit"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
	jsonDict["partnerRefundNo"] = partnerReferenceNo
	jsonDict["merchantId"] = merchantId

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

// TestRefundOrderDueToExceedRefundWindowTime tests if the refund fails when exceeding refund window time
func TestRefundOrderDueToExceedRefundWindowTime(t *testing.T) {
	// Use a specific case for exceeding refund window time
	caseName := "RefundOrderDueToExceedRefundWindowTime"

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

// TestRefundOrderMultipleRefund tests the multiple refund scenario
func TestRefundOrderMultipleRefund(t *testing.T) {
	// Use a specific case for multiple refund
	caseName := "RefundOrderMultipleRefund"

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
	t.Skip("Skip: Need implementation for idempotent refund order test")
	// Create a paid order
	partnerReferenceNo, err := createPaidOrder(phoneNumber, pin)
	if err != nil {
		t.Fatalf("Failed to create paid order: %v", err)
	}

	// Number of concurrent refund attempts
	const numRefunds = 3

	// Use channels to collect results
	results := make(chan string, numRefunds)
	errors := make(chan error, numRefunds)

	// Use WaitGroup to wait for all goroutines
	var wg sync.WaitGroup
	wg.Add(numRefunds)

	// Launch concurrent refunds
	for i := 0; i < numRefunds; i++ {
		go func(index int) {
			defer wg.Done()

			// Generate unique refund reference for each attempt
			refundNo := fmt.Sprintf("%s-refund-%d", partnerReferenceNo, index)

			// Get request data
			jsonDict, err := helper.GetRequest(refundOrderJsonPath, refundOrderTitleCase, "RefundOrderValidScenario")
			if err != nil {
				errors <- fmt.Errorf("failed to get request data: %v", err)
				results <- ""
				return
			}

			jsonDict["originalPartnerReferenceNo"] = partnerReferenceNo
			jsonDict["partnerRefundNo"] = refundNo
			jsonDict["merchantId"] = merchantId

			// Create refund request
			refundOrderRequest := &pg.RefundOrderRequest{}
			jsonBytes, _ := json.Marshal(jsonDict)
			json.Unmarshal(jsonBytes, refundOrderRequest)

			// Make the API call
			ctx := context.Background()
			apiResponse, _, err := helper.ApiClient.PaymentGatewayAPI.RefundOrder(ctx).
				RefundOrderRequest(*refundOrderRequest).Execute()

			if err != nil {
				errors <- err
				results <- ""
				return
			}

			// Get refund ID from response
			refundId := apiResponse.PartnerRefundNo
			results <- refundId
			errors <- nil

		}(i)
	}

	// Wait for all goroutines to complete
	wg.Wait()
	close(results)
	close(errors)

	// Check results
	successCount := 0
	failCount := 0

	for err := range errors {
		if err != nil {
			failCount++
			t.Logf("Refund error: %v", err)
		} else {
			successCount++
		}
	}

	t.Logf("Concurrent refunds: %d succeeded, %d failed", successCount, failCount)

	// At least one refund should succeed
	if successCount == 0 {
		t.Errorf("All concurrent refunds failed")
	}
}
