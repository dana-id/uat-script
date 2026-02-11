/**
 * @fileoverview DANA Payment Gateway Refund Order API Integration Tests
 * 
 * This test suite provides comprehensive validation of the DANA Payment Gateway's
 * refund order functionality through automated integration testing. It covers
 * both positive scenarios (successful refunds) and extensive negative scenarios
 * (validation errors, business rule violations, authorization failures, and edge cases).
 * 
 * Key Features:
 * - Complete refund workflow testing including payment automation for setup
 * - Business rule validation (refund windows, amount limits, merchant status)
 * - Error handling validation (authentication, authorization, invalid parameters)
 * - Edge case testing (duplicate refunds, timeouts, insufficient funds)
 * - Idempotency testing for concurrent refund requests
 * - Manual API testing for authentication and validation bypass scenarios
 * 
 * Test Structure:
 * - Uses shared order creation (paid and unpaid) in beforeAll for test efficiency
 * - Validates refund functionality across different order states and conditions
 * - Tests comprehensive error conditions including business logic violations
 * - Provides detailed assertions using helper validation functions
 * 
 * Dependencies:
 * - DANA Node.js SDK for payment API interactions
 * - Browser automation scripts for payment completion during setup
 * - JSON test data files for request/response validation
 * - Helper utilities for API testing and response validation
 * 
 * @requires dana-node DANA Payment Gateway SDK
 * @requires uuid Unique identifier generation for test isolation
 * @requires dotenv Environment configuration management
 */

import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';

// Import helper functions
import { getRequest, automatePayment, generateFormattedDate } from '../helper/util';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CreateOrderByRedirectRequest, CreateOrderByApiRequest, RefundOrderRequest } from 'dana-node/payment_gateway/v1';
import { ResponseError } from 'dana-node';

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const titleCase = "RefundOrder";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json');

// Initialize DANA Payment Gateway client with environment credentials
const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox'
});

const merchantId = process.env.MERCHANT_ID || "";

// Shared test data for cross-test dependencies
let sharedOriginalPartnerReference: string;
let sharedOriginalPaidPartnerReference: string;
let sharedOriginalPaidPartnerReferenceforDuplicate: string;
let sharedOriginalPaidPartnerReferenceforIdempotent: string;

/**
 * Generates a unique partner reference number for test isolation
 * 
 * This utility function creates UUID-based reference numbers to ensure
 * each test operation has a unique identifier, preventing conflicts
 * between concurrent test runs and ensuring test isolation.
 * 
 * @returns {string} A unique UUID-based reference number
 */
function generatePartnerReferenceNo(): string {
    return uuidv4();
}


/**
 * DANA Payment Gateway Refund Order Integration Test Suite
 * 
 * This test suite validates the refund order functionality of the DANA Payment Gateway API.
 * It includes comprehensive testing of various refund scenarios and error conditions to ensure
 * robust refund processing capabilities and proper business rule enforcement.
 * 
 * Test Coverage:
 * - Successful refund processing for paid orders
 * - Business rule validation: refund windows, amount limits, merchant status
 * - Error handling: invalid parameters, unauthorized access, insufficient funds
 * - Edge cases: duplicate refunds, timeouts, concurrent requests
 * - Security validation: authentication and authorization testing
 */
