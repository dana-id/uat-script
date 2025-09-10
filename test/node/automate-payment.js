/**
 * @fileoverview DANA Payment Automation Script for Node.js Integration Testing
 * 
 * This module automates the DANA payment process using Playwright for web automation.
 * It supports retry logic, error handling, and comprehensive logging for integration testing.
 * 
 * @author Integration Test Team
 * @version 1.0.0
 * @since 2024
 * @module PaymentAutomation
 * @requires playwright
 * @requires fs
 * 
 * Features:
 * - Automated DANA payment processing
 * - Retry logic for failed payments
 * - Support for headless and headed browser modes
 * - Comprehensive error handling and logging
 * - Mobile device emulation support
 * - Environment-specific browser configuration
 */

const playwright = require('playwright');
const fs = require('fs');

// Parse command line parameters for standalone execution
const params = JSON.parse(process.argv[2] || '{}');
const {
    phoneNumber = '0811742234',
    pin = '123321',
    redirectUrl = 'https://www.dana.id/',
    maxRetries = 3,
    retryDelay = 2000,
    headless = false
} = params;

/**
 * CSS selectors for DANA payment page elements
 * @constant {Object} SELECTORS
 */
const SELECTORS = {
    DANA_BUTTON: "//*[contains(@class,\"dana\")]/*[contains(@class,\"bank-title\")]",
    PHONE_INPUT: ".desktop-input>.txt-input-phone-number-field",
    SUBMIT_PHONE_BUTTON: ".agreement__button>.btn-continue",
    PIN_INPUT: ".txt-input-pin-field",
    PAY_BUTTON: ".btn.btn-primary",
    FAILED_PAYMENT: ".card-header-content__title.lbl-failed-payment"
};

/**
 * Multi-selector configurations for better element detection with fallback options
 * @constant {Object} MULTI_SELECTORS
 */
const MULTI_SELECTORS = {
    DANA_BUTTON: [
        "//*[contains(@class,\"dana\")]/*[contains(@class,\"bank-title\")]",
        ".dana-payment-option",
        "//*[contains(@class,'dana')]//parent::*[contains(@class,'bank-title')]",
        "button[class*='dana']",
        ".payment-method-dana"
    ],
    PHONE_INPUT: [
        ".desktop-input>.txt-input-phone-number-field",
        ".txt-input-phone-number-field",
        "input[placeholder*='phone']",
        "input[name='phoneNumber']",
        "#phoneNumber",
        "[data-testid='phone-input']"
    ],
    SUBMIT_PHONE_BUTTON: [
        ".agreement__button>.btn-continue",
        ".btn-continue",
        "button[type='submit']",
        ".submit-button",
        "[data-testid='submit-phone']"
    ],
    PIN_INPUT: [
        ".txt-input-pin-field",
        "input[type='password']",
        "input[name='pin']",
        "#pin",
        "[data-testid='pin-input']",
        ".pin-field"
    ],
    PAY_BUTTON: [
        ".btn.btn-primary",
        ".btn-primary",
        "button[type='submit']",
        ".pay-button",
        "[data-testid='pay-button']",
        "button[class*='pay']"
    ],
    FAILED_PAYMENT: [
        ".card-header-content__title.lbl-failed-payment",
        ".lbl-failed-payment",
        ".error-message",
        ".payment-failed",
        "[data-testid='payment-error']"
    ]
};

/**
 * Waits for any of the provided selectors to become visible
 * 
 * @async
 * @function waitForAnySelector
 * @param {Object} page - Playwright page instance
 * @param {string[]} selectors - Array of CSS selectors to check
 * @param {number} [timeout=10000] - Maximum wait time in milliseconds
 * @returns {Promise<string>} The selector that was found
 * @throws {Error} When none of the selectors are found within timeout
 * 
 * @example
 * const foundSelector = await waitForAnySelector(page, ['.btn1', '.btn2', '#button'], 5000);
 * await page.click(foundSelector);
 */
async function waitForAnySelector(page, selectors, timeout = 10000) {
    const promises = selectors.map(selector => 
        page.waitForSelector(selector, { timeout }).then(() => selector)
    );
    
    try {
        const foundSelector = await Promise.race(promises);
        console.log(`Found element with selector: ${foundSelector}`);
        return foundSelector;
    } catch (error) {
        throw new Error(`None of the selectors found within ${timeout}ms: ${selectors.join(', ')}`);
    }
}

