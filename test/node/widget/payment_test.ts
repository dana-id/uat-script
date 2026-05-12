/**
 * @fileoverview Widget Payment Test Suite for DANA Widget API Integration
 * 
 * This test suite validates the payment functionality of the DANA Widget API.
 * The Widget API provides a lightweight integration approach for payment processing
 * that can be embedded directly into merchant applications and websites.
 * 
 * Key Features Tested:
 * - Direct payment processing through Widget API
 * - Error handling for various payment failure scenarios
 * - Authentication and authorization validation
 * - Input validation and business rule enforcement
 * 
 * The Widget Payment API differs from the Payment Gateway API by providing:
 * - Simpler integration with fewer required fields
 * - Direct host-to-host communication
 * - Optimized for mobile and web widget implementations
 */

import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Import helper functions and assertion utilities for comprehensive testing
import { fail } from 'assert';
import { getRequest, generateFormattedDate } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { WidgetPaymentRequest } from 'dana-node/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const titleCase = 'Payment'; // Main test category identifier
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json'); // Widget test data file
const merchantId = process.env.MERCHANT_ID || ''; // Merchant configuration
const apiUrl: string = '/rest/redirection/v1.0/debit/payment-host-to-host'; // Widget payment endpoint
const baseUrl: string = 'https://api.sandbox.dana.id/'; // DANA API base URL
const userPhoneNumber = '083811223355';
const userPin = '181818';
const deviceId = 'deviceid123';

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',  // Partner ID from environment
    privateKey: process.env.PRIVATE_KEY || '',  // RSA private key for authentication
    origin: process.env.ORIGIN || '',            // Request origin URL
    env: process.env.ENV || 'sandbox',          // Environment (sandbox/production)
});

/**
 * Generates a unique reference number for each payment request
 * This ensures each test has a unique transaction identifier to avoid conflicts
 * 
 * @returns {string} A unique UUID string for payment reference
 */
function generateReferenceNo(): string {
    return uuidv4();
}

/**
 * Widget Payment Test Suite
 * 
 * This comprehensive test suite validates all aspects of the DANA Widget Payment API,
 * ensuring robust behavior across various payment scenarios and error conditions.
 */
describe('Payment Tests', () => {

    /**
     * Test Case: Successful Widget Payment
     * 
     * This test validates the complete successful payment flow through the Widget API.
     * It verifies that valid payment requests are processed correctly and return
     * appropriate success responses with required payment confirmation data.
     * 
     * @scenario Positive test case for successful payment processing
     * @paymentMethod Widget-based payment integration
     */
    test('PaymentSuccess - should successfully perform payment', async () => {
        // Define the test case name for data extraction
        const caseName = 'PaymentSuccess';

        // Extract request data from test data file based on case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);

        // Configure request with unique reference number and validUpTo timestamp
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.validUpTo = generateFormattedDate(15 * 60, 7);

        try {
            // Execute widget payment API call
            const response = await dana.widgetApi.widgetPayment(requestData);

            // Validate response against expected result from test data
            await assertResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If an error occurs, fail the test with the error message
            fail('Payment test failed: ' + (e.message || e));
        }
    });

    // Test: Payment Fail - Invalid Format
    test('PaymentFailInvalidFormat - should fail with invalid format', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailInvalidFormat';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the failure response against the expected result
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If a ResponseError occurs, assert the failure response
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - Missing or Invalid Mandatory Field
    test('PaymentFailMissingOrInvalidMandatoryField - should fail with missing or invalid mandatory field', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailMissingOrInvalidMandatoryField';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Set custom headers with missing or invalid mandatory fields
            const customHeaders: Record<string, string> = {
                'X-TIMESTAMP': '',
            };
            // Call the manual API request helper with the custom headers
            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiUrl,
                apiUrl,
                requestData,
                customHeaders
            );
            // If no error is thrown, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            // If a ResponseError occurs, assert the failure response
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - Invalid Signature
    test('PaymentFailInvalidSignature - should fail with invalid signature', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailInvalidSignature';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Set custom headers with an invalid signature
            const customHeaders: Record<string, string> = {
                'X-SIGNATURE': 'invalid_signature',
            };
            // Call the manual API request helper with the custom headers
            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiUrl,
                apiUrl,
                requestData,
                customHeaders
            );
            // If no error is thrown, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // If a ResponseError occurs, assert the failure response
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - General Error (5005400)
    test('PaymentFailGeneralError - should fail with general error', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailGeneralError';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the failure response against the expected result
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // If a ResponseError occurs, assert the failure response
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - Not Permitted (4035415) — Widget.json key is PaymentFailNotPermitted
    test('PaymentFailNotPermitted - should fail when transaction is not permitted', async () => {
        const caseName = 'PaymentFailNotPermitted';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the failure response against the expected result
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If a ResponseError occurs, assert the failure response
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - Merchant Not Exist or Status Abnormal
    test('PaymentFailMerchantNotExistOrStatusAbnormal - should fail with merchant not exist or status abnormal', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailMerchantNotExistOrStatusAbnormal';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID (use a non-existent merchant ID for testing)
        requestData.partnerReferenceNo = generateReferenceNo();
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the failure response against the expected result
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If a ResponseError occurs, assert the failure response
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - Inconsistent Request
    test('PaymentFailInconsistentRequest - should fail with inconsistent request', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailInconsistentRequest';

        // Extract request data from test data file based on case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);

        // Configure request with unique reference number and merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();

        try {
            await dana.widgetApi.widgetPayment(requestData);
            requestData.amount.currency = "IDR"; // Change currency to create inconsistency
            requestData.amount.value = "12000.00"; // Change value to create inconsistency
            // Execute widget payment API call
            const response = await dana.widgetApi.widgetPayment(requestData);

            // Validate response against expected result from test data
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If a ResponseError occurs, assert the failure response
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - Internal Server Error
    test('PaymentFailInternalServerError - should fail with internal server error', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailInternalServerError';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the failure response against the expected result
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If a ResponseError occurs, assert the failure response
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Payment Fail - Exceed Amount Limit (4035402) — Widget.json key is PaymentFailExceedAmountLimit
    test('PaymentFailExceedAmountLimit - should fail when amount exceeds limit', async () => {
        const caseName = 'PaymentFailExceedAmountLimit';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the failure response against the expected result
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If a ResponseError occurs, assert the failure response
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If another error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Not in the 10-code payment matrix: no PaymentFailTimeout fixture in Widget.json
    test.skip('PaymentFailTimeout - should fail with timeout', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailTimeout';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the response against the expected data
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
            // If the API call succeeds, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // If an error occurs, assert the failure response
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If an unexpected error occurs, fail the test with the error message
                fail('Payment test failed: ' + (e.message || e));
            }
        }
    });

    // Not in the 10-code payment matrix: placeholder only (no Widget.json fixture)
    test.skip('PaymentFailIdempotent - should fail with idempotent', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailIdempotent';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Placeholder for idempotent test
            fail('Payment test is a placeholder.');
        } catch (e: any) { }
    });
});
