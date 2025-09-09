/**
 * @fileoverview Dana Account Inquiry Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the DANA account inquiry functionality of the DANA Disbursement API.
 * Each test case includes comprehensive documentation following JSDoc standards.
 * 
 * @author Integration Test Team
 * @version 1.0.0
 * @since 2024
 * @module DanaAccountInquiryTests
 * @requires dana-node
 * @requires uuid
 * @requires dotenv
 */

import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Import helper functions and assertion utilities
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { fail } from 'assert';
import { ResponseError } from 'dana-node';
import { executeManualApiRequest } from '../helper/apiHelpers';

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const titleCase = "DanaAccountInquiry";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Disbursement.json');

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

/**
 * Dana Account Inquiry Test Suite
 * 
 * @description Comprehensive test coverage for DANA account inquiry operations
 * @author Integration Test Team
 * @version 1.0.0
 * @testSuite DanaAccountInquiry
 * @component Disbursement
 * @apiVersion v1.0
 */
describe('Disbursement - Dana Account Inquiry Tests', () => {

  /**
   * Test Case: Successful DANA Account Inquiry
   * 
   * @description Validates that a valid DANA account inquiry request processes successfully
   * @testId INQUIRY_CUSTOMER_VALID_DATA_001
   * @priority High
   * @category Positive Test
   * @expectedResult HTTP 200 with successful account inquiry response including account details
   * @prerequisites Valid partner credentials and registered DANA account
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryCustomerValidData - should successfully inquire DANA account', async () => {
    const caseName = "InquiryCustomerValidData";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Execute DANA account inquiry API call
      const response = await dana.disbursementApi.danaAccountInquiry(requestData);
      await assertResponse(jsonPathFile, titleCase, caseName, response, { partnerReferenceNo });
    } catch (e) {
      console.error('DANA account inquiry test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: DANA Account Inquiry with Unauthorized Signature
   * 
   * @description Validates that account inquiry requests with invalid signatures are rejected
   * @testId INQUIRY_CUSTOMER_UNAUTHORIZED_SIGNATURE_002
   * @priority High
   * @category Security Test
   * @expectedResult HTTP 401 with unauthorized signature error response
   * @prerequisites Invalid or tampered signature in request headers
   * @errorCode UNAUTHORIZED_SIGNATURE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryCustomerUnauthorizedSignature - should fail inquiry due to unauthorized signature', async () => {
    const caseName = "InquiryCustomerUnauthorizedSignature";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/v1.0/emoney/account-inquiry.htm';

      const customHeaders: Record<string, string> = {
        'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
      };

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
      // If a ResponseError occurs, assert the failure response
      if (e instanceof ResponseError) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse), { partnerReferenceNo });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });

  /**
   * Test Case: DANA Account Inquiry with Frozen Account
   * 
   * @description Validates that inquiries for frozen DANA accounts are properly handled
   * @testId INQUIRY_CUSTOMER_FROZEN_ACCOUNT_003
   * @priority Medium
   * @category Negative Test
   * @expectedResult HTTP 400 with frozen account error response
   * @prerequisites Valid partner credentials but target DANA account is frozen
   * @errorCode ACCOUNT_FROZEN
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryCustomerFrozenAccount - should fail inquiry due to frozen account', async () => {
    const caseName = "InquiryCustomerFrozenAccount";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.danaAccountInquiry(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // If a ResponseError occurs, assert the failure response
      if (e instanceof ResponseError) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': partnerReferenceNo });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });

  /**
   * Test Case: DANA Account Inquiry with Unregistered Account
   * 
   * @description Validates that inquiries for unregistered DANA accounts are properly rejected
   * @testId INQUIRY_CUSTOMER_UNREGISTERED_ACCOUNT_004
   * @priority Medium
   * @category Negative Test
   * @expectedResult HTTP 404 with account not found error response
   * @prerequisites Valid partner credentials but target account is not registered
   * @errorCode ACCOUNT_NOT_FOUND
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryCustomerUnregisteredAccount - should fail inquiry due to unregistered account', async () => {
    const caseName = "InquiryCustomerUnregisteredAccount";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.danaAccountInquiry(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // If a ResponseError occurs, assert the failure response
      if (e instanceof ResponseError) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': partnerReferenceNo });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });

  /**
   * Test Case: DANA Account Inquiry with Exceeded Limit
   * 
   * @description Validates that account inquiries exceeding rate limits are properly handled
   * @testId INQUIRY_CUSTOMER_EXCEEDED_LIMIT_005
   * @priority Low
   * @category Rate Limiting Test
   * @expectedResult HTTP 429 with rate limit exceeded error response
   * @prerequisites Valid partner credentials but inquiry rate limit exceeded
   * @errorCode RATE_LIMIT_EXCEEDED
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryCustomerExceededLimit - should fail inquiry due to exceeded limit', async () => {
    const caseName = "InquiryCustomerExceededLimit";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.danaAccountInquiry(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // If a ResponseError occurs, assert the failure response
      if (e instanceof ResponseError) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': partnerReferenceNo });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });
});