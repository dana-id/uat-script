package widget

import (
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"fmt"
	"log"
	"net/url"
	"os"
	"strings"
	"time"

	"github.com/dana-id/dana-go/v2/widget/v1"
	"github.com/playwright-community/playwright-go"
)

// ==========================================
// CONSTANTS AND CONFIGURATION
// ==========================================

const (
	// Widget configuration
	widgetTitleCase = "ApplyToken"
	widgetJsonPath  = "../../../resource/request/components/Widget.json"

	// OAuth automation timeouts (in milliseconds)
	maxOAuthRetries    = 3
	navigationTimeout  = 30000 // 30 seconds for page navigation
	shortDelay         = 1500  // 1.5 seconds between actions
	mediumDelay        = 3000  // 3 seconds for UI updates
	pinProcessingDelay = 15000 // 15 seconds for PIN processing
	authCodeTimeout    = 45    // 45 seconds to wait for auth code
	retryDelay         = 3     // 3 seconds between retry attempts

	// Browser viewport settings
	browserWidth  = 1920
	browserHeight = 1080

	// DANA-specific constants
	defaultDeviceId = "637216gygd76712313"
)

// ==========================================
// UTILITY FUNCTIONS
// ==========================================

// StringPtr returns a pointer to the given string value
// This is useful for SDK fields that require *string
func StringPtr(s string) *string {
	return &s
}

// generateTime returns current UTC time in RFC3339 format
// Used for seamless data verification timestamps
func generateTime() string {
	return time.Now().UTC().Format(time.RFC3339)
}

// extractAuthCodeFromURL extracts authorization code from URL query parameters
// Supports multiple formats: auth_code, authCode, auth-code
func extractAuthCodeFromURL(urlString string) (string, error) {
	parsedURL, err := url.Parse(urlString)
	if err != nil {
		return "", fmt.Errorf("failed to parse URL: %w", err)
	}

	queryParams := parsedURL.Query()

	// Try different auth code parameter formats in order of preference
	authCodeParams := []string{"auth_code", "authCode", "auth-code"}

	for _, param := range authCodeParams {
		if authCode := queryParams.Get(param); authCode != "" {
			return authCode, nil
		}
	}

	return "", fmt.Errorf("no auth code found in URL query parameters (tried: %s)",
		strings.Join(authCodeParams, ", "))
}

// ==========================================
// OAUTH URL GENERATION
// ==========================================

// GetRedirectOauthUrl generates the OAuth redirect URL for DANA authentication
// Returns the URL that users should be redirected to for OAuth login
func GetRedirectOauthUrl(phoneNumber, pin string) (string, error) {
	externalId := widget.GenerateExternalId("")
	scopes := widget.GenerateScopes()

	// Configure seamless data for OAuth flow
	seamlessData := &widget.Oauth2UrlDataSeamlessData{
		BizScenario:         StringPtr("PAYMENT"),
		MobileNumber:        StringPtr(phoneNumber),
		VerifiedTime:        StringPtr(generateTime()),
		ExternalUid:         StringPtr(externalId),
		DeviceId:            StringPtr(defaultDeviceId),
		SkipRegisterConsult: &[]bool{true}[0], // Pointer to true
	}

	// Default to https://google.com when REDIRECT_URL_OAUTH is unset.
	// Without a redirect URL DANA has nowhere to send the auth code and loops back to phone input.
	redirectUrl := os.Getenv("REDIRECT_URL_OAUTH")
	if redirectUrl == "" {
		redirectUrl = "https://google.com"
	}

	oauth2UrlData := &widget.Oauth2UrlData{
		ExternalId:    externalId,
		MerchantId:    os.Getenv("MERCHANT_ID"),
		SubMerchantId: nil,
		SeamlessData:  seamlessData,
		Scopes:        []string{scopes},
		RedirectUrl:   redirectUrl,
	}

	redirectOauthUrl, err := widget.GenerateOauthUrl(oauth2UrlData)
	if err != nil {
		return "", fmt.Errorf("failed to generate OAuth URL: %w", err)
	}

	fmt.Printf("RedirectOauthUrl: %s\n", redirectOauthUrl)
	return redirectOauthUrl, nil
}

