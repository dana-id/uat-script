package payment_gateway

import (
	"fmt"
	"log"
	"time"

	"github.com/playwright-community/playwright-go"
)

func PayOrder(phoneNumber, pin, redirectUrl string) error {
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

	// Launch browser with CI-optimized settings
	browserType := pw.Chromium
	browser, err := browserType.Launch(playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(true),
		Args: []string{
			"--no-sandbox",
			"--disable-setuid-sandbox",
			"--disable-dev-shm-usage",
			"--disable-gpu",
			"--disable-web-security",
			"--disable-extensions",
			"--disable-background-timer-throttling",
			"--disable-renderer-backgrounding",
			"--disable-features=TranslateUI",
			"--disable-ipc-flooding-protection",
		},
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
	textFailedPaid := "div.card-header-content__title.lbl-failed-payment" // More specific selector
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

	// Wait until the buttonPay is visible
	err = page.Locator(buttonPay).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(20 * time.Second / time.Millisecond)), // Reduced timeout for CI
	})
	if err != nil {
		return fmt.Errorf("error: buttonPay not visible: %w", err)
	}

	time.Sleep(2 * time.Second) // Reduced wait time

	page.Locator(buttonPay).Click()
	log.Println("Clicked Pay button")

	// Wait until the textSuccess is visible with shorter timeout
	err = page.Locator(textSuccess).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(20 * time.Second / time.Millisecond)), // Reduced timeout for CI
	})
	if err != nil {
		return fmt.Errorf("error: textSuccess not visible: %w", err)
	}

	log.Println("Payment success label appeared")

	// Second payment verification (simplified)
	if _, err = page.Goto(redirectUrl); err != nil {
		return fmt.Errorf("could not goto verification URL: %w", err)
	}
	page.WaitForLoadState()

	// Wait until the buttonPay is visible for verification
	err = page.Locator(buttonPay).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(15 * time.Second / time.Millisecond)), // Shorter timeout
	})
	if err != nil {
		return fmt.Errorf("error: verification buttonPay not visible: %w", err)
	}

	log.Println("Verify payment")
	page.Locator(buttonPay).Click()

	// Wait for failed payment indicator (this verifies the order was already paid)
	err = page.Locator(textFailedPaid).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(15 * time.Second / time.Millisecond)), // Shorter timeout
	})
	if err != nil {
		return fmt.Errorf("error: textFailedPaid not visible during verification: %w", err)
	}

	log.Println("Payment successful!")
	return nil
}
