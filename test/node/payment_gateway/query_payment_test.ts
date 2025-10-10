/**
 * @fileoverview DANA Payment Gateway Query Payment API Integration Tests
 * 
 * This test suite provides comprehensive validation of the DANA Payment Gateway's
 * query payment functionality through automated integration testing. It covers
 * both positive scenarios (successful queries for different payment states) and
 * negative scenarios (validation errors, authorization failures, and edge cases).
 * 
 * Key Features:
 * - Payment status validation for PAID, INIT, and CANCELLED orders
 * - Error handling validation (authentication, authorization, missing fields)
 * - Payment automation using browser automation for redirect scenarios
 * - Manual API testing for edge cases and error conditions
 * - Shared test data setup for optimal test performance
 * 
 * Test Structure:
 * - Uses shared order creation in beforeAll for test efficiency
 * - Validates query functionality across different payment states
 * - Tests error conditions including malformed requests and unauthorized access
 * - Provides comprehensive assertions using helper validation functions
 * 
 * Dependencies:
 * - DANA Node.js SDK for payment API interactions
 * - Browser automation scripts for payment completion
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
import { CreateOrderByRedirectRequest, CreateOrderByApiRequest, QueryPaymentRequest, CancelOrderRequest } from 'dana-node/payment_gateway/v1';
import { ResponseError } from 'dana-node';

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const titleCase = "QueryPayment";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json');

// Merchant configuration from environment variables
const merchantId = process.env.MERCHANT_ID || "216620010016033632482";

// Initialize DANA Payment Gateway client with environment credentials
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

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

// Shared test data for cross-test dependencies
let sharedOriginalPartnerReference: string;
let sharedOriginalCanceledPartnerReference: string;
let sharedOriginalPaidPartnerReference: string;

/**
 * DANA Payment Gateway Query Payment Integration Test Suite
 * 
 * This test suite validates the query payment functionality of the DANA Payment Gateway API.
 * It includes comprehensive testing of various payment states and error conditions to ensure
 * robust payment status tracking capabilities.
 * 
 * Test Coverage:
 * - Query payment for different states: PAID, INIT, CANCELLED
 * - Error handling: invalid formats, missing fields, authorization failures
 * - Edge cases: transaction not found, general errors
 * - Security validation: authentication and authorization testing
 */
