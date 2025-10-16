package payment_gateway

import (
	"fmt"
	"log"
	"time"

	"github.com/playwright-community/playwright-go"
)

// WaitCondition represents different wait conditions
type WaitCondition string

const (
	ConditionVisible  WaitCondition = "visible"
	ConditionHidden   WaitCondition = "hidden"
	ConditionAttached WaitCondition = "attached"
	ConditionDetached WaitCondition = "detached"
	ConditionEnabled  WaitCondition = "enabled"
	ConditionDisabled WaitCondition = "disabled"
)

// WaitUntilOptions contains options for wait operations
type WaitUntilOptions struct {
	Timeout   time.Duration // Maximum time to wait (default: 30 seconds)
	Condition WaitCondition // What condition to wait for (default: visible)
	Interval  time.Duration // How often to check (default: 500ms)
}

// WaitUntil waits for a specific condition on an element with custom options
// Returns true if condition is met, false if timeout occurs
func WaitUntil(page playwright.Page, selector string, options *WaitUntilOptions) (bool, error) {
	// Set default options
	if options == nil {
		options = &WaitUntilOptions{}
	}
	if options.Timeout == 0 {
		options.Timeout = 30 * time.Second
	}
	if options.Condition == "" {
		options.Condition = ConditionVisible
	}
	if options.Interval == 0 {
		options.Interval = 500 * time.Millisecond
	}

	locator := page.Locator(selector)
	endTime := time.Now().Add(options.Timeout)

	for time.Now().Before(endTime) {
		var conditionMet bool
		var err error

		switch options.Condition {
		case ConditionVisible:
			conditionMet, err = locator.IsVisible()
		case ConditionHidden:
			visible, visErr := locator.IsVisible()
			conditionMet = !visible
			err = visErr
		case ConditionAttached:
			count, countErr := locator.Count()
			conditionMet = count > 0
			err = countErr
		case ConditionDetached:
			count, countErr := locator.Count()
			conditionMet = count == 0
			err = countErr
		case ConditionEnabled:
			conditionMet, err = locator.IsEnabled()
		case ConditionDisabled:
			enabled, enabledErr := locator.IsEnabled()
			conditionMet = !enabled
			err = enabledErr
		default:
			return false, fmt.Errorf("unsupported condition: %s", options.Condition)
		}

		if err != nil {
			// If there's an error, continue trying unless it's a critical error
			log.Printf("Error checking condition for %s: %v", selector, err)
		} else if conditionMet {
			return true, nil
		}

		time.Sleep(options.Interval)
	}

	return false, fmt.Errorf("timeout waiting for %s to be %s", selector, options.Condition)
}

// WaitForVisible is a convenience function for waiting until element is visible
func WaitForVisible(page playwright.Page, selector string, timeout ...time.Duration) (bool, error) {
	timeoutDuration := 30 * time.Second
	if len(timeout) > 0 {
		timeoutDuration = timeout[0]
	}

	return WaitUntil(page, selector, &WaitUntilOptions{
		Timeout:   timeoutDuration,
		Condition: ConditionVisible,
	})
}

// WaitForHidden is a convenience function for waiting until element is hidden
func WaitForHidden(page playwright.Page, selector string, timeout ...time.Duration) (bool, error) {
	timeoutDuration := 30 * time.Second
	if len(timeout) > 0 {
		timeoutDuration = timeout[0]
	}

	return WaitUntil(page, selector, &WaitUntilOptions{
		Timeout:   timeoutDuration,
		Condition: ConditionHidden,
	})
}

// WaitForClickable waits until element is both visible and enabled
func WaitForClickable(page playwright.Page, selector string, timeout ...time.Duration) (bool, error) {
	timeoutDuration := 30 * time.Second
	if len(timeout) > 0 {
		timeoutDuration = timeout[0]
	}

	// First wait for visible
	visible, err := WaitForVisible(page, selector, timeoutDuration)
	if !visible || err != nil {
		return false, fmt.Errorf("element not visible: %v", err)
	}

	// Then wait for enabled
	return WaitUntil(page, selector, &WaitUntilOptions{
		Timeout:   timeoutDuration,
		Condition: ConditionEnabled,
	})
}

