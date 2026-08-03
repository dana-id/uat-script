package disbursement_test

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"testing"
	"time"
	"uat-script/helper"

	"github.com/dana-id/dana-go/v2/disbursement/v1"
	"github.com/google/uuid"
)

const (
	transferToBankTitleCase = "TransferToBank"
	transferToBankJsonPath  = "../../../resource/request/components/Disbursement.json"
	transferToBankEndpoint  = "https://api.sandbox.dana.id/v1.0/emoney/transfer-bank.htm"
	transferToBankPath      = "/v1.0/emoney/transfer-bank.htm"
)

// assertTransferToBankErrorViaSDK calls the SDK with the fixture payload and logs the error response.
func assertTransferToBankErrorViaSDK(t *testing.T, caseName string) error {
	t.Helper()

	jsonDict, err := helper.GetRequest(transferToBankJsonPath, transferToBankTitleCase, caseName)
	if err != nil {
		return fmt.Errorf("Failed to get request data: %v", err)
	}

	partnerReferenceNo := uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	transferToBankRequest := &disbursement.TransferToBankRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		return fmt.Errorf("Failed to marshal JSON: %v", err)
	}
	if err = json.Unmarshal(jsonBytes, transferToBankRequest); err != nil {
		return fmt.Errorf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
	if httpResponse != nil {
		defer httpResponse.Body.Close()
	}

	fmt.Printf("case=%s err=%v apiResponse=%+v\n", caseName, err, apiResponse)
	if httpResponse != nil && httpResponse.Body != nil {
		if body, readErr := io.ReadAll(httpResponse.Body); readErr == nil {
			fmt.Printf("case=%s httpBody=%s\n", caseName, string(body))
		}
	}
	return nil
}

func TestDisbursementBankValidAccount(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
		err = helper.AssertResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, string(responseJSON), variableDict)
		if err != nil {
			t.Fatal(err)
		}
		return nil
	})
}

func TestDisbursementBankInsufficientFund(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertTransferToBankErrorViaSDK(t, "DisbursementBankInsufficientFund")
	})
}

func TestDisbursementBankMissingMandatoryField(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertTransferToBankErrorViaSDK(t, "DisbursementBankMissingMandatoryField")
	})
}

func TestDisbursementBankUnauthorizedSignature(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
			transferToBankRequest,
			"POST",
			transferToBankEndpoint,
			transferToBankPath,
			transferToBankJsonPath,
			transferToBankTitleCase,
			caseName,
			customHeaders,
			variableDict,
		)
		return nil
	})
}

func TestDisbursementBankInvalidFieldFormat(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
		return nil
	})
}

func TestDisbursementBankInconsistentRequest(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
		return nil
	})
}

func TestDisbursementBankUnknownError(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertTransferToBankErrorViaSDK(t, "DisbursementBankUnknownError")
	})
}

func TestDisbursementBankGeneralError(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertTransferToBankErrorViaSDK(t, "DisbursementBankGeneralError")
	})
}

func TestDisbursementBankInactiveAccount(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertTransferToBankErrorViaSDK(t, "DisbursementBankInactiveAccount")
	})
}

func TestDisbursementBankSuspectedFraud(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertTransferToBankErrorViaSDK(t, "DisbursementBankSuspectedFraud")
	})
}

func TestDisbursementBankValidAccountInProgress(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
		err = helper.AssertResponse(transferToBankJsonPath, transferToBankTitleCase, caseName, string(responseJSON), variableDict)
		if err != nil {
			t.Fatal(err)
		}
		return nil
	})
}

func TestDisbursementBankInvalidMandatoryFieldFormat(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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

		ctx := context.Background()
		customHeaders := map[string]string{
			"X-SIGNATURE": "",
		}
		variableDict := map[string]interface{}{
			"partnerReferenceNo": partnerReferenceNo,
		}

		_ = helper.ExecuteAndAssertErrorResponse(
			t,
			ctx,
			transferToBankRequest,
			"POST",
			transferToBankEndpoint,
			transferToBankPath,
			transferToBankJsonPath,
			transferToBankTitleCase,
			caseName,
			customHeaders,
			variableDict,
		)
		return nil
	})
}
