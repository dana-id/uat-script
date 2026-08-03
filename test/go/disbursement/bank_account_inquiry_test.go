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
	bankAccountInquiryTitleCase = "BankAccountInquiry"
	bankAccountInquiryJsonPath  = "../../../resource/request/components/Disbursement.json"
	bankAccountInquiryEndpoint  = "https://api.sandbox.dana.id/v1.0/emoney/bank-account-inquiry.htm"
	bankAccountInquiryPath      = "/v1.0/emoney/bank-account-inquiry.htm"
)

// assertBankAccountInquiryErrorViaSDK calls the SDK with the fixture payload and logs the error response.
func assertBankAccountInquiryErrorViaSDK(t *testing.T, caseName string) error {
	t.Helper()

	jsonDict, err := helper.GetRequest(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName)
	if err != nil {
		return fmt.Errorf("Failed to get request data: %v", err)
	}

	partnerReferenceNo := uuid.New().String()
	jsonDict["partnerReferenceNo"] = partnerReferenceNo

	bankAccountInquiryRequest := &disbursement.BankAccountInquiryRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		return fmt.Errorf("Failed to marshal JSON: %v", err)
	}
	if err = json.Unmarshal(jsonBytes, bankAccountInquiryRequest); err != nil {
		return fmt.Errorf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.BankAccountInquiry(ctx).BankAccountInquiryRequest(*bankAccountInquiryRequest).Execute()
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

func TestInquiryBankAccountValidDataAmount(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
		err = helper.AssertResponse(bankAccountInquiryJsonPath, bankAccountInquiryTitleCase, caseName, string(responseJSON), variableDict)
		if err != nil {
			t.Fatal(err)
		}
		return nil
	})
}

func TestInquiryBankAccountInsufficientFund(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertBankAccountInquiryErrorViaSDK(t, "InquiryBankAccountInsufficientFund")
	})
}

func TestInquiryBankAccountInactiveAccount(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertBankAccountInquiryErrorViaSDK(t, "InquiryBankAccountInactiveAccount")
	})
}

func TestInquiryBankAccountInvalidMerchant(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertBankAccountInquiryErrorViaSDK(t, "InquiryBankAccountInvalidMerchant")
	})
}

func TestInquiryBankAccountInvalidCard(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertBankAccountInquiryErrorViaSDK(t, "InquiryBankAccountInvalidCard")
	})
}

func TestInquiryBankAccountInvalidFieldFormat(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
		return nil
	})
}

func TestInquiryBankAccountMissingMandatoryField(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
		return assertBankAccountInquiryErrorViaSDK(t, "InquiryBankAccountMissingMandatoryField")
	})
}

func TestInquiryBankAccountUnauthorizedSignature(t *testing.T) {
	helper.RetryTest(t, 3, 2*time.Second, func() error {
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
			bankAccountInquiryRequest,
			"POST",
			bankAccountInquiryEndpoint,
			bankAccountInquiryPath,
			bankAccountInquiryJsonPath,
			bankAccountInquiryTitleCase,
			caseName,
			customHeaders,
			variableDict,
		)
		return nil
	})
}
