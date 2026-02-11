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

	"github.com/dana-id/dana-go/v2/widget/v1"
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

// getCurrentURLWithRetry attempts to get the current URL with retry mechanism
func getCurrentURLWithRetry(page playwright.Page, maxRetries int) (string, error) {
	var lastErr error
	for attempt := 1; attempt <= maxRetries; attempt++ {
		url := page.URL()
		if url != "" {
			return url, nil
		}

		lastErr = fmt.Errorf("page.URL() returned empty string on attempt %d", attempt)
		log.Printf("Failed to get URL on attempt %d/%d: %v", attempt, maxRetries, lastErr)

		if attempt < maxRetries {
			time.Sleep(time.Duration(attempt) * 100 * time.Millisecond) // Reduced from 200ms
		}
	}
	return "", fmt.Errorf("failed to get current URL after %d attempts: %w", maxRetries, lastErr)
} // isOAuthRedirectWithAuthCode checks if the URL contains OAuth redirect with auth code
func isOAuthRedirectWithAuthCode(currentURL, urlRedirectOauth string) bool {
	return strings.Contains(currentURL, urlRedirectOauth) &&
		(strings.Contains(currentURL, "authCode=") || strings.Contains(currentURL, "auth_code="))
}

// extractAuthCodeWithRetry attempts to extract auth code with retry mechanism
func extractAuthCodeWithRetry(urlString string, maxRetries int) (string, error) {
	var lastErr error
	for attempt := 1; attempt <= maxRetries; attempt++ {
		authCode, err := extractAuthCodeFromURL(urlString)
		if err == nil && authCode != "" {
			return authCode, nil
		}

		lastErr = err
		log.Printf("Failed to extract auth code on attempt %d/%d: %v", attempt, maxRetries, err)

		if attempt < maxRetries {
			time.Sleep(time.Duration(attempt) * 50 * time.Millisecond) // Reduced from 100ms
		}
	}
	return "", fmt.Errorf("failed to extract auth code after %d attempts: %w", maxRetries, lastErr)
}

// performNavigationAssistanceWithRetry attempts navigation assistance with retry mechanism
func performNavigationAssistanceWithRetry(page playwright.Page, maxRetries int) bool {
	for attempt := 1; attempt <= maxRetries; attempt++ {
		log.Printf("Navigation assistance attempt %d/%d", attempt, maxRetries)

		success, err := performNavigationAssistance(page)
		if err != nil {
			log.Printf("Navigation assistance attempt %d failed: %v", attempt, err)
		} else if success {
			log.Printf("Navigation assistance succeeded on attempt %d", attempt)
			return true
		}

		if attempt < maxRetries {
			time.Sleep(time.Duration(attempt) * 300 * time.Millisecond) // Reduced from 500ms
		}
	}
	log.Printf("Navigation assistance failed after %d attempts", maxRetries)
	return false
}

