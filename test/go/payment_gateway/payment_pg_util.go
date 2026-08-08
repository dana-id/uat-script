package payment_gateway

import (
	"fmt"
	"log"
	"strings"
	"time"

	"github.com/mxschmitt/playwright-go"
)

var phoneInputSelectors = []string{
	".desktop-input>.txt-input-phone-number-field",
	".txt-input-phone-number-field",
	".desktop-input>input",
	"input[placeholder*='phone' i]",
}

var submitPhoneSelectors = []string{
	".agreement__button>.btn-continue",
	".btn-continue",
	"button.btn-continue",
}

func waitForVisibleSelector(page playwright.Page, selectors []string, timeout time.Duration) (playwright.Locator, string, error) {
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		for _, selector := range selectors {
			loc := page.Locator(selector).First()
			visible, err := loc.IsVisible()
			if err == nil && visible {
				return loc, selector, nil
			}
		}
		time.Sleep(500 * time.Millisecond)
	}
	return nil, "", fmt.Errorf("no visible element after %s (tried %v)", timeout, selectors)
}

var payButtonSelectors = []string{
	"button.btn-pay",
	".btn.btn-primary.btn-pay",
	".btn-pay",
	"button:has-text(\"BAYAR\")",
	".btn.btn-primary",
	"button[type='submit']",
}

var checkoutErrorSelectors = []string{
	".lbl-failed-payment",
	".card-header-content__title.lbl-failed-payment",
	"[class*='lbl-failed']",
}

func normalizePhoneNumber(phoneNumber string) string {
	phone := strings.TrimSpace(phoneNumber)
	if strings.HasPrefix(phone, "0") {
		return phone[1:]
	}
	return phone
}

func waitForVisiblePayButton(page playwright.Page, timeout time.Duration) (playwright.Locator, string, error) {
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		for _, selector := range payButtonSelectors {
			loc := page.Locator(selector).First()
			visible, err := loc.IsVisible()
			if err == nil && visible {
				return loc, selector, nil
			}
		}
		time.Sleep(500 * time.Millisecond)
	}
	return nil, "", fmt.Errorf("pay button not visible after %s (tried %v)", timeout, payButtonSelectors)
}

func checkoutPageError(page playwright.Page) string {
	for _, selector := range checkoutErrorSelectors {
		loc := page.Locator(selector).First()
		visible, err := loc.IsVisible()
		if err != nil || !visible {
			continue
		}
		text, err := loc.InnerText()
		if err == nil && strings.TrimSpace(text) != "" {
			return strings.TrimSpace(text)
		}
	}
	return ""
}

func enterPIN(page playwright.Page, pin string) error {
	pinSelector := ".txt-input-pin-field, input[maxlength=\"6\"][inputmode=\"numeric\"], input[type=\"password\"]"
	pinLoc := page.Locator(pinSelector).First()
	if err := pinLoc.WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(15 * time.Second / time.Millisecond)),
	}); err != nil {
		return fmt.Errorf("PIN field not visible: %w", err)
	}
	if err := pinLoc.Click(); err != nil {
		return fmt.Errorf("PIN field click: %w", err)
	}
	time.Sleep(300 * time.Millisecond)
	if err := page.Keyboard().Type(pin, playwright.KeyboardTypeOptions{
		Delay: playwright.Float(100),
	}); err != nil {
		return fmt.Errorf("PIN keyboard type: %w", err)
	}
	time.Sleep(500 * time.Millisecond)
	if err := pinLoc.Press("Enter"); err != nil {
		log.Printf("PIN Enter key: %v", err)
	}
	return nil
}

func paymentAlreadySucceeded(page playwright.Page) bool {
	selectors := []string{
		"//*[contains(@class,'lbl-success')]",
		".sdetfe-lbl-success",
		"text=This transaction is paid using DANA",
	}
	for _, selector := range selectors {
		visible, err := page.Locator(selector).First().IsVisible()
		if err == nil && visible {
			return true
		}
	}
	return false
}

