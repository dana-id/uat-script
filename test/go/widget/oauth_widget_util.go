package widget

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"strings"
	"time"
	"uat-script/helper"

	"github.com/dana-id/dana-go/widget/v1"
	"github.com/playwright-community/playwright-go"
)

const (
	widgetTitleCase = "ApplyToken"
	widgetJsonPath  = "../../../resource/request/components/Widget.json"
)

// StringPtr returns a pointer to the given string.
func StringPtr(s string) *string {
	return &s
}

func generateTime() string {
	// Current time in local timezone with RFC3339 format
	currentTime := time.Now().Format(time.RFC3339)
	fmt.Println(currentTime)

	// For a specific time with a specific timezone
	loc, _ := time.LoadLocation("Asia/Jakarta") // UTC+7
	specificTime := time.Date(2024, 12, 23, 7, 44, 11, 0, loc).Format(time.RFC3339)
	fmt.Println(specificTime) // Output: 2024-12-23T07:44:11+07:00

	// For UTC time
	utcTime := time.Now().UTC().Format(time.RFC3339)
	fmt.Println(utcTime)
	return utcTime
}

func GetRedirectOauthUrl(phoneNumber, pin string) (string, error) {
	deviceId := "637216gygd76712313"
	externalId := widget.GenerateExternalId("")
	scopes := widget.GenerateScopes()

	seamlessData := &widget.Oauth2UrlDataSeamlessData{}
	seamlessData.BizScenario = StringPtr("PAYMENT")
	seamlessData.MobileNumber = StringPtr(phoneNumber)
	seamlessData.VerifiedTime = StringPtr(generateTime())
	seamlessData.ExternalUid = StringPtr(externalId)
	seamlessData.DeviceId = StringPtr(deviceId)
	skipRegisterConsult := true
	seamlessData.SkipRegisterConsult = &skipRegisterConsult

	oauth2UrlData := &widget.Oauth2UrlData{
		ExternalId:    externalId,
		MerchantId:    os.Getenv("MERCHANT_ID"),
		SubMerchantId: nil,
		SeamlessData:  seamlessData,
		Scopes:        []string{scopes},
		RedirectUrl:   "https://google.com",
	}

	redirectOauthUrl, err := widget.GenerateOauthUrl(oauth2UrlData)
	fmt.Printf("RedirectOauthUrl: %s\n", redirectOauthUrl)
	return redirectOauthUrl, err
}

func GetAuthCode(phoneNumber, pin, redirectUrl string) (string, error) {
	// Create a custom allocator with non-headless mode
	log.Println("Starting OAuth automation...")

	if redirectUrl == "" {
		return "", fmt.Errorf("error: no redirect URL provided")
	}

	// Install playwright if it's not already installed
	err := playwright.Install()
	if err != nil {
		return "", fmt.Errorf("could not install playwright: %w", err)
	}

	pw, err := playwright.Run()
	if err != nil {
		return "", fmt.Errorf("could not start playwright: %v", err)
	}

	// Launch browser
	browserType := pw.Chromium
	browser, err := browserType.Launch(playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(true),
	})
	if err != nil {
		return "", fmt.Errorf("could not launch browser: %w", err)
	}

	defer browser.Close()

	page, err := browser.NewPage()
	if err != nil {
		log.Fatalf("could not create page: %v", err)
	}

	if _, err = page.Goto(redirectUrl); err != nil {
		log.Fatalf("could not goto: %v", err)
	}

	// Elements for DANA payment
	inputPhoneNumber := ".desktop-input>.txt-input-phone-number-field"
	buttonSubmitPhoneNumber := ".agreement__button>.btn-continue"
	inputPin := ".txt-input-pin-field"
	urlSuccessPaid := "**google.com**"

	// Wait for the phone number input to be visible
	page.Locator(inputPhoneNumber).WaitFor()

	// Fill in the phone number and pin
	page.Locator(inputPhoneNumber).Fill(phoneNumber)
	page.Locator(buttonSubmitPhoneNumber).Click()
	page.Locator(inputPin).Fill(pin)
	page.WaitForURL(urlSuccessPaid)

	oauth := page.URL()
	if oauth == "" {
		return "", fmt.Errorf("error: could not retrieve OAuth URL")
	}

	oauth = strings.Replace(oauth, "https://www.google.com/?", "", 1) // Remove unwanted prefix if present

	// Extract authCode correctly
	parts := strings.Split(oauth, "auth_code=")
	if len(parts) < 2 {
		return "", fmt.Errorf("error: authCode not found in URL: %s", oauth)
	}
	authCode := strings.Split(parts[1], "&")[0]
	fmt.Println("OAuth URL:", oauth)
	fmt.Println("Auth Code:", authCode)

	return authCode, nil
}

func GetAccessToken(authCode string) string {
	// Get the request data from JSON
	jsonDict, _ := helper.GetRequest(widgetJsonPath, "ApplyToken", "ApplyTokenSuccess")
	jsonDict["authCode"] = authCode

	// Marshal to JSON and unmarshal to widget SDK struct for type safety
	jsonBytes, _ := json.Marshal(jsonDict)

	applyTokenAuthorizationCodeRequest := &widget.ApplyTokenAuthorizationCodeRequest{}
	json.Unmarshal(jsonBytes, applyTokenAuthorizationCodeRequest)

	// Create Apply Token request with Authorization Code
	authCodeReq := widget.NewApplyTokenAuthorizationCodeRequest("AUTHORIZATION_CODE", authCode)
	applyTokenRequestValue := widget.ApplyTokenAuthorizationCodeRequestAsApplyTokenRequest(authCodeReq)
	applyTokenRequest := &applyTokenRequestValue

	// Execute the SDK API call with success expectation
	ctx := context.Background()

	// Make the API call using the Widget SDK
	apiResponse, httpResponse, _ := helper.ApiClient.WidgetAPI.ApplyToken(ctx).ApplyTokenRequest(*applyTokenRequest).Execute()
	defer httpResponse.Body.Close()

	return apiResponse.GetAccessToken()
}
