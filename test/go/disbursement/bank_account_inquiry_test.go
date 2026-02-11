package disbursement_test

import (
	"context"
	"encoding/json"
	"testing"
	"uat-script/helper"

	"github.com/dana-id/dana-go/v2/disbursement/v1"
	"github.com/google/uuid"
)

const (
	bankAccountInquiryTitleCase = "BankAccountInquiry"
	bankAccountInquiryJsonPath  = "../../../resource/request/components/Disbursement.json"
)

func TestInquiryBankAccountValidDataAmount(t *testing.T) {
	caseName := "InquiryBankAccountValidDataAmount"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
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
	err = helper.AssertResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestInquiryBankAccountInsufficientFund(t *testing.T) {
	caseName := "InquiryBankAccountInsufficientFund"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryBankAccountInactiveAccount(t *testing.T) {
	caseName := "InquiryBankAccountInactiveAccount"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryBankAccountInvalidMerchant(t *testing.T) {
	caseName := "InquiryBankAccountInvalidMerchant"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryBankAccountInvalidCard(t *testing.T) {
	caseName := "InquiryBankAccountInvalidCard"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryBankAccountInvalidFieldFormat(t *testing.T) {
	caseName := "InquiryBankAccountInvalidFieldFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryBankAccountMissingMandatoryField(t *testing.T) {
	caseName := "InquiryBankAccountMissingMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestInquiryBankAccountUnauthorizedSignature(t *testing.T) {
	caseName := "InquiryBankAccountUnauthorizedSignature"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the BankAccountInquiryRequest object and populate it with JSON data
	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/emoney/bank-account-inquiry.htm"
	resourcePath := "/v1.0/emoney/bank-account-inquiry.htm"

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
		bankAccountInquiryRequest,
		"POST",
		endpoint,
		resourcePath,
		bankAccountInquiryJsonPath,
		bankAccountInquiryTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}
