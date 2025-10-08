package disbursement_test

import (
	"context"
	"encoding/json"
	"fmt"
	"testing"
	"time"
	"uat-script/helper"

	"github.com/dana-id/dana-go/disbursement/v1"
	"github.com/google/uuid"
)

const (
	transferToDanaInquiryStatusTitleCase = "TransferToDanaInquiryStatus"
	transferToDanaInquiryStatusJsonPath  = "../../../resource/request/components/Disbursement.json"
)

func createTransferToDanaSuccess() (string, error) {
	var partnerReferenceSuccess string
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		// Get the request data from the JSON file
		jsonDict, _ := helper.GetRequest(transferToDanaInquiryStatusJsonPath, "TransferToDana", "TopUpCustomerValid")

		// Set the correct partner reference number
		var partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo

		// Create the TransferToDanaRequest object and populate it with JSON data
		transferToDanaRequest := &disbursement.TransferToDanaRequest{}
		jsonBytes, _ := json.Marshal(jsonDict)

		json.Unmarshal(jsonBytes, transferToDanaRequest)

		// Make the API call
		ctx := context.Background()
		_, httpResponse, _ := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()

		partnerReferenceSuccess, _ = helper.GetValueFromResponseBody(httpResponse, "partnerReferenceNo")
		defer httpResponse.Body.Close()

		return partnerReferenceSuccess, nil
	}, 3, 2*time.Second)

	if err != nil {
		return "", fmt.Errorf("failed to create order after retries: %w", err)
	}

	// Check if result is nil before type assertion
	if result == nil {
		return "", fmt.Errorf("createOrderInit returned nil result")
	}

	return result.(string), nil
}

func createTransferToDanaFail() (string, error) {
	var partnerReferenceSuccess string
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		// Get the request data from the JSON file
		jsonDict, _ := helper.GetRequest(transferToDanaInquiryStatusJsonPath, "TransferToDana", "TopUpCustomerExceedAmountLimit")

		// Set the correct partner reference number
		var partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo

		// Create the TransferToDanaRequest object and populate it with JSON data
		transferToDanaRequest := &disbursement.TransferToDanaRequest{}
		jsonBytes, _ := json.Marshal(jsonDict)

		json.Unmarshal(jsonBytes, transferToDanaRequest)

		// Make the API call
		ctx := context.Background()
		_, httpResponse, _ := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()

		partnerReferenceSuccess, _ = helper.GetValueFromResponseBody(httpResponse, "partnerReferenceNo")
		defer httpResponse.Body.Close()

		return partnerReferenceSuccess, nil
	}, 3, 2*time.Second)

	if err != nil {
		return "", fmt.Errorf("failed to create order after retries: %w", err)
	}

	// Check if result is nil before type assertion
	if result == nil {
		return "", fmt.Errorf("createOrderInit returned nil result")
	}

	return result.(string), nil
}

