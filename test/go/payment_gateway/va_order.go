package payment_gateway

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	pg "github.com/dana-id/dana-go/v2/payment_gateway/v1"
	"github.com/google/uuid"

	"uat-script/helper"
)

const (
	DefaultPGComponentJSON = "../../../resource/request/components/PaymentGateway.json"
	defaultSeedOrderAmount = "1.00"
)

// DefaultSeedOrderAmount is the order amount used for readiness and refund happy paths.
func DefaultSeedOrderAmount() string {
	return defaultSeedOrderAmount
}

// PatchCreateOrderAPIForVABRI switches a CreateOrderApi payload to VA BRI (sandbox-friendly, no browser).
func PatchCreateOrderAPIForVABRI(jsonDict map[string]interface{}, amount string) {
	if amount == "" {
		amount = defaultSeedOrderAmount
	}
	if amt, ok := jsonDict["amount"].(map[string]interface{}); ok {
		amt["value"] = amount
	}
	jsonDict["payOptionDetails"] = []interface{}{
		map[string]interface{}{
			"payMethod": "VIRTUAL_ACCOUNT",
			"payOption": "VIRTUAL_ACCOUNT_BRI",
			"transAmount": map[string]interface{}{
				"value":    amount,
				"currency": "IDR",
			},
		},
	}
}

// CreateOrderVABRI creates a PG API order with VA BRI and returns partner ref + JSON body.
func CreateOrderVABRI(componentJSONPath, amount string) (partnerReferenceNo, responseJSON string, err error) {
	if componentJSONPath == "" {
		componentJSONPath = DefaultPGComponentJSON
	}
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		jsonDict, err := helper.GetRequest(componentJSONPath, "CreateOrder", "CreateOrderApi")
		if err != nil {
			return nil, err
		}

		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo
		jsonDict["validUpTo"] = helper.GenerateFormattedDate(360, 7)
		PatchCreateOrderAPIForVABRI(jsonDict, amount)

		createOrderByAPIRequest := &pg.CreateOrderByApiRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return nil, err
		}
		if err = json.Unmarshal(jsonBytes, createOrderByAPIRequest); err != nil {
			return nil, err
		}

		ctx := context.Background()
		createOrderReq := pg.CreateOrderRequest{CreateOrderByApiRequest: createOrderByAPIRequest}
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, err
		}
		defer httpResponse.Body.Close()

		out, err := apiResponse.MarshalJSON()
		if err != nil {
			return nil, err
		}
		return map[string]string{
			"partnerReferenceNo": partnerReferenceNo,
			"body":               string(out),
		}, nil
	}, 3, 2*time.Second)
	if err != nil {
		return "", "", err
	}
	m := result.(map[string]string)
	return m["partnerReferenceNo"], m["body"], nil
}

// PayVAFromCreateOrderResponse pays a VA order via merchant-portal sandbox tools (no Playwright).
func PayVAFromCreateOrderResponse(responseJSON string) error {
	paymentCode, err := helper.PaymentCodeFromCreateOrderResponse(responseJSON)
	if err != nil {
		return fmt.Errorf("extract paymentCode: %w", err)
	}
	if err := helper.PayVirtualAccountSandbox(paymentCode); err != nil {
		return fmt.Errorf("pay VA: %w", err)
	}
	return nil
}

// CreateAndPayOrderVABRI creates a VA BRI order and pays it through sandbox tools.
func CreateAndPayOrderVABRI(componentJSONPath string) (partnerReferenceNo string, err error) {
	partnerReferenceNo, body, err := CreateOrderVABRI(componentJSONPath, defaultSeedOrderAmount)
	if err != nil {
		return "", err
	}
	if err := PayVAFromCreateOrderResponse(body); err != nil {
		return "", err
	}
	time.Sleep(3 * time.Second)
	return partnerReferenceNo, nil
}
