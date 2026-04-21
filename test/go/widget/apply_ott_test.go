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

		// Get auth code via OAuth flow
		redirectUrlAuthCode, _ := widget_helper.GetRedirectOauthUrl(
			helper.TestConfig.PhoneNumber,
			helper.TestConfig.PIN,
		)
		authCode, _ := widget_helper.GetAuthCode(
			helper.TestConfig.PhoneNumber,
			helper.TestConfig.PIN,
			redirectUrlAuthCode,
		)

		// Exchange auth code for a real access token via ApplyToken API
		ctx := context.Background()
		applyTokenReq := widget.NewApplyTokenAuthorizationCodeRequest("AUTHORIZATION_CODE", authCode)
		applyTokenReqValue := widget.ApplyTokenAuthorizationCodeRequestAsApplyTokenRequest(applyTokenReq)
		applyTokenResp, _, err := helper.ApiClient.WidgetAPI.ApplyToken(ctx).ApplyTokenRequest(applyTokenReqValue).Execute()
		if err != nil {
			return fmt.Errorf("failed to obtain access token: %v", err)
		}
		accessToken := applyTokenResp.GetAccessToken()
		fmt.Printf("Obtained access token: %s\n", accessToken)

		// Get the request data from JSON and only patch accessToken (matching PHP)
		jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName)
		if err != nil {
			return fmt.Errorf("failed to get request data: %v", err)
		}

		if additionalInfo, ok := jsonDict["additionalInfo"].(map[string]interface{}); ok {
			additionalInfo["accessToken"] = accessToken
		} else {
			jsonDict["additionalInfo"] = map[string]interface{}{"accessToken": accessToken}
		}

		jsonBytes, err := json.Marshal(jsonDict)
		if err != nil {
			return fmt.Errorf("failed to marshal JSON: %v", err)
		}

		applyOttReq := widget.ApplyOTTRequest{}
		err = json.Unmarshal(jsonBytes, &applyOttReq)
		if err != nil {
			return fmt.Errorf("failed to unmarshal JSON: %v", err)
		}

		apiResponse, httpResponse, err := helper.ApiClient.WidgetAPI.ApplyOTT(ctx).ApplyOTTRequest(applyOttReq).Execute()
		if err != nil {
			return fmt.Errorf("API request failed: %v", err)
		}
		defer httpResponse.Body.Close()

		responseJSON, err := apiResponse.MarshalJSON()
		if err != nil {
			return fmt.Errorf("failed to convert response to JSON: %v", err)
		}

		err = helper.AssertResponse(
			helper.TestConfig.JsonWidgetPath,
			widgetApplyOttCase,
			caseName,
			string(responseJSON),
			nil,
		)
		if err != nil {
			return err
		}
		return nil
	})
}

func TestApplyOttFailTokenNotFound(t *testing.T) {
	caseName := "ApplyOttCustomerTokenNotFound"
	jsonDict, err := helper.GetRequest(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Only patch accessToken (matching PHP: $jsonDict['additionalInfo']['accessToken'] = 'invalid_access_token_for_testing')
	if additionalInfo, ok := jsonDict["additionalInfo"].(map[string]interface{}); ok {
		additionalInfo["accessToken"] = "invalid_access_token_for_testing"
	} else {
		jsonDict["additionalInfo"] = map[string]interface{}{"accessToken": "invalid_access_token_for_testing"}
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

	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.WidgetAPI.ApplyOTT(ctx).ApplyOTTRequest(applyOttReq).Execute()
	if err != nil {
		err = helper.AssertFailResponse(helper.TestConfig.JsonWidgetPath, widgetApplyOttCase, caseName, httpResponse, nil)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		defer httpResponse.Body.Close()
		t.Fatalf("Expected ApiException was not thrown")
	}
}

func TestApplyOttFailInvalidUserStatus(t *testing.T) {
	t.Skip("Scenario skipped because the result the same with testApplyOttFailTokenNotFound")
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