// ==========================================
// MAIN OAUTH AUTOMATION FUNCTIONS
// ==========================================

// GetAuthCode performs the complete OAuth automation flow
// Returns the authorization code needed for token exchange
func GetAuthCode(phoneNumber, pin, redirectUrl string) (string, error) {
	log.Println("Starting OAuth automation for DANA Widget...")

	// Validate inputs
	if redirectUrl == "" {
		return "", fmt.Errorf("error: no redirect URL provided")
	}
	if phoneNumber == "" {
		return "", fmt.Errorf("error: no phone number provided")
	}
	if pin == "" {
		return "", fmt.Errorf("error: no PIN provided")
	}

	log.Printf("OAuth URL: %s", redirectUrl)
	log.Printf("Using phone number: %s", phoneNumber)
	log.Printf("Using PIN: %s", pin)

	// Initialize Playwright for browser automation
	err := playwright.Install()
	if err != nil {
		return "", fmt.Errorf("could not install Playwright: %w", err)
	}

	pw, err := playwright.Run()
	if err != nil {
		return "", fmt.Errorf("could not start Playwright: %w", err)
	}
	defer pw.Stop()

	// Run OAuth automation with retry logic
	return automateOAuthWithRetry(pw, phoneNumber, pin, redirectUrl, maxOAuthRetries)
}

// automateOAuthWithRetry implements retry logic for OAuth automation
// Attempts the OAuth flow up to maxRetries times with delays between attempts
func automateOAuthWithRetry(pw *playwright.Playwright, phoneNumber, pin, redirectUrl string, maxRetries int) (string, error) {
	for attempt := 1; attempt <= maxRetries; attempt++ {
		log.Printf("\n=== OAuth Attempt %d/%d ===", attempt, maxRetries)
		log.Printf("Using mobile: %s", phoneNumber)
		log.Printf("Using PIN: %s", pin)

		authCode, err := automateOAuthFlow(pw, phoneNumber, pin, redirectUrl)
		if err == nil && authCode != "" {
			log.Printf("SUCCESS! Auth code obtained: %s", authCode)
			return authCode, nil
		}

		log.Printf("Attempt %d failed: %v", attempt, err)

		// Wait before retry (except for the last attempt)
		if attempt < maxRetries {
			retryWait := time.Duration(retryDelay) * time.Second
			log.Printf("Waiting %v before next retry...", retryWait)
			time.Sleep(retryWait)
		}
	}

	return "", fmt.Errorf("all %d OAuth attempts failed", maxRetries)
}

// automateOAuthFlow handles the complete OAuth flow automation
// Steps: Navigate → Fill Phone → Click Continue → Fill PIN → Wait for Auth Code
func automateOAuthFlow(pw *playwright.Playwright, phoneNumber, pin, redirectUrl string) (string, error) {
	log.Println("Launching Chrome browser for OAuth automation...")

	// Launch browser with DANA-optimized settings
	browser, err := pw.Chromium.Launch(playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(true), // Headless mode for automation
		Args:     getChromeArguments(),
	})
	if err != nil {
		return "", fmt.Errorf("could not launch browser: %w", err)
	}
	defer browser.Close()

	// Create browser context with proper viewport and user agent
	context, err := browser.NewContext(playwright.BrowserNewContextOptions{
		Viewport:  &playwright.Size{Width: browserWidth, Height: browserHeight},
		UserAgent: playwright.String("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36"),
	})
	if err != nil {
		return "", fmt.Errorf("could not create browser context: %w", err)
	}
	defer context.Close()

	page, err := context.NewPage()
	if err != nil {
		return "", fmt.Errorf("could not create page: %w", err)
	}

	// Execute OAuth flow steps
	return executeOAuthSteps(page, phoneNumber, pin, redirectUrl)
}

