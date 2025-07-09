import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Import helper functions and assertion utilities
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { RefundOrderRequest } from 'dana-node/dist/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';

// Load environment variables from .env file
dotenv.config();

// Define constants for the test
const titleCase = 'RefundOrder';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');
const baseUrl: string = 'https://api.sandbox.dana.id/';
const apiPath: string = '/v1.0/debit/refund.htm';
const merchantId: string = process.env.MERCHANT_ID || '';

// Initialize DANA API client with credentials from environment variables
const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});

// Utility function to generate unique reference numbers for refunds
function generateReferenceNo(): string {
    return uuidv4();
}

describe('RefundOrder Tests', () => {
    //Test: Refund an order that is in process
    test('should successfully refund order (in process)', async () => {
        // Define the test case name
        const caseName = 'RefundInProcess';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;

        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
            // Assert the response against the expected data
            await assertResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // If an error occurs, assert the failure response
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If an unexpected error occurs, fail the test with the error message
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    test.skip('should fail with exceed payment amount', async () => {
        const caseName = 'RefundFailExceedPaymentAmount';
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            const response = await dana.widgetApi.refundOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order that is not allowed by agreement
    test('should fail not allowed by agreement', async () => {
        // Define the test case name
        const caseName = 'RefundFailNotAllowedByAgreement';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order that exceeds the refund window time
    test('should fail with exceed refund window time', async () => {
        // Define the test case name
        const caseName = 'RefundFailExceedRefundWindowTime';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order that has already been refunded multiple times
    test('should fail with multiple refund not allowed', async () => {
        // Define the test case name
        const caseName = 'RefundFailMultipleRefundNotAllowed';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    test.skip('should fail with duplicate request', async () => {
        const caseName = 'RefundFailDuplicateRequest';
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('RefundOrder test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with order not paid', async () => {
        const caseName = 'RefundFailOrderNotPaid';
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('RefundOrder test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Refund an order with illegal parameters
    test('should fail with parameter illegal', async () => {
        // Define the test case name
        const caseName = 'RefundFailParameterIllegal';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order with mandatory parameters invalid
    test('should fail with mandatory parameter invalid', async () => {
        // Define the test case name
        const caseName = 'RefundFailMandatoryParameterInvalid';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Set customer headers with an invalid timestamp
        const customerHeaders: Record<string, string> = {
            'X-TIMESTAMP': '', // Use an invalid timestamp for testing
        };
        try {
            // Execute the API request manually with invalid parameters
            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiPath,
                apiPath,
                requestData,
                customerHeaders,
            );
            // If the API call succeeds, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // If an error occurs, assert the failure response
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If an unexpected error occurs, fail the test with the error message
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order that does not exist
    test('should fail with order not exist', async () => {
        // Define the test case name
        const caseName = 'RefundFailOrderNotExist';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Set the merchant ID
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order with insufficient merchant balance
    test('should fail with insufficient merchant balance', async () => {
        // Define the test case name
        const caseName = 'RefundFailInsufficientMerchantBalance';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order with an invalid signature
    test('should fail with invalid signature', async () => {
        // Define the test case name
        const caseName = 'RefundFailInvalidSignature';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Set customer headers with an invalid signature
        const customerHeaders: Record<string, string> = {
            'X-SIGNATURE': 'invalid_signature', // Use an invalid signature for testing
        };
        try {
            // Execute the API request manually with invalid signature
            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiPath,
                apiPath,
                requestData,
                customerHeaders,
            );
            // If the API call succeeds, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // If an error occurs, assert the failure response
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // If an unexpected error occurs, fail the test with the error message
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    // Test: Refund an order that fails due to timeout
    test('should fail with timeout', async () => {
        // Define the test case name
        const caseName = 'RefundFailTimeout';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });

    test.skip('should fail with idempotent', async () => {
        const caseName = 'RefundFailIdempotent';
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('RefundOrder test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Refund an order with merchant status abnormal
    test('should fail with merchant status abnormal', async () => {
        // Define the test case name
        const caseName = 'RefundFailMerchantStatusAbnormal';
        // Get the request data from the JSON file
        const requestData: RefundOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique refund reference number and set the merchant ID
        requestData.partnerRefundNo = generateReferenceNo();
        requestData.merchantId = merchantId;
        try {
            // Call the refundOrder API with the request data
            const response = await dana.widgetApi.refundOrder(requestData);
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
                fail('RefundOrder test failed: ' + (e.message || e));
            }
        }
    });
});
