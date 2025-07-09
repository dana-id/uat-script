import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { QueryPaymentRequest } from 'dana-node/dist/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';

dotenv.config();

const titleCase = 'QueryOrder';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');
const baseUrl: string = 'https://api.sandbox.dana.id/';
const apiPath: string = '/v1.0/debit/status.htm';

const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});

function generateReferenceNo(): string {
    return uuidv4();
}

describe('QueryOrder Tests', () => {
    // Test: Query Order Success (Paid) (Skipped)
    test.skip('should successfully query order (paid)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessPaid';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Placeholder for query order (paid) test
            fail('QueryOrder test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Query Order Success (Initiated) (Skipped)
    test.skip('should successfully query order (initiated)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessInitiated';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Placeholder for query order (initiated) test
            fail('QueryOrder test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Query Order Success (Paying) (Skipped)
    test.skip('should successfully query order (paying)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessPaying';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Placeholder for query order (paying) test
            fail('QueryOrder test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Query Order Success (Cancelled) (Skipped)
    test.skip('should successfully query order (cancelled)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessCancelled';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Placeholder for query order (cancelled) test
            fail('QueryOrder test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Query Order Not Found (Skipped)
    test.skip('should fail with not found', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderNotFound';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Placeholder for query order not found test
            fail('QueryOrder test is a placeholder.');
        } catch (e: any) { }
    });

    // Test: Query Order Fail - Invalid Field
    test('should fail with invalid field', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderFailInvalidField';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number for the original partner reference number
        requestData.originalPartnerReferenceNo = generateReferenceNo();
        try {
            // Set custom headers with a valid timestamp
            const customHeaders: Record<string, string> = {
                'X-TIMESTAMP': new Date(Date.now() + 7 * 60 * 60 * 1000)
                    .toISOString()
                    .replace('T', ' ')
                    .replace(/\.\d{3}Z$/, '+07:00')
                    .replace(/-/g, '-')
                    .replace(/:/g, ':')
            };
            // Call the manual API request helper with the custom headers
            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders
            );
            // If no error is thrown, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 400) {
                // Assert the failure response for invalid field
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else if (e instanceof ResponseError) {
                // Fail if a different error is received
                fail("Expected not found failed but got status code " + e.status);
            }
        }
    });

    // Test: Query Order Fail - Invalid Mandatory Field
    test('should fail with invalid mandatory field', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderFailInvalidMandatoryField';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Generate a unique reference number for the original external ID
        requestData.originalExternalId = generateReferenceNo();
        try {
            // Set custom headers with an invalid (empty) timestamp
            const customHeaders: Record<string, string> = {
                'X-TIMESTAMP': ''
            };
            // Call the manual API request helper with the custom headers
            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders
            );
            // If no error is thrown, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 400) {
                // Assert the failure response for invalid mandatory field
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else if (e instanceof ResponseError) {
                // Fail if a different error is received
                fail("Expected not found failed but got status code " + e.status);
            }
        }
    });

    // Test: Query Order Fail - Unauthorized
    test('should fail with unauthorized', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderFailUnauthorized';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Set custom headers with an invalid signature
            const customHeaders: Record<string, string> = {
                'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
            };
            // Call the manual API request helper with the custom headers
            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders
            );
            // If no error is thrown, fail the test
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 401) {
                // Assert the failure response for unauthorized
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else if (e instanceof ResponseError) {
                // Fail if a different error is received
                fail("Expected not found failed but got status code " + e.status);
            }
        }
    });

    // Test: Query Order Fail - Transaction Not Found
    test('should fail with transaction not found', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderFailTransactionNotFound';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            // Call the queryPayment API with the request data
            const response = await dana.widgetApi.queryPayment(requestData);
            // Assert the failure response for transaction not found
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // Assert the failure response for transaction not found
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // Fail if a different error is received
                fail("Expected transaction not found failed but got status code " + e.status);
            }
        }
    });

    // Test: Query Order Fail - General Error
    test('should fail with general error', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderFailGeneralError';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        // Log the request data for debugging
        console.log(`Request Data: ${JSON.stringify(requestData)}`);
        try {
            // Call the queryPayment API with the request data
            const response = await dana.widgetApi.queryPayment(requestData);
            // Assert the failure response for general error
            await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response));
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // Assert the failure response for general error
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                // Fail if a different error is received
                fail("Expected general error failed but got status code " + e.status);
            }
        }
    });
});