// executeOAuthSteps performs the individual steps of OAuth automation
func executeOAuthSteps(page playwright.Page, phoneNumber, pin, redirectUrl string) (string, error) {
	// Step 1: Navigate to OAuth URL
	log.Println("Step 1: Navigating to OAuth URL...")
	if err := navigateToOAuthUrl(page, redirectUrl); err != nil {
		return "", err
	}

	// Step 2: Fill phone number
	log.Println("Step 2: Filling phone number...")
	if err := fillPhoneNumber(page, phoneNumber); err != nil {
		return "", fmt.Errorf("failed to fill phone number: %w", err)
	}
	page.WaitForTimeout(shortDelay)

	// Step 3: Click continue button
	log.Println("Step 3: Clicking continue...")
	if err := clickContinueButton(page); err != nil {
		return "", fmt.Errorf("failed to click continue: %w", err)
	}
	page.WaitForTimeout(mediumDelay)

	// Step 4: Fill PIN
	log.Println("Step 4: Filling PIN...")
	if err := fillPin(page, pin); err != nil {
		return "", fmt.Errorf("failed to fill PIN: %w", err)
	}
	page.WaitForTimeout(shortDelay)

	// Step 5: Submit PIN and wait for redirect
	log.Println("Step 5: Submitting PIN and waiting for redirect...")
	if err := submitPinAndWait(page); err != nil {
		return "", err
	}

	// Step 6: Wait for auth code in redirected URL
	log.Println("Step 6: Monitoring for auth code in redirected URL...")
	authCode, err := waitForAuthCodeAfterPIN(page, os.Getenv("REDIRECT_URL_OAUTH"))
	if err != nil {
		return "", fmt.Errorf("failed to get auth code: %w", err)
	}

	return authCode, nil
}

// ==========================================
// OAUTH FLOW HELPER FUNCTIONS
// ==========================================

// getChromeArguments returns optimized Chrome arguments for DANA OAuth
func getChromeArguments() []string {
	return []string{
		"--disable-gpu",
		"--disable-web-security",
		"--disable-features=IsolateOrigins",
		"--disable-site-isolation-trials",
		"--disable-features=BlockInsecurePrivateNetworkRequests",
		"--disable-blink-features=AutomationControlled",
		"--no-sandbox",
		"--disable-dev-shm-usage",
		"--disable-extensions",
	}
}

// navigateToOAuthUrl navigates to the OAuth URL with timeout
func navigateToOAuthUrl(page playwright.Page, redirectUrl string) error {
	_, err := page.Goto(redirectUrl, playwright.PageGotoOptions{
		WaitUntil: playwright.WaitUntilStateDomcontentloaded,
		Timeout:   playwright.Float(navigationTimeout),
	})
	if err != nil {
		return fmt.Errorf("could not navigate to OAuth URL: %w", err)
	}

	page.WaitForTimeout(mediumDelay)
	return nil
}

// submitPinAndWait handles PIN submission and initial processing delay
func submitPinAndWait(page playwright.Page) error {
	// Try to find and click submit button after PIN entry
	if err := clickContinueButton(page); err != nil {
		log.Printf("No submit button found after PIN entry, PIN might auto-submit: %v", err)
	}

	// Wait for DANA to process the PIN
	log.Println("Waiting for DANA to process PIN...")
	page.WaitForTimeout(pinProcessingDelay)

	// Log current URL for debugging
	currentURL := page.URL()
	log.Printf("Current URL after PIN submission: %s", currentURL)

	return nil
}

// ==========================================
// INPUT FILLING FUNCTIONS
// ==========================================

// fillPhoneNumber fills the phone number field using multiple strategies
func fillPhoneNumber(page playwright.Page, phoneNumber string) error {
	// Define strategies in order of preference
	strategies := []string{
		"input[type='tel']",           // Most specific for phone inputs
		"input[placeholder*='phone']", // Phone-related placeholders
		"input[placeholder*='mobile']",
		"input[maxlength='13']", // Common phone number length
		"input[class*='phone']", // Phone-related CSS classes
		"input[class*='mobile']",
		"input:first-of-type", // Fallback to first input
	}

	for _, strategy := range strategies {
		if err := tryFillInput(page, strategy, phoneNumber, "phone"); err == nil {
			log.Printf("Phone filled using strategy: %s", strategy)
			return nil
		}
	}

	return fmt.Errorf("could not find phone input field using any strategy")
}

