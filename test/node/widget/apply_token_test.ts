import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { generateAuthCode } from '../helper/auth-utils';

dotenv.config();

const titleCase = 'ApplyToken';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');

const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});


// Utility function to generate unique reference numbers (if needed in future)
function generateReferenceNo(): string {
    return uuidv4();
}

describe('ApplyToken Tests', () => {
    test('should successfully apply token', async () => {
        const caseName = 'ApplyTokenSuccess';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        requestData.authCode = await generateAuthCode("087875849373","131000");
        try {
            const response = await dana.widgetApi.applyToken(requestData);
            await assertResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            fail('ApplyToken test failed: ' + (e.message || e));
        }
    });

    test.skip('should fail to apply token with expired authcode', async () => {
        const caseName = 'ApplyTokenFailExpiredAuthcode';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const response = await dana.widgetApi.applyToken(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test('should fail to apply token with used authcode', async () => {
        const caseName = 'ApplyTokenFailAuthcodeUsed';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        requestData.authCode = await generateAuthCode();
        // First apply token to use the auth code
        await dana.widgetApi.applyToken(requestData);
        // Now try to apply token again with the same auth code
        try {
            const response = await dana.widgetApi.applyToken(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test('should fail to apply token with invalid authcode', async () => {
        const caseName = 'ApplyTokenFailAuthcodeInvalid';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        requestData.authCode = 'invalid_auth_code'; // Use an invalid auth code
        try {
            const response = await dana.widgetApi.applyToken(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test.skip('should fail to apply token with invalid params', async () => {
        const caseName = 'ApplyTokenFailInvalidParams';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const response = await dana.widgetApi.applyToken(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test('should fail to apply token with invalid mandatory fields', async () => {
        const caseName = 'ApplyTokenFailInvalidMandatoryFields';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        requestData.authCode = "aklsdkalskdw1232ds"; // Ensure authCode is present

        const customHeaders: Record<string, string> = {
            'X-TIMESTAMP': '', // Use an invalid timestamp for testing
        }
        try {
            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/v1.0/access-token/b2b2c.htm';

            await executeManualApiRequest(
                caseName,
                "POST",
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders
            );

            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 400) {
                // Expected error for invalid mandatory fields
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            }
            else if (e instanceof ResponseError) {
                // Expected error for invalid signature
                fail("Expected unauthorized failed but got status code " + e.status);
            }
            else {
                throw e;
            }
        }
    });

    test.skip('should fail to apply token with invalid signature', async () => {
        const caseName = 'ApplyTokenFailInvalidSignature';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        const customHeaders: Record<string, string> = {
            'X-SIGNATURE': '', // Use an invalid timestamp for testing
        }
        try {
            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/v1.0/access-token/b2b2c.htm';

            await executeManualApiRequest(
                caseName,
                "POST",
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders
            );

            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 401) {
                // Expected error for invalid signature
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            }
            else if (e instanceof ResponseError) {
                // Expected error for invalid signature
                fail("Expected unauthorized failed but got status code " + e.status);
            }
            else {
                throw e;
            }
        }
    });
});
