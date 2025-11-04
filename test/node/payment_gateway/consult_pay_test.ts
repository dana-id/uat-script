/**
 * @fileoverview Consult Pay Test Suite for DANA Payment Gateway Integration
 * 
 * This test suite validates the consult pay functionality of the DANA Payment Gateway API.
 * It covers both successful consult pay scenarios and error handling for various failure cases.
 * The Consult Pay API allows merchants to query available payment methods and their details
 * before initiating a payment transaction.
 */

import Dana from 'dana-node';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Import helper functions and assertion utilities for robust testing
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { fail } from 'assert';
import { ResponseError } from 'dana-node';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { ConsultPayRequest } from 'dana-node/payment_gateway/v1';

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const titleCase = "ConsultPay"; // Main test category identifier
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json'); // Test data file path

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',     // Partner ID from environment
  privateKey: process.env.PRIVATE_KEY || '',     // RSA private key for authentication
  origin: process.env.ORIGIN || '',               // Request origin URL
  env: process.env.ENV || 'sandbox'              // Environment (sandbox/production)
});

// Merchant configuration from environment variables
const merchantId = process.env.MERCHANT_ID || "216620010016033632482";

/**
 * Payment Gateway Consult Pay Test Suite
 * 
 * This comprehensive test suite validates all aspects of the DANA Payment Gateway's
 * consult pay functionality, ensuring robust behavior across various scenarios.
 */
describe('Payment Gateway - Consult Pay Tests', () => {

  /**
   * Test Case: Consult Pay Success
   * 
   * This test validates the successful consult pay scenario where the API returns
   * available payment methods and their details. It verifies that the API returns
   * a valid response with payment information.
   * 
   * @scenario Positive test case for consult pay functionality
   * @expectedResult Success response with payment methods array
   */
  test('should successfully return available payment methods', async () => {
    const caseName = "ConsultPayBalancedSuccess";
    const requestData: ConsultPayRequest = getRequest<ConsultPayRequest>(jsonPathFile, titleCase, caseName);

    // Set merchant ID for the request

    try {
      // Execute consult pay API call
      const response = await dana.paymentGatewayApi.consultPay(requestData);

      // Validate API response against expected result
      await assertResponse(jsonPathFile, titleCase, caseName, response, null);
    } catch (e) {
      console.error('Consult pay success test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Invalid Field Format
   * 
   * This test validates API input validation by sending requests with improperly
   * formatted fields. Specifically tests scenarios like empty merchant ID,
   * which should trigger validation errors.
   * 
   * @scenario Negative test case for input format validation
   * @expectedError HTTP 400 Bad Request due to invalid field format
   */
  test('should fail when field format is invalid', async () => {
    const caseName = "ConsultPayBalancedInvalidFieldFormat";
    const requestData: ConsultPayRequest = getRequest<ConsultPayRequest>(jsonPathFile, titleCase, caseName);
    // Set merchant ID to an invalid format (empty string)
    requestData.merchantId = '';
    try {
      // This API call should fail due to invalid field format (empty merchantId)
      await dana.paymentGatewayApi.consultPay(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // Expecting a 400 Bad Request error for invalid format
      if (e instanceof ResponseError && Number(e.status) === 400) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
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
   * This test validates API authentication and header validation by making a manual
   * request with missing mandatory headers. Specifically tests scenarios where
   * required headers like X-TIMESTAMP are omitted, which should trigger validation errors.
   * 
   * @scenario Negative test case for API authentication validation
   * @technique Uses manual API call to bypass SDK validation
   * @expectedError HTTP 400 Bad Request due to missing mandatory header
   */
  test('should fail when mandatory field is missing (manual API call)', async () => {
    const caseName = "ConsultPayBalancedInvalidMandatoryField";
    const requestData: ConsultPayRequest = getRequest<ConsultPayRequest>(jsonPathFile, titleCase, caseName);

    // Set merchant ID for the request

    try {
      // API endpoint configuration
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/v1.0/payment-gateway/consult-pay.htm';

      // Custom headers: deliberately omit X-TIMESTAMP to trigger validation error
      const customHeaders: Record<string, string> = {
        'X-TIMESTAMP': '' // Empty timestamp will cause authentication failure
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
      // Expecting a 400 Bad Request error for missing mandatory header
      if (Number(e.status) === 400) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

});