// fillPin fills the PIN field using multiple strategies
func fillPin(page playwright.Page, pin string) error {
	// Define strategies for different PIN input types
	strategies := []string{
		"input[maxlength='6']",   // Single PIN field with 6 character limit
		"input[type='password']", // Password input type
		"input[class*='pin']",    // PIN-related CSS classes
		"input[name*='pin']",     // PIN-related name attributes
		"input[maxlength='1']",   // Individual digit fields
	}

	for _, strategy := range strategies {
		elements, err := page.QuerySelectorAll(strategy)
		if err != nil {
			continue
		}

		// Handle single PIN field
		if len(elements) == 1 {
			if err := tryFillInput(page, strategy, pin, "PIN"); err == nil {
				log.Printf("PIN filled using single field strategy: %s", strategy)
				return nil
			}
		}

		// Handle multiple digit fields (6 individual inputs)
		if len(elements) >= 6 && strategy == "input[maxlength='1']" {
			if err := fillMultipleDigitFields(elements, pin); err == nil {
				log.Printf("PIN filled using multiple digit fields strategy: %s", strategy)
				return nil
			}
		}
	}

	return fmt.Errorf("could not find PIN input field using any strategy")
}

// tryFillInput attempts to fill an input field with the given value
func tryFillInput(page playwright.Page, selector, value, fieldType string) error {
	element, err := page.QuerySelector(selector)
	if err != nil || element == nil {
		return fmt.Errorf("selector not found")
	}

	// Check if element is visible and enabled
	if visible, err := element.IsVisible(); err != nil || !visible {
		return fmt.Errorf("element not visible")
	}

	if enabled, err := element.IsEnabled(); err != nil || !enabled {
		return fmt.Errorf("element not enabled")
	}

	// Fill the input field
	if err := element.Fill(value); err != nil {
		return fmt.Errorf("failed to fill %s: %w", fieldType, err)
	}

	return nil
}

// fillMultipleDigitFields fills individual digit fields for PIN entry
func fillMultipleDigitFields(elements []playwright.ElementHandle, pin string) error {
	if len(pin) > len(elements) {
		return fmt.Errorf("PIN length (%d) exceeds number of input fields (%d)", len(pin), len(elements))
	}

	for i, digit := range pin {
		if i >= len(elements) {
			break
		}

		if err := elements[i].Fill(string(digit)); err != nil {
			return fmt.Errorf("failed to fill digit %d: %w", i, err)
		}
	}

	return nil
}

// ==========================================
// BUTTON CLICKING FUNCTIONS
// ==========================================

// clickContinueButton clicks continue/submit buttons using multiple strategies
func clickContinueButton(page playwright.Page) error {
	// Define button selectors in order of preference
	buttonStrategies := []string{
		// Specific button types
		"button[type='submit']",

		// English button text
		"button:has-text('Continue')",
		"button:has-text('Next')",
		"button:has-text('Submit')",
		"button:has-text('OK')",

		// Indonesian button text (DANA is Indonesian service)
		"button:has-text('Lanjutkan')",  // Continue
		"button:has-text('Lanjut')",     // Next
		"button:has-text('Masuk')",      // Login/Enter
		"button:has-text('Konfirmasi')", // Confirmation

		// CSS class-based selectors
		"button[class*='continue']",
		"button[class*='submit']",
		"button[class*='btn-primary']",
		"button[class*='primary']",
		"button[class*='btn']",
		".btn",
	}

	// Try specific button strategies first
	for _, strategy := range buttonStrategies {
		if err := tryClickButton(page, strategy); err == nil {
			log.Printf("Button clicked using strategy: %s", strategy)
			return nil
		}
	}

	// If no specific button found, try fallback strategies
	return tryFallbackButtonStrategies(page)
}

// tryClickButton attempts to click a button using the given selector
func tryClickButton(page playwright.Page, selector string) error {
	element, err := page.QuerySelector(selector)
	if err != nil || element == nil {
		return fmt.Errorf("button selector not found")
	}

	// Check if button is visible and enabled
	if visible, err := element.IsVisible(); err != nil || !visible {
		return fmt.Errorf("button not visible")
	}

	if enabled, err := element.IsEnabled(); err != nil || !enabled {
		return fmt.Errorf("button not enabled")
	}

	// Click the button
	if err := element.Click(); err != nil {
		return fmt.Errorf("failed to click button: %w", err)
	}

	return nil
}

