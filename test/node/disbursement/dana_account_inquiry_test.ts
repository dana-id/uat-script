/**
 * @fileoverview Dana Account Inquiry Test Suite for DANA Disbursement Integration
 * 
 * This suite validates the DANA account inquiry functionality of the DANA Disbursement API.
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
 */
describe('Disbursement - Dana Account Inquiry Tests', () => {

  /**
   * Test Case: Successful Dana Account Inquiry
   */
  test('DanaAccountInquirySuccessful - should successfully inquire DANA account', async () => {
    const caseName = "DanaAccountInquirySuccessful";
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
   * Test Case: Dana Account Inquiry with Insufficient Fund
   */
  test('DanaAccountInquiryInsufficientFund - should fail inquiry due to insufficient fund', async () => {
    const caseName = "DanaAccountInquiryInsufficientFund";
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Assign unique reference for test isolation
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;

    try {
      // This API call should fail due to insufficient fund
      await dana.disbursementApi.danaAccountInquiry(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // Expecting a 403 forbidden error for insufficient fund
      if (e instanceof ResponseError && Number(e.status) === 403) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, null);
      } else if (e instanceof ResponseError && Number(e.status) !== 403) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

});