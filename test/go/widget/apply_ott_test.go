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
	widgetApplyOttCase = "ApplyOtt"
)

// ApplyOtt
func TestApplyOttSuccess(t *testing.T) {
	helper.RetryTest(t, 3, 1, func() error {
		caseName := "ApplyOttSuccess"
		// Get OAuth redirect URL
		redirectUrlAuthCode, err := widget_helper.GetRedirectOauthUrl(
			helper.TestConfig.PhoneNumber,
			helper.TestConfig.PIN,
		)
		// Get auth code
		authCode, err := widget_helper.GetAuthCode(
			helper.TestConfig.PhoneNumber,
			helper.TestConfig.PIN,
			redirectUrlAuthCode,
		)
		// Get access token
		accessToken := widget_helper.GetAccessToken(authCode)
		fmt.Printf("Access Token: %s\n", accessToken)

		// Get the request data from JSON
		jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName)
		if err != nil {
			t.Fatalf("Failed to get request data: %v", err)
		}

		jsonDict["additionalInfo"] = map[string]interface{}{
			"accessToken": accessToken,
			"deviceId":    "1234567890",
		}

		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			t.Fatalf("Failed to marshal JSON: %v", err)
		}

		applyOttReq := widget.ApplyOTTRequest{}
		err = json.Unmarshal(jsonBytes, &applyOttReq)
		if err != nil {
			t.Fatalf("Failed to unmarshal JSON: %v", err)
		}
		// Execute the SDK API call with success expectation
		ctx := context.Background()

		// Make the API call using the Widget SDK
		apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.ApplyOTT(ctx).ApplyOTTRequest(applyOttReq).Execute()
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
			widgetApplyOttCase,
			caseName,
			string(responseJSON),
			nil,
		)
		if err != nil {
			t.Fatal(err)
		}
		return nil
	})
}
func TestApplyOttFailTokenNotFound(t *testing.T) {
	caseName := "ApplyOttCustomerTokenNotFound"
	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonDict["additionalInfo"] = map[string]interface{}{
		"accessToken": "GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800",
		"deviceId":    "1234567890",
	}

	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	applyOttReq := widget.ApplyOTTRequest{}
	err = json.Unmarshal(jsonBytes, &applyOttReq)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}
	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.ApplyOTT(ctx).ApplyOTTRequest(applyOttReq).Execute()
	if err != nil {
		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName, httpResponse, nil)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestApplyOttFailInvalidUserStatus(t *testing.T) {
	t.Skip("Skipping test ApplyOttFailInvalidUserStatus")
	caseName := "ApplyOttFailInvalidUserStatus"
	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}
	jsonDict["authCode"] = "GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800"

	// Marshal to JSON and unmarshal to widget SDK struct for type safety
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	applyOTTRequest := &widget.ApplyOTTRequest{}
	err = json.Unmarshal(jsonBytes, applyOTTRequest)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.ApplyOTT(ctx).ApplyOTTRequest(*applyOTTRequest).Execute()
	if err != nil {
		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName, httpResponse, nil)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}
