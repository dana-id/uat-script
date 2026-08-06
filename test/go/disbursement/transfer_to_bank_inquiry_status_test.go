package disbursement_test

import (
	"context"
	"encoding/json"
	"fmt"
	"testing"
	"time"
	"uat-script/helper"

	"github.com/dana-id/dana-go/v2/disbursement/v1"
	"github.com/google/uuid"
)

const (
	transferToBankInquiryStatusTitleCase = "TransferToBankInquiryStatus"
	transferToBankInquiryStatusJsonPath  = "../../../resource/request/components/Disbursement.json"
)

func createTransferToBankForInquiry() (string, error) {
	var partnerReferenceNo string
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		jsonDict, err := helper.GetRequest(transferToBankInquiryStatusJsonPath, "TransferToBank", "DisbursementBankValidAccount")
		if err != nil {
			return "", err
		}

		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo

		transferToBankRequest := &disbursement.TransferToBankRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return "", err
		}
		if err = json.Unmarshal(jsonBytes, transferToBankRequest); err != nil {
			return "", err
		}

		ctx := context.Background()
		_, httpResponse, err := helper.ApiClient.DisbursementAPI.TransferToBank(ctx).TransferToBankRequest(*transferToBankRequest).Execute()
		if err != nil {
			return "", err
		}
		defer httpResponse.Body.Close()

		ref, err := helper.GetValueFromResponseBody(httpResponse, "partnerReferenceNo")
		if err != nil || ref == "" {
			return partnerReferenceNo, nil
		}
		return ref, nil
	}, 3, 2*time.Second)
	if err != nil {
		return "", fmt.Errorf("failed to create transfer to bank: %w", err)
	}
	if result == nil {
		return "", fmt.Errorf("createTransferToBankForInquiry returned nil")
	}
	return result.(string), nil
}

func TestTransferToBankInquiryStatusSuccessful(t *testing.T) {
	originalPartnerReferenceNo, err := createTransferToBankForInquiry()
	if err != nil {
		t.Fatalf("Failed to create transfer to bank: %v", err)
	}

	caseName := "TransferToBankInquiryStatusSuccessful"

	jsonDict, err := helper.GetRequest(transferToBankInquiryStatusJsonPath, transferToBankInquiryStatusTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonDict["originalPartnerReferenceNo"] = originalPartnerReferenceNo

	inquiryRequest := &disbursement.TransferToBankInquiryStatusRequest{}
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}
	if err = json.Unmarshal(jsonBytes, inquiryRequest); err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()
	apiResponse, httpResponse, err := helper.ApiClient.DisbursementAPI.
		TransferToBankInquiryStatus(ctx).
		TransferToBankInquiryStatusRequest(*inquiryRequest).
		Execute()
	if err != nil {
		fmt.Printf("[REF] case=%s originalPartnerReferenceNo=%s\n", caseName, originalPartnerReferenceNo)
		t.Fatalf("API call failed: %v", err)
	}
	defer httpResponse.Body.Close()

	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	variableDict := map[string]interface{}{
		"originalPartnerReferenceNo": originalPartnerReferenceNo,
	}

	if err = helper.AssertResponse(
		transferToBankInquiryStatusJsonPath,
		transferToBankInquiryStatusTitleCase,
		caseName,
		string(responseJSON),
		variableDict,
	); err != nil {
		t.Fatal(err)
	}
}