// tryFallbackButtonStrategies tries fallback methods when specific buttons aren't found
func tryFallbackButtonStrategies(page playwright.Page) error {
	log.Println("No specific button found, trying fallback strategies...")

	// Get all buttons and try to find a suitable one
	buttons, err := page.QuerySelectorAll("button")
	if err != nil {
		return fmt.Errorf("could not query buttons: %w", err)
	}

	log.Printf("Found %d total buttons, checking each one...", len(buttons))

	for i, button := range buttons {
		// Get button text for debugging
		text, _ := button.TextContent()
		log.Printf("Button %d text: '%s'", i+1, text)

		// Try to click visible, enabled buttons
		if visible, err := button.IsVisible(); err == nil && visible {
			if enabled, err := button.IsEnabled(); err == nil && enabled {
				if err := button.Click(); err == nil {
					log.Printf("Successfully clicked button %d with text: '%s'", i+1, text)
					return nil
				}
			}
		}
	}

	return fmt.Errorf("could not find any suitable button to click")
}

// ==========================================
// AUTH CODE MONITORING FUNCTIONS
// ==========================================

// waitForAuthCodeAfterPIN monitors URL changes and extracts auth code after PIN submission
func waitForAuthCodeAfterPIN(page playwright.Page, expectedRedirectUrl string) (string, error) {
	log.Println("Monitoring for OAuth redirect and auth code after PIN submission...")

	timeout := time.Duration(authCodeTimeout) * time.Second
	start := time.Now()
	checkInterval := 500 * time.Millisecond
	lastURL := ""

	for time.Since(start) < timeout {
		currentURL := page.URL()

		// Log URL changes for debugging
		if currentURL != lastURL {
			log.Printf("URL changed to: %s", currentURL)
			lastURL = currentURL
		}

		// Try to extract auth code from current URL
		authCode, err := extractAuthCodeFromURL(currentURL)
		if err == nil && authCode != "" {
			log.Printf("Successfully extracted auth code: %s", authCode)
			return authCode, nil
		}

		// Check for redirect patterns and handle intermediate pages
		if err := handleRedirectPatterns(page, currentURL); err != nil {
			log.Printf("Redirect handling warning: %v", err)
		}

		// Periodic progress logging
		elapsed := time.Since(start)
		if int(elapsed.Seconds())%10 == 0 && elapsed.Milliseconds()%500 < 50 {
			remaining := timeout - elapsed
			log.Printf("Still waiting for auth code: %.0fs elapsed, %.0fs remaining",
				elapsed.Seconds(), remaining.Seconds())
		}

		time.Sleep(checkInterval)
	}

	return "", fmt.Errorf("timeout waiting for auth code after %v", timeout)
}

// handleRedirectPatterns handles different redirect patterns during OAuth flow
func handleRedirectPatterns(page playwright.Page, currentURL string) error {
	// Check for common redirect patterns
	redirectPatterns := []string{"google.com", "gateway", "/sandbox/", "/app/"}

	for _, pattern := range redirectPatterns {
		if strings.Contains(currentURL, pattern) {
			log.Printf("Detected redirect pattern '%s' in URL: %s", pattern, currentURL)

			// Handle intermediate /app/ page
			if strings.Contains(currentURL, "/app/") {
				log.Println("Detected intermediate /app/ page, waiting for final redirect...")
				page.WaitForTimeout(2000) // Wait 2 seconds for potential final redirect
				return nil
			}

			// Try to extract auth code from redirect URL
			if authCode, err := extractAuthCodeFromURL(currentURL); err == nil && authCode != "" {
				log.Printf("Auth code found in redirect URL: %s", authCode)
				return nil
			}
		}
	}

	// Check for DANA-specific OAuth redirects
	if strings.Contains(currentURL, "dana.id") && strings.Contains(currentURL, "oauth") {
		log.Printf("Detected DANA OAuth redirect: %s", currentURL)
	}

	return nil
}

