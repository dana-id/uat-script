package widget

import (
	"context"
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"log"
	"net/url"
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

// extractAuthCodeFromURL extracts auth_code or authCode from URL using proper URL parsing
func extractAuthCodeFromURL(urlString string) (string, error) {
	parsedURL, err := url.Parse(urlString)
	if err != nil {
		return "", fmt.Errorf("failed to parse URL: %w", err)
	}

	queryParams := parsedURL.Query()

	// Try auth_code first (snake_case)
	if authCode := queryParams.Get("auth_code"); authCode != "" {
		return authCode, nil
	}

	// Try authCode (camelCase)
	if authCode := queryParams.Get("authCode"); authCode != "" {
		return authCode, nil
	}

	return "", fmt.Errorf("neither auth_code nor authCode found in URL query parameters")
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

// Function from lib
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
		RedirectUrl:   os.Getenv("REDIRECT_URL_OAUTH"),
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

	print("Redirect URL:", redirectUrl, "\n")

	if _, err = page.Goto(redirectUrl); err != nil {
		log.Fatalf("could not goto: %v", err)
	}

	// Elements for DANA payment
	inputPhoneNumber := ".desktop-input>.txt-input-phone-number-field"
	buttonSubmitPhoneNumber := ".agreement__button>.btn-continue"
	inputPin := ".txt-input-pin-field"
	urlRedirectOauth := os.Getenv("REDIRECT_URL_OAUTH")

	// Wait for the phone number input to be visible
	page.Locator(inputPhoneNumber).WaitFor()

	// Fill in the phone number and pin
	page.Locator(inputPhoneNumber).Fill(phoneNumber)
	page.Locator(buttonSubmitPhoneNumber).Click()
	page.Locator(inputPin).Fill(pin)
	time.Sleep(2 * time.Second) // Wait for 2 seconds to ensure PIN is processed

	oauth := page.URL()
	if oauth == "" {
		return "", fmt.Errorf("error: could not retrieve OAuth URL")
	}

	// Extract authCode or auth_code using proper URL parsing
	authCode, err := extractAuthCodeFromURL(oauth)
	if err != nil {
		// Fallback to string parsing if URL parsing fails
		oauth = strings.Replace(oauth, urlRedirectOauth, "", 1) // Remove unwanted prefix if present

		// First try to find "authCode="
		if strings.Contains(oauth, "authCode=") {
			parts := strings.Split(oauth, "authCode=")
			if len(parts) >= 2 {
				authCode = strings.Split(parts[1], "&")[0]
			}
		}

		// If authCode not found, try "auth_code="
		if authCode == "" && strings.Contains(oauth, "auth_code=") {
			parts := strings.Split(oauth, "auth_code=")
			if len(parts) >= 2 {
				authCode = strings.Split(parts[1], "&")[0]
			}
		}

		// Check if we found any auth code
		if authCode == "" {
			return "", fmt.Errorf("error: neither authCode nor auth_code found in URL: %s", oauth)
		}
	}

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

// Generate url from UAT script
func SetManualAuthCode(phoneNumber, pin string) (string, error) {
	seamlessData := GenerateSeamlessData(phoneNumber)
	seamlessSign, err := GenerateSeamlessSign(seamlessData)
	if err != nil {
		return "", fmt.Errorf("failed to generate seamless sign: %w", err)
	}
	urlRedirectAuth := GenerateRedirectLinkAuthCode(seamlessData, seamlessSign)
	print("Redirect URL Manual:", urlRedirectAuth, "\n")
	authCode, _ := GetAuthCode(
		helper.TestConfig.PhoneNumber,
		helper.TestConfig.PIN,
		urlRedirectAuth)
	return authCode, nil
}

// Generate url from UAT script
func GenerateSeamlessData(phoneNumber string) string {
	bizScenario := "PAYMENT"
	timeVerified := generateTime()
	externalUid := widget.GenerateExternalId("")
	deviceId := "637216gygd76712313"
	skipRegisterConsult := true

	seamlessData := fmt.Sprintf("{\"phoneNumber\":\"%s\",\"bizScenario\":\"%s\",\"verifiedTime\":\"%s\",\"externalUid\":\"%s\",\"deviceId\":\"%s\",\"skipRegisterConsult\":%t}",
		phoneNumber, bizScenario, timeVerified, externalUid, deviceId, skipRegisterConsult)

	return seamlessData
}

// GetPrivateKey converts a base64-encoded private key string to an RSA private key
// This is the Go equivalent of the Java method:
// public static PrivateKey getPrivateKey(String privateKeyMerchant)
func GetPrivateKey(privateKeyMerchant string) (*rsa.PrivateKey, error) {
	// Decode the base64-encoded private key
	keyBytes, err := base64.StdEncoding.DecodeString(privateKeyMerchant)
	if err != nil {
		return nil, fmt.Errorf("failed to decode base64 private key: %w", err)
	}

	// Parse the PKCS8-encoded private key
	privateKey, err := x509.ParsePKCS8PrivateKey(keyBytes)
	if err != nil {
		return nil, fmt.Errorf("failed to parse PKCS8 private key: %w", err)
	}

	// Type assert to RSA private key
	rsaPrivateKey, ok := privateKey.(*rsa.PrivateKey)
	if !ok {
		return nil, fmt.Errorf("private key is not an RSA key")
	}

	return rsaPrivateKey, err
}

// Sign creates a SHA256withRSA signature for the given text payload using the private key
// This is the Go equivalent of the Java method:
// private static String sign(String textPayload, String privateKeyMerchant)
func Sign(textPayload, privateKeyMerchant string) (string, error) {
	// Get the private key object
	privateKeyObject, err := GetPrivateKey(privateKeyMerchant)
	if err != nil {
		return "", fmt.Errorf("failed to get private key: %w", err)
	}

	// Create SHA256 hash of the payload
	hash := sha256.Sum256([]byte(textPayload))

	// Sign the hash using RSA-PSS with SHA256
	signature, err := rsa.SignPKCS1v15(rand.Reader, privateKeyObject, crypto.SHA256, hash[:])
	if err != nil {
		return "", fmt.Errorf("failed to sign payload: %w", err)
	}

	// Encode the signature to base64
	signatureBase64 := base64.StdEncoding.EncodeToString(signature)

	return signatureBase64, nil
}

func GenerateSeamlessSign(payload string) (string, error) {
	privateKey := os.Getenv("PRIVATE_KEY")
	signature, err := Sign(payload, privateKey)
	if err != nil {
		return "", fmt.Errorf("failed to generate seamless sign: %w", err)
	}
	return url.QueryEscape(signature), err
}

func GenerateRedirectLinkAuthCode(seamlessData, seamlessSign string) string {
	basePath := "https://m.sandbox.dana.id/"
	path := "v1.0/get-auth-code"

	// Encode them
	partnerId := os.Getenv("X_PARTNER_ID")
	channelId := "95221"
	scopes := widget.GenerateScopes()
	redirectUrl := os.Getenv("REDIRECT_URL_OAUTH")
	encodedSeamlessData := url.QueryEscape(seamlessData)
	encodedSeamlessSign := url.QueryEscape(seamlessSign)

	// Use in URL building
	url := fmt.Sprintf("%s%s?partnerId=%s&timestamp=2023-08-31T22:27:48+00:00&externalId=test&channelId=%s&scopes=%s&redirectUrl=%s&state=22321&seamlessData=%s&seamlessSign=%s",
		basePath, path, partnerId, channelId, scopes, redirectUrl, encodedSeamlessData, encodedSeamlessSign)

	return url
}
