package payment_gateway

import (
	"fmt"
	"log"
	"os"
	"strings"
	"sync"
	"time"

	"github.com/mxschmitt/playwright-go"
)

var (
	playwrightInstallOnce sync.Once
	playwrightInstallErr  error
)

var pinInputSelectors = []string{
	".txt-input-pin-field",
	"input[maxlength=\"6\"][inputmode=\"numeric\"]",
	"input[type=\"password\"]",
}

func ensurePlaywrightInstalled() error {
	playwrightInstallOnce.Do(func() {
		playwrightInstallErr = playwright.Install()
	})
	return playwrightInstallErr
}

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
	"button:has-text(\"Bayar Rp\")",
	"button:has-text(\"PAY Rp\")",
	"button:has-text(\"PAY\")",
	"button:has-text(\"Pay\")",
	"button:has-text(\"Bayar\")",
	"button:has-text(\"Confirm\")",
	"button:has-text(\"Continue\")",
	".btn.btn-primary",
	"button.payment-button",
	"button.dana-button",
	"button[type='submit']",
}

var submitPinSelectors = []string{
	"button[type='submit']:not(.btn-pay)",
	".btn-submit",
	"button:has-text(\"Submit\")",
	"button:has-text(\"Confirm\")",
	"button:has-text(\"Konfirmasi\")",
	"button:has-text(\"Masuk\")",
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

func logCheckoutDebug(page playwright.Page, context string) {
	url := page.URL()
	summary, err := page.Evaluate(`() => {
		const buttons = Array.from(document.querySelectorAll('button'))
			.filter(b => b.offsetParent !== null)
			.map(b => ({
				text: (b.textContent || '').trim().slice(0, 80),
				className: b.className,
				type: b.type || '',
			}));
		const bodyText = (document.body && document.body.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 500);
		return { buttons, bodyText };
	}`)
	if err != nil {
		log.Printf("checkout debug (%s): url=%s evaluate err=%v", context, url, err)
		return
	}
	log.Printf("checkout debug (%s): url=%s snapshot=%v", context, url, summary)
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
	time.Sleep(3 * time.Second)

	pinLoc, selector, err := waitForVisibleSelector(page, pinInputSelectors, 30*time.Second)
	if err != nil {
		return fmt.Errorf("PIN field not visible: %w", err)
	}
	log.Printf("PIN field visible (%s)", selector)

	if err := pinLoc.Click(); err != nil {
		return fmt.Errorf("PIN field click: %w", err)
	}
	time.Sleep(300 * time.Millisecond)
	if err := page.Keyboard().Type(pin, playwright.KeyboardTypeOptions{
		Delay: playwright.Float(100),
	}); err != nil {
		log.Printf("PIN keyboard type: %v", err)
	}

	ok, err := page.Evaluate(`(pin) => {
		const selectors = [
			'input.txt-input-pin-field',
			'input[class*="txt-input-pin-field"]',
			'input[maxlength="6"][inputmode="numeric"]',
			'input[type="password"]',
			'input[inputmode="numeric"]',
		];
		let el = null;
		for (const sel of selectors) {
			const candidate = document.querySelector(sel);
			if (candidate && candidate.offsetParent !== null) {
				el = candidate;
				break;
			}
		}
		if (!el) return false;
		const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
		setter.call(el, pin);
		el.dispatchEvent(new Event('input', { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
		el.dispatchEvent(new Event('blur', { bubbles: true }));
		el.focus();
		for (let i = 0; i < pin.length; i++) {
			const char = pin[i];
			el.dispatchEvent(new KeyboardEvent('keydown', { key: char, code: 'Digit' + char, bubbles: true }));
			el.dispatchEvent(new KeyboardEvent('keyup', { key: char, code: 'Digit' + char, bubbles: true }));
		}
		el.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', bubbles: true }));
		el.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', code: 'Enter', bubbles: true }));
		return el.value === pin;
	}`, pin)
	if err != nil {
		return fmt.Errorf("PIN JS entry: %w", err)
	}
	if entered, _ := ok.(bool); !entered {
		return fmt.Errorf("PIN JS entry failed")
	}
	log.Println("PIN entered")
	time.Sleep(2 * time.Second)
	return nil
}

func submitPinAfterEntry(page playwright.Page) {
	if _, selector, err := waitForVisibleSelector(page, submitPinSelectors, 3*time.Second); err == nil {
		loc := page.Locator(selector).First()
		if clickErr := loc.Click(); clickErr == nil {
			log.Printf("Submitted PIN via button (%s)", selector)
			return
		}
	}
	clicked, err := page.Evaluate(`() => {
		const buttons = document.querySelectorAll('button');
		for (const button of buttons) {
			if (button.offsetParent === null) continue;
			if (button.classList.contains('btn-pay') || button.classList.contains('btn-continue')) continue;
			const text = (button.textContent || '').trim();
			if (!text) continue;
			if (/pay|bayar|continue|lanjut/i.test(text)) continue;
			if (button.type === 'submit' || /submit|confirm|konfirmasi|masuk/i.test(text)) {
				button.click();
				return text;
			}
		}
		return null;
	}`)
	if err == nil {
		if label, ok := clicked.(string); ok && label != "" {
			log.Printf("Submitted PIN via JS fallback (%q)", label)
			return
		}
	}
	log.Println("No PIN submit button found; relying on Enter key from PIN entry")
}

func clickPayButton(page playwright.Page, payButton playwright.Locator, selector string) error {
	if err := payButton.Click(); err == nil {
		log.Printf("Clicked Pay button (%s)", selector)
		return nil
	}
	clicked, jsErr := page.Evaluate(`() => {
		const buttons = document.querySelectorAll('button');
		for (const button of buttons) {
			if (button.offsetParent === null) continue;
			const text = (button.textContent || '').trim();
			if (button.classList.contains('btn-pay') ||
				/pay|bayar|confirm|continue/i.test(text)) {
				button.click();
				return true;
			}
		}
		return false;
	}`)
	if jsErr != nil {
		return fmt.Errorf("error: could not click pay button: %w", jsErr)
	}
	if ok, _ := clicked.(bool); ok && ok {
		log.Println("Clicked Pay button via JS fallback")
		return nil
	}
	return fmt.Errorf("error: could not click pay button")
}

func playwrightHeadless() bool {
	raw := strings.TrimSpace(strings.ToLower(os.Getenv("PLAYWRIGHT_HEADLESS")))
	if raw == "false" || raw == "0" || raw == "no" {
		return false
	}
	return true
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

	// Install playwright once per process (retries reuse the same browser driver).
	if err := ensurePlaywrightInstalled(); err != nil {
		return fmt.Errorf("could not install playwright: %w", err)
	}

	pw, err := playwright.Run()
	if err != nil {
		return fmt.Errorf("could not start playwright: %v", err)
	}

	browserType := pw.Chromium
	browser, err := browserType.Launch(playwright.BrowserTypeLaunchOptions{
		Headless: playwright.Bool(playwrightHeadless()),
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
	submitPinAfterEntry(page)
	log.Println("Waiting for PIN processing...")
	time.Sleep(10 * time.Second)
	_ = page.WaitForLoadState(playwright.PageWaitForLoadStateOptions{
		State:   playwright.LoadStateNetworkidle,
		Timeout: playwright.Float(float64(15 * time.Second / time.Millisecond)),
	})

	if paymentAlreadySucceeded(page) {
		log.Println("Payment already succeeded on checkout page")
		return nil
	}
	if checkoutErr := checkoutPageError(page); checkoutErr != "" {
		return fmt.Errorf("checkout error after PIN: %s", checkoutErr)
	}

	payButton, selector, err := waitForVisiblePayButton(page, 45*time.Second)
	if err != nil {
		logCheckoutDebug(page, "pay-button-missing")
		if checkoutErr := checkoutPageError(page); checkoutErr != "" {
			return fmt.Errorf("checkout error: %s", checkoutErr)
		}
		return fmt.Errorf("error: buttonPay not visible: %w", err)
	}
	log.Printf("Pay button visible (%s)", selector)

	time.Sleep(2 * time.Second)

	if err := clickPayButton(page, payButton, selector); err != nil {
		return err
	}

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
