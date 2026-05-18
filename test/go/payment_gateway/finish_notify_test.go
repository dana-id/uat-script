package payment_gateway_test

import (
	"context"
	"encoding/json"
	"fmt"
	"testing"
	"time"

	"github.com/dana-id/dana-go/v2/exceptions"
	pg "github.com/dana-id/dana-go/v2/payment_gateway/v1"
	"github.com/google/uuid"

	"uat-script/helper"
)

const (
	createOrderRequestCaseFinishNotify = "CreateOrderApi"
	createOrderAssertCaseFinishNotify  = "CreateOrderApi"
	notificationN8nURL                 = "https://n8n.automation.dana.id/webhook/3676a08f-b06e-416c-b6cd-bea04f71c4d5"
	// Default validUpTo for success/error notify (matches TestCreateOrderNetworkPayPgOtherVaBank).
	finishNotifyDefaultValidUpToOffsetSeconds = 360
	// validUpTo for Expired Notify: now + 2m15s (same timezone as helper.GenerateFormattedDate).
	finishNotifyValidUpToOffsetExpiredSeconds = 2*60 + 15
)

func patchCreateOrderAPIForFinishNotify(jsonDict map[string]interface{}, amount string) {
	if amt, ok := jsonDict["amount"].(map[string]interface{}); ok {
		amt["value"] = amount
	}
	jsonDict["payOptionDetails"] = []interface{}{
		map[string]interface{}{
			"payMethod": "VIRTUAL_ACCOUNT",
			"payOption": "VIRTUAL_ACCOUNT_CIMB",
			"transAmount": map[string]interface{}{
				"value":    amount,
				"currency": "IDR",
			},
		},
	}
	if ups, ok := jsonDict["urlParams"].([]interface{}); ok {
		for _, u := range ups {
			if um, ok := u.(map[string]interface{}); ok {
				if typ, ok := um["type"].(string); ok && typ == "NOTIFICATION" {
					um["url"] = notificationN8nURL
				}
			}
		}
	}
}

func formatCreateOrderAPIError(err error) string {
	if err == nil {
		return ""
	}
	if apiErr, ok := err.(*exceptions.GenericOpenAPIError); ok {
		if body := string(apiErr.Body()); body != "" {
			return fmt.Sprintf("%v: %s", err, body)
		}
	}
	return err.Error()
}

func payVAFromCreateOrderResponse(t *testing.T, body string) {
	t.Helper()
	paymentCode, err := helper.PaymentCodeFromCreateOrderResponse(body)
	if err != nil {
		t.Fatalf("extract paymentCode: %v", err)
	}
	if err := helper.PayVirtualAccountSandbox(paymentCode); err != nil {
		t.Fatalf("pay VA: %v", err)
	}
}

// createOrderAPIFinishNotifyOnce sends CreateOrder (API flow) with VA CIMB. If validUpTo is empty, validUpTo defaults to now+360s.
func createOrderAPIFinishNotifyOnce(amount, validUpTo string) (partnerRef string, responseJSON string, err error) {
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		jsonDict, err := helper.GetRequest(createOrderJsonPath, createOrderTitleCase, createOrderRequestCaseFinishNotify)
		if err != nil {
			return nil, err
		}

		partnerRef := uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerRef
		if validUpTo != "" {
			jsonDict["validUpTo"] = validUpTo
		} else {
			jsonDict["validUpTo"] = helper.GenerateFormattedDate(finishNotifyDefaultValidUpToOffsetSeconds, 7)
		}
		patchCreateOrderAPIForFinishNotify(jsonDict, amount)

		createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return nil, err
		}
		if err := json.Unmarshal(jsonBytes, createOrderByApiRequest); err != nil {
			return nil, err
		}

		createOrderReq := pg.CreateOrderRequest{
			CreateOrderByApiRequest: createOrderByApiRequest,
		}

		ctx := context.Background()
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()

		out, err := apiResponse.MarshalJSON()
		if err != nil {
			return nil, err
		}
		return map[string]interface{}{
			"partnerRef": partnerRef,
			"body":       string(out),
		}, nil
	}, 3, 2*time.Second)
	if err != nil {
		return "", "", err
	}
	if result == nil {
		return "", "", nil
	}
	m := result.(map[string]interface{})
	return m["partnerRef"].(string), m["body"].(string), nil
}

// TestTransactionSuccessNotify creates a PG API order with NOTIFICATION url (n8n) and amount 11011.00.
func TestTransactionSuccessNotify(t *testing.T) {
	partnerRef, body, err := createOrderAPIFinishNotifyOnce("11011.00", "")
	if err != nil {
		t.Fatalf("create order: %s", formatCreateOrderAPIError(err))
	}
	if partnerRef == "" || body == "" {
		t.Fatal("empty partner reference or response")
	}
	if err := helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, createOrderAssertCaseFinishNotify, body, map[string]interface{}{
		"partnerReferenceNo": partnerRef,
	}); err != nil {
		t.Fatal(err)
	}
	payVAFromCreateOrderResponse(t, body)
}

// TestInternalServerErrorNotify creates a PG API order with NOTIFICATION url (n8n) and amount 11012.00.
func TestInternalServerErrorNotify(t *testing.T) {
	partnerRef, body, err := createOrderAPIFinishNotifyOnce("11012.00", "")
	if err != nil {
		t.Fatalf("create order: %s", formatCreateOrderAPIError(err))
	}
	if partnerRef == "" || body == "" {
		t.Fatal("empty partner reference or response")
	}
	if err := helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, createOrderAssertCaseFinishNotify, body, map[string]interface{}{
		"partnerReferenceNo": partnerRef,
	}); err != nil {
		t.Fatal(err)
	}
	payVAFromCreateOrderResponse(t, body)
}

// TestExpiredNotify (Expired Notify) creates a PG API order with NOTIFICATION url (n8n), amount 11013.00, and validUpTo set to now + 2m15s.
func TestExpiredNotify(t *testing.T) {
	validUpTo := helper.GenerateFormattedDate(finishNotifyValidUpToOffsetExpiredSeconds, 7)
	partnerRef, body, err := createOrderAPIFinishNotifyOnce("11013.00", validUpTo)
	if err != nil {
		t.Fatalf("create order: %s", formatCreateOrderAPIError(err))
	}
	if partnerRef == "" || body == "" {
		t.Fatal("empty partner reference or response")
	}
	if err := helper.AssertResponse(createOrderJsonPath, createOrderTitleCase, createOrderAssertCaseFinishNotify, body, map[string]interface{}{
		"partnerReferenceNo": partnerRef,
	}); err != nil {
		t.Fatal(err)
	}
}