describe('Query Payment Tests', () => {

  /**
   * Creates a basic order for query testing purposes
   * 
   * This helper function creates an unpaid order using the API endpoint,
   * which will remain in INIT status for testing query functionality
   * on pending transactions.
   * 
   * @returns {Promise<void>} Resolves when order is successfully created
   */
  async function createOrder() {
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderApi");
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
   * in a PAID order that can be queried for testing successful payment status.
   * 
   * @returns {Promise<void>} Resolves when order is created and payment is completed
   * @throws {Error} If order creation or payment automation fails
   */
  async function createPaidOrder() {
    const createOrderRequestData: CreateOrderByRedirectRequest = getRequest<CreateOrderByRedirectRequest>(jsonPathFile, "CreateOrder", "CreateOrderRedirect");
    sharedOriginalPaidPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalPaidPartnerReference;
    createOrderRequestData.merchantId = merchantId;
    createOrderRequestData.validUpTo = generateFormattedDate(1800); // Set validUpTo to 30 seconds from now

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
          '0811742234', // phoneNumber
          '123321',     // pin
          response.webRedirectUrl, // redirectUrl from create order response
          3,            // maxRetries
          2000,         // retryDelay
          true         // headless (set to true for CI/CD)
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

      // Wait for payment to be processed by the payment system
      await new Promise(resolve => setTimeout(resolve, 5000));

    } catch (error) {
      console.error('Failed to create and pay order:', error);
      throw error;
    }
  }

  /**
   * Creates an order and immediately cancels it for testing CANCELLED status
   * 
   * This helper function creates an order and then cancels it to provide
   * a CANCELLED order for testing query functionality on canceled transactions.
   * 
   * @returns {Promise<void>} Resolves when order is created and canceled
   */
  async function createCanceledOrder() {
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderRedirect");
    sharedOriginalCanceledPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalCanceledPartnerReference;
    createOrderRequestData.validUpTo = generateFormattedDate(1800); // Set validUpTo to 30 seconds from now
    await dana.paymentGatewayApi.createOrder(createOrderRequestData);
    // await new Promise(resolve => setTimeout(resolve, 4000));

    const cancelOrderRequestData = getRequest<CancelOrderRequest>(jsonPathFile, "CancelOrder", "CancelOrderValidScenario");
    cancelOrderRequestData.originalPartnerReferenceNo = sharedOriginalCanceledPartnerReference;
    await dana.paymentGatewayApi.cancelOrder(cancelOrderRequestData);
    // await new Promise(resolve => setTimeout(resolve, 10000));
  }

  /**
   * Test Setup: Create shared orders for testing different payment states
   * 
   * This setup creates three different order states that will be used across
   * multiple test cases to validate query payment functionality:
   * 1. Basic order (INIT status) - for testing pending payments
   * 2. Paid order (PAID status) - for testing successful payments  
   * 3. Canceled order (CANCELLED status) - for testing canceled payments
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

    try {
      await createCanceledOrder()

      console.log(`Shared canceled order created with reference: ${sharedOriginalCanceledPartnerReference}`);
    } catch (e) {
      console.error('Failed to create shared canceled order - tests cannot continue:', e);
    }
  });

  /**
   * Test Case: Query Payment with PAID Status
   * 
   * This test validates the query payment functionality for orders that have been
   * successfully paid. It uses a pre-created paid order (from beforeAll setup)
   * that was completed using browser automation.
   * 
   * @scenario Positive test case for querying completed payments
   * @technique Uses shared paid order reference from test setup
   * @expectedResult HTTP 200 OK with payment status PAID
   * @note This test can be flaky as it depends on payment automation which may fail due to security reasons
   */
  test('should successfully query payment with status paid (PAID)', async () => {
    const queryPaymentCaseName = "QueryPaymentPaidOrder";

    try {
      // Query the pre-created paid order using its reference number
      const queryRequestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, queryPaymentCaseName);
      queryRequestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;

      const response = await dana.paymentGatewayApi.queryPayment(queryRequestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, queryPaymentCaseName, response, { 'partnerReferenceNo': sharedOriginalPaidPartnerReference });
    } catch (e) {
      console.error('Query payment test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Query Payment with INIT Status
   * 
   * This test validates the query payment functionality for orders that have been
   * created but not yet paid. It uses a pre-created order (from beforeAll setup)
   * that remains in pending state.
   * 
   * @scenario Positive test case for querying pending payments
   * @technique Uses shared unpaid order reference from test setup
   * @expectedResult HTTP 200 OK with payment status INIT
   */
  test('should successfully query payment with status created but not paid (INIT)', async () => {
    const queryPaymentCaseName = "QueryPaymentCreatedOrder";

    try {
      // Query the pre-created unpaid order using its reference number
      const queryRequestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, queryPaymentCaseName);
      queryRequestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;

      const response = await dana.paymentGatewayApi.queryPayment(queryRequestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, queryPaymentCaseName, response, { 'partnerReferenceNo': sharedOriginalPartnerReference });
    } catch (e) {
      console.error('Query payment test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Query Payment with CANCELLED Status
   * 
   * This test validates the query payment functionality for orders that have been
   * canceled. It uses a pre-created canceled order (from beforeAll setup) and
   * includes a delay to ensure cancellation has propagated through the system.
   * 
   * @scenario Positive test case for querying canceled payments
   * @technique Uses shared canceled order reference from test setup with propagation delay
   * @expectedResult HTTP 200 OK with payment status CANCELLED
   */
  test('should successfully query payment with status canceled (CANCELLED)', async () => {
    const queryPaymentCaseName = "QueryPaymentCanceledOrder";
    await new Promise(resolve => setTimeout(resolve, 5000)); // Wait for cancellation to propagate
    try {
      // Query the pre-created canceled order using its reference number
      const queryRequestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, queryPaymentCaseName);
      queryRequestData.originalPartnerReferenceNo = sharedOriginalCanceledPartnerReference;

      const response = await dana.paymentGatewayApi.queryPayment(queryRequestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, queryPaymentCaseName, response, { 'partnerReferenceNo': sharedOriginalCanceledPartnerReference });
    } catch (e) {
      console.error('Query payment test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Invalid Field Format Validation
   * 
   * This test validates API input validation by sending a query request with
   * an invalid timestamp format. The test uses manual API calls to bypass
   * SDK validation and trigger server-side validation errors.
   * 
   * @scenario Negative test case for input format validation
   * @technique Uses manual API call with malformed X-TIMESTAMP header
   * @expectedError HTTP 400 Bad Request due to invalid timestamp format
   */
  test('should fail when field format is invalid (ex: invalid format for X-TIMESTAMP)', async () => {
    const caseName = "QueryPaymentInvalidFormat";

    try {
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/status.htm';
      // await dana.paymentGatewayApi.queryPayment(requestData);

      // Custom headers with malformed timestamp format
      const customHeaders: Record<string, string> = {
        // Override the signature with an invalid one
        'X-TIMESTAMP': new Date(Date.now() + 7 * 60 * 60 * 1000)
          .toISOString()
          .replace('T', ' ')
          .replace(/\.\d{3}Z$/, '+07:00')
          .replace(/-/g, '-')
          .replace(/:/g, ':')
      };

      // Execute manual API call - this should fail due to invalid timestamp format
      await executeManualApiRequest(
        caseName,
        "POST",
        baseUrl + apiPath,
        apiPath,
        requestData,
        customHeaders
      );

      fail("Expected an error but the API call succeeded ");
    } catch (e: any) {

      if (Number(e.status) === 400) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Missing Mandatory Field Validation
   * 
   * This test validates API authentication and header validation by making a query
   * request with missing mandatory headers. Specifically tests scenarios where
   * required headers like X-TIMESTAMP are omitted, which should trigger validation errors.
   * 
   * @scenario Negative test case for mandatory field validation
   * @technique Uses manual API call with empty X-TIMESTAMP header
   * @expectedError HTTP 400 Bad Request due to missing mandatory header
   */
  test('should fail when mandatory field is missing (ex: missing X-TIMESTAMP header in request)', async () => {
    const caseName = "QueryPaymentInvalidMandatoryField";

    try {
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Use valid reference number but with missing mandatory header
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/status.htm';

      // Define custom headers with empty timestamp to trigger mandatory field error
      const customHeaders: Record<string, string> = {
        'X-TIMESTAMP': ''
      };

      // Execute manual API call - this should fail due to missing mandatory header
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
        // Assert the error response matches expected format
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
   * Test Case: Authorization Failure
   * 
   * This test validates API security by making a query request with invalid
   * authentication signature. This ensures that the API properly rejects
   * requests with incorrect or tampered authentication credentials.
   * 
   * @scenario Negative test case for authentication security validation
   * @technique Uses manual API call with invalid signature to bypass SDK authentication
   * @expectedError HTTP 401 Unauthorized due to invalid signature
   */
  test('should fail when authorization fails (ex: wrong signature)', async () => {
    const caseName = "QueryPaymentUnauthorized";
    const titleCase = "QueryPaymentApi";

    try {
      // Prepare query request with valid data but invalid authentication
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Use the shared original partner reference number
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;

      // Define base URL and API path
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/status.htm';

      // Define custom headers with invalid signature to trigger authorization error
      const customHeaders: Record<string, string> = {
        // Override the signature with an invalid one
        'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
      };

      // Execute manual API call with custom headers - should fail authentication
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
        // Assert the error response matches expected format
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 401) {
        fail("Expected unauthorized failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: Transaction Not Found Error
   * 
   * This test validates the API's handling of queries for non-existent transactions.
   * It uses a reference number that is guaranteed not to exist by appending
   * "_NOT_FOUND" to an existing reference, ensuring proper error handling.
   * 
   * @scenario Negative test case for non-existent transaction queries
   * @technique Uses modified reference number to ensure transaction doesn't exist
   * @expectedError HTTP 404 Not Found due to non-existent transaction
   */
  test('should fail when transaction is not found', async () => {
    const caseName = "QueryPaymentTransactionNotFound";
    const titleCase = "QueryPayment";

    try {
      // Get the request data from the JSON file
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Set a unique partner reference number for the query with _NOT_FOUND suffix to ensure it doesn't exist
      // This matches the pattern used in the Python/Go tests
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference + "_NOT_FOUND";

      // Make the API call using SDK - should fail with 404
      await dana.paymentGatewayApi.queryPayment(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {

      if (e instanceof ResponseError && Number(e.status) === 404) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 404) {
        fail("Expected nout found request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  /**
   * Test Case: General Error Scenario
   * 
   * This test validates the API's handling of general server errors that may
   * occur during query processing. It's designed to test edge cases and
   * unexpected server conditions.
   * 
   * @scenario Negative test case for general server error handling
   * @technique Uses potentially problematic request data to trigger server errors
   * @expectedError HTTP 500 Internal Server Error due to general processing error
   * @note This test may be environment-dependent and could behave differently in different API versions
   */
  test('should handle general error scenario (QueryPaymentGeneralError)', async () => {
    const caseName = "QueryPaymentGeneralError";
    const titleCase = "QueryPayment";

    try {
      // Prepare request data that will trigger a general error
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Use a deliberately malformed or problematic reference number to simulate a general error
      requestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;

      // Make the API call using SDK - should fail with general error
      await dana.paymentGatewayApi.queryPayment(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // Expecting a 500 or general error
      if (e instanceof ResponseError && Number(e.status) === 500) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { 'partnerReferenceNo': "INVALID_GENERAL_ERROR_REF" });
      } else if (e instanceof ResponseError && Number(e.status) !== 500) {
        fail("Expected general error but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

});