func TestInquiryTopUpStatusValidPaid(t *testing.T) {
	originalPartnerReferenceNo, err := createTransferToDanaSuccess()
	if err != nil {
		t.Fatalf("Failed to create initial order: %v", err)
	}
	caseName := "InquiryTopUpStatusValidPaid"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = originalPartnerReferenceNo

	// Create the TransferToDanaRequest object and populate it with JSON data
	transferToDanaInquiryStatus := &disbursement.TransferToDanaInquiryStatusRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaInquiryStatus)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDanaInquiryStatus(ctx).TransferToDanaInquiryStatusRequest(*transferToDanaInquiryStatus).Execute()
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
		"originalPartnerReferenceNo": originalPartnerReferenceNo,
	}

	// Assert the API response with variable substitution
	err = helper.AssertResponse(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestInquiryTopUpStatusValidFail(t *testing.T) {
	originalPartnerReferenceNo, err := createTransferToDanaFail()
	if err != nil {
		t.Fatalf("Failed to create initial order: %v", err)
	}
	caseName := "InquiryTopUpStatusValidFail"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	jsonDict["originalPartnerReferenceNo"] = originalPartnerReferenceNo

	// Create the TransferToDanaRequest object and populate it with JSON data
	transferToDanaInquiryStatus := &disbursement.TransferToDanaInquiryStatusRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaInquiryStatus)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDanaInquiryStatus(ctx).TransferToDanaInquiryStatusRequest(*transferToDanaInquiryStatus).Execute()
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
		"originalPartnerReferenceNo": originalPartnerReferenceNo,
	}

	// Assert the API response with variable substitution
	err = helper.AssertResponse(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestInquiryTopUpStatusInvalidFieldFormat(t *testing.T) {
	caseName := "InquiryTopUpStatusInvalidFieldFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var originalPartnerReferenceNo = uuid.New().String()
	jsonDict["originalPartnerReferenceNo"] = originalPartnerReferenceNo

	// Create the TransferToDanaRequest object and populate it with JSON data
	transferToDanaInquiryStatus := &disbursement.TransferToDanaInquiryStatusRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaInquiryStatus)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDanaInquiryStatus(ctx).TransferToDanaInquiryStatusRequest(*transferToDanaInquiryStatus).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": jsonDict["partnerReferenceNo"],
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

func TestInquiryTopUpStatusNotFoundTransaction(t *testing.T) {
	caseName := "InquiryTopUpStatusNotFoundTransaction"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var originalPartnerReferenceNo = uuid.New().String()
	jsonDict["originalPartnerReferenceNo"] = originalPartnerReferenceNo

	// Create the TransferToDanaRequest object and populate it with JSON data
	transferToDanaInquiryStatus := &disbursement.TransferToDanaInquiryStatusRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaInquiryStatus)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDanaInquiryStatus(ctx).TransferToDanaInquiryStatusRequest(*transferToDanaInquiryStatus).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName, httpResponse, map[string]interface{}{
			"partnerReferenceNo": jsonDict["partnerReferenceNo"],
		})
		if err != nil {
			t.Fatal(err)
		}
	} else {
		httpResponse.Body.Close()
		t.Fatal("Expected error but got successful response")
	}
}

func TestInquiryTopUpStatusMissingMandatoryField(t *testing.T) {
	caseName := "InquiryTopUpStatusMissingMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var originalPartnerReferenceNo = uuid.New().String()
	jsonDict["originalPartnerReferenceNo"] = originalPartnerReferenceNo

	// Create the TransferToDanaRequest object and populate it with JSON data
	transferToDanaInquiryStatus := &disbursement.TransferToDanaInquiryStatusRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaInquiryStatus)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/emoney/topup-status.htm"
	resourcePath := "/v1.0/emoney/topup-status.htm"

	// Set custom headers with invalid authorization to trigger unauthorized error
	customHeaders := map[string]string{
		"X-SIGNATURE": "",
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": originalPartnerReferenceNo,
	}

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		transferToDanaInquiryStatus,
		"POST",
		endpoint,
		resourcePath,
		transferToDanaInquiryStatusJsonPath,
		transferToDanaInquiryStatusTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

func TestInquiryTopUpStatusUnauthorizedSignature(t *testing.T) {
	caseName := "InquiryTopUpStatusUnauthorizedSignature"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaInquiryStatusJsonPath, transferToDanaInquiryStatusTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var originalPartnerReferenceNo = uuid.New().String()
	jsonDict["originalPartnerReferenceNo"] = originalPartnerReferenceNo

	// Create the TransferToDanaRequest object and populate it with JSON data
	transferToDanaInquiryStatus := &disbursement.TransferToDanaInquiryStatusRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaInquiryStatus)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/emoney/topup-status.htm"
	resourcePath := "/v1.0/emoney/topup-status.htm"

	// Set custom headers with invalid authorization to trigger unauthorized error
	customHeaders := map[string]string{
		"X-SIGNATURE": "invalid_signature",
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": originalPartnerReferenceNo,
	}

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		transferToDanaInquiryStatus,
		"POST",
		endpoint,
		resourcePath,
		transferToDanaInquiryStatusJsonPath,
		transferToDanaInquiryStatusTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}
