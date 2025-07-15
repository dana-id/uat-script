import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';

// Import helper functions
import { getRequest, retryOnInconsistentRequest } from '../helper/util';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CreateOrderByApiRequest, CancelOrderRequest, RefundOrderRequest } from 'dana-node/payment_gateway/v1';
import { ResponseError } from 'dana-node';

// Load environment variables
dotenv.config();

// Setup constants
const titleCase = "RefundOrder";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json');

// Initialize DANA client
const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox'
});

const merchantId = process.env.MERCHANT_ID || "";
let sharedOriginalPartnerReference: string;
let sharedOriginalPaidPartnerReference: string;

// Utility function to generate unique reference numbers
function generatePartnerReferenceNo(): string {
    return uuidv4();
}


describe('Payment Gateway - Refund Order Tests', () => {

    async function createOrder() {
        const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderRedirect");
        sharedOriginalPartnerReference = generatePartnerReferenceNo();
        createOrderRequestData.partnerReferenceNo = sharedOriginalPartnerReference
        await dana.paymentGatewayApi.createOrder(createOrderRequestData);
    }

    async function createPaidOrder() {
        const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderNetworkPayPgOtherWallet");
        sharedOriginalPaidPartnerReference = generatePartnerReferenceNo();
        createOrderRequestData.partnerReferenceNo = sharedOriginalPaidPartnerReference;
        createOrderRequestData.amount.value = "50001.00"; // Set a valid amount to simulate a paid order
        await dana.paymentGatewayApi.createOrder(createOrderRequestData);
        await new Promise(resolve => setTimeout(resolve, 10000));
    }

    // Create a shared order before all tests
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

    // Test case for successful refund
    test.skip('should successfully refund an order', async () => {
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, "RefundOrderValidScenario");
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;
        refundRequestData.partnerRefundNo = sharedOriginalPaidPartnerReference;

        const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);
        console.log(`Refund Order Response: ${JSON.stringify(response)}`);
        
        await assertResponse(jsonPathFile, titleCase, "RefundOrderValidScenario", JSON.stringify(response.responseMessage), { 'partnerReferenceNo': sharedOriginalPaidPartnerReference });
    });


    // Test case for refund in progress
    test('RefundOrderInProgress - should fail to refund an order that is in process', async () => {
        const refundOrderCaseName = "RefundOrderInProgress";

        try {
            // Retrieve the refund request data for the "in progress" scenario
            const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // Assert that the response matches the expected successful scenario (should not reach here if refund is truly in progress)
            await assertResponse(jsonPathFile, titleCase, refundOrderCaseName, response, { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
        } catch (error) {
            if (error instanceof ResponseError) {
                // Assert that the error response matches the expected failure for an in-progress refund
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, error);
            } else {
                // Fail the test if an unexpected error type is thrown
                fail(`Unexpected error type: ${error}`);
            }
        }
    });

    // Test case for refund exceeding transaction amount limit
    test.skip('RefundOrderExceedsTransactionAmountLimit - should fail when refund amount exceeds transaction limit', async () => {
        const refundOrderCaseName = "RefundOrderExceedsTransactionAmountLimit";

        try {
            // Retrieve the refund request data for the "exceeds amount limit" scenario
            const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
            refundRequestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;
            refundRequestData.partnerRefundNo = sharedOriginalPaidPartnerReference; // Use the paid order reference

            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // Assert that the response matches the expected successful scenario (should not reach here if refund exceeds limit)
            await assertResponse(jsonPathFile, titleCase, refundOrderCaseName, response, { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // Assert that the error response matches the expected failure for exceeding amount limit
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse));
            } else {
                // Fail the test if an unexpected error type is thrown
                fail(`Unexpected error type: ${e.rawResponse}`);
            }
        }
    });

    // Test case for refund not allowed by agreement
    test('RefundOrderNotAllowed - should fail when refund is not allowed by agreement', async () => {
        const refundOrderCaseName = "RefundOrderNotAllowed";

        // Retrieve the refund request data for the "not allowed" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 403) {
                // Assert the error response matches expected format
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError && Number(e.status) !== 403) {
                // If the error is not a 403, fail the test
                fail("Expected forbidden failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for refund due to exceeding refund window time
    test('RefundOrderDueToExceedRefundWindowTime - should fail when refund window time is exceeded', async () => {
        const refundOrderCaseName = "RefundOrderDueToExceedRefundWindowTime";

        // Retrieve the refund request data for the "exceed refund window time" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 403) {
                // Assert the error response matches expected format
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError && Number(e.status) !== 403) {
                // If the error is not a 403, fail the test
                fail("Expected forbidden failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for multiple refunds on the same order
    test('RefundOrderMultipleRefund - should fail with forbidden error when attempting multiple refunds on the same order', async () => {
        const refundOrderCaseName = "RefundOrderMultipleRefund";

        // Retrieve the refund request data for the "multiple refund" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 403) {
                // Assert the error response matches expected forbidden error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError && Number(e.status) !== 403) {
                // If the error is not a 403, fail the test
                fail("Expected forbidden failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for duplicate refund request
    test.skip('RefundOrderDuplicateRequest - should fail when sending a duplicate refund request', async () => {
        const refundOrderCaseName = "RefundOrderDuplicateRequest";

        // Retrieve the refund request data for the "duplicate request" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;
        refundRequestData.partnerRefundNo = sharedOriginalPaidPartnerReference; // Use the same reference to simulate a duplicate request

        // First attempt: should succeed (or be handled as a valid refund)
        let firstResponse;
        try {
            firstResponse = await dana.paymentGatewayApi.refundOrder(refundRequestData);
        } catch (e: any) {
            // If the first call fails, fail the test
            fail("First refund attempt failed unexpectedly: " + (e.rawResponse ? JSON.stringify(e.rawResponse) : e));
        }

        // Second attempt: should fail with 409 Conflict
        try {
            await dana.paymentGatewayApi.refundOrder(refundRequestData);
            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded on duplicate request");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 409) {
                // Assert the error response matches expected conflict error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                // If the error is not a 409, fail the test
                fail("Expected conflict failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for refunding an order that has not been paid
    test('RefundOrderNotPaid - should fail when attempting to refund an unpaid order', async () => {
        const refundOrderCaseName = "RefundOrderNotPaid";

        // Retrieve the refund request data for the "not paid" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
        refundRequestData.partnerRefundNo = sharedOriginalPartnerReference;
        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // Assert the error response matches expected forbidden error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse));
            } else {
                throw e;
            }
        }
    });

    // Test case for refund with illegal parameter
    test('RefundOrderIllegalParameter - should fail when illegal parameters are provided', async () => {
        const refundOrderCaseName = "RefundOrderIllegalParameter";

        // Retrieve the refund request data for the "illegal parameter" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 400) {
                // Assert the error response matches expected bad request error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                // If the error is not a 400, fail the test
                fail("Expected bad request failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for refund with invalid mandatory parameter (missing X-TIMESTAMP)
    test('RefundOrderInvalidMandatoryParameter - should fail when X-TIMESTAMP header is missing', async () => {
        const refundOrderCaseName = "RefundOrderInvalidMandatoryParameter";

        // Retrieve the refund request data for the "invalid mandatory parameter" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Define base URL and API path
            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';

            // Define custom headers without X-TIMESTAMP to trigger mandatory field error
            const customHeaders: Record<string, string> = {
                'X-TIMESTAMP': ''
            };

            // Make direct API call - this should fail
            await executeManualApiRequest(
                refundOrderCaseName,
                "POST",
                baseUrl + apiPath,
                apiPath,
                refundRequestData,
                customHeaders
            );

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (Number(e.status) === 400) {
                // Assert the error response matches expected bad request error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                // If the error is not a 400, fail the test
                fail("Expected bad request failed but got status code " + e.status);
            } else {
                throw e;
            }
        }

    });

    // Test case for refund with invalid bill parameter
    test.skip('RefundOrderInvalidBill - should fail when invalid bill information is provided', async () => {
        const refundOrderCaseName = "RefundOrderInvalidBill";

        // Retrieve the refund request data for the "invalid bill" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        refundRequestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
        refundRequestData.partnerRefundNo = "invalid-bill-refund-" + uuidv4(); // Set a unique refund reference
        console.log(`Refund Request Data: ${JSON.stringify(refundRequestData)}`);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // Assert the error response matches expected bad request error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse));
            } else {
                throw e;
            }
        }
    });

    // Test case for refund with insufficient funds
    test('RefundOrderInsufficientFunds - should fail when merchant has insufficient funds for refund', async () => {
        const refundOrderCaseName = "RefundOrderInsufficientFunds";

        // Retrieve the refund request data for the "insufficient funds" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 403) {
                // Assert the error response matches expected forbidden error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                // If the error is not a 403, fail the test
                fail("Expected forbidden failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for refund with unauthorized access
    test('RefundOrderUnauthorized - should fail when unauthorized access is attempted', async () => {
        const refundOrderCaseName = "RefundOrderUnauthorized";

        // Retrieve the refund request data for the "unauthorized" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Define base URL and API path
            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';

            // Define custom headers with invalid or missing authorization to trigger unauthorized error
            const customHeaders: Record<string, string> = {
                'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
            };

            // Make direct API call - this should fail
            await executeManualApiRequest(
                refundOrderCaseName,
                "POST",
                baseUrl + apiPath,
                apiPath,
                refundRequestData,
                customHeaders
            );

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (Number(e.status) === 401) {
                // Assert the error response matches expected unauthorized error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                // If the error is not a 401, fail the test
                fail("Expected unauthorized failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for refund order timeout
    test('RefundOrderTimeout - should handle timeout scenario gracefully', async () => {
        const refundOrderCaseName = "RefundOrderTimeout";

        // Retrieve the refund request data for the "timeout" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected for a timeout scenario
            fail("Expected a timeout error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 500) {
                // Assert the error response matches expected gateway timeout error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                // If the error is not a 500, fail the test
                fail("Expected gateway timeout but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for refund when merchant status is abnormal
    test('RefundOrderMerchantStatusAbnormal - should fail when merchant status is abnormal', async () => {
        const refundOrderCaseName = "RefundOrderMerchantStatusAbnormal";

        // Retrieve the refund request data for the "merchant status abnormal" scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // If the API call succeeds, this is unexpected
            fail("Expected an error but the API call succeeded");
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 404) {
                // Assert the error response matches expected not found error
                await assertFailResponse(jsonPathFile, titleCase, refundOrderCaseName, JSON.stringify(e.rawResponse),
                    { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
            } else if (e instanceof ResponseError) {
                // If the error is not a 404, fail the test
                fail("Expected not found failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    // Test case for idempotent refund
    test.skip('RefundOrderIdempotent - should succeed idempotently when refund is retried with same partnerRefundNo', async () => {
        const refundOrderCaseName = "RefundOrderIdempotent";

        // Retrieve the refund request data for the idempotent scenario
        const refundRequestData = getRequest<RefundOrderRequest>(jsonPathFile, titleCase, refundOrderCaseName);
        // Optionally, set unique reference if needed
        // refundRequestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
        // refundRequestData.partnerRefundNo = sharedOriginalPartnerReference;

        try {
            // Attempt to refund the order via the API
            const response = await dana.paymentGatewayApi.refundOrder(refundRequestData);

            // Assert that the response matches the expected idempotent scenario
            await assertResponse(jsonPathFile, titleCase, refundOrderCaseName, response, { 'partnerReferenceNo': refundRequestData.originalPartnerReferenceNo });
        } catch (e: any) {
            if (e instanceof ResponseError) {
                // If API returns error, fail the test
                fail(`Expected idempotent refund to succeed but got error: ${e.status}`);
            } else {
                throw e;
            }
        }
    });

});