// performNavigationAssistance performs the actual navigation assistance logic
func performNavigationAssistance(page playwright.Page) (bool, error) {
	navigationResult, err := page.Evaluate(`
		() => {
			const results = [];
			
			// Strategy 1: Check for specific DANA continue buttons
			const danaSpecificSelectors = [
				'.btn-continue',
				'.btn-submit', 
				'.btn-confirm',
				'button[type="submit"]',
				'.submit-button',
				'[data-testid="continue-btn"]',
				'[data-testid="submit-btn"]',
				'.ant-btn-primary',
				'.button-primary',
				'.primary-button'
			];
			
			for (const selector of danaSpecificSelectors) {
				const buttons = document.querySelectorAll(selector);
				for (const button of buttons) {
					if (button.offsetParent !== null && !button.disabled) {
						button.click();
						results.push('clicked_selector_' + selector);
						return results.join(', ');
					}
				}
			}
			
			// Strategy 2: Look for buttons with Indonesian/English text
			const allButtons = document.querySelectorAll('button');
			for (const button of allButtons) {
				if (button.offsetParent !== null && !button.disabled) {
					const text = (button.textContent || button.innerText || '').toLowerCase().trim();
					if (text.includes('lanjut') || text.includes('continue') || 
						text.includes('konfirm') || text.includes('confirm') ||
						text.includes('next') || text.includes('submit') ||
						text.includes('kirim') || text.includes('send') ||
						text.includes('masuk') || text.includes('login') ||
						text.includes('oke') || text.includes('ok')) {
						button.click();
						results.push('clicked_text_button: ' + text);
						return results.join(', ');
					}
				}
			}
			
			// Strategy 3: Try form submissions
			const forms = document.querySelectorAll('form');
			for (const form of forms) {
				if (form.offsetParent !== null) {
					const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
					form.dispatchEvent(submitEvent);
					results.push('submitted_form');
					return results.join(', ');
				}
			}
			
			// Strategy 4: Check if there are any input elements that need focus/blur
			const inputs = document.querySelectorAll('input');
			for (const input of inputs) {
				if (input.offsetParent !== null && input.value) {
					input.focus();
					input.blur();
					input.dispatchEvent(new Event('change', { bubbles: true }));
					results.push('triggered_input_events');
				}
			}
			
			// Strategy 5: Try pressing Enter key on the document
			const enterEvent = new KeyboardEvent('keydown', {
				key: 'Enter',
				code: 'Enter',
				keyCode: 13,
				which: 13,
				bubbles: true
			});
			document.dispatchEvent(enterEvent);
			results.push('pressed_enter');
			
			return results.length > 0 ? results.join(', ') : 'no_actions_taken';
		}
	`)

	if err != nil {
		return false, fmt.Errorf("navigation assistance JavaScript execution failed: %w", err)
	}

	resultStr, ok := navigationResult.(string)
	if !ok {
		return false, fmt.Errorf("unexpected navigation result type: %T", navigationResult)
	}

	log.Printf("Navigation assistance actions: %s", resultStr)
	return resultStr != "no_actions_taken", nil
}

