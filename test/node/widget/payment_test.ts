import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Import helper functions and assertions utilities
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { WidgetPaymentRequest } from 'dana-node/dist/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';

// Load environment variables from .env file
dotenv.config();

// Constants for test configuration
const titleCase = 'Payment';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');
const merchantId = process.env.MERCHANT_ID || '';
const apiUrl: string = '/rest/redirection/v1.0/debit/payment-host-to-host';
const baseUrl: string = 'https://api.sandbox.dana.id/';

// Initialize DANA API client with credentials from environment variables
const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});

// Utility function to generate a unique reference number for each payment request
function generateReferenceNo(): string {
    return uuidv4();
}

describe('Payment Tests', () => {
    // Test: Payment Success
    test('should successfully perform payment', async () => {
        // Define the case name for the test
        const caseName = 'PaymentSuccess';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the response against the expected result
            await assertResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            // If an error occurs, fail the test with the error message
            fail('Payment test failed: ' + (e.message || e));
        }
    });

    // Test: Payment Fail - Invalid Format
    test('should fail with invalid format', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailInvalidFormat';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
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
    test('should fail with missing or invalid mandatory field', async () => {
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
    test('should fail with invalid signature', async () => {
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

    // Test: Payment Fail - General Error
    test('should fail with general error', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailGeneralError';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
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

    // Test: Payment Fail - Transaction Not Permitted
    test('should fail with transaction not permitted', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailTransactionNotPermitted';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
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
    test('should fail with merchant not exist or status abnormal', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailMerchantNotExistOrStatusAbnormal';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID (use a non-existent merchant ID for testing)
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
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

    // Test: Payment Fail - Inconsistent Request (Skipped)
    test.skip('should fail with inconsistent request', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailInconsistentRequest';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Placeholder for inconsistent request test
            fail('Payment test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Payment Fail - Internal Server Error
    test('should fail with internal server error', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailInternalServerError';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
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

    // Test: Payment Fail - Exceeds Transaction Amount Limit
    test.skip('should fail with exceeds transaction amount limit', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailExceedsTransactionAmountLimit';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
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

    // Test: Payment Fail - Timeout (Skipped)
    test.skip('should fail with timeout', async () => {
        // Define the case name for the test
        const caseName = 'PaymentFailTimeout';
        // Get the request data from the JSON file based on the case name
        const requestData: WidgetPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number and set the merchant ID
        requestData.partnerReferenceNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the widget payment API with the request data
            const response = await dana.widgetApi.widgetPayment(requestData);
            // Assert the failure response against the expected result
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
            // Log the response for debugging
            console.log(`Response: ${JSON.stringify(response)}`);
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

    // Test: Payment Fail - Idempotent (Skipped)
    test.skip('should fail with idempotent', async () => {
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
