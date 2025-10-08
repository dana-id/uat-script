/**
 * @fileoverview Transfer To DANA Inquiry Status Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the inquiry status functionality for transfer to DANA transactions
 * in the DANA Disbursement API. Each test case includes comprehensive documentation following JSDoc standards.
 * 
 * @author Integration Test Team
 * @version 1.0.0
 * @since 2024
 * @module TransferToDanaInquiryStatusTests
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
import { DisbursementApi, TransferToDanaInquiryStatusRequest, TransferToDanaRequest } from 'dana-node/disbursement/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const transferToDanaTitleCase = "TransferToDana";
const titleCase = "TransferToDanaInquiryStatus";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Disbursement.json');

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

function generatePartnerReferenceNo(): string {
  return uuidv4();
}

// Shared test data for cross-test dependencies
let originalPartnerReferencePaid: string;
let originalPartnerReferenceFailed: string;

/**
 * Transfer To DANA Inquiry Status Test Suite
 * 
 * @description Comprehensive test coverage for DANA transfer status inquiry operations
 * @author Integration Test Team
 * @version 1.0.0
 * @testSuite TransferToDanaInquiryStatus
 * @component Disbursement
 * @apiVersion v1.0
 * @requires createDisbursementPaid Pre-condition for status inquiry tests
 */