func GetAuthCode(phoneNumber, pin, redirectUrl string) (string, error) {
	// Start OAuth automation with mobile browser emulation
	log.Println("Starting OAuth automation with mobile device emulation...")

	if redirectUrl == "" {
		return "", fmt.Errorf("Error: No redirect URL provided")
	}

	// Install playwright if it's not already installed
	err := playwright.Install()
	if err != nil {
		return "", fmt.Errorf("Could not install playwright: %w", err)
	}

	pw, err := playwright.Run()
	if err != nil {
		return "", fmt.Errorf("Could not start playwright: %v", err)
	}

	// Launch browser with mobile-first approach (iPhone 12 emulation)
	log.Println("Launching mobile browser (iPhone 12 emulation)...")
	browserType := pw.Chromium
	browser, err := browserType.Launch(playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(true),
		Args: []string{
			"--disable-web-security",
			"--disable-features=IsolateOrigins",
			"--disable-site-isolation-trials",
			"--disable-features=BlockInsecurePrivateNetworkRequests",
			"--disable-blink-features=AutomationControlled",
			"--user-agent=Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1",
		},
	})
	if err != nil {
		return "", fmt.Errorf("Could not launch browser: %w", err)
	}

	defer browser.Close()

	// Create mobile context with Jakarta location
	log.Println("Setting up mobile context (Jakarta, Indonesia)...")
	context, err := browser.NewContext(playwright.BrowserNewContextOptions{
		Viewport:  &playwright.Size{Width: 390, Height: 844}, // iPhone 12 dimensions
		UserAgent: playwright.String("Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1"),
		Locale:    playwright.String("id-ID"),
		Geolocation: &playwright.Geolocation{
			Longitude: 106.8456, // Jakarta coordinates
			Latitude:  -6.2088,
		},
		Permissions:       []string{"geolocation"},
		IsMobile:          playwright.Bool(true),
		HasTouch:          playwright.Bool(true),
		DeviceScaleFactor: playwright.Float(3.0), // iPhone retina display
	})
	if err != nil {
		return "", fmt.Errorf("Could not create mobile context: %w", err)
	}
	defer context.Close()

	page, err := context.NewPage()
	if err != nil {
		log.Fatalf("Could not create page: %v", err)
	}

	log.Printf("Navigating to OAuth URL: %s", redirectUrl)

	if _, err = page.Goto(redirectUrl); err != nil {
		log.Fatalf("Could not navigate to URL: %v", err)
	}

	// Start OAuth flow automation
	var urlRedirectOauth string
	urlRedirectOauth = os.Getenv("REDIRECT_URL_OAUTH")

	// Wait for page to load completely
	log.Println("Waiting for page to load...")
	time.Sleep(2 * time.Second)

	// Fill phone number using mobile-optimized JavaScript
	log.Printf("Filling phone number: %s", phoneNumber)
	phoneInputFilled, err := page.Evaluate(`
		(mobile) => {
			const inputs = document.querySelectorAll('input');
			for (const input of inputs) {
				if (input.type === 'tel' ||
					input.placeholder === '12312345678' ||
					input.maxLength === 13 ||
					input.className.includes('phone-number')) {
					input.value = mobile;
					input.dispatchEvent(new Event('input', { bubbles: true }));
					return { filled: true, message: 'Found and filled mobile number input' };
				}
			}
			return { filled: false, message: 'No suitable mobile number input found' };
		}
	`, phoneNumber)

	if err != nil {
		log.Printf("Phone input failed: %v", err)
	} else {
		log.Printf("Phone input: %v", phoneInputFilled)
	}

	// Find and click submit button
	log.Println("Looking for submit button...")
	submitButtonClicked, err := page.Evaluate(`
		() => {
			const buttons = document.querySelectorAll('button');
			for (const button of buttons) {
				if (button.type === 'submit' ||
					button.innerText.includes('Next') ||
					button.innerText.includes('Continue') ||
					button.innerText.includes('Submit') ||
					button.innerText.includes('Lanjutkan')) {
					button.click();
					return { clicked: true, message: 'Found and clicked button via JS evaluation' };
				}
			}
			return { clicked: false, message: 'No suitable submit button found' };
		}
	`)

	if err != nil {
		log.Printf("Submit button failed: %v", err)
	} else {
		log.Printf("Submit button: %v", submitButtonClicked)
	}

	time.Sleep(2 * time.Second)
	log.Println("Waiting for PIN input page...")

	// Look for additional continue button
	_, err = page.Evaluate(`
		() => {
			const continueBtn = document.querySelector('button.btn-continue.fs-unmask.btn.btn-primary');
			if (continueBtn) {
				continueBtn.click();
				return { clicked: true, message: 'Found another continue button' };
			}
			return { clicked: false, message: 'No additional continue button found' };
		}
	`)

	time.Sleep(1500 * time.Millisecond)

	// Fill PIN using mobile-optimized JavaScript
	log.Printf("Filling PIN: %s", pin)
	pinInputResult, err := page.Evaluate(`
		(pinCode) => {
			// First try specific PIN input field
			const specificPinInput = document.querySelector('.txt-input-pin-field');
			if (specificPinInput) {
				specificPinInput.value = pinCode;
				specificPinInput.dispatchEvent(new Event('input', { bubbles: true }));
				specificPinInput.dispatchEvent(new Event('change', { bubbles: true }));
				return { success: true, method: 'specific', message: 'Found specific PIN input field: .txt-input-pin-field' };
			}

			const inputs = document.querySelectorAll('input');

			// Approach 1: Look for an input with maxlength=6 (full PIN)
			const singlePinInput = Array.from(inputs).find(input =>
				input.maxLength === 6 &&
				(input.type === 'text' || input.type === 'tel' || input.type === 'number' || input.inputMode === 'numeric')
			);

			if (singlePinInput) {
				singlePinInput.value = pinCode;
				singlePinInput.dispatchEvent(new Event('input', { bubbles: true }));
				singlePinInput.dispatchEvent(new Event('change', { bubbles: true }));
				return { success: true, method: 'single', message: 'Found single PIN input field with maxLength=6' };
			}

			// Approach 2: Look for multiple inputs with common PIN input characteristics
			const pinInputs = Array.from(inputs).filter(input =>
				input.maxLength === 1 ||
				input.type === 'password' ||
				input.className.includes('pin')
			);

			if (pinInputs.length >= pinCode.length) {
				for (let i = 0; i < pinCode.length; i++) {
					pinInputs[i].value = pinCode.charAt(i);
					pinInputs[i].dispatchEvent(new Event('input', { bubbles: true }));
					pinInputs[i].dispatchEvent(new Event('change', { bubbles: true }));
				}
				return { success: true, method: 'multi', message: 'Found ' + pinInputs.length + ' PIN inputs via JS' };
			}

			return { success: false, method: 'none', message: 'Could not find any suitable PIN input field' };
		}
	`, pin)

	if err != nil {
		log.Printf("PIN input failed: %v", err)
	} else {
		log.Printf("PIN input: %v", pinInputResult)
	}

	// Look for confirmation/continue buttons
	log.Println("Looking for confirmation buttons...")

	buttonClicked, err := page.Evaluate(`
		() => {
			const allButtons = document.querySelectorAll('button');
			let continueButton = null;
			let backButton = null;

			allButtons.forEach((button) => {
				const buttonText = button.innerText.trim().toLowerCase();
				// Identify buttons by text or class
				if (buttonText.includes('lanjut') ||
					buttonText.includes('continue') ||
					buttonText.includes('submit') ||
					buttonText.includes('confirm') ||
					buttonText.includes('next') ||
					button.className.includes('btn-continue') ||
					button.className.includes('btn-submit') ||
					button.className.includes('btn-confirm')) {
					continueButton = button;
				}

				// Avoid buttons with text 'kembali'
				if (buttonText.includes('kembali') ||
					buttonText.includes('back') ||
					button.className.includes('btn-back')) {
					backButton = button;
				}
			});

			// Prioritize continue button if found
			if (continueButton) {
				continueButton.click();
				return { clicked: true, message: 'Found continue button, clicked it: ' + continueButton.innerText };
			}

			return { clicked: false, message: 'No confirm/continue button found' };
		}
	`)

	if err != nil {
		log.Printf("Continue button search failed: %v", err)
	} else {
		log.Printf("Continue button: %v", buttonClicked)
	}

	time.Sleep(500 * time.Millisecond)

	// Wait for redirect to callback URL - this is where we get the auth code
	log.Println("Waiting for OAuth redirect to callback URL...")

	timeout := 25 * time.Second // Reduced from 30s to leave room for test timeout
	start := time.Now()
	lastURL := ""
	stuckCounter := 0

	for time.Since(start) < timeout {
		// Early termination check - if we're close to test timeout, exit early
		elapsed := time.Since(start)
		if elapsed > 20*time.Second { // If we've been running for more than 20s
			log.Printf("Approaching timeout limit, performing quick final check...")
			currentURL, _ := getCurrentURLWithRetry(page, 1) // Single retry only
			if isOAuthRedirectWithAuthCode(currentURL, urlRedirectOauth) {
				if authCode, err := extractAuthCodeWithRetry(currentURL, 1); err == nil {
					log.Printf("Quick final check found auth code: %s", authCode)
					return authCode, nil
				}
			}
			break // Exit the loop to avoid test timeout
		}

		// Get current URL safely with retry mechanism
		currentURL, urlErr := getCurrentURLWithRetry(page, 2) // Reduced retries from 3 to 2
		if urlErr != nil {
			log.Printf("Failed to get current URL after retries: %v", urlErr)
			time.Sleep(500 * time.Millisecond) // Reduced from 1s
			continue
		}

		// Check if we have the redirect URL with auth code - use retry mechanism
		if isOAuthRedirectWithAuthCode(currentURL, urlRedirectOauth) {
			log.Printf("SUCCESS! Found redirect URL with auth code")
			log.Printf("Final redirect URL: %s", currentURL)

			// Extract and return auth code with retry
			authCode, err := extractAuthCodeWithRetry(currentURL, 2) // Reduced retries from 3 to 2
			if err != nil {
				log.Printf("Failed to extract auth code after retries: %v", err)
				// Continue the loop instead of returning error immediately
				time.Sleep(300 * time.Millisecond) // Reduced from 500ms
				continue
			}
			log.Printf("Successfully extracted auth code: %s", authCode)
			return authCode, nil
		}

		// Log progress every 5 seconds
		if int(elapsed.Seconds())%5 == 0 && elapsed > 5*time.Second {
			remaining := timeout - elapsed
			log.Printf("OAuth progress: %v elapsed, %v remaining, current URL: %s",
				elapsed.Round(time.Second), remaining.Round(time.Second), currentURL)
		} // Check if we're stuck on the same URL and try to help with navigation
		if currentURL == lastURL {
			stuckCounter++
			if stuckCounter > 3 { // Reduced from 4 - after 1.5 seconds of being stuck (3 * 500ms)
				log.Printf("Page seems stuck on URL: %s, attempting navigation assistance...", currentURL)

				// First, check if we've already reached the redirect URL but missed the auth code
				if strings.Contains(currentURL, urlRedirectOauth) {
					log.Printf("Already on redirect URL, checking for auth code with retry...")
					authCode, err := extractAuthCodeWithRetry(currentURL, 2) // Reduced retries
					if err == nil {
						log.Printf("Found auth code in current URL: %s", authCode)
						return authCode, nil
					}
					log.Printf("Could not extract auth code from redirect URL: %v", err)
				}

				// Try navigation assistance with reduced retries for speed
				success := performNavigationAssistanceWithRetry(page, 1) // Reduced from 2 to 1
				if success {
					// Wait shorter time after successful navigation assistance
					time.Sleep(1 * time.Second) // Reduced from 2s

					// Check URL again after assistance
					newURL, err := getCurrentURLWithRetry(page, 2) // Reduced retries
					if err == nil && newURL != currentURL {
						log.Printf("Navigation assistance worked! URL changed from %s to %s", currentURL, newURL)
						lastURL = newURL
						stuckCounter = 0 // Reset counter after successful navigation
						continue
					}
				}

				stuckCounter = 0 // Reset counter after attempting assistance
			}
		} else {
			stuckCounter = 0
			lastURL = currentURL
		}

		time.Sleep(400 * time.Millisecond) // Reduced from 500ms for faster polling
	}

	// If we reach here, we timed out without finding the auth code
	finalURL, urlErr := getCurrentURLWithRetry(page, 2) // Reduced retries for final check
	if urlErr != nil {
		log.Printf("Failed to get final URL: %v", urlErr)
		return "", fmt.Errorf("Timeout: Could not retrieve final OAuth URL: %w", urlErr)
	}

	log.Printf("Timeout reached after %v", timeout)
	log.Printf("Final URL when timeout occurred: %s", finalURL)

	if finalURL == "" {
		return "", fmt.Errorf("Error: Could not retrieve OAuth URL after timeout")
	}

	// Try to extract auth code from final URL as fallback with reduced retries
	authCode, err := extractAuthCodeWithRetry(finalURL, 2) // Reduced from 3 to 2
	if err != nil {
		// Enhanced fallback to string parsing if URL parsing fails
		log.Printf("URL parsing failed, trying alternative extraction: %v", err)
		authCode = extractAuthCodeFallback(finalURL, urlRedirectOauth)

		// Check if we found any auth code
		if authCode == "" {
			return "", fmt.Errorf("Timeout: Auth code not found in final URL after %v: %s", timeout, finalURL)
		}
	}

	log.Printf("Auth code extracted from timeout URL: %s", authCode)
	return authCode, nil
}