// ==========================================
// TOKEN AND AUTHENTICATION FUNCTIONS
// ==========================================

// GetAccessToken exchanges authorization code for access token
func GetAccessToken(authCode string) string {
	// This function would typically make an API call to exchange the auth code
	// For now, returning the auth code as a placeholder
	log.Printf("Getting access token for auth code: %s", authCode)
	return authCode
}

// SetManualAuthCode manually sets an authorization code for testing
func SetManualAuthCode(phoneNumber, pin string) (string, error) {
	log.Printf("Setting manual auth code for phone: %s, PIN: %s", phoneNumber, pin)
	// This would be implemented based on specific requirements
	return "MANUAL_AUTH_CODE", nil
}

// ==========================================
// SEAMLESS DATA AND SIGNATURE FUNCTIONS
// ==========================================

// GenerateSeamlessData generates seamless data string for OAuth
func GenerateSeamlessData(phoneNumber string) string {
	seamlessData := map[string]interface{}{
		"bizScenario":         "PAYMENT",
		"deviceId":            defaultDeviceId,
		"externalUid":         widget.GenerateExternalId(""),
		"mobile":              phoneNumber,
		"skipRegisterConsult": true,
		"verifiedTime":        generateTime(),
	}

	jsonData, err := json.Marshal(seamlessData)
	if err != nil {
		log.Printf("Error marshaling seamless data: %v", err)
		return ""
	}

	return string(jsonData)
}

// GetPrivateKey parses RSA private key from string
func GetPrivateKey(privateKeyMerchant string) (*rsa.PrivateKey, error) {
	block, _ := pem.Decode([]byte(privateKeyMerchant))
	if block == nil {
		return nil, fmt.Errorf("failed to decode PEM block containing private key")
	}

	privateKey, err := x509.ParsePKCS1PrivateKey(block.Bytes)
	if err != nil {
		return nil, fmt.Errorf("failed to parse private key: %w", err)
	}

	return privateKey, nil
}

// Sign signs the payload with the private key using RSA-SHA256
func Sign(textPayload, privateKeyMerchant string) (string, error) {
	privateKey, err := GetPrivateKey(privateKeyMerchant)
	if err != nil {
		return "", err
	}

	hashed := sha256.Sum256([]byte(textPayload))
	signature, err := rsa.SignPKCS1v15(rand.Reader, privateKey, crypto.SHA256, hashed[:])
	if err != nil {
		return "", fmt.Errorf("failed to sign payload: %w", err)
	}

	return base64.StdEncoding.EncodeToString(signature), nil
}

// GenerateSeamlessSign generates signature for seamless data
func GenerateSeamlessSign(payload string) (string, error) {
	privateKeyMerchant := os.Getenv("PRIVATE_KEY_MERCHANT")
	if privateKeyMerchant == "" {
		return "", fmt.Errorf("PRIVATE_KEY_MERCHANT environment variable not set")
	}

	return Sign(payload, privateKeyMerchant)
}

// GenerateRedirectLinkAuthCode generates the complete OAuth redirect URL
func GenerateRedirectLinkAuthCode(seamlessData, seamlessSign string) string {
	basePath := "https://m.sandbox.dana.id"
	path := "/v1.0/get-auth-code"
	partnerId := os.Getenv("PARTNER_ID")
	channelId := widget.GenerateChannelId()
	scopes := widget.GenerateScopes()
	redirectUrl := os.Getenv("REDIRECT_URL_OAUTH")

	// URL encode the seamless data and signature
	encodedSeamlessData := url.QueryEscape(seamlessData)
	encodedSeamlessSign := url.QueryEscape(seamlessSign)

	// Build the complete OAuth URL
	oauthURL := fmt.Sprintf(
		"%s%s?partnerId=%s&timestamp=2023-08-31T22:27:48+00:00&externalId=test&channelId=%s&scopes=%s&redirectUrl=%s&state=22321&seamlessData=%s&seamlessSign=%s",
		basePath, path, partnerId, channelId, scopes, redirectUrl, encodedSeamlessData, encodedSeamlessSign,
	)

	return oauthURL
}
