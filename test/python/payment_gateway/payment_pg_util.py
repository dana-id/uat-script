import asyncio
from urllib.parse import urlparse, parse_qs, unquote
from playwright.sync_api import expect
from playwright.async_api import async_playwright
from time import sleep

async def automate_payment_pg(phone_number=None, pin=None, redirectUrlPayment=None, show_log=False):
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
    
    buttonSubmitPhoneNumber = ".agreement__button>.btn-continue"
    inputPhone = ".desktop-input>.txt-input-phone-number-field"
    inputPin = ".txt-input-pin-field"
    buttonPay = "button.btn-pay"
    endpointSuccess = "**/v1/test"
    textAlreadyPaid = "//*[contains(text(),'order is already paid.')]"

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

        log('Opening Payment PG URL...')
        await page.goto(redirectUrlPayment)
        await page.locator(inputPhone).fill(phone_number)
        log('Page loaded, waiting for scripts to initialize...')
        log('Waiting for phone number input to appear...')

        if await page.is_visible(inputPhone, timeout=100000):
            await page.locator(inputPhone).fill(phone_number)
            await page.locator(buttonSubmitPhoneNumber).click()
            log('Phone number submitted, waiting for PIN input...')
            log('Filling in PIN...')
            await page.locator(inputPin).fill(pin)

        
        log('Waiting for payment button...')
        log('Clicking Pay button...')
        await page.locator(buttonPay).click()
        await page.wait_for_url(endpointSuccess)
        await browser.close()
        log('Browser closed')

if __name__ == '__main__':
    code = asyncio.run(automate_payment_pg())