package helper

import "os"

// TestConfig holds common test configuration variables
var TestConfig = struct {
	PhoneNumber                string
	PIN                        string
	MerchantID                 string
	JsonWidgetPath             string
	JsonPgPath                 string
	JsonMerchantManagementPath string
}{
	PhoneNumber:                "083811223355",
	PIN:                        "181818",
	MerchantID:                 os.Getenv("MERCHANT_ID"),
	JsonWidgetPath:             "../../../resource/request/components/Widget.json",
	JsonPgPath:                 "../../../resource/request/components/PaymentGateway.json",
	JsonMerchantManagementPath: "../../../resource/request/components/MerchantManagement.json",
}
