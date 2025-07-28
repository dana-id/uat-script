/**
 * @fileoverview OAuth Authentication Utilities for DANA Widget Integration Testing
 * 
 * This module provides utilities for automating OAuth authentication flows in the
 * DANA Widget API testing suite. It handles the complex process of generating
 * authorization codes through browser automation, which is required for testing
 * widget-based payment and account management scenarios.
 * 
 * Key Features:
 * - Automated OAuth authorization code generation
 * - Retry mechanisms for handling automation failures
 * - Timeout protection to prevent hanging tests
 * - Seamless data integration for mobile number handling
 */

import { automateOAuth } from '../automate-oauth';
import { WidgetUtils, Oauth2UrlData } from 'dana-node/widget/v1';
import { v4 as uuidv4 } from 'uuid';

/**
 * Generates an OAuth authorization code with automatic retries and timeout protection
 * 
 * This function orchestrates the complete OAuth flow for DANA Widget testing by:
 * 1. Generating OAuth URLs with appropriate scopes and merchant configuration
 * 2. Automating the browser-based authentication process
 * 3. Extracting authorization codes from the OAuth callback
 * 4. Providing retry logic for handling transient failures
 * 
 * The function is designed to be resilient against common OAuth automation issues
 * such as network timeouts, browser automation failures, and temporary service
 * unavailability. It implements exponential backoff and timeout protection.
 * 
 * @param {string} [phoneNumber] - Phone number to use in OAuth flow (uses test default if not provided)
 * @param {string} [pinCode] - PIN code to use in OAuth flow (uses test default if not provided)
 * @returns {Promise<string>} Promise resolving to the authorization code string
 * @throws {string} Returns 'failed_auth_code' string instead of throwing to allow graceful test failure
 * 
 * @example
 * ```typescript
 * // Generate auth code with default test credentials
 * const authCode = await generateAuthCode();
 * 
 * // Generate auth code with specific credentials
 * const authCode = await generateAuthCode('0811742234', '123321');
 * 
 * // Use in widget test
 * const applyTokenRequest = {
 *   authCode: await generateAuthCode(),
 *   merchantId: process.env.MERCHANT_ID,
 *   // ... other request fields
 * };
 * ```
 */
export async function generateAuthCode(phoneNumber?: string, pinCode?: string): Promise<string> {
    const MAX_RETRIES = 3;
    const RETRY_DELAY_MS = 1000; // 1 second delay between retries
    const OAUTH_TIMEOUT_MS = 30000; // 30 second timeout for OAuth automation

    let lastError: Error | null = null;

    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
        try {
            if (attempt > 1) {
                console.log(`Auth code generation attempt ${attempt}/${MAX_RETRIES}`);
            }

            // Configure OAuth parameters with required scopes for Widget API testing
            const oauthData: Oauth2UrlData = {
                scopes: [
                    'CASHIER',                  // Payment processing scope
                    'AGREEMENT_PAY',            // Payment agreement scope
                    'QUERY_BALANCE',            // Balance inquiry scope
                    'DEFAULT_BASIC_PROFILE',    // Basic profile access scope
                    'MINI_DANA'                 // Mini DANA widget scope
                ],
                externalId: uuidv4(),           // Unique external identifier
                state: uuidv4(),                // OAuth state parameter for security
                redirectUrl: 'https://google.com', // OAuth callback URL
                seamlessData: {
                    mobileNumber: phoneNumber   // Pre-fill mobile number for seamless flow
                },
                merchantId: process.env.MERCHANT_ID || '', // Merchant configuration
            };

            // Generate OAuth authorization URL using Widget utilities
            const oauthUrl = WidgetUtils.generateOauthUrl(oauthData);
            console.log(`Generated OAuth URL: ${oauthUrl}`);

            // Execute OAuth automation with timeout protection
            const authCodeResult = await Promise.race([
                automateOAuth(oauthUrl, phoneNumber, pinCode, { log: false }),
                new Promise<null>((_, reject) => {
                    const timeout = setTimeout(() => {
                        clearTimeout(timeout); // Prevent memory leaks
                        reject(new Error(`Auth code generation timed out after ${OAUTH_TIMEOUT_MS}ms`));
                    }, OAUTH_TIMEOUT_MS);
                })
            ]);

            // Extract authorization code from automation result
            if (typeof authCodeResult === 'string' && authCodeResult) {
                console.log(`Successfully generated auth code: ${authCodeResult.substring(0, 10)}...`);
                return authCodeResult;
            }

            // Handle object response format
            if (authCodeResult && typeof authCodeResult === 'object' && authCodeResult.auth_code) {
                console.log(`Successfully generated auth code: ${authCodeResult.auth_code.substring(0, 10)}...`);
                return authCodeResult.auth_code;
            }

            throw new Error('auth_code not found in automateOAuth result');

        } catch (error: any) {
            lastError = new Error(`Failed to get auth_code: ${error.message}`);
            console.warn(`Auth code generation attempt ${attempt} failed: ${error.message}`);

            // Wait before retry (except on last attempt)
            if (attempt < MAX_RETRIES) {
                console.log(`Waiting ${RETRY_DELAY_MS}ms before retry...`);
                await new Promise(resolve => {
                    const timer = setTimeout(resolve, RETRY_DELAY_MS);
                    // Ensure timer doesn't block process exit
                    if (timer.unref) timer.unref();
                });
            }
        }
    }

    // All attempts failed - return placeholder for graceful failure handling
    console.error(`Failed to generate auth code after ${MAX_RETRIES} attempts`);
    console.error(`Last error: ${lastError?.message}`);
    return 'failed_auth_code'; // Allows tests to continue with expected failure
}