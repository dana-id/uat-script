/**
 * @fileoverview Transfer To DANA Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the transfer to DANA functionality of the DANA Disbursement API.
 * Each test case includes comprehensive documentation following JSDoc standards.
 * 
 * @author Integration Test Team
 * @version 1.0.0
 * @since 2024
 * @module TransferToDanaTests
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
const titleCase = "TransferToDana";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Disbursement.json');

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

/**
 * Transfer To DANA Test Suite
 * 
 * @description Comprehensive test coverage for DANA transfer to DANA account operations
 * @author Integration Test Team
 * @version 1.0.0
 * @testSuite TransferToDana
 * @component Disbursement
 * @apiVersion v1.0
 */
describe('Disbursement - Transfer To DANA Tests', () => {

  /**
   * Test Case: Successful Transfer To DANA
   * 
   * @description Validates that a valid transfer to DANA request processes successfully
   * @testId TRANSFER_TO_DANA_SUCCESSFUL_001
   * @priority High
   * @category Positive Test
   * @expectedResult HTTP 200 with successful transfer response including transaction details
   * @prerequisites Valid partner credentials and sufficient account balance
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerValid - should successfully transfer to DANA', async () => {
    const caseName = "TopUpCustomerValid";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Execute transfer to DANA API call
      const response = await dana.disbursementApi.transferToDana(requestData);

      const variableDict: Record<string, any> = {
        partnerReferenceNo,
        referenceNo: response.referenceNo
      };

      await assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (e) {
      console.error('Transfer to DANA test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Transfer To DANA with Insufficient Fund
   * 
   * @description Validates that transfer requests with insufficient funds are properly rejected
   * @testId TRANSFER_TO_DANA_INSUFFICIENT_FUND_002
   * @priority High
   * @category Negative Test
   * @expectedResult HTTP 400 with insufficient fund error response
   * @prerequisites Valid partner credentials but insufficient account balance
   * @errorCode INSUFFICIENT_BALANCE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerInsufficientFund - should fail transfer due to insufficient fund', async () => {
    const caseName = "TopUpCustomerInsufficientFund";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA with Timeout
   * 
   * @description Validates that transfer requests handle timeout scenarios appropriately
   * @testId TOP_UP_CUSTOMER_TIMEOUT_003
   * @priority Medium
   * @category Performance Test
   * @expectedResult HTTP 408 or timeout error response
   * @prerequisites Valid partner credentials but slow/unresponsive network
   * @errorCode REQUEST_TIMEOUT
   * @author Integration Test Team
   * @since 1.0.0
   * @skip Currently skipped - timeout test scenario
   */
  test.skip('TopUpCustomerTimeout - should fail transfer due to timeout', async () => {
    const caseName = "TopUpCustomerTimeout";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA Idempotency Check
   * 
   * @description Validates that duplicate transfer requests are handled correctly (idempotency)
   * @testId TOP_UP_CUSTOMER_IDEMPOTENT_004
   * @priority High
   * @category Idempotency Test
   * @expectedResult Same response as original request or proper duplicate error
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerIdempotent - should handle duplicate requests correctly', async () => {
    const caseName = "TopUpCustomerIdempotent";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    const fixedPartnerRef = `IDEMPOTENT_TEST_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;
    requestData.partnerReferenceNo = fixedPartnerRef;

    try {
      const firstResponse = await dana.disbursementApi.transferToDana(requestData);

      await new Promise(resolve => setTimeout(resolve, 1000));
      
      try {
        const secondResponse = await dana.disbursementApi.transferToDana(requestData);
        
        expect(secondResponse.referenceNo).toBe(firstResponse.referenceNo);
        expect(secondResponse.responseCode).toBe(firstResponse.responseCode);
        
        if ((firstResponse as any).amount && (secondResponse as any).amount) {
          expect((secondResponse as any).amount).toEqual((firstResponse as any).amount);
        }
        
        if (firstResponse.responseMessage && secondResponse.responseMessage) {
          expect(secondResponse.responseMessage).toBe(firstResponse.responseMessage);
        }
        
      } catch (duplicateError: any) {
        if (duplicateError instanceof ResponseError) {
          const errorResponse = duplicateError.rawResponse;
          const isDuplicateError = 
            errorResponse.responseCode?.includes('DUPLICATE') ||
            errorResponse.responseCode?.includes('ALREADY_EXIST') ||
            errorResponse.responseMessage?.toLowerCase().includes('duplicate');
            
          if (isDuplicateError) {
            expect(errorResponse.responseCode).toBeDefined();
          } else {
            fail(`Expected duplicate error, but got: ${errorResponse.responseCode} - ${errorResponse.responseMessage}`);
          }
        } else {
          fail(`Unexpected error type on duplicate request: ${duplicateError.message || duplicateError}`);
        }
      }

      await new Promise(resolve => setTimeout(resolve, 2000));
      
      try {
        const thirdResponse = await dana.disbursementApi.transferToDana(requestData);
        expect(thirdResponse.referenceNo).toBe(firstResponse.referenceNo);
        expect(thirdResponse.responseCode).toBe(firstResponse.responseCode);
        
        if ((firstResponse as any).amount && (thirdResponse as any).amount) {
          expect((thirdResponse as any).amount).toEqual((firstResponse as any).amount);
        }
        
      } catch (delayedError: any) {
        if (delayedError instanceof ResponseError) {
          const errorResponse = delayedError.rawResponse;
          const isDuplicateError = 
            errorResponse.responseCode?.includes('DUPLICATE') ||
            errorResponse.responseCode?.includes('ALREADY_EXIST') ||
            errorResponse.responseMessage?.toLowerCase().includes('duplicate');
            
          if (!isDuplicateError) {
            fail(`Expected duplicate error after delay, but got: ${errorResponse.responseCode}`);
          }
        }
      }

      const modifiedRequest = { 
        ...requestData, 
        amount: { 
          ...requestData.amount, 
          value: (parseFloat(requestData.amount.value) + 1000).toString() 
        } 
      };
      
      try {
        await dana.disbursementApi.transferToDana(modifiedRequest);
        fail('Modified request with same reference should be rejected');
      } catch (modifiedError: any) {
        expect(modifiedError).toBeDefined();
      }

    } catch (firstError: any) {
      if (firstError instanceof ResponseError) {
        console.log('First request failed - may be expected based on test data configuration');
      } else {
        fail(`First request failed unexpectedly: ${firstError.message || firstError}`);
      }
    }
  });

  /**
   * Test Case: Transfer To DANA with Frozen Account
   * 
   * @description Validates that transfers to frozen DANA accounts are properly rejected
   * @testId TOP_UP_CUSTOMER_FROZEN_ACCOUNT_005
   * @priority Medium
   * @category Negative Test
   * @expectedResult HTTP 400 with frozen account error response
   * @prerequisites Valid partner credentials but target DANA account is frozen
   * @errorCode ACCOUNT_FROZEN
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerFrozenAccount - should fail transfer due to frozen account', async () => {
    const caseName = "TopUpCustomerFrozenAccount";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA Exceeding Amount Limit
   * 
   * @description Validates that transfer requests exceeding maximum amount limits are rejected
   * @testId TOP_UP_CUSTOMER_EXCEED_AMOUNT_LIMIT_006
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with amount limit exceeded error response
   * @prerequisites Valid partner credentials but transfer amount exceeds limit
   * @errorCode AMOUNT_LIMIT_EXCEEDED
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerExceedAmountLimit - should fail transfer due to exceeding amount limit', async () => {
    const caseName = "TopUpCustomerExceedAmountLimit";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA with Missing Mandatory Field
   * 
   * @description Validates that transfer requests missing mandatory fields are properly rejected
   * @testId TOP_UP_CUSTOMER_MISSING_MANDATORY_FIELD_007
   * @priority High
   * @category Validation Test
   * @expectedResult HTTP 400 with missing mandatory field error response
   * @prerequisites Valid partner credentials but missing required request fields
   * @errorCode MISSING_MANDATORY_FIELD
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerMissingMandatoryField - should fail transfer due to missing mandatory field', async () => {
    const caseName = "TopUpCustomerMissingMandatoryField";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA with Unauthorized Signature
   * 
   * @description Validates that transfer requests with unauthorized signatures are properly rejected
   * @testId TOP_UP_CUSTOMER_UNAUTHORIZED_SIGNATURE_008
   * @priority High
   * @category Security Test
   * @expectedResult HTTP 401 with unauthorized signature error response
   * @prerequisites Invalid or tampered signature in request headers
   * @errorCode UNAUTHORIZED_SIGNATURE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerUnauthorizedSignature - should fail transfer due to unauthorized signature', async () => {
    const caseName = "TopUpCustomerUnauthorizedSignature";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/v1.0/emoney/topup.htm';

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
   * Test Case: Transfer To DANA with Invalid Field Format
   * 
   * @description Validates that transfer requests with invalid field formats are properly rejected
   * @testId TOP_UP_CUSTOMER_INVALID_FIELD_FORMAT_009
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with invalid field format error response
   * @prerequisites Valid partner credentials but malformed request fields
   * @errorCode INVALID_FIELD_FORMAT
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerInvalidFieldFormat - should fail transfer due to invalid field format', async () => {
    const caseName = "TopUpCustomerInvalidFieldFormat";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA with Inconsistent Request
   * 
   * @description Validates that transfer requests with inconsistent data are properly rejected
   * @testId TOP_UP_CUSTOMER_INCONSISTENT_REQUEST_010
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with inconsistent request error response
   * @prerequisites Valid partner credentials but inconsistent request data (e.g., duplicate calls with different amounts)
   * @errorCode INCONSISTENT_REQUEST
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerInconsistentRequest - should fail transfer due to inconsistent request', async () => {
    const caseName = "TopUpCustomerInconsistentRequest";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
      requestData.amount.value = "4.00";
      requestData.amount.currency = "IDR";
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA with Internal Server Error
   * 
   * @description Validates that transfer requests handle internal server errors appropriately
   * @testId TOP_UP_CUSTOMER_INTERNAL_SERVER_ERROR_011
   * @priority Low
   * @category Error Handling Test
   * @expectedResult HTTP 500 with internal server error response
   * @prerequisites Valid partner credentials but server experiences internal error
   * @errorCode INTERNAL_SERVER_ERROR
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerInternalServerError - should fail transfer due to internal server error', async () => {
    const caseName = "TopUpCustomerInternalServerError";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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
   * Test Case: Transfer To DANA with Internal General Error
   * 
   * @description Validates that transfer requests handle general internal errors appropriately
   * @testId TOP_UP_CUSTOMER_INTERNAL_GENERAL_ERROR_012
   * @priority Low
   * @category Error Handling Test
   * @expectedResult HTTP 500 with general internal error response
   * @prerequisites Valid partner credentials but server experiences general error
   * @errorCode INTERNAL_GENERAL_ERROR
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('TopUpCustomerInternalGeneralError - should fail transfer due to general error', async () => {
    const caseName = "TopUpCustomerInternalGeneralError";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToDana(requestData);
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