package payment_gateway

import (
	"fmt"
	"log"
	"time"

	"github.com/playwright-community/playwright-go"
)

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

	// Wait until the buttonPay is visible
	err = page.Locator(buttonPay).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(30 * time.Second / time.Millisecond)),
	})
	if err != nil {
		return fmt.Errorf("error: buttonPay not visible: %w", err)
	}

	time.Sleep(5 * time.Second)

	page.Locator(buttonPay).Click()
	log.Println("Clicked Pay button")

	// Wait until the textSuccess is visible
	err = page.Locator(textSuccess).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(30 * time.Second / time.Millisecond)),
	})
	if err != nil {
		return fmt.Errorf("error: textSuccess not visible: %w", err)
	}

	log.Println("Payment success label appeared")
	if _, err = page.Goto(redirectUrl); err != nil {
		log.Fatalf("could not goto: %v", err)
	}
	page.WaitForLoadState()

	// Wait until the buttonPay is visible
	err = page.Locator(buttonPay).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(30 * time.Second / time.Millisecond)),
	})
	if err != nil {
		return fmt.Errorf("error: buttonPay not visible: %w", err)
	}

	log.Println("Verify payment")

	page.Locator(buttonPay).Click()
	page.Locator(textFailedPaid).WaitFor()

	fmt.Println("Payment successful!")
	return nil
}
