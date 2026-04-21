package widget_test

import (
	"context"
	"encoding/json"
	"fmt"
	"testing"
	"uat-script/helper"
	widget_helper "uat-script/widget"

	"github.com/dana-id/dana-go/v2/widget/v1"
)

const (
	widgetAccountUnbindingCase = "AccountUnbinding"
)

// AccountUnbinding
func TestAccountUnbindSuccess(t *testing.T) {
	caseName := "AccountUnbindSuccess"

	// Get auth code via OAuth flow
	redirectUrlAuthCode, _ := widget_helper.GetRedirectOauthUrl(
		helper.TestConfig.PhoneNumber,
		helper.TestConfig.PIN,
	)
	authCode, _ := widget_helper.GetAuthCode(
		helper.TestConfig.PhoneNumber,
		helper.TestConfig.PIN,
		redirectUrlAuthCode)

	// Exchange auth code for a real access token via ApplyToken API (matching PHP setUpBeforeClass)
	ctx := context.Background()
	applyTokenReq := widget.NewApplyTokenAuthorizationCodeRequest("AUTHORIZATION_CODE", authCode)
	applyTokenReqValue := widget.ApplyTokenAuthorizationCodeRequestAsApplyTokenRequest(applyTokenReq)
	applyTokenResp, _, err := helper.ApiClient.WidgetAPI.ApplyToken(ctx).ApplyTokenRequest(applyTokenReqValue).Execute()
	if err != nil {
		t.Fatalf("Failed to obtain access token: %v", err)
	}
	accessToken := applyTokenResp.GetAccessToken()
	fmt.Printf("Obtained access token: %s\n", accessToken)

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetAccountUnbindingCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonDict["additionalInfo"] = map[string]interface{}{
		"accessToken": accessToken,
		"deviceId":    helper.TestConfig.DeviceID,
	}
	jsonDict["merchantId"] = helper.TestConfig.MerchantID

	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	accountUnbindingRequest := widget.AccountUnbindingRequest{}
	err = json.Unmarshal(jsonBytes, &accountUnbindingRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.AccountUnbinding(ctx).AccountUnbindingRequest(accountUnbindingRequest).Execute()
	if err != nil {
		t.Fatalf("API request failed: %v", err)
	}
	defer httpResponse.Body.Close()

	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	err = helper.AssertResponse(
		helper.TestConfig.JsonWidgetPath,
		widgetAccountUnbindingCase,
		caseName,
		string(responseJSON),
		nil,
	)
	if err != nil {
		t.Fatal(err)
	}
}

func TestAccountUnbindFailInvalidUserStatus(t *testing.T) {
	t.Skip("Skipping test AccountUnbindFailInvalidUserStatus")
	caseName := "AccountUnbindFailInvalidUserStatus"
	redirectUrlAuthCode, _ := widget_helper.GetRedirectOauthUrl(
		helper.TestConfig.PhoneNumber,
		helper.TestConfig.PIN,
	)
	authCode, _ := widget_helper.GetAuthCode(
		helper.TestConfig.PhoneNumber,
		helper.TestConfig.PIN,
		redirectUrlAuthCode)

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetAccountUnbindingCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}
	jsonDict["authCode"] = authCode

	// Marshal to JSON and unmarshal to widget SDK struct for type safety
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	accountUnbindingRequest := &widget.AccountUnbindingRequest{}
	err = json.Unmarshal(jsonBytes, accountUnbindingRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.AccountUnbinding(ctx).AccountUnbindingRequest(*accountUnbindingRequest).Execute()
	if err != nil {
		t.Fatalf("API request failed: %v", err)
	}
	defer httpResponse.Body.Close()

	// Convert the response to JSON for assertion
	responseJSON, err := apiResponse.MarshalJSON()
	if err != nil {
		t.Fatalf("Failed to convert response to JSON: %v", err)
	}

	err = helper.AssertResponse(
		helper.TestConfig.JsonWidgetPath,
		widgetAccountUnbindingCase,
		caseName,
		string(responseJSON),
		nil,
	)
	if err != nil {
		t.Fatal(err)
	}
}
