import asyncio
from urllib.parse import urlparse, parse_qs, unquote
from playwright.sync_api import expect
from playwright.async_api import async_playwright
from time import sleep

async def automate_payment_widget(phone_number=None, pin=None, redirectUrlPayment=None, show_log=False):
    """
    Automates the payment process using Playwright in a headless browser, simulating a mobile device.
    This function navigates to the payment URL, enters the provided or extracted phone number, submits it,
    waits for the PIN input screen, enters the provided or default PIN, and attempts to extract the
    authorization code from the redirect URL or page content.
    Args:
        phone_number (str, optional): The phone number to use for login. If not provided, attempts to extract from the OAuth URL.
        pin (str, optional): The PIN code to use for authentication. If not provided, uses the default PIN.
        show_log (bool, optional): If True, prints detailed log messages during the automation process.
    Returns:
        str or None: The extracted authorization code if found, otherwise None.
    Notes:
        - Requires Playwright and asyncio.
        - Simulates an iPhone 12 device with Indonesian locale and Jakarta geolocation.
        - Handles various input field and button selectors to maximize compatibility with different OAuth UIs.
        - Waits for redirects and listens for URL changes to capture the authorization code.
        - Closes the browser context after completion.
    """
    
    buttonDana = ".dana>.bank-title"
    buttonSubmitPhoneNumber = ".agreement__button>.btn-continue"
    inputPhone = ".desktop-input>.txt-input-phone-number-field"
    inputPin = ".txt-input-pin-field"
    buttonPay = "button.btn-pay"
    labelFailedPayment = ".lbl-failed-payment"
    endpointSuccess = "**/v1/test"
    
    wait_timeout = 5000

    def log(msg):
        if show_log:
            print(msg)
    log('Starting Payment automation...')
    # Use provided phone_number or extract from URL or fallback
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, slow_mo=100, args=[
        # Disable telemetry
        '--disable-default-apps',
        '--disable-extensions',
        '--disable-component-update',
        '--disable-features=Translate',
        '--disable-metrics',
        '--disable-metrics-reporting',
    ])
        page = await browser.new_page()

        log('Opening Payment Widget URL...')
        await page.goto(redirectUrlPayment)
        if await page.is_visible(buttonDana, timeout=100000):
            await page.locator(buttonDana).click()
            log('Dana button clicked, waiting for phone number input...')

        # Input phone number
        log('Trying to find the phone input field...')
        try:
            await page.wait_for_selector('.desktop-input>.txt-input-phone-number-field', timeout=30000)
        except Exception:
            pass
        await page.locator(inputPhone).fill(phone_number)
        await page.locator(buttonSubmitPhoneNumber).click()

        # Input pin
        log('Trying to find the PIN input field...')
        try:
            await page.wait_for_selector('.txt-input-pin-field, input[maxlength="6"], input[type="password"]', timeout=30000)
        except Exception:
            pass
        await page.locator(inputPin).fill(pin)

        # Submit payment
        log('Submitting payment...')
        await page.wait_for_selector(buttonPay, timeout=30000)
        await page.wait_for_timeout(wait_timeout)
        await page.locator(buttonPay).click()
        await page.wait_for_timeout(wait_timeout)

        log('Waiting for payment success...')
        await page.goto(redirectUrlPayment)
        if await page.is_visible(buttonPay, timeout=100000):
            await page.locator(buttonPay).click()
            await page.wait_for_selector(labelFailedPayment, timeout=30000)
        
        await browser.close()
        log('Payment automation completed, browser closed')

if __name__ == '__main__':
    code = asyncio.run(automate_payment_widget())