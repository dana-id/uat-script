/**
 * @fileoverview Transfer To Bank Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the transfer to bank functionality of the DANA Disbursement API.
 * Each test case includes comprehensive documentation following JSDoc standards.
 * 
 * @author Integration Test Team
 * @version 1.0.0
 * @since 2024
 * @module TransferToBankTests
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
const titleCase = "TransferToBank";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Disbursement.json');

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

/**
 * Transfer To Bank Test Suite
 * 
 * @description Comprehensive test coverage for DANA Disbursement transfer to bank operations
 * @author Integration Test Team
 * @version 1.0.0
 * @testSuite TransferToBank
 * @component Disbursement
 * @apiVersion v1.0
 */
describe('Disbursement - Transfer To Bank Tests', () => {

  /**
   * Test Case: Successful Transfer To Bank
   * 
   * @description Validates that a valid transfer to bank request processes successfully
   * @testId TOP_UP_CUSTOMER_VALID_001
   * @priority High
   * @category Positive Test
   * @expectedResult HTTP 200 with successful transfer response including referenceNo and transactionDate
   * @prerequisites Valid partner credentials and sufficient account balance
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankValidAccount - should successfully transfer to bank', async () => {
    const caseName = "DisbursementBankValidAccount";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Execute transfer to bank API call
      const response = await dana.disbursementApi.transferToBank(requestData);
      await assertResponse(jsonPathFile, titleCase, caseName, response, {
        'partnerReferenceNo': partnerReferenceNo
      });
    } catch (e) {
      console.error('Transfer to bank test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Transfer To Bank with Insufficient Fund
   * 
   * @description Validates that transfer requests with insufficient funds are properly rejected
   * @testId TOP_UP_CUSTOMER_INSUFFICIENT_FUND_002
   * @priority High
   * @category Negative Test
   * @expectedResult HTTP 400 with insufficient fund error response
   * @prerequisites Valid partner credentials but insufficient account balance
   * @errorCode INSUFFICIENT_BALANCE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankInsufficientFund - should fail transfer due to insufficient fund', async () => {
    const caseName = "DisbursementBankInsufficientFund";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToBank(requestData);
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
   * Test Case: Transfer To Bank with Missing Mandatory Field
   * 
   * @description Validates that transfer requests missing mandatory fields are properly rejected
   * @testId DISBURSEMENT_BANK_MISSING_MANDATORY_FIELD_003
   * @priority High
   * @category Validation Test
   * @expectedResult HTTP 400 with missing mandatory field error response
   * @prerequisites Valid partner credentials but missing required request fields
   * @errorCode MISSING_MANDATORY_FIELD
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankMissingMandatoryField - should fail transfer due to missing mandatory field', async () => {
    const caseName = "DisbursementBankMissingMandatoryField";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToBank(requestData);
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
   * Test Case: Transfer To Bank with Unauthorized Signature
   * 
   * @description Validates that transfer requests with unauthorized signatures are properly rejected
   * @testId DISBURSEMENT_BANK_UNAUTHORIZED_SIGNATURE_004
   * @priority High
   * @category Security Test
   * @expectedResult HTTP 401 with unauthorized signature error response
   * @prerequisites Invalid or tampered signature in request headers
   * @errorCode UNAUTHORIZED_SIGNATURE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankUnauthorizedSignature - should fail transfer due to unauthorized signature', async () => {
    const caseName = "DisbursementBankUnauthorizedSignature";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/v1.0/emoney/transfer-bank.htm';

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
   * Test Case: Transfer To Bank with Invalid Field Format
   * 
   * @description Validates that transfer requests with invalid field formats are properly rejected
   * @testId DISBURSEMENT_BANK_INVALID_FIELD_FORMAT_005
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with invalid field format error response
   * @prerequisites Valid partner credentials but malformed request fields
   * @errorCode INVALID_FIELD_FORMAT
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankInvalidFieldFormat - should fail transfer due to invalid field format', async () => {
    const caseName = "DisbursementBankInvalidFieldFormat";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToBank(requestData);
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
   * Test Case: Transfer To Bank with Inconsistent Request
   * 
   * @description Validates that transfer requests with inconsistent data are properly rejected
   * @testId DISBURSEMENT_BANK_INCONSISTENT_REQUEST_006
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with inconsistent request error response
   * @prerequisites Valid partner credentials but inconsistent request data
   * @errorCode INCONSISTENT_REQUEST
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankInconsistentRequest - should fail transfer due to inconsistent request', async () => {
    const caseName = "DisbursementBankInconsistentRequest";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;
    
    try {
      await dana.disbursementApi.transferToBank(requestData);
      
      requestData.amount.value = "2.00";
      requestData.amount.currency = "IDR";
      await dana.disbursementApi.transferToBank(requestData);
      
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': partnerReferenceNo });
      } else {
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });

  /**
   * Test Case: Transfer To Bank with Unknown Error
   * 
   * @description Validates that transfer requests handle unknown internal server errors appropriately
   * @testId DISBURSEMENT_BANK_UNKNOWN_ERROR_007
   * @priority Low
   * @category Error Handling Test
   * @expectedResult HTTP 500 with internal server error response
   * @prerequisites Valid partner credentials but server experiences unknown error
   * @errorCode UNKNOWN_ERROR
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankUnknownError - should fail transfer due to internal server error', async () => {
    const caseName = "DisbursementBankUnknownError";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToBank(requestData);
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
   * Test Case: Transfer To Bank with General Error
   * 
   * @description Validates that transfer requests handle general internal errors appropriately
   * @testId DISBURSEMENT_BANK_GENERAL_ERROR_008
   * @priority Low
   * @category Error Handling Test
   * @expectedResult HTTP 500 with general internal error response
   * @prerequisites Valid partner credentials but server experiences general error
   * @errorCode GENERAL_ERROR
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankGeneralError - should fail transfer due to internal general error', async () => {
    const caseName = "DisbursementBankGeneralError";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToBank(requestData);
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
   * Test Case: Transfer To Bank with Inactive Account
   * 
   * @description Validates that transfers to inactive bank accounts are properly rejected
   * @testId DISBURSEMENT_BANK_INACTIVE_ACCOUNT_009
   * @priority Medium
   * @category Negative Test
   * @expectedResult HTTP 400 with inactive account error response
   * @prerequisites Valid partner credentials but target bank account is inactive
   * @errorCode ACCOUNT_INACTIVE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankInactiveAccount - should fail transfer due to inactive account', async () => {
    const caseName = "DisbursementBankInactiveAccount";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToBank(requestData);
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
   * Test Case: Transfer To Bank with Suspected Fraud
   * 
   * @description Validates that transfers flagged as suspected fraud are properly blocked
   * @testId DISBURSEMENT_BANK_SUSPECTED_FRAUD_010
   * @priority High
   * @category Security Test
   * @expectedResult HTTP 403 with suspected fraud error response
   * @prerequisites Valid partner credentials but transaction triggers fraud detection
   * @errorCode SUSPECTED_FRAUD
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankSuspectedFraud - should fail transfer due to suspected fraud', async () => {
    const caseName = "DisbursementBankSuspectedFraud";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to suspected fraud
      await dana.disbursementApi.transferToBank(requestData);
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
   * Test Case: Transfer To Bank with Valid Account - In Progress Status
   * 
   * @description Validates that valid transfer requests can return "in progress" status appropriately
   * @testId DISBURSEMENT_BANK_VALID_ACCOUNT_IN_PROGRESS_011
   * @priority Medium
   * @category Positive Test
   * @expectedResult HTTP 200 with in progress status response including transaction reference
   * @prerequisites Valid partner credentials and valid bank account with processing delay
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('DisbursementBankValidAccountInProgress - should successfully initiate transfer with in progress status', async () => {
    const caseName = "DisbursementBankValidAccountInProgress";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Execute transfer to bank API call
      const response = await dana.disbursementApi.transferToBank(requestData);
      await assertResponse(jsonPathFile, titleCase, caseName, response, {
        'partnerReferenceNo': partnerReferenceNo
      });
    } catch (e) {
      console.error('Transfer to bank test failed:', e);
      throw e;
    }
  });
});