describe('Disbursement - Transfer To DANA Inquiry Status Tests', () => {

  /**
   * Creates a successful disbursement transaction for testing status inquiry
   * 
   * @description Helper function to create a paid disbursement for status testing
   * @returns Promise<void>
   * @throws Error if disbursement creation fails
   */

  async function createDisbursementPaid() {

  /**
   * Creates a failed disbursement transaction for testing status inquiry
   * 
   * @description Helper function to create a failed disbursement for status testing
   * @returns Promise<void>
   * @throws Error if disbursement creation fails
   */
    const disbursementRequest: TransferToDanaRequest = getRequest<TransferToDanaRequest>(jsonPathFile, "TransferToDana", "TopUpCustomerValid");
    originalPartnerReferencePaid = generatePartnerReferenceNo();
    disbursementRequest.partnerReferenceNo = originalPartnerReferencePaid;
    await dana.disbursementApi.transferToDana(disbursementRequest);
  }

  async function createDisbursementFailed() {
    const disbursementRequest: TransferToDanaRequest = getRequest<TransferToDanaRequest>(jsonPathFile, "TransferToDana", "TopUpCustomerExceedAmountLimit");
    originalPartnerReferenceFailed = generatePartnerReferenceNo();
    disbursementRequest.partnerReferenceNo = originalPartnerReferenceFailed;
    await dana.disbursementApi.transferToDana(disbursementRequest);
  }

  /**
   * Test Setup Hook
   * 
   * @description Creates prerequisite disbursement transactions before running status inquiry tests
   * @beforeAll Jest hook that runs once before all tests in the suite
   * @author Integration Test Team
   */

  beforeAll(async () => {
    try {
      await createDisbursementPaid()
      console.log(`Shared order created with reference: ${originalPartnerReferencePaid}`);
      await createDisbursementFailed()
      console.log(`Shared order created with reference: ${originalPartnerReferenceFailed}`);
    } catch (e) {
      console.error('Failed to create shared order - tests cannot continue:', e);
    }
  });

  /**
   * Test Case: Successful Transfer To DANA Status Inquiry - PAID
   * 
   * @description Validates that status inquiry for a successful transfer returns PAID status
   * @testId INQUIRY_TOPUP_STATUS_VALID_PAID_001
   * @priority High
   * @category Positive Test
   * @expectedResult HTTP 200 with PAID status and transaction details
   * @prerequisites Previously successful transfer to DANA transaction
   * @dependsOn createDisbursementPaid
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryTopUpStatusValidPaid - should successfully inquire transfer to DANA status PAID', async () => {
    const caseName = "InquiryTopUpStatusValidPaid";

    // Prepare transfer to DANA request and inquiry status request
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    requestData.originalPartnerReferenceNo = originalPartnerReferencePaid;

    const variableDict: Record<string, any> = { originalPartnerReferenceNo: originalPartnerReferencePaid };

    try {
      // Execute inquiry status API call
      const response = await dana.disbursementApi.transferToDanaInquiryStatus(requestData);

      variableDict.originalReferenceNo = response.originalReferenceNo;

      await assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (e) {
      console.error('Transfer to DANA inquiry status test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Successful Transfer To DANA Status Inquiry - FAILED
   *
   * @description Validates that status inquiry for a failed transfer returns FAILED status
   * @testId INQUIRY_TOPUP_STATUS_VALID_FAILED_001
   * @priority High
   * @category Positive Test
   * @expectedResult HTTP 200 with FAILED status and transaction details
   * @prerequisites Previously failed transfer to DANA transaction
   * @dependsOn createDisbursementFailed
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryTopUpStatusValidFailed - should successfully inquire transfer to DANA status FAILED', async () => {
    const caseName = "InquiryTopUpStatusValidFail";

    // Prepare transfer to DANA request and inquiry status request
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    requestData.originalPartnerReferenceNo = originalPartnerReferenceFailed;

    const variableDict: Record<string, any> = { originalPartnerReferenceNo: originalPartnerReferenceFailed };

    try {
      // Execute inquiry status API call
      const response = await dana.disbursementApi.transferToDanaInquiryStatus(requestData);

      variableDict.originalReferenceNo = response.originalReferenceNo;

      await assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (e) {
      console.error('Transfer to DANA inquiry status test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Transfer To DANA Status Inquiry with Invalid Field Format
   * 
   * @description Validates that status inquiry requests with invalid field formats are rejected
   * @testId INQUIRY_TOPUP_STATUS_INVALID_FIELD_FORMAT_002
   * @priority Medium
   * @category Validation Test
   * @expectedResult HTTP 400 with invalid field format error response
   * @prerequisites Valid partner credentials but malformed request fields
   * @errorCode INVALID_FIELD_FORMAT
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryTopUpStatusInvalidFieldFormat - should fail inquiry when input is invalid', async () => {
    const caseName = "InquiryTopUpStatusInvalidFieldFormat";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    requestData.originalPartnerReferenceNo = originalPartnerReferencePaid;

    try {
      // This API call should fail due to transaction not found
      await dana.disbursementApi.transferToDanaInquiryStatus(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // If a ResponseError occurs, assert the failure response
      if (e instanceof ResponseError) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': originalPartnerReferencePaid });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });

  /**
   * Test Case: Transfer To DANA Status Inquiry - Transaction Not Found
   * 
   * @description Validates that status inquiry for non-existent transactions returns appropriate error
   * @testId INQUIRY_TOPUP_STATUS_NOT_FOUND_TRANSACTION_003
   * @priority Medium
   * @category Negative Test
   * @expectedResult HTTP 404 with transaction not found error response
   * @prerequisites Valid partner credentials but non-existent transaction reference
   * @errorCode TRANSACTION_NOT_FOUND
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryTopUpStatusNotFoundTransaction - should fail inquiry when transaction not found', async () => {
    const caseName = "InquiryTopUpStatusNotFoundTransaction";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    requestData.originalPartnerReferenceNo = "test123123";

    try {
      // This API call should fail due to transaction not found
      await dana.disbursementApi.transferToDanaInquiryStatus(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // If a ResponseError occurs, assert the failure response
      if (e instanceof ResponseError) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'originalPartnerReferenceNo': "test123123" });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });

  /**
   * Test Case: Transfer To DANA Status Inquiry - Missing Mandatory Field
   * 
   * @description Validates that status inquiry requests missing mandatory fields are rejected
   * @testId INQUIRY_TOPUP_STATUS_MISSING_MANDATORY_FIELD_004
   * @priority High
   * @category Validation Test
   * @expectedResult HTTP 400 with missing mandatory field error response
   * @prerequisites Valid partner credentials but missing required request fields
   * @errorCode MISSING_MANDATORY_FIELD
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryTopUpStatusMissingMandatoryField - should fail inquiry when transaction is missing mandatory field', async () => {
    const caseName = "InquiryTopUpStatusMissingMandatoryField";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    requestData.originalPartnerReferenceNo = originalPartnerReferencePaid;

    try {
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/v1.0/emoney/topup-status.htm';

      const customHeaders: Record<string, string> = {
        'X-TIMESTAMP': ''
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
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse), { 'partnerReferenceNo': originalPartnerReferencePaid });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });

  /**
   * Test Case: Transfer To DANA Status Inquiry - Unauthorized Signature
   * 
   * @description Validates that status inquiry requests with unauthorized signatures are rejected
   * @testId INQUIRY_TOPUP_STATUS_UNAUTHORIZED_SIGNATURE_005
   * @priority High
   * @category Security Test
   * @expectedResult HTTP 401 with unauthorized signature error response
   * @prerequisites Invalid or tampered signature in request headers
   * @errorCode UNAUTHORIZED_SIGNATURE
   * @author Integration Test Team
   * @since 1.0.0
   */
  test('InquiryTopUpStatusUnauthorizedSignature - should fail inquiry when signature is unauthorized', async () => {
    const caseName = "InquiryTopUpStatusUnauthorizedSignature";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    requestData.originalPartnerReferenceNo = originalPartnerReferencePaid;

    try {
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/v1.0/emoney/topup-status.htm';

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
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse), { 'partnerReferenceNo': originalPartnerReferencePaid });
      } else {
        // If another error occurs, fail the test with the error message
        fail('Payment test failed: ' + (e.message || e));
      }
    }
  });
});