/**
 * Clicks the first available element from multiple selectors
 * 
 * @async
 * @function clickAnySelector
 * @param {Object} page - Playwright page instance
 * @param {string[]} selectors - Array of CSS selectors to try
 * @param {number} [timeout=10000] - Maximum wait time in milliseconds
 * @returns {Promise<string>} The selector that was successfully clicked
 * @throws {Error} When none of the selectors can be clicked
 * 
 * @example
 * await clickAnySelector(page, MULTI_SELECTORS.DANA_BUTTON);
 */
async function clickAnySelector(page, selectors, timeout = 10000) {
    const foundSelector = await waitForAnySelector(page, selectors, timeout);
    await page.click(foundSelector);
    console.log(`Clicked element: ${foundSelector}`);
    return foundSelector;
}

/**
 * Fills input field using the first available selector from multiple options
 * 
 * @async
 * @function fillAnySelector
 * @param {Object} page - Playwright page instance
 * @param {string[]} selectors - Array of CSS selectors to try
 * @param {string} value - Value to fill in the input field
 * @param {number} [timeout=10000] - Maximum wait time in milliseconds
 * @returns {Promise<string>} The selector that was successfully filled
 * @throws {Error} When none of the selectors can be filled
 * 
 * @example
 * await fillAnySelector(page, MULTI_SELECTORS.PHONE_INPUT, '811742234');
 */
async function fillAnySelector(page, selectors, value, timeout = 10000) {
    const foundSelector = await waitForAnySelector(page, selectors, timeout);
    await page.fill(foundSelector, value);
    console.log(`Filled element ${foundSelector} with value: ${value}`);
    return foundSelector;
}

/**
 * Checks if any of the provided selectors is visible on the page
 * 
 * @async
 * @function isAnyVisible
 * @param {Object} page - Playwright page instance
 * @param {string[]} selectors - Array of CSS selectors to check
 * @param {number} [timeout=5000] - Maximum wait time in milliseconds
 * @returns {Promise<string|null>} The first visible selector or null if none found
 * 
 * @example
 * const visibleSelector = await isAnyVisible(page, MULTI_SELECTORS.FAILED_PAYMENT);
 * if (visibleSelector) {
 *     console.log('Payment failed element found');
 * }
 */
async function isAnyVisible(page, selectors, timeout = 5000) {
    for (const selector of selectors) {
        try {
            const isVisible = await page.isVisible(selector, { timeout: timeout / selectors.length });
            if (isVisible) {
                console.log(`Visible element found: ${selector}`);
                return selector;
            }
        } catch (error) {
            // Continue to next selector
        }
    }
    return null;
}

/**
 * Success URL pattern for payment completion detection
 * @constant {string}
 */
const SUCCESS_URL_PATTERN = "**/v1/test";

/**
 * Performs the core payment automation workflow
 * 
 * @async
 * @function performPaymentAutomation
 * @param {Object} browser - Playwright browser instance
 * @param {string} phoneNumber - Phone number for DANA account (without leading 0)
 * @param {string} pin - 6-digit PIN for DANA account
 * @param {string} redirectUrl - Payment URL to navigate to
 * @returns {Promise<Object>} Result object with success status, authCode, and error details
 * @throws {Error} When payment fails or automation encounters errors
 * 
 * @example
 * const result = await performPaymentAutomation(browser, '811742234', '123321', 'https://payment.url');
 * console.log(result); // { success: true, authCode: null, error: null }
 */
