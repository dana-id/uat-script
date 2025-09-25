import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { AccountUnbindingRequest, ApplyTokenRequest } from 'dana-node/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { generateAuthCode } from '../helper/auth-utils';

dotenv.config();

const titleCase = 'AccountUnbinding';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');

const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});

async function generateApplyToken(phoneNumber?: string, pinCode?: string): Promise<string> {
    const caseName = 'ApplyTokenSuccess';
    const requestData: ApplyTokenRequest = getRequest<ApplyTokenRequest>(jsonPathFile, "ApplyToken", caseName);

    requestData.authCode = await generateAuthCode(phoneNumber, pinCode);

    const response = await dana.widgetApi.applyToken(requestData);
    return response.accessToken;
}

describe('Account Unbinding Tests', () => {
  // Add beforeAll if any shared setup is needed in the future

  test('should successfully unbind account', async () => {
    const caseName = 'AccountUnbindSuccess';
    const requestData: AccountUnbindingRequest = getRequest<AccountUnbindingRequest>(jsonPathFile, titleCase, caseName);
    requestData.additionalInfo.accessToken = await generateApplyToken();
    requestData.additionalInfo.deviceId = "deviceid123";
    requestData.merchantId = process.env.MERCHANT_ID || '';

    try {
      const response = await dana.widgetApi.accountUnbinding(requestData);
      console.log("Response Data for Account Unbinding:", JSON.stringify(response, null, 2));
      await assertResponse(jsonPathFile, titleCase, caseName, response);
    } catch (e: any) {
      console.error("Error during Account Unbinding:", e);
      fail('Account unbinding test failed: ' + (e.message || e));
    }
  });

  test.skip('should fail to unbind account when access token does not exist', async () => {
    const caseName = 'AccountUnbindFailAccessTokenNotExist';
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
    try {
      const response = await dana.widgetApi.accountUnbinding(requestData);
      await assertFailResponse(jsonPathFile, titleCase, caseName, response);
      fail('Expected an error but the API call succeeded');
    } catch (e: any) {
      // Optionally assert error structure here if needed
    }
  });

  test.skip('should fail to unbind account with invalid user status', async () => {
    const caseName = 'AccountUnbindFailInvalidUserStatus';
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
    try {
      const response = await dana.widgetApi.accountUnbinding(requestData);
      await assertFailResponse(jsonPathFile, titleCase, caseName, response);
      fail('Expected an error but the API call succeeded');
    } catch (e: any) {
      // Optionally assert error structure here if needed
    }
  });

  test.skip('should fail to unbind account with invalid params', async () => {
    const caseName = 'AccountUnbindFailInvalidParams';
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
    try {
      const response = await dana.widgetApi.accountUnbinding(requestData);
      await assertFailResponse(jsonPathFile, titleCase, caseName, response);
      fail('Expected an error but the API call succeeded');
    } catch (e: any) {
      // Optionally assert error structure here if needed
    }
  });
});