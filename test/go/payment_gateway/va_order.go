package payment_gateway

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/dana-id/dana-go/v2/exceptions"
	pg "github.com/dana-id/dana-go/v2/payment_gateway/v1"
	"github.com/google/uuid"

	"uat-script/helper"
)

const (
	DefaultPGComponentJSON = "../../../resource/request/components/PaymentGateway.json"
	// VA sandbox rejects tiny amounts; matches CreateOrderNetworkPayPgOtherVaBank fixture.
	defaultSeedOrderAmount = "15000.00"
	createOrderVACIMBCase  = "CreateOrderNetworkPayPgOtherVaBank"
)

// DefaultSeedOrderAmount is the order amount used for readiness seed/refund setup.
func DefaultSeedOrderAmount() string {
	return defaultSeedOrderAmount
}

func patchCreateOrderAmount(jsonDict map[string]interface{}, amount string) {
	if amount == "" {
		return
	}
	if amt, ok := jsonDict["amount"].(map[string]interface{}); ok {
		amt["value"] = amount
	}
	if details, ok := jsonDict["payOptionDetails"].([]interface{}); ok && len(details) > 0 {
		if detail, ok := details[0].(map[string]interface{}); ok {
			if trans, ok := detail["transAmount"].(map[string]interface{}); ok {
				trans["value"] = amount
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

// CreateOrderVABRI creates a PG API VA CIMB order and returns partner ref + JSON body.
// Uses the dedicated VA fixture (not CreateOrderApi/BALANCE) — cancel readiness uses BALANCE
// without payment; refund/seed need a payable VA order.
func CreateOrderVABRI(componentJSONPath, amount string) (partnerReferenceNo, responseJSON string, err error) {
	if componentJSONPath == "" {
		componentJSONPath = DefaultPGComponentJSON
	}
	if amount == "" {
		amount = defaultSeedOrderAmount
	}
	result, err := helper.RetryOnInconsistentRequest(func() (interface{}, error) {
		jsonDict, err := helper.GetRequest(componentJSONPath, "CreateOrder", createOrderVACIMBCase)
		if err != nil {
			return nil, err
		}

		partnerReferenceNo = uuid.New().String()
		jsonDict["partnerReferenceNo"] = partnerReferenceNo
		jsonDict["validUpTo"] = helper.GenerateFormattedDate(360, 7)
		patchCreateOrderAmount(jsonDict, amount)

		createOrderByApiRequest := &pg.CreateOrderByApiRequest{}
		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return nil, err
		}
		if err = json.Unmarshal(jsonBytes, createOrderByApiRequest); err != nil {
			return nil, err
		}

		ctx := context.Background()
		createOrderReq := pg.CreateOrderRequest{CreateOrderByApiRequest: createOrderByApiRequest}
		apiResponse, httpResponse, err := helper.ApiClient.PaymentGatewayAPI.CreateOrder(ctx).CreateOrderRequest(createOrderReq).Execute()
		if err != nil {
			return nil, fmt.Errorf("%s", formatCreateOrderAPIError(err))
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

// CreateAndPayOrderVABRI creates a VA order and pays it through sandbox tools.
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
