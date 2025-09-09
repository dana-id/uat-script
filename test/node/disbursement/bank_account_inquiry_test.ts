/**
 * @fileoverview Bank Account Inquiry Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the bank account inquiry functionality of the DANA Disbursement API.
 * Each test case includes comprehensive documentation following JSDoc standards.
 * 
 * @author Integration Test Team
 * @version 1.0.0
 * @since 2024
 * @module BankAccountInquiryTests
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
const titleCase = "BankAccountInquiry";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Disbursement.json');

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

/**
 * Bank Account Inquiry Test Suite
 * 
 * @description Comprehensive test coverage for bank account inquiry operations
 * @author Integration Test Team
 * @version 1.0.0
 * @testSuite BankAccountInquiry
 * @component Disbursement
 * @apiVersion v1.0
 */
describe('Disbursement - Bank Account Inquiry Tests', () => {

  /**
   * Test Case: Successful Bank Account Inquiry
   * 
   * @description Validates that a valid bank account inquiry request processes successfully
   * @testId INQUIRY_BANK_ACCOUNT_VALID_DATA_AMOUNT_001
   * @priority High
   * @category Positive Test
   * @expectedResult HTTP 200 with successful bank account inquiry response including account details
   * @prerequisites Valid partner credentials and valid bank account information
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountValidDataAmount - should successfully inquire bank account', async () => {
    const caseName = "InquiryBankAccountValidDataAmount";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Execute bank account inquiry API call
      const response = await dana.disbursementApi.bankAccountInquiry(requestData);
      await assertResponse(jsonPathFile, titleCase, caseName, response, {
              'partnerReferenceNo': partnerReferenceNo
            });
    } catch (e) {
      console.error('Bank account inquiry test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Bank Account Inquiry with Insufficient Fund
   * 
   * @description Validates that bank account inquiries with insufficient funds are properly handled
   * @testId INQUIRY_BANK_ACCOUNT_INSUFFICIENT_FUND_002
   * @priority Medium
   * @category Negative Test
   * @expectedResult HTTP 400 with insufficient fund error response
   * @prerequisites Valid partner credentials but insufficient account balance
   * @errorCode INSUFFICIENT_BALANCE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountInsufficientFund - should fail inquiry due to insufficient fund', async () => {
    const caseName = "InquiryBankAccountInsufficientFund";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Call the widget payment API with the request data
      const response = await dana.disbursementApi.bankAccountInquiry(requestData);
      // Assert the failure response against the expected result
      await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response) , {
        'partnerReferenceNo': partnerReferenceNo
      });
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
   * Test Case: Bank Account Inquiry with Inactive Account
   * 
   * @description Validates that inquiries for inactive bank accounts are properly rejected
   * @testId INQUIRY_BANK_ACCOUNT_INACTIVE_ACCOUNT_003
   * @priority Medium
   * @category Negative Test
   * @expectedResult HTTP 400 with inactive account error response
   * @prerequisites Valid partner credentials but target bank account is inactive
   * @errorCode ACCOUNT_INACTIVE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountInactiveAccount - should fail inquiry due to inactive account', async () => {
    const caseName = "InquiryBankAccountInactiveAccount";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Call the widget payment API with the request data
      const response = await dana.disbursementApi.bankAccountInquiry(requestData);
      // Assert the failure response against the expected result
      await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response) , {
        'partnerReferenceNo': partnerReferenceNo
      });
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
   * Test Case: Bank Account Inquiry with Invalid Merchant
   * 
   * @description Validates that bank account inquiries with invalid merchant information are rejected
   * @testId INQUIRY_BANK_ACCOUNT_INVALID_MERCHANT_004
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with invalid merchant error response
   * @prerequisites Invalid merchant credentials or information
   * @errorCode INVALID_MERCHANT
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountInvalidMerchant - should fail inquiry due to invalid merchant', async () => {
    const caseName = "InquiryBankAccountInvalidMerchant";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Call the widget payment API with the request data
      const response = await dana.disbursementApi.bankAccountInquiry(requestData);
      // Assert the failure response against the expected result
      await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response) , {
        'partnerReferenceNo': partnerReferenceNo
      });
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
   * Test Case: Bank Account Inquiry with Invalid Card
   * 
   * @description Validates that bank account inquiries with invalid card information are rejected
   * @testId INQUIRY_BANK_ACCOUNT_INVALID_CARD_005
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with invalid card error response
   * @prerequisites Valid partner credentials but invalid card/account number
   * @errorCode INVALID_CARD_NUMBER
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountInvalidCard - should fail inquiry due to invalid card', async () => {
    const caseName = "InquiryBankAccountInvalidCard";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Call the widget payment API with the request data
      const response = await dana.disbursementApi.bankAccountInquiry(requestData);
      // Assert the failure response against the expected result
      await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response) , {
        'partnerReferenceNo': partnerReferenceNo
      });
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
   * Test Case: Bank Account Inquiry with Invalid Field Format
   * 
   * @description Validates that bank account inquiries with invalid field formats are rejected
   * @testId INQUIRY_BANK_ACCOUNT_INVALID_FIELD_FORMAT_006
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with invalid field format error response
   * @prerequisites Valid partner credentials but malformed request fields
   * @errorCode INVALID_FIELD_FORMAT
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountInvalidFieldFormat - should fail inquiry due to invalid field format', async () => {
    const caseName = "InquiryBankAccountInvalidFieldFormat";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Call the widget payment API with the request data
      const response = await dana.disbursementApi.bankAccountInquiry(requestData);
      // Assert the failure response against the expected result
      await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response) , {
        'partnerReferenceNo': partnerReferenceNo
      });
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
   * Test Case: Bank Account Inquiry with Missing Mandatory Field
   * 
   * @description Validates that bank account inquiries missing mandatory fields are rejected
   * @testId INQUIRY_BANK_ACCOUNT_MISSING_MANDATORY_FIELD_007
   * @priority High
   * @category Validation Test
   * @expectedResult HTTP 400 with missing mandatory field error response
   * @prerequisites Valid partner credentials but missing required request fields
   * @errorCode MISSING_MANDATORY_FIELD
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountMissingMandatoryField - should fail inquiry due to missing mandatory field', async () => {
    const caseName = "InquiryBankAccountMissingMandatoryField";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Call the widget payment API with the request data
      const response = await dana.disbursementApi.bankAccountInquiry(requestData);
      // Assert the failure response against the expected result
      await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(response) , {
        'partnerReferenceNo': partnerReferenceNo
      });
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
   * Test Case: Bank Account Inquiry with Unauthorized Signature
   * 
   * @description Validates that bank account inquiries with unauthorized signatures are rejected
   * @testId INQUIRY_BANK_ACCOUNT_UNAUTHORIZED_SIGNATURE_008
   * @priority High
   * @category Security Test
   * @expectedResult HTTP 401 with unauthorized signature error response
   * @prerequisites Invalid or tampered signature in request headers
   * @errorCode UNAUTHORIZED_SIGNATURE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryBankAccountUnauthorizedSignature - should fail inquiry due to unauthorized signature', async () => {
    const caseName = "InquiryBankAccountUnauthorizedSignature";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/v1.0/emoney/bank-account-inquiry.htm';

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
});