// extractAuthCodeFallback performs fallback auth code extraction using string parsing
func extractAuthCodeFallback(oauth, urlRedirectOauth string) string {
	// Clean the URL first
	cleanedURL := strings.Replace(oauth, urlRedirectOauth, "", 1)

	// First try to find "authCode="
	if strings.Contains(cleanedURL, "authCode=") {
		parts := strings.Split(cleanedURL, "authCode=")
		if len(parts) >= 2 {
			return strings.Split(parts[1], "&")[0]
		}
	}

	// If authCode not found, try "auth_code="
	if strings.Contains(cleanedURL, "auth_code=") {
		parts := strings.Split(cleanedURL, "auth_code=")
		if len(parts) >= 2 {
			return strings.Split(parts[1], "&")[0]
		}
	}

	return ""
}

// waitForURLChangeWithRetry waits for URL to change with retry mechanism
func waitForURLChangeWithRetry(page playwright.Page, previousURL string, timeout time.Duration) (string, bool) {
	start := time.Now()
	checkInterval := 100 * time.Millisecond

	for time.Since(start) < timeout {
		currentURL, err := getCurrentURLWithRetry(page, 2)
		if err != nil {
			log.Printf("Failed to get URL during change detection: %v", err)
			time.Sleep(checkInterval)
			continue
		}

		if currentURL != previousURL {
			log.Printf("URL changed from %s to %s", previousURL, currentURL)
			return currentURL, true
		}

		time.Sleep(checkInterval)
	}

	return previousURL, false
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