func PayOrder(phoneNumber, pin, redirectUrl string) error {
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

	browserType := pw.Chromium
	browser, err := browserType.Launch(playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(true),
		Args: []string{
			"--no-sandbox",
			"--disable-setuid-sandbox",
			"--disable-dev-shm-usage",
			"--disable-gpu",
			"--disable-software-rasterizer",
			"--disable-extensions",
		},
	})
	if err != nil {
		return fmt.Errorf("could not launch browser: %w", err)
	}

	defer browser.Close()

	page, err := browser.NewPage()
	if err != nil {
		return fmt.Errorf("could not create page: %w", err)
	}
	print("Redirect URL:", redirectUrl, "\n")

	if _, err = page.Goto(redirectUrl, playwright.PageGotoOptions{
		WaitUntil: playwright.WaitUntilStateDomcontentloaded,
		Timeout:   playwright.Float(float64(60 * time.Second / time.Millisecond)),
	}); err != nil {
		return fmt.Errorf("could not goto: %w", err)
	}
	page.WaitForLoadState()

	// Elements for DANA payment
	textSuccess := "//*[contains(@class,'lbl-success')]"

	phoneInput, phoneSelector, err := waitForVisibleSelector(page, phoneInputSelectors, 20*time.Second)
	if err != nil {
		log.Println("Phone input not visible, looking for DANA payment option...")
		danaPaySelectors := []string{
			"div.bank-item.sdetfe-lbl-dana-pay-option",
			"div.bank-item[class*='dana-pay-option']",
			"div.bank-title:has-text('DANA')",
			"div.bank-item:has(div.bank-title:has-text('DANA'))",
			"//*[contains(@class,'dana')]//*[contains(@class,'bank-title')]",
		}
		var danaButtonFound bool
		for _, selector := range danaPaySelectors {
			elementCount, countErr := page.Locator(selector).Count()
			if countErr == nil && elementCount > 0 {
				log.Printf("DANA payment option found with selector: %s", selector)
				danaButtonFound = true
				if clickErr := page.Locator(selector).First().Click(); clickErr != nil {
					log.Printf("could not click DANA payment option: %v", clickErr)
					continue
				}
				time.Sleep(2 * time.Second)
				break
			}
		}
		if !danaButtonFound {
			return fmt.Errorf("DANA payment option not found")
		}
		phoneInput, phoneSelector, err = waitForVisibleSelector(page, phoneInputSelectors, 20*time.Second)
		if err != nil {
			return fmt.Errorf("phone input not visible after DANA option: %w", err)
		}
	}
	log.Printf("phone number input visible (%s)", phoneSelector)

	submitPhone, submitSelector, err := waitForVisibleSelector(page, submitPhoneSelectors, 10*time.Second)
	if err != nil {
		return fmt.Errorf("submit phone button not visible: %w", err)
	}
	phone := normalizePhoneNumber(phoneNumber)
	if err := phoneInput.Fill(phone); err != nil {
		return fmt.Errorf("fill phone number: %w", err)
	}
	if err := submitPhone.Click(); err != nil {
		return fmt.Errorf("click submit phone (%s): %w", submitSelector, err)
	}
	log.Println("Submitted phone number")
	if err := enterPIN(page, pin); err != nil {
		return err
	}
	log.Println("Submitted PIN")
	log.Println("Waiting for PIN processing...")
	time.Sleep(5 * time.Second)

	if paymentAlreadySucceeded(page) {
		log.Println("Payment already succeeded on checkout page")
		return nil
	}
	if checkoutErr := checkoutPageError(page); checkoutErr != "" {
		return fmt.Errorf("checkout error after PIN: %s", checkoutErr)
	}

	payButton, selector, err := waitForVisiblePayButton(page, 30*time.Second)
	if err != nil {
		if checkoutErr := checkoutPageError(page); checkoutErr != "" {
			return fmt.Errorf("checkout error: %s", checkoutErr)
		}
		return fmt.Errorf("error: buttonPay not visible: %w", err)
	}
	log.Printf("Pay button visible (%s)", selector)

	time.Sleep(2 * time.Second)

	if err := payButton.Click(); err != nil {
		return fmt.Errorf("error: could not click pay button: %w", err)
	}
	log.Println("Clicked Pay button")

	// Wait until the textSuccess is visible with shorter timeout
	err = page.Locator(textSuccess).WaitFor(playwright.LocatorWaitForOptions{
		State:   playwright.WaitForSelectorStateVisible,
		Timeout: playwright.Float(float64(30 * time.Second / time.Millisecond)),
	})
	if err != nil {
		return fmt.Errorf("error: textSuccess not visible: %w", err)
	}

	log.Println("Payment success label appeared")
	// Success label means payment is already paid; no need for second verification step.
	log.Println("Payment successful!")
	return nil
}
