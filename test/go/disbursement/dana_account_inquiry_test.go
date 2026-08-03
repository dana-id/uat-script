package disbursement_test

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"testing"
	"uat-script/helper"

	"github.com/dana-id/dana-go/v2/disbursement/v1"
	"github.com/google/uuid"
)

const (
	danaAccountInquiryTitleCase = "DanaAccountInquiry"
	danaAccountInquiryJsonPath  = "../../../resource/request/components/Disbursement.json"
	danaAccountInquiryEndpoint  = "https://api.sandbox.dana.id/rest/v1.0/emoney/account-inquiry"
	danaAccountInquiryPath      = "/rest/v1.0/emoney/account-inquiry"
)

func TestInquiryCustomerValidData(t *testing.T) {
	caseName := "InquiryCustomerValidData"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the DanaAccountInquiryRequest object and populate it with JSON data
	danaAccountInquiryRequest := &disbursement.DanaAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, danaAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.DanaAccountInquiry(ctx).DanaAccountInquiryRequest(*danaAccountInquiryRequest).Execute()
	if err != nil {
		fmt.Printf("[REF] case=%s partnerReferenceNo=%s\n", caseName, partnerReferenceNo)
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
	err = helper.AssertResponse(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestInquiryCustomerUnauthorizedSignature(t *testing.T) {
	caseName := "InquiryCustomerUnauthorizedSignature"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the DanaAccountInquiryRequest object and populate it with JSON data
	danaAccountInquiryRequest := &disbursement.DanaAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, danaAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()
	customHeaders := map[string]string{
		"X-SIGNATURE": "invalid_signature",
	}
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		danaAccountInquiryRequest,
		"POST",
		danaAccountInquiryEndpoint,
		danaAccountInquiryPath,
		danaAccountInquiryJsonPath,
		danaAccountInquiryTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

func TestInquiryCustomerFrozenAccount(t *testing.T) {
	caseName := "InquiryCustomerFrozenAccount"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the DanaAccountInquiryRequest object and populate it with JSON data
	danaAccountInquiryRequest := &disbursement.DanaAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, danaAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.DanaAccountInquiry(ctx).DanaAccountInquiryRequest(*danaAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryCustomerUnregisteredAccount(t *testing.T) {
	caseName := "InquiryCustomerUnregisteredAccount"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the DanaAccountInquiryRequest object and populate it with JSON data
	danaAccountInquiryRequest := &disbursement.DanaAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, danaAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.DanaAccountInquiry(ctx).DanaAccountInquiryRequest(*danaAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryCustomerExceededLimit(t *testing.T) {
	caseName := "InquiryCustomerExceededLimit"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(danaAccountInquiryJsonPath, danaAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the DanaAccountInquiryRequest object and populate it with JSON data
	danaAccountInquiryRequest := &disbursement.DanaAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, danaAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.DanaAccountInquiry(ctx).DanaAccountInquiryRequest(*danaAccountInquiryRequest).Execute()
	if httpResponse != nil {
		defer httpResponse.Body.Close()
	}

	fmt.Printf("case=%s err=%v apiResponse=%+v\n", caseName, err, apiResponse)
	if httpResponse != nil && httpResponse.Body != nil {
		if body, readErr := io.ReadAll(httpResponse.Body); readErr == nil {
			fmt.Printf("case=%s httpBody=%s\n", caseName, string(body))
		}
	}
}
