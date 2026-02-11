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
	transferToDanaTitleCase = "TransferToDana"
	transferToDanaJsonPath  = "../../../resource/request/components/Disbursement.json"
)

func TestTopUpCustomerValid(t *testing.T) {
	caseName := "TopUpCustomerValid"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	// Create the TransferToDanaRequest object and populate it with JSON data
	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
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
	err = helper.AssertResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestTopUpCustomerInsufficientFund(t *testing.T) {
	caseName := "TopUpCustomerInsufficientFund"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerTimeout(t *testing.T) {
	t.Skip("Skip: Test not capable to do automation, need to be run manually TopUpCustomerTimeout")
	caseName := "TopUpCustomerTimeout"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerIdempotent(t *testing.T) {
	caseName := "TopUpCustomerIdempotent"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	// First call to refund the order
	helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	// Second call to test idempotency
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
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
	err = helper.AssertResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, string(responseJSON), variableDict)
	if err != nil {
		t.Fatal(err)
	}
}

func TestTopUpCustomerFrozenAccount(t *testing.T) {
	caseName := "TopUpCustomerFrozenAccount"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerExceedAmountLimit(t *testing.T) {
	caseName := "TopUpCustomerExceedAmountLimit"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerMissingMandatoryField(t *testing.T) {
	caseName := "TopUpCustomerMissingMandatoryField"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerUnauthorizedSignature(t *testing.T) {
	caseName := "TopUpCustomerUnauthorizedSignature"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Set up the context and endpoint details
	ctx := context.Background()
	endpoint := "https://api.sandbox.dana.id/v1.0/emoney/topup.htm"
	resourcePath := "/v1.0/emoney/topup.htm"

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
		transferToDanaRequest,
		"POST",
		endpoint,
		resourcePath,
		transferToDanaJsonPath,
		transferToDanaTitleCase,
		caseName,
		customHeaders,
		variableDict,
	)
}

func TestTopUpCustomerInvalidFieldFormat(t *testing.T) {
	caseName := "TopUpCustomerInvalidFieldFormat"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerInconsistentRequest(t *testing.T) {
	caseName := "TopUpCustomerInconsistentRequest"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	transferToDanaRequest.Amount.Value = "12.00"
	transferToDanaRequest.Amount.Currency = "IDR"
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerInternalServerError(t *testing.T) {
	caseName := "TopUpCustomerInternalServerError"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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

func TestTopUpCustomerInternalGeneralError(t *testing.T) {
	caseName := "TopUpCustomerInternalGeneralError"

	// Get the request data from the JSON file
	jsonDict, err := helper.GetRequest(transferToDanaJsonPath, transferToDanaTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Set the correct partner reference number
	var partnerReferenceNo = uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToDanaRequest := &disbursement.TransferToDanaRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	err = json.Unmarshal(jsonBytes, transferToDanaRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call
	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToDana(ctx).TransferToDanaRequest(*transferToDanaRequest).Execute()
	if err != nil {
		// Assert the API error response
		err = helper.AssertFailResponse(transferToDanaJsonPath, transferToDanaTitleCase, caseName, httpResponse, map[string]interface{}{
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