func PayOrder(phoneNumber, pin, redirectUrl string) interface{} {
	// Create a custom allocator with non-headless mode
	log.Println("Starting payment automation...")

	if redirectUrl == "" {
		return fmt.Errorf("error: no checkout URL provided")
	}

	// Install playwright if it's not already installed
	err := playwright.Install()
	if err != nil {
		return fmt.Errorf("could not install playwright: %w", err)
	}

	pw, err := playwright.Run()
	if err != nil {
		return fmt.Errorf("could not start playwright: %v", err)
	}

	// Launch browser
	browserType := pw.Chromium
	browser, err := browserType.Launch(playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(true),
	})
	if err != nil {
		return fmt.Errorf("could not launch browser: %w", err)
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
	page.WaitForLoadState()

	// Elements for DANA payment
	inputPhoneNumber := ".desktop-input>.txt-input-phone-number-field"
	buttonSubmitPhoneNumber := ".agreement__button>.btn-continue"
	inputPin := ".txt-input-pin-field"
	buttonPay := ".btn.btn-primary"
	textFailedPaid := "//*[contains(@class,'lbl-failed')]"
	textSuccess := "//*[contains(@class,'lbl-success')]"

	// Wait for the phone number input to be visible
	page.Locator(inputPhoneNumber).WaitFor()
	isVisible, _ := page.Locator(inputPhoneNumber).IsVisible()
	log.Println("phone number input visible:", isVisible)

	if !isVisible {
		log.Println("Looking for DANA payment option...")
		var danaButtonFound bool

		// Try different selectors for DANA payment button
		danaPaySelectors := []string{
			"div.bank-item.sdetfe-lbl-dana-pay-option",
			"div.bank-item[class*='dana-pay-option']",
			"div.bank-title:has-text('DANA')",
			"div.bank-item:has(div.bank-title:has-text('DANA'))",
		}

		for _, selector := range danaPaySelectors {
			elementCount, err := page.Locator(selector).Count()
			if err != nil && elementCount > 0 {
				log.Printf("DANA payment option found with selector: %s\n", selector)
				danaButtonFound = true
				err := page.Locator(selector).Click()
				if err != nil {
					log.Printf("could not click DANA payment option: %v\n", err)
					continue
				}
				break
			}
		}
		if !danaButtonFound {
			return fmt.Errorf("DANA payment option not found")
		}
	}
	// Fill in the phone number and pin
	page.Locator(inputPhoneNumber).Fill(phoneNumber)
	page.Locator(buttonSubmitPhoneNumber).Click()
	log.Println("Submitted phone number")
	page.Locator(inputPin).Fill(pin)
	log.Println("Submitted PIN")

	// Wait for the pay button to be visible
	WaitForClickable(page, buttonPay, 10*time.Second)
	countButtonPay, err := page.Locator(buttonPay).Count()
	if err != nil || countButtonPay == 0 {
		return fmt.Errorf("error: DANA payment option not found or button not available")
	}

	page.Locator(buttonPay).Click()
	log.Println("Clicked Pay button")
	visible, err := WaitForVisible(page, textSuccess, 10*time.Second)
	if err != nil {
		return fmt.Errorf("error: %v", err)
	}
	log.Println("Payment success label appeared:", visible)
	if _, err = page.Goto(redirectUrl); err != nil {
		log.Fatalf("could not goto: %v", err)
	}
	page.WaitForLoadState()

	WaitForClickable(page, buttonPay, 10*time.Second)
	log.Println("Pay button visible:", isVisible)
	if err != nil || countButtonPay == 0 {
		return fmt.Errorf("error: DANA payment option not found or button not available")
	}
	page.Locator(buttonPay).Click()
	page.Locator(textFailedPaid).WaitFor()

	fmt.Println("Payment successful!")
	return nil
}