async function performPaymentAutomation(browser, phoneNumber, pin, redirectUrl) {
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        console.log(`Navigating to payment URL: ${redirectUrl}`);
        await page.goto(redirectUrl);

        // Check if DANA payment option is available and click it using multi-selector
        const danaButton = await isAnyVisible(page, MULTI_SELECTORS.DANA_BUTTON, 20000);
        
        if (danaButton) {
            console.log('DANA payment option found, clicking...');
            await page.click(danaButton);
        }

        // Wait for phone input field with retry logic using multi-selector
        await waitForPhoneInputWithRetry(page, MULTI_SELECTORS.PHONE_INPUT, MULTI_SELECTORS.DANA_BUTTON);
        
        // Fill phone number (remove leading 0 if present) using multi-selector
        const cleanPhoneNumber = phoneNumber.startsWith('0') ? phoneNumber.substring(1) : phoneNumber;
        console.log(`Entering phone number: ${cleanPhoneNumber}`);
        await fillAnySelector(page, MULTI_SELECTORS.PHONE_INPUT, cleanPhoneNumber);
        await clickAnySelector(page, MULTI_SELECTORS.SUBMIT_PHONE_BUTTON);

        // Fill PIN using multi-selector
        console.log('Entering PIN...');
        await fillAnySelector(page, MULTI_SELECTORS.PIN_INPUT, pin);

        // Click pay button using multi-selector
        console.log('Initiating payment...');
        await clickAnySelector(page, MULTI_SELECTORS.PAY_BUTTON);

        // Wait for payment result
        return await waitForPaymentResult(page);

    } finally {
        // Keep page open for 15 seconds to allow payment processing
        console.log('Waiting for payment processing to complete...');
        await sleep(15000);
        await context.close();
    }
}

/**
 * Waits for phone input field with retry logic using multi-selector approach
 * 
 * @async
 * @function waitForPhoneInputWithRetry
 * @param {Object} page - Playwright page instance
 * @param {string[]} inputSelectors - Array of CSS selectors for phone input field
 * @param {string[]} danaButtonSelectors - Array of CSS selectors for DANA button
 * @param {number} maxRetries - Maximum number of retry attempts
 * @throws {Error} When input field is not found after maximum retries
 */
async function waitForPhoneInputWithRetry(page, inputSelectors, danaButtonSelectors, maxRetries = 3) {
    let inputFound = false;
    let retryCount = 0;

    while (!inputFound && retryCount < maxRetries) {
        try {
            await waitForAnySelector(page, inputSelectors, 1000);
            inputFound = true;
            console.log('Phone input field found');
        } catch (error) {
            retryCount++;
            console.log(`Phone input field not found, retry ${retryCount}/${maxRetries}`);

            if (retryCount < maxRetries) {
                // Try clicking DANA option again using multi-selector
                const danaOption = await isAnyVisible(page, danaButtonSelectors);
                if (danaOption) {
                    await page.click(danaOption);
                    await page.waitForTimeout(2000);
                }
            } else {
                throw new Error('Phone input field not found after maximum retries');
            }
        }
    }
}

/**
 * Waits for payment result and determines success or failure using multi-selectors
 * 
 * @async
 * @function waitForPaymentResult
 * @param {Object} page - Playwright page instance
 * @returns {Promise<Object>} Payment result object
 * @throws {Error} When payment fails or status is unclear
 */
async function waitForPaymentResult(page) {
    // Wait for either success redirect or failure message
    await Promise.race([
        page.waitForURL('**/tinknet.my.id/v1/test**', { timeout: 30000 }),
        waitForAnySelector(page, MULTI_SELECTORS.FAILED_PAYMENT, 30000)
    ]).catch(() => {
        // Continue to manual check if race conditions aren't met
    });

    // Check for success redirect
    const currentUrl = page.url();
    if (currentUrl.includes(SUCCESS_URL_PATTERN.replace('**/', ''))) {
        console.log('Payment successful - redirected to success URL');
        return { success: true, authCode: null, error: null };
    }

    // Check for failure indicator using multi-selector
    const failedPaymentElement = await isAnyVisible(page, MULTI_SELECTORS.FAILED_PAYMENT, 2000);
    if (failedPaymentElement) {
        const errorText = await page.textContent(failedPaymentElement).catch(() => 'Payment failed');
        throw new Error(`Payment failed: ${errorText}`);
    }

    // Wait additional time for delayed redirects
    await page.waitForTimeout(5000);
    const finalUrl = page.url();
    if (finalUrl.includes(SUCCESS_URL_PATTERN.replace('**/', ''))) {
        console.log('Payment successful - redirected to success URL (delayed)');
        return { success: true, authCode: null, error: null };
    }

    throw new Error('Payment status unclear - no success redirect or failure message detected');
}

/**
 * Utility function to pause execution for specified milliseconds
 * 
 * @async
 * @function sleep
 * @param {number} ms - Milliseconds to sleep
 * @returns {Promise<void>} Promise that resolves after the specified delay
 * 
 * @example
 * await sleep(2000); // Wait for 2 seconds
 */
