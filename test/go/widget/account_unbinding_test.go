package widget_test

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"testing"
	"uat-script/helper"
	widget_helper "uat-script/widget"

	"github.com/dana-id/dana-go/widget/v1"
)

const (
	widgetAccountUnbindingCase = "AccountUnbinding"
)

// AccountUnbinding
func TestAccountUnbindSuccess(t *testing.T) {
	caseName := "AccountUnbindSuccess"
	redirectUrlAuthCode, _ := widget_helper.GetRedirectOauthUrl(
		helper.TestConfig.PhoneNumber,
		helper.TestConfig.PIN,
	)
	authCode, _ := widget_helper.GetAuthCode(
		helper.TestConfig.PhoneNumber,
		helper.TestConfig.PIN,
		redirectUrlAuthCode)
	// Get access token
	accessToken := widget_helper.GetAccessToken(authCode)
	fmt.Printf("Access Token: %s\n", accessToken)

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetAccountUnbindingCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}
	jsonDict["merchantId"] = os.Getenv("MERCHANT_ID")
	jsonDict["additionalInfo"] = map[string]interface{}{
		"accessToken": accessToken,
		"deviceId":    "1234567890",
	}

	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	accountUnbindingRequest := widget.AccountUnbindingRequest{}
	err = json.Unmarshal(jsonBytes, &accountUnbindingRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.AccountUnbinding(ctx).AccountUnbindingRequest(accountUnbindingRequest).Execute()
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
