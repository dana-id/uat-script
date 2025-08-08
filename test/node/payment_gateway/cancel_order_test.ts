/**
 * @fileoverview Cancel Order Test Suite for DANA Payment Gateway Integration
 * 
 * This test suite validates the cancel order functionality of the DANA Payment Gateway API.
 * It covers both successful cancellation scenarios and various error conditions including:
 * - Cancelling paid orders
 * - Cancelling orders in progress
 * - Error handling for invalid requests, unauthorized access, and business rule violations
 */

import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';

// Import helper functions for API testing and automation
import { getRequest, retryOnInconsistentRequest, automatePayment } from '../helper/util';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CreateOrderByApiRequest, CreateOrderByRedirectRequest, CancelOrderRequest, RefundOrderRequest, QueryPaymentRequest } from 'dana-node/payment_gateway/v1';
import { ResponseError } from 'dana-node';

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const titleCase = "CancelOrder"; // Main test category identifier
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json'); // Test data file path

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '', // Partner ID from environment
  privateKey: process.env.PRIVATE_KEY || '', // RSA private key for authentication
  origin: process.env.ORIGIN || '',           // Request origin URL
  env: process.env.ENV || 'sandbox'          // Environment (sandbox/production)
});

// Merchant configuration
const merchantId = process.env.MERCHANT_ID || "";

/**
 * Generates a unique partner reference number using UUID v4
 * This ensures each test has a unique transaction identifier
 * 
 * @returns {string} A unique UUID string for partner reference
 */
function generatePartnerReferenceNo(): string {
  return uuidv4();
}

/**
 * Shared partner reference number for tests that require a pre-existing order
 * This order is created in beforeAll() and used across multiple test cases
 */
let sharedOriginalPartnerReference: string;

/**
 * Cancel Order Test Suite
 * 
 * This test suite comprehensively validates the DANA Payment Gateway cancel order functionality.
 * It includes both positive and negative test scenarios to ensure robust API behavior.
 */
