/**
 * @fileoverview Transfer To Bank Inquiry Status Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the inquiry status functionality for transfer to bank transactions
 * in the DANA Disbursement API.
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

// Load environment variables from .env file
dotenv.config();

// Test configuration constants
const transferToBankTitleCase = "TransferToBank";
const titleCase = "TransferToBankInquiryStatus";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Disbursement.json');

// Initialize DANA SDK client with environment configuration
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

/**
 * Transfer To Bank Inquiry Status Test Suite
 */
describe('Disbursement - Transfer To Bank Inquiry Status Tests', () => {

  /**
   * Test Case: Successful Transfer To Bank Inquiry Status
   */
  test('TransferToBankInquiryStatusSuccessful - should successfully inquire transfer to bank status', async () => {
    const transferToBankCaseName = "TransferToBankSuccessful";
    const caseName = "TransferToBankInquiryStatusSuccessful";

    // Prepare transfer to bank request and inquiry status request
    const transferToBankRequest: any = getRequest(jsonPathFile, transferToBankTitleCase, transferToBankCaseName);
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const originalPartnerReferenceNo = uuidv4();
    transferToBankRequest.partnerReferenceNo = originalPartnerReferenceNo;
    requestData.originalPartnerReferenceNo = originalPartnerReferenceNo;

    const variableDict: Record<string, any> = { originalPartnerReferenceNo };

    try {
      // Execute transfer to bank to create a transaction
      await dana.disbursementApi.transferToBank(transferToBankRequest);

      // Execute inquiry status API call
      const response = await dana.disbursementApi.transferToBankInquiryStatus(requestData);

      variableDict.originalReferenceNo = response.originalReferenceNo;
      variableDict.latestTransactionStatus = response.latestTransactionStatus;
      variableDict.transactionStatusDesc = response.transactionStatusDesc;

      await assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (e) {
      console.error('Transfer to bank inquiry status test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Transfer To Bank Inquiry Status - Transaction Not Found
   */
  test('TransferToBankInquiryStatusTransactionNotFound - should fail inquiry when transaction not found', async () => {
    const caseName = "TransferToBankInquiryStatusTransactionNotFound";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const originalPartnerReferenceNo = uuidv4();
    requestData.originalPartnerReferenceNo = originalPartnerReferenceNo;

    const variableDict: Record<string, any> = { originalPartnerReferenceNo };

    try {
      // This API call should fail due to transaction not found
      await dana.disbursementApi.transferToBankInquiryStatus(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // Expecting a 404 not found error
      if (e instanceof ResponseError && Number(e.status) === 404) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, variableDict);
      } else if (e instanceof ResponseError && Number(e.status) !== 404) {
        fail("Expected not found failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

});