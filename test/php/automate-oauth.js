const playwright = require('playwright');
const fs = require('fs');

// Get parameters passed from PHP
const params = JSON.parse(process.argv[2] || '{}');
const {
    phoneNumber = '0811742234',
    pin = '123321',
    redirectUrl = 'https://www.dana.id/',
} = params;

(async () => {
    // Create result object to return to PHP
    const result = {
        success: false,
        authCode: null,
        error: null
    };

    const browser = await playwright.chromium.launch({
        headless: true
    });

    try {
        const context = await browser.newContext();
        const page = await context.newPage();

        console.log(`Navigating to: ${redirectUrl}`);
        await page.goto(redirectUrl);

        // Wait for and fill phone number field
        await page.waitForSelector('//*[contains(@class,"desktop-input")]//input');
        await page.fill('//*[contains(@class,"desktop-input")]//input', phoneNumber);

        // Submit phone number
        await page.click('//*[contains(@class,"agreement__button")]//button');

        // Wait for PIN input
        await page.waitForSelector('//*[contains(@class,"input-pin")]//input');
        await page.fill('//*[contains(@class,"input-pin")]//input', pin);

        await page.waitForTimeout(5000);

        // Get the current URL which should contain the auth code
        const currentUrl = page.url();
        console.log(`Redirected to: ${currentUrl}`);

        // Extract auth code
        const tempUrl = new URL(currentUrl);
        const url = tempUrl.toString();
        const tempCurrentUrl = url
            .replace("https://www.google.com/?", "");

        const code = tempCurrentUrl.split("auth_code=")[1]?.split("&")[0];
        console.log(`Extracted auth code: ${code}`);

        if (code) {
            result.success = true;
            result.authCode = code;
        } else {
            result.error = 'Auth code not found in redirect URL';
        }
    } catch (error) {
        result.error = error.message;
        console.error('Automation error:', error);
    } finally {
        await browser.close();

        // Return result as JSON so PHP can parse it
        console.log(JSON.stringify(result));
    }
})();