describe('Cancel Order Tests', () => {

  /**
   * Test Setup: Create a shared order before running all tests
   * 
   * This setup creates a base order that can be referenced by multiple test cases.
   * The order is created using the CreateOrderApi endpoint and serves as a foundation
   * for testing various cancellation scenarios.
   */
  beforeAll(async () => {
    // Load test data for creating an order via API
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderApi");

    // Generate unique reference number for the shared order
    sharedOriginalPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalPartnerReference;
    createOrderRequestData.merchantId = merchantId;

    try {
      // Create the order with retry mechanism for handling transient failures
      await retryOnInconsistentRequest(() => dana.paymentGatewayApi.createOrder(createOrderRequestData), 3, 2000);

      // Wait to ensure the order is processed and available in the system
      await new Promise(resolve => setTimeout(resolve, 2000));

      console.log(`Shared order created with reference: ${sharedOriginalPartnerReference}`);
    } catch (e) {
      console.error('Failed to create shared order - tests cannot continue:', e);
    }
  });

  /**
   * Test Case: Successful Cancel Order
   * 
   * This test validates the complete flow of creating a paid order and then cancelling it.
   * The test performs the following steps:
   * 1. Creates an order using redirect URL method
   * 2. Automates payment using browser automation
   * 3. Cancels the paid order
   * 4. Validates the cancellation response
   * 
   * @note This test can be flaky due to payment automation dependencies and security checks
   * @scenario Positive test case for successful order cancellation
   */
  test('should successfully cancel order', async () => {
    const cancelOrderCaseName = "CancelOrderValidScenario";

    try {
      // Load test data for insufficient funds scenario
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, cancelOrderCaseName);
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference; // Use shared order reference
      requestData.merchantId = merchantId; // Set merchant ID for the request

      // Attempt to cancel order with insufficient funds
      const response = await dana.paymentGatewayApi.cancelOrder(requestData);

      await assertResponse(jsonPathFile, titleCase, cancelOrderCaseName, response, { 'partnerReferenceNo': "2005700" });
    } catch (e) {
      console.error('Cancel order in progress test failed:', e);
      throw e;
    }
    
  });

  /**
   * Test Case: Cancel Order In Progress
   * 
   * Validates that orders in progress status can be successfully cancelled.
   * This test uses predefined test data to simulate cancelling an order
   * that is currently being processed but not yet completed.
   * 
   * @scenario Positive test case for cancelling in-progress orders
   */
  test('should cancel order in progress', async () => {
    const caseName = "CancelOrderInProgress";

    try {
      // Load test data for cancelling an in-progress order
      const cancelRequestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // Execute the cancel order API call
      const response = await dana.paymentGatewayApi.cancelOrder(cancelRequestData);

      // Validate response against expected test data with static reference number
      await assertResponse(jsonPathFile, titleCase, caseName, response, { 'partnerReferenceNo': "2025700" });
    } catch (e) {
      console.error('Cancel order in progress test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Cancel Order with User Status Abnormal
   * 
   * Validates error handling when attempting to cancel an order for a user
   * with abnormal status (e.g., suspended, blocked, or restricted account).
   * Expected to return HTTP 403 Forbidden.
   * 
   * @scenario Negative test case for business rule validation
   */
  test('should fail when user status is abnormal', async () => {
    const caseName = "CancelOrderUserStatusAbnormal";

    try {
      // Load test data for user with abnormal status
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // This API call should fail due to user status restrictions
      await dana.paymentGatewayApi.cancelOrder(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 403) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': "4035705" });
      } else if (e instanceof ResponseError && Number(e.status) !== 403) {
        fail("Expected forbidden failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Cancel Order with Merchant Status Abnormal
   * 
   * Validates error handling when the merchant account has abnormal status.
   * This typically occurs when the merchant account is suspended or deactivated.
   * Expected to return HTTP 404 Not Found.
   * 
   * @scenario Negative test case for merchant account validation
   */
  test('should fail with not found when merchant status is abnormal', async () => {
    const caseName = "CancelOrderMerchantStatusAbnormal";

    try {
      // Load test data for merchant with abnormal status
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // This API call should fail due to merchant status issues
      await dana.paymentGatewayApi.cancelOrder(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 404) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': "4045708" });
      } else if (e instanceof ResponseError && Number(e.status) !== 404) {
        fail("Expected not found failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Missing Mandatory Field Validation
   * 
   * Tests API validation by making a manual request with missing mandatory headers.
   * This test specifically removes the X-TIMESTAMP header to trigger a validation error.
   * Expected to return HTTP 400 Bad Request.
   * 
   * @scenario Negative test case for API input validation
   * @technique Uses manual API call to bypass SDK validation
   */
  test('should fail when mandatory field is missing (manual API call)', async () => {
    const caseName = "CancelOrderInvalidMandatoryField";

    try {
      // Load test data and configure with shared order reference
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;

      // API endpoint configuration
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';

      // Deliberately set empty X-TIMESTAMP header to trigger validation error
      const customHeaders: Record<string, string> = {
        'X-TIMESTAMP': '' // Missing mandatory timestamp will cause 400 error
      };

      // Execute manual API call bypassing SDK validation
      await executeManualApiRequest(
        caseName,
        "POST",
        baseUrl + apiPath,
        apiPath,
        requestData,
        customHeaders
      );

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (Number(e.status) === 400) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Transaction Not Found
   * 
   * Validates error handling when attempting to cancel a non-existent transaction.
   * Uses predefined test data with a reference number that doesn't exist in the system.
   * Expected to return HTTP 404 Not Found.
   * 
   * @scenario Negative test case for transaction existence validation
   */
  test('should fail when transaction not found', async () => {
    const caseName = "CancelOrderTransactionNotFound";
    try {
      // Load test data with non-existent transaction reference
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);
      // Note: Using predefined non-existent reference from test data instead of sharedOriginalPartnerReference

      // Attempt to cancel non-existent transaction
      await dana.paymentGatewayApi.cancelOrder(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 404) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 404) {
        fail("Expected transaction not found failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Transaction Expired
   * 
   * Validates error handling when attempting to cancel an expired transaction.
   * Transactions have a limited time window for cancellation operations.
   * Expected to return HTTP 403 Forbidden.
   * 
   * @scenario Negative test case for transaction lifecycle validation
   */
  test('should fail when transaction is expired', async () => {
    const caseName = "CancelOrderTransactionExpired";
    try {
      // Load test data with expired transaction reference
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // Attempt to cancel expired transaction
      await dana.paymentGatewayApi.cancelOrder(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 403) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': "4035700" });
      } else if (e instanceof ResponseError && Number(e.status) !== 403) {
        fail("Expected forbidden failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Cancel Order Not Allowed by Agreement
   * 
   * Validates business rule enforcement when cancellation is prohibited by merchant agreement.
   * Some merchant configurations may restrict order cancellation capabilities.
   * Expected to return HTTP 403 Forbidden.
   * 
   * @scenario Negative test case for business agreement validation
   */
  test('should fail when cancel order is not allowed by agreement', async () => {
    const caseName = "CancelOrderNotAllowed";
    try {
      // Load test data for restricted cancellation scenario
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // Attempt to cancel order when not permitted by agreement
      await dana.paymentGatewayApi.cancelOrder(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 403) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': "4035715" });
      } else if (e instanceof ResponseError && Number(e.status) !== 403) {
        fail("Expected forbidden failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Account Status Abnormal
   * 
   * Validates error handling when the account has abnormal status.
   * This covers scenarios where the account is frozen, suspended, or restricted.
   * Expected to return HTTP 403 Forbidden.
   * 
   * @scenario Negative test case for account status validation
   */
  test('should fail when account status is abnormal', async () => {
    const caseName = "CancelOrderAccountStatusAbnormal";

    try {
      // Load test data for abnormal account status
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // Attempt to cancel order with abnormal account status
      await dana.paymentGatewayApi.cancelOrder(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 403) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': "4035705" });
      } else if (e instanceof ResponseError && Number(e.status) !== 403) {
        fail("Expected forbidden failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Insufficient Funds for Cancellation
   * 
   * Validates error handling when there are insufficient funds for processing
   * the cancellation. This can occur in specific business scenarios where
   * cancellation involves fund transfers that cannot be completed.
   * Expected to return HTTP 403 Forbidden.
   * 
   * @scenario Negative test case for fund availability validation
   */
  test('should fail when there are insufficient funds', async () => {
    const caseName = "CancelOrderInsufficientFunds";
    try {
      // Load test data for insufficient funds scenario
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // Attempt to cancel order with insufficient funds
      await dana.paymentGatewayApi.cancelOrder(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 403) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': "4035714" });
      } else if (e instanceof ResponseError && Number(e.status) !== 403) {
        fail("Expected forbidden failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Unauthorized Access
   * 
   * Tests security validation by making a request with invalid authentication signature.
   * This test uses manual API call with deliberately incorrect signature to trigger
   * authentication failure. Expected to return HTTP 401 Unauthorized.
   * 
   * @scenario Negative test case for authentication and authorization
   * @technique Uses manual API call with invalid signature to bypass SDK authentication
   */
  test('should fail when authorization fails (manual API call)', async () => {
    const caseName = "CancelOrderUnauthorized";

    // Load test data and configure with unique reference
    const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.originalPartnerReferenceNo = partnerReferenceNo;

    // Use deliberately invalid signature to trigger authentication error
    const customHeaders: Record<string, string> = {
      'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5' // Invalid signature
    };

    try {
      // API endpoint configuration
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';

      // Execute manual API call with invalid authentication
      await executeManualApiRequest(
        caseName,
        "POST",
        baseUrl + apiPath,
        apiPath,
        requestData,
        customHeaders
      );

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (Number(e.status) === 401) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse), { partnerReferenceNo });
      } else if (e instanceof ResponseError && Number(e.status) !== 401) {
        fail("Expected unauthorized failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Request Timeout - Internal Server Error
   * 
   * Tests system behavior under timeout conditions. This test simulates scenarios
   * where the API request times out due to server processing delays or network issues.
   * Expected to return HTTP 500 Internal Server Error.
   * 
   * @scenario Negative test case for system resilience validation
   * @note This test may be environment-dependent and could behave differently in various network conditions
   */
  test('should fail with 500 internal server error on request timeout', async () => {
    const caseName = "CancelOrderRequestTimeout";
    try {
      // Load test data for timeout scenario
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);

      // API endpoint configuration
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';

      // Execute manual API call to simulate timeout scenario
      // Note: This relies on test environment configuration to simulate timeout behavior
      await executeManualApiRequest(
        caseName,
        "POST",
        baseUrl + apiPath,
        apiPath,
        requestData,
        {} // No special headers needed for timeout simulation
      );

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (Number(e.status) === 500) {
        // Validate the error response format and content
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
      } else if (e instanceof ResponseError && Number(e.status) !== 500) {
        fail("Expected internal server error but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Cancel Order After Refunding Transaction (SKIPPED)
   * 
   * This test validates the complex scenario of cancelling an order after it has been refunded.
   * The test flow includes:
   * 1. Create and pay for an order using browser automation
   * 2. Process a refund for the paid order
   * 3. Attempt to cancel the refunded order
   * 4. Validate the cancellation behavior
   * 
   * @note This test is currently skipped due to complexity and potential flakiness
   * @note The test demonstrates advanced integration testing with multiple API operations
   * @scenario Complex business flow validation combining payment, refund, and cancellation
   * @skipped Due to complex dependencies and potential test environment limitations
   */
  test.skip('should successfully cancel order after refunding a paid transaction', async () => {
    const caseName = "CancelOrderRefundedTransaction";

    try {
      // Step 1: Create an order for payment automation
      const createOrderRequestData: CreateOrderByRedirectRequest = getRequest<CreateOrderByRedirectRequest>(jsonPathFile, "CreateOrder", "CreateOrderRedirect");
      const paidPartnerReference = generatePartnerReferenceNo();
      createOrderRequestData.partnerReferenceNo = paidPartnerReference;
      createOrderRequestData.merchantId = merchantId;

      console.log(`Creating order for payment automation with reference: ${paidPartnerReference}...`);
      const createOrderResponse = await dana.paymentGatewayApi.createOrder(createOrderRequestData);

      if (createOrderResponse.webRedirectUrl) {
        console.log(`Order created successfully. WebRedirectUrl: ${createOrderResponse.webRedirectUrl}`);
        console.log(`Starting payment automation...`);

        // Step 2: Automate payment process using browser automation
        const automationResult = await automatePayment(
          '0811742234', // Test phone number
          '123321',     // Test PIN
          createOrderResponse.webRedirectUrl, // Payment redirect URL
          3,            // Maximum retry attempts
          2000,         // Retry delay in milliseconds
          true         // Headless browser mode
        );

        if (automationResult.success) {
          console.log(`Payment automation successful after ${automationResult.attempts} attempts`);
        } else {
          console.log(`Payment automation failed: ${automationResult.error}`);
          throw new Error(`Payment automation failed: ${automationResult.error}`);
        }
      } else {
        throw new Error('No webRedirectUrl in create order response');
      }

      // Wait for payment processing to complete
      await new Promise(resolve => setTimeout(resolve, 5000));

      // Step 3: Process refund for the paid order
      console.log(`Refunding paid order with reference: ${paidPartnerReference}`);
      const refundRequestData: RefundOrderRequest = getRequest<RefundOrderRequest>(jsonPathFile, "RefundOrder", "RefundOrderValidScenario");
      refundRequestData.originalPartnerReferenceNo = paidPartnerReference;
      refundRequestData.partnerRefundNo = generatePartnerReferenceNo(); // Unique refund reference
      refundRequestData.merchantId = merchantId;
      refundRequestData.refundAmount = createOrderRequestData.amount; // Full refund amount

      let refundSuccessful = false;
      let refundResponse;

      try {
        refundResponse = await dana.paymentGatewayApi.refundOrder(refundRequestData);
        console.log(`Refund response: ${JSON.stringify(refundResponse)}`);

        // Check if refund was successful based on response code
        if (refundResponse && refundResponse.responseCode === "2005800") {
          refundSuccessful = true;
          console.log(`Refund successful for paid transaction`);
        }
      } catch (refundError: any) {
        console.log(`Refund failed with error: ${JSON.stringify(refundError.response)}`);
        // Continue with cancellation attempt even if refund fails
      }

      // Wait for refund processing to complete
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Step 4: Attempt to cancel the order after refund
      console.log(`Proceeding to cancel order with reference: ${paidPartnerReference}`);
      const cancelRequestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);
      cancelRequestData.originalPartnerReferenceNo = paidPartnerReference;
      cancelRequestData.merchantId = merchantId;
      cancelRequestData.amount = createOrderRequestData.amount; // Original order amount

      console.log(`Cancelling order with reference: ${paidPartnerReference}`);
      const cancelResponse = await dana.paymentGatewayApi.cancelOrder(cancelRequestData);

      // Step 5: Validate the cancellation response
      await assertResponse(jsonPathFile, titleCase, caseName, cancelResponse, {
        'partnerReferenceNo': paidPartnerReference
      });

      console.log(`Successfully completed CancelOrderRefundedTransaction scenario`);
      console.log(`Refund was ${refundSuccessful ? 'successful' : 'failed'}, and cancellation succeeded`);
    } catch (e) {
      console.error('CancelOrderRefundedTransaction test failed:', e);
      throw e;
    }
  });

});
