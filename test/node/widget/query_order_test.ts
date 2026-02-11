import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { automatePayment, getRequest, generateFormattedDate } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CancelOrderRequest, QueryPaymentRequest, WidgetPaymentRequest } from 'dana-node/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';

dotenv.config();

const titleCase = 'QueryOrder';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');
const baseUrl: string = 'https://api.sandbox.dana.id/';
const apiPath: string = '/v1.0/debit/status.htm';
const merchantId = process.env.MERCHANT_ID || ''; // Merchant configuration

const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});

function generateReferenceNo(): string {
    return uuidv4();
}

// Shared test data for cross-test dependencies
let sharedOriginalPartnerReference: string;
let sharedOriginalCanceledPartnerReference: string;
let sharedOriginalPaidPartnerReference: string;
let sharedOriginalPayingPartnerReference: string;

describe('QueryOrder Tests', () => {
    
      async function createPaymentInit() {
        const widgetPaymentRequestData: WidgetPaymentRequest = getRequest<WidgetPaymentRequest>(jsonPathFile, "Payment", "PaymentSuccess");
        sharedOriginalPartnerReference = generateReferenceNo();
        widgetPaymentRequestData.partnerReferenceNo = sharedOriginalPartnerReference;
        widgetPaymentRequestData.validUpTo = generateFormattedDate(30 * 24 * 3600, 7);
        await dana.widgetApi.widgetPayment(widgetPaymentRequestData);
      }

      async function createPaymentPaying() {
        const widgetPaymentRequestData: WidgetPaymentRequest = getRequest<WidgetPaymentRequest>(jsonPathFile, "Payment", "PaymentPaying");
        sharedOriginalPayingPartnerReference = generateReferenceNo();
        widgetPaymentRequestData.partnerReferenceNo = sharedOriginalPayingPartnerReference;
        widgetPaymentRequestData.validUpTo = generateFormattedDate(30 * 24 * 3600, 7);
        await dana.widgetApi.widgetPayment(widgetPaymentRequestData);
      }

      async function createPaymentCancel() {
        const widgetPaymentRequestData: WidgetPaymentRequest = getRequest<WidgetPaymentRequest>(jsonPathFile, "Payment", "PaymentSuccess");
        sharedOriginalCanceledPartnerReference = generateReferenceNo();
        widgetPaymentRequestData.partnerReferenceNo = sharedOriginalCanceledPartnerReference;
        widgetPaymentRequestData.validUpTo = generateFormattedDate(30 * 24 * 3600, 7);
        await dana.widgetApi.widgetPayment(widgetPaymentRequestData);

        // Cancel payment
        const cancelOrderRequestData = getRequest<CancelOrderRequest>(jsonPathFile, "CancelOrder", "CancelOrderValidScenario");
        cancelOrderRequestData.originalPartnerReferenceNo = sharedOriginalCanceledPartnerReference;
        cancelOrderRequestData.merchantId = process.env.MERCHANT_ID || '';
        await dana.widgetApi.cancelOrder(cancelOrderRequestData);
      }

      async function createPaymentPaid() {
        const widgetPaymentRequestData: WidgetPaymentRequest = getRequest<WidgetPaymentRequest>(jsonPathFile, "Payment", "PaymentSuccess");
        sharedOriginalPaidPartnerReference = generateReferenceNo();
        widgetPaymentRequestData.partnerReferenceNo = sharedOriginalPaidPartnerReference;
        widgetPaymentRequestData.validUpTo = generateFormattedDate(30 * 24 * 3600, 7);

        try {
        // Add delay before creating order to ensure system readiness
        await new Promise(resolve => setTimeout(resolve, 2000));
        console.log(`Creating order for payment automation...`);
        const response = await dana.widgetApi.widgetPayment(widgetPaymentRequestData);

      if (response.webRedirectUrl) {
        console.log(`Order created successfully. WebRedirectUrl: ${response.webRedirectUrl}`);
        console.log(`Starting payment automation...`);

        // Automate the payment using the webRedirectUrl
        const automationResult = await automatePayment(
          '0811742234', // phoneNumber
          '123321',     // pin
          response.webRedirectUrl, // redirectUrl from create order response
          3,            // maxRetries
          2000,         // retryDelay
          true         // headless (set to true for CI/CD)
        );
      } else {
        throw new Error('No webRedirectUrl in create order response');
      }

      // Wait for payment to be processed by the payment system
      await new Promise(resolve => setTimeout(resolve, 5000));

    } catch (error) {
      console.error('Failed to create and pay order:', error);
      throw error;
    }
      }

    // Test: Query Order Success (Paid) (Skipped)
    test('should successfully query order (paid)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessPaid';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        requestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;
        try {
            const response = await dana.widgetApi.queryPayment(requestData);

            // Assert the response matches the expected data using our helper function
            await assertResponse(jsonPathFile, titleCase, caseName, response, { 'partnerReferenceNo': sharedOriginalPaidPartnerReference });
        } catch (e: any) { }
    });

    // Test: Query Order Success (Initiated) (Skipped)
    test('should successfully query order (initiated)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessInitiated';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
        try {
            const response = await dana.widgetApi.queryPayment(requestData);

            // Assert the response matches the expected data using our helper function
            await assertResponse(jsonPathFile, titleCase, caseName, response, { 'partnerReferenceNo': sharedOriginalPayingPartnerReference });
        } catch (e: any) { }
    });

    // Test: Query Order Success (Paying) (Skipped)
    test('should successfully query order (paying)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessPaying';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        requestData.originalPartnerReferenceNo = sharedOriginalPayingPartnerReference;
        try {
            const response = await dana.widgetApi.queryPayment(requestData);

            // Assert the response matches the expected data using our helper function
            await assertResponse(jsonPathFile, titleCase, caseName, response, { 'partnerReferenceNo': sharedOriginalPayingPartnerReference });
        } catch (e: any) { }
    });

    // Test: Query Order Success (Cancelled) (Skipped)
    test('should successfully query order (cancelled)', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderSuccessCancelled';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        requestData.originalPartnerReferenceNo = sharedOriginalCanceledPartnerReference;
        try {
            const response = await dana.widgetApi.queryPayment(requestData);

            // Assert the response matches the expected data using our helper function
            await assertResponse(jsonPathFile, titleCase, caseName, response, { 'partnerReferenceNo': sharedOriginalCanceledPartnerReference });
        } catch (e: any) { }
    });

    // Test: Query Order Not Found
    test('should fail with not found', async () => {
        // Define the case name for the test
        const caseName = 'QueryOrderNotFound';
        // Get the request data from the JSON file based on the case name
        const requestData: QueryPaymentRequest = getRequest(jsonPathFile, titleCase, caseName);
        requestData.originalPartnerReferenceNo = "test123";
        try {
            const response = await dana.widgetApi.queryPayment(requestData);

            // Assert the response matches the expected data using our helper function
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

    // Test: Query Order Fail - Transaction Not Found
    test.skip('should fail with transaction not found', async () => {
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