async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Main automation function for DANA payment processing
 * 
 * @async
 * @function automatePayment
 * @param {Object} params - Payment automation parameters
 * @param {string} params.phoneNumber - DANA account phone number (with or without leading 0)
 * @param {string} params.pin - 6-digit DANA PIN
 * @param {string} params.redirectUrl - Payment URL to navigate to
 * @param {number} [params.maxRetries=5] - Maximum number of retry attempts
 * @param {number} [params.retryDelay=2000] - Delay between retries in milliseconds
 * @param {boolean} [params.headless=true] - Whether to run browser in headless mode
 * @returns {Promise<Object>} Payment result object with success status, authCode, error details, and attempt count
 * @throws {Error} When required parameters are missing
 * 
 * @example
 * // Basic payment automation
 * const result = await automatePayment({
 *     phoneNumber: '0811742234',
 *     pin: '123321',
 *     redirectUrl: 'https://payment.url'
 * });
 * 
 * @example
 * // Payment with custom settings
 * const result = await automatePayment({
 *     phoneNumber: '0811742234',
 *     pin: '123321',
 *     redirectUrl: 'https://payment.url',
 *     maxRetries: 3,
 *     retryDelay: 5000,
 *     headless: false
 * });
 */
async function automatePayment(params) {
    const {
        phoneNumber,
        pin,
        redirectUrl,
        maxRetries = 5,
        retryDelay = 2000,
        headless = true
    } = params;

    // Validate required parameters
    if (!phoneNumber || !pin || !redirectUrl) {
        throw new Error('phoneNumber, pin, and redirectUrl are required parameters');
    }

    // Initialize result object
    let result = {
        success: false,
        authCode: null,
        error: null,
        attempts: 0
    };

    // Configure browser launch options
    const launchOptions = {
        headless: headless,
        slowMo: 100,
        args: [
            '--disable-blink-features=AutomationControlled',
            '--disable-web-security',
            '--disable-features=VizDisplayCompositor'
        ]
    };

    // Set executable path based on environment
    if (process.env.CHROMIUM_PATH) {
        launchOptions.executablePath = process.env.CHROMIUM_PATH;
    } else if (process.env.CI) {
        launchOptions.executablePath = '/usr/bin/chromium-browser';
    }
    
    console.log('Chromium Executable Path:', launchOptions.executablePath || 'default');
    const browser = await playwright.chromium.launch(launchOptions);

    try {
        let lastError = null;

        // Payment retry loop
        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            result.attempts = attempt;
            console.log(`Payment attempt ${attempt}/${maxRetries}`);

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

                // Check if this is a retryable payment failure
                const shouldRetry = error.message.includes('Payment failed');

                if (!shouldRetry) {
                    console.log('Non-retryable error detected, stopping retries');
                    result.error = error.message;
                    break;
                }

                // Wait before retry (except on last attempt)
                if (attempt < maxRetries) {
                    console.log(`⏳ Payment failure detected. Waiting ${retryDelay}ms before retry...`);
                    await sleep(retryDelay);
                } else {
                    console.log('Max retries reached for payment failure');
                }
            }
        }

        // Set final error if all attempts failed
        if (!result.success) {
            result.error = lastError ? lastError.message : 'All retry attempts failed';
        }

    } catch (error) {
        result.error = error.message;
        console.error('Automation error:', error);
    } finally {
        await browser.close();
    }

    return result;
}

/**
 * Script execution handler for direct execution
 * Executes payment automation when script is run directly (not imported as module)
 */
if (require.main === module) {
    (async () => {
        try {
            // Example parameters for direct execution
            const params = {
                phoneNumber: process.env.DANA_PHONE || '0811742234',
                pin: process.env.DANA_PIN || '123321',
                redirectUrl: redirectUrl,
                maxRetries: 3,
                retryDelay: 2000,
                headless: true
            };
            
            console.log('🚀 Starting direct script execution...');
            const result = await automatePayment(params);
            
            // Output result as JSON for script parsing
            console.log('Final Result:', JSON.stringify(result, null, 2));
            
            // Exit with appropriate code
            process.exit(result.success ? 0 : 1);
            
        } catch (error) {
            console.error('Script execution failed:', error);
            process.exit(1);
        }
    })();
}

/**
 * Module exports for use in other scripts
 * @module AutomatePayment
 */
module.exports = { automatePayment };
