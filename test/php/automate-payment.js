const playwright = require('playwright');
const fs = require('fs');

// Get parameters passed from PHP
const params = JSON.parse(process.argv[2] || '{}');
const {
    phoneNumber = '0811742234',
    pin = '123321',
    redirectUrl = 'https://www.dana.id/',
    maxRetries = 3,
    retryDelay = 2000
} = params;

// Function to perform the payment automation
async function performPaymentAutomation(browser, phoneNumber, pin, redirectUrl) {
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        console.log(`Navigating to: ${redirectUrl}`);
        await page.goto(redirectUrl);

        const isDanaPaymentPage = await page.isVisible('//*[contains(@class,"dana")]/*[contains(@class,"bank-title")]',
            { timeout: 5000 });

        if (isDanaPaymentPage) {
            await page.click('//*[contains(@class,"dana")]/*[contains(@class,"bank-title")]');
        }

        // Input phone number
        await page.waitForSelector('//*[contains(@class,"desktop-input")]//input');
        await page.fill('//*[contains(@class,"desktop-input")]//input', phoneNumber.substring(1));
        await page.click('//*[contains(@class,"agreement__button")]//button');

        // Input pin user
        await page.fill('//*[contains(@class,"input-pin")]//input', pin);

        // Click button pay
        await page.click('//*[contains(@class,"btn-pay")]');

        // Wait for either success redirect or failure message
        await Promise.race([
            // Wait for success redirect
            page.waitForURL('**/tinknet.my.id/v1/test**', { timeout: 30000 }),
            // Wait for failure element to appear
            page.waitForSelector('.card-header-content__title.lbl-failed-payment', { timeout: 30000 })
        ]).catch(() => {
            // If neither condition is met within timeout, continue to check manually
        });

        // Check current URL for success
        const currentUrl = page.url();
        if (currentUrl.includes('https://tinknet.my.id/v1/test')) {
            console.log('Payment successful - redirected to success URL');
            return { success: true, authCode: null, error: null };
        }

        // Check for failed payment indicator
        const failedPaymentElement = await page.isVisible('.card-header-content__title.lbl-failed-payment', { timeout: 2000 }).catch(() => false);

        if (failedPaymentElement) {
            const errorText = await page.textContent('.card-header-content__title.lbl-failed-payment').catch(() => 'Payment failed');
            throw new Error(`Payment failed: ${errorText}`);
        }

        // If we're still on the payment page without success redirect or failure message
        // Wait a bit more and check again
        await page.waitForTimeout(5000);
        const finalUrl = page.url();
        if (finalUrl.includes('https://tinknet.my.id/v1/test')) {
            console.log('Payment successful - redirected to success URL (delayed)');
            return { success: true, authCode: null, error: null };
        }

        // If no success redirect and no failure message, consider it uncertain
        throw new Error('Payment status unclear - no success redirect or failure message detected');
    } finally {
        await context.close();
    }
}

// Function to retry with delay
async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

(async () => {
    // Create result object to return to PHP
    let result = {
        success: false,
        authCode: null,
        error: null,
        attempts: 0
    };

    const browser = await playwright.chromium.launch({
        headless: true
    });

    try {
        let lastError = null;

        // Retry loop
        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            result.attempts = attempt;
            console.log(`Attempt ${attempt}/${maxRetries}`);

            try {
                const attemptResult = await performPaymentAutomation(browser, phoneNumber, pin, redirectUrl);

                if (attemptResult.success) {
                    result = { ...result, ...attemptResult };
                    console.log(`Payment automation successful on attempt ${attempt}`);
                    break;
                }
            } catch (error) {
                lastError = error;
                console.error(`Attempt ${attempt} failed:`, error.message);

                // Check if this is a payment failure that should trigger retry
                const shouldRetry = error.message.includes('Payment failed');

                if (!shouldRetry) {
                    console.log('Error is not a payment failure, stopping retries');
                    result.error = error.message;
                    break;
                }

                // If this is not the last attempt, wait before retrying
                if (attempt < maxRetries) {
                    console.log(`Payment failure detected. Waiting ${retryDelay}ms before retry...`);
                    await sleep(retryDelay);
                } else {
                    console.log('Max retries reached for payment failure');
                }
            }
        }

        // If we exhausted all attempts and still failed
        if (!result.success) {
            result.error = lastError ? lastError.message : 'All retry attempts failed';
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