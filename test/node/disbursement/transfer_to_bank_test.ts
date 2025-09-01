/**
 * @fileoverview Transfer To Bank Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the transfer to bank functionality of the DANA Disbursement API.
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
 */
describe('Disbursement - Transfer To Bank Tests', () => {

  /**
   * Test Case: Successful Transfer To Bank
   */
  test('TransferToBankSuccessful - should successfully transfer to bank', async () => {
    const caseName = "TransferToBankSuccessful";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // Execute transfer to bank API call
      const response = await dana.disbursementApi.transferToBank(requestData);

      const variableDict: Record<string, any> = {
        partnerReferenceNo,
        referenceNo: response.referenceNo,
        referenceNumber: response.referenceNumber,
        transactionDate: response.transactionDate,
        additionalInfo: response.additionalInfo
      };

      await assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (e) {
      console.error('Transfer to bank test failed:', e);
      throw e;
    }
  });

  /**
   * Test Case: Transfer To Bank with Insufficient Fund
   */
  test('TransferToBankInsufficientFund - should fail transfer due to insufficient fund', async () => {
    const caseName = "TransferToBankInsufficientFund";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.transferToBank(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // Expecting a 403 forbidden error for insufficient fund
      if (e instanceof ResponseError && Number(e.status) === 403) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { partnerReferenceNo, additionalInfo: e.rawResponse?.response?.body?.additionalInfo });
      } else if (e instanceof ResponseError && Number(e.status) !== 403) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

});