describe('Payment Gateway - Refund Order Tests', () => {

    /**
     * Creates a basic unpaid order for negative testing scenarios
     * 
     * This helper function creates an order that remains in unpaid state,
     * which is used for testing refund scenarios that should fail when
     * attempting to refund an order that hasn't been paid.
     * 
     * @returns {Promise<void>} Resolves when unpaid order is successfully created
     */
    async function createOrder() {
        const createOrderRequestData: CreateOrderByRedirectRequest = getRequest<CreateOrderByRedirectRequest>(jsonPathFile, "CreateOrder", "CreateOrderRedirect");
        sharedOriginalPartnerReference = generatePartnerReferenceNo();
        createOrderRequestData.partnerReferenceNo = sharedOriginalPartnerReference
        createOrderRequestData.validUpTo = generateFormattedDate(1800); // Set validUpTo to 30 seconds from now
        await dana.paymentGatewayApi.createOrder(createOrderRequestData);
    }

    /**
     * Creates and completes payment for an order using browser automation
     * 
     * This helper function creates an order using the redirect flow and then
     * automates the payment process using browser automation. This results
     * in a PAID order that can be used for testing successful refund scenarios.
     * 
     * @returns {Promise<void>} Resolves when order is created and payment is completed
     * @throws {Error} If order creation or payment automation fails
     */
    async function createPaidOrder() {
        const createOrderRequestData: CreateOrderByRedirectRequest = getRequest<CreateOrderByRedirectRequest>(jsonPathFile, "CreateOrder", "CreateOrderRedirect");
        sharedOriginalPaidPartnerReference = generatePartnerReferenceNo();
        createOrderRequestData.partnerReferenceNo = sharedOriginalPaidPartnerReference;
        createOrderRequestData.validUpTo = generateFormattedDate(1800); // Set validUpTo to 6 minutes from now

        try {
            // Add delay before creating order to ensure system readiness
            await new Promise(resolve => setTimeout(resolve, 2000));
            console.log(`Creating order for payment automation...`);
            const response = await dana.paymentGatewayApi.createOrder(createOrderRequestData);

            if (response.webRedirectUrl) {
                console.log(`Order created successfully. WebRedirectUrl: ${response.webRedirectUrl}`);
                console.log(`Starting payment automation...`);

                // Automate the payment using the webRedirectUrl
                const automationResult = await automatePayment(
                    '083811223355', // phoneNumber
                    '181818',     // pin
                    response.webRedirectUrl, // redirectUrl from create order response
                    3,            // maxRetries
                    2000,         // retryDelay
                    true         // headless (set to true for CI/CD)
                );

                if (automationResult.success) {
                    console.log(`Payment automation successful`);
                } else {
                    console.log(`Payment automation failed: ${automationResult.error}`);
                    throw new Error(`Payment automation failed: ${automationResult.error}`);
                }
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

    /**
     * Test Setup: Create shared orders for testing different refund scenarios
     * 
     * This setup creates two different order states that will be used across
     * multiple test cases to validate refund functionality:
     * 1. Unpaid order - for testing refund attempts on non-paid orders (should fail)
     * 2. Paid order - for testing successful refund scenarios and business rules
     */
    beforeAll(async () => {
        try {
            await createOrder()

            console.log(`Shared order created with reference: ${sharedOriginalPartnerReference}`);
        } catch (e) {
            console.error('Failed to create shared order - tests cannot continue:', e);
        }

        try {
            await createPaidOrder()
            console.log(`Shared paid order created with reference: ${sharedOriginalPaidPartnerReference}`);
        } catch (e) {
            console.error('Failed to create shared paid order - tests cannot continue:', e);
        }
    });

    /**
     * Test Case: Successful Refund of Paid Order
     * 
     * This test validates the successful refund functionality for a paid order.
     * It uses a pre-created paid order (from beforeAll setup) that was completed
     * using browser automation, ensuring the refund can be processed successfully.
     * 
     * @scenario Positive test case for successful refund processing
     * @technique Uses shared paid order reference from test setup
     * @expectedResult HTTP 200 OK with successful refund response
     */
    test('RefundOrderValidScenario- should successfully refund an order', async () => {
        const refundOrderCaseName = "RefundOrderValidScenario";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;
        refundRequestData.partnerRefundNo = sharedOriginalPaidPartnerReference;

        const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
        console.log(`Refund Order Response: ${JSON.stringify(response)}`);

        await assertResponse(jsonPathFile, titleCase, refundOrderCaseName, response, { 'partnerReferenceNo': sharedOriginalPaidPartnerReference });
    });

    /**
     * Test Case: Refund Order In Progress Validation
     * 
     * This test validates the API's handling of refund attempts on orders that are
     * currently being processed. It ensures proper error handling when attempting
     * to refund an order that is in an intermediate processing state.
     * 
     * @scenario Negative test case for refund timing validation
     * @technique Tests refund attempt on order in processing state
     * @expectedError HTTP 400/403 due to order being in progress
     */
    test('RefundOrderInProgress - should fail to refund an order that is in process', async () => {
        const refundOrderCaseName = "RefundOrderInProgress";

        try {
            const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // Should not reach here if refund is truly in progress
            await assertResponse(jsonPathFile, titleCase, refundOrderCaseName, response, { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
        } catch (error) {
            if (error instanceof ResponseError) {
                // Assert expected failure for in-progress refund
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, error);
            } else {
                // Fail if unexpected error type
                fail(`Unexpected error type: ${error}`);
            }
        }
    });

    /**
     * Test Case: Refund Not Allowed by Merchant Agreement
     * 
     * This test validates the API's enforcement of merchant agreement restrictions
     * on refund operations. It ensures that refunds are properly blocked when
     * the merchant's agreement or configuration doesn't allow refund processing.
     * 
     * @scenario Negative test case for merchant agreement validation
     * @technique Tests refund attempt with merchant restrictions
     * @expectedError HTTP 403 Forbidden due to merchant agreement restrictions
     */
    test('RefundOrderNotAllowed - should fail when refund is not allowed by agreement', async () => {
        const refundOrderCaseName = "RefundOrderNotAllowed";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 403) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError && Number(e.status) !== 403) {
                fail("Expected forbidden failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Duplicate refund request with same parameters
     * Expected: HTTP 409 or response code 4045818 for inconsistent request
     */
    test('RefundOrderDuplicateRequest - should fail when sending a duplicate refund request', async () => {
        const refundOrderCaseName = "RefundOrderDuplicateRequest";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;
        refundRequestData.partnerRefundNo = sharedOriginalPaidPartnerReference;
        refundRequestData.refundAmount.currency = "IDR";
        refundRequestData.refundAmount.value = "10000.00"; // Set a valid refund amount

        // Second attempt: should fail with "4045818" (Inconsistent Request)
        try {
            const secondResponse = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            console.log(`Second refund response: ${JSON.stringify(secondResponse)}`);

            // Check if response contains expected error code for duplicate request
            if (secondResponse.responseCode === "4045818") {
                await assertResponse(jsonPathFile, titleCase, refundOrderCaseName, secondResponse,
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
                console.log("Duplicate refund request handled correctly with response code 4045818");
            } else {
                fail(`Expected response code 4045818 for duplicate request but got: ${secondResponse.responseCode}`);
            }
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 409) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Refund unpaid order
     * Expected: HTTP 400/403, error response for unpaid order
     */
    test('RefundOrderNotPaid - should fail when attempting to refund an unpaid order', async () => {
        const refundOrderCaseName = "RefundOrderNotPaid";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
        refundRequestData.partnerRefundNo = sharedOriginalPartnerReference;

        try {
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse));
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Refund with illegal/invalid parameters
     * Expected: HTTP 400, bad request error for illegal parameters
     */
    test('RefundOrderIllegalParameter - should fail when illegal parameters are provided', async () => {
        const refundOrderCaseName = "RefundOrderIllegalParameter";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 400) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                fail("Expected bad request failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Missing mandatory header (X-SIGNATURE)
     * Expected: HTTP 400, bad request error for missing mandatory parameter
     */
    test('RefundOrderInvalidMandatoryParameter - should fail when X-SIGNATURE header is missing', async () => {
        const refundOrderCaseName = "RefundOrderInvalidMandatoryParameter";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/payment-gateway/v1.0/debit/refund.htm';

            // Custom headers with malformed timestamp format
            const customHeaders: Record<string, string> = {
                'X-SIGNATURE': ''
            };

            await executeManualApiRequest(
                refundOrderCaseName,
                "POST",
                baseUrl + apiPath,
                apiPath,
                refundRequestData,
                customHeaders
            );

            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (Number(e.status) === 400) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                fail("Expected bad request failed but got status code " + e.status);
            } else {
                throw e;
            }
        }

    });

    /**
     * Test: Refund with invalid bill information
     * Expected: HTTP 400, bad request error for invalid bill data
     */
    test('RefundOrderInvalidBill - should fail when invalid bill information is provided', async () => {
        const refundOrderCaseName = "RefundOrderInvalidBill";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = "f77466d6-1825-4091";
        refundRequestData.partnerRefundNo = "f77466d6-1825-4091";
        console.log(`Refund Request Data: ${JSON.stringify(refundRequestData)}`);

        try {
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse));
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Merchant has insufficient funds for refund
     * Expected: HTTP 403, forbidden error for insufficient merchant balance
     */
    test('RefundOrderInsufficientFunds - should fail when merchant has insufficient funds for refund', async () => {
        const refundOrderCaseName = "RefundOrderInsufficientFunds";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 403) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                fail("Expected forbidden failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Unauthorized access with invalid signature
     * Expected: HTTP 401, unauthorized error for invalid credentials
     */
    test('RefundOrderUnauthorized - should fail when unauthorized access is attempted', async () => {
        const refundOrderCaseName = "RefundOrderUnauthorized";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/payment-gateway/v1.0/debit/refund.htm';

            const customHeaders: Record<string, string> = {
                'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
            };

            await executeManualApiRequest(
                refundOrderCaseName,
                "POST",
                baseUrl + apiPath,
                apiPath,
                refundRequestData,
                customHeaders
            );

            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (Number(e.status) === 401) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                fail("Expected unauthorized failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: API request timeout scenario
     * Expected: HTTP 500, gateway timeout error
     */
    test('RefundOrderTimeout - should handle timeout scenario gracefully', async () => {
        const refundOrderCaseName = "RefundOrderTimeout";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            fail("Expected a timeout error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 500) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                fail("Expected gateway timeout but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Merchant account status is abnormal
     * Expected: HTTP 404, not found error for abnormal merchant status
     */
    test('RefundOrderMerchantStatusAbnormal - should fail when merchant status is abnormal', async () => {
        const refundOrderCaseName = "RefundOrderMerchantStatusAbnormal";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 404) {
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                fail("Expected not found failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    /**
     * Test: Idempotent refund with concurrent requests
     * Expected: Consistent responses for identical concurrent refund requests
     */
    test('RefundOrderIdempotent - should succeed idempotently when refund is retried with same partnerRefundNo', async () => {
        const refundOrderCaseName = "RefundOrderIdempotent";
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;
        refundRequestData.partnerRefundNo = sharedOriginalPaidPartnerReference;

        // Second attempt: should succeed with same response
        const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
        console.log(`Refund Order Response: ${JSON.stringify(response)}`);

        await assertResponse(jsonPathFile, titleCase, refundOrderCaseName, response, { 'partnerReferenceNo': sharedOriginalPaidPartnerReference });
    });

});