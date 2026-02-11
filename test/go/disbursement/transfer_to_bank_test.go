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
	transferToBankTitleCase = "TransferToBank"
	transferToBankJsonPath  = "../../../resource/request/components/Disbursement.json"
)

func TestDisbursementBankValidAccount(t *testing.T) {
	caseName := "DisbursementBankValidAccount"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
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
	err = helper.AssertResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestDisbursementBankInsufficientFund(t *testing.T) {
	caseName := "DisbursementBankInsufficientFund"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankMissingMandatoryField(t *testing.T) {
	caseName := "DisbursementBankMissingMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankUnauthorizedSignature(t *testing.T) {
	caseName := "DisbursementBankUnauthorizedSignature"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/emoney/transfer-bank.htm"
	resourcePath := "/v1.0/emoney/transfer-bank.htm"

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
		transferToBankRequest,
		"POST",
		endpoint,
		resourcePath,
		transferToBankJsonPath,
		transferToBankTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

func TestDisbursementBankInvalidFieldFormat(t *testing.T) {
	caseName := "DisbursementBankInvalidFieldFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankInconsistentRequest(t *testing.T) {
	caseName := "DisbursementBankInconsistentRequest"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	transferToBankRequest.Amount.Value = "12.00"
	transferToBankRequest.Amount.Currency = "IDR"
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankUnknownError(t *testing.T) {
	caseName := "DisbursementBankUnknownError"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankGeneralError(t *testing.T) {
	caseName := "DisbursementBankGeneralError"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankInactiveAccount(t *testing.T) {
	caseName := "DisbursementBankInactiveAccount"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankSuspectedFraud(t *testing.T) {
	caseName := "DisbursementBankSuspectedFraud"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestDisbursementBankValidAccountInProgress(t *testing.T) {
	caseName := "DisbursementBankValidAccountInProgress"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
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
	err = helper.AssertResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestDisbursementBankInvalidMandatoryFieldFormat(t *testing.T) {
	caseName := "DisbursementBankInvalidMandatoryFieldFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToBankRequest object and populate it with JSON data
	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToBankRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/emoney/transfer-bank.htm"
	resourcePath := "/v1.0/emoney/transfer-bank.htm"

	// Set custom headers with invalid authorization to trigger unauthorized error
	customHeaders := map[string]string{
		"X-SIGNATURE": "",
	}

	// Create a variable dictionary to substitute in the response
	variableDict := map[string]interface{}{
		"partnerReferenceNo": partnerReferenceNo,
	}

	_ = helper.ExecuteAndAssertErrorResponse(
		t,
		ctx,
		transferToBankRequest,
		"POST",
		endpoint,
		resourcePath,
		transferToBankJsonPath,
		transferToBankTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}
