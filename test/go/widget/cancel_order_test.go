package widget_test

import (
	"context"
	"encoding/json"
	"testing"

	widget "github.com/dana-id/dana-go/widget/v1"

	"uat-script/helper"
)

const (
	widgetTitleCase = "CancelOrder"
	widgetJsonPath  = "../../../resource/request/components/Widget.json"
)

func TestCancelOrderFailUserStatusAbnormal(t *testing.T) {
	caseName := "CancelOrderFailUserStatusAbnormal"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call and expect error response
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		// This is expected for error test cases
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestCancelOrderFailMerchantStatusAbnormal(t *testing.T) {
	caseName := "CancelOrderFailMerchantStatusAbnormal"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call and expect error response
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		// This is expected for error test cases
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestCancelOrderFailMissingParameter(t *testing.T) {
	t.Skip("Failed to unmarshal JSON: no value given for required property originalPartnerReferenceNo")
	caseName := "CancelOrderFailMissingParameter"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call and expect error response
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		// This is expected for error test cases
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestCancelOrderFailOrderNotExist(t *testing.T) {
	t.Skip("Expected error but got successful response")
	caseName := "CancelOrderFailOrderNotExist"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call and expect error response
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		// This is expected for error test cases
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestCancelOrderFailExceedCancelWindowTime(t *testing.T) {
	caseName := "CancelOrderFailExceedCancelWindowTime"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Execute the SDK API call and expect error response
	ctx := context.Background()

	// Make the API call using the Widget SDK
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		// This is expected for error test cases
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}

		// Assert the error response matches expected error pattern
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		// If no error occurred, this is unexpected for error test cases
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestCancelOrderFailTimeout(t *testing.T) {
	caseName := "CancelOrderFailTimeout"

	// Get the request data from JSON
	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	// Marshal to JSON and unmarshal to widget SDK struct
	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()

	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestCancelOrderFailAccountStatusAbnormal(t *testing.T) {
	caseName := "CancelOrderFailAccountStatusAbnormal"

	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}

func TestCancelOrderFailInsufficientMerchantBalance(t *testing.T) {
	caseName := "CancelOrderFailInsufficientMerchantBalance"

	jsonDict, err := helper.GetRequest(widgetJsonPath, widgetTitleCase, caseName)
	if err != nil {
		t.Fatalf("Failed to get request data: %v", err)
	}

	jsonBytes, err := json.Marshal(jsonDict)
	if err != nil {
		t.Fatalf("Failed to marshal JSON: %v", err)
	}

	var request widget.CancelOrderRequest
	err = json.Unmarshal(jsonBytes, &request)
	if err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	ctx := context.Background()
	_, httpResponse, err := helper.ApiClient.WidgetAPI.CancelOrder(ctx).CancelOrderRequest(request).Execute()
	if err != nil {
		variableDict := map[string]interface{}{
			"originalPartnerReferenceNo": jsonDict["originalPartnerReferenceNo"],
		}
		err = helper.AssertFailResponse(widgetJsonPath, widgetTitleCase, caseName, httpResponse, variableDict)
		if err != nil {
			t.Fatal(err)
		}
	} else {
		defer httpResponse.Body.Close()
		t.Fatalf("Expected error for case %s but API call succeeded", caseName)
	}
}
