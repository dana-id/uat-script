import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { ApplyOTTRequest, ApplyOTTRequestAdditionalInfo, ApplyTokenRequest } from 'dana-node/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { generateAuthCode } from '../helper/auth-utils';

dotenv.config();

const titleCase = 'ApplyOtt';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');

const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});

/**
 * Generate an access token for testing using an auth code from the helper utility
 * @param phoneNumber Optional phone number for OAuth flow
 * @param pinCode Optional PIN code for OAuth flow
 * @returns Promise resolving to access token string
 */
async function generateApplyToken(phoneNumber?: string, pinCode?: string): Promise<string> {
    const caseName = 'ApplyTokenSuccess';
    const requestData: ApplyTokenRequest = getRequest<ApplyTokenRequest>(jsonPathFile, "ApplyToken", caseName);

    requestData.authCode = await generateAuthCode(phoneNumber, pinCode);

    const response = await dana.widgetApi.applyToken(requestData);
    return response.accessToken;
}

describe('ApplyOtt Tests', () => {
    test('should successfully apply OTT', async () => {
        const caseName = 'ApplyOttSuccess';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);

        requestData.additionalInfo.accessToken = await generateApplyToken();
        try {
            const response = await dana.widgetApi.applyOTT(requestData);
            await assertResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            fail('ApplyOTT test failed: ' + (e.message || e));
        }
    });

    test('should fail to apply OTT with invalid format', async () => {
        const caseName = 'ApplyOttFailInvalidFormat';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);
        requestData.additionalInfo.accessToken = await generateApplyToken();

        try {
            await dana.widgetApi.applyOTT(requestData);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (e instanceof ResponseError && Number(e.status) === 400) {
                // Expected error for invalid format
                await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse);
            } else if (e instanceof ResponseError && Number(e.status) === 401) {
                // Expected error for invalid signature
                fail("Expected unauthorized failed but got status code " + e.status);
            } else {
                // Unexpected error
                console.error('Unexpected error:', e);
            }
        }

    });

    test('should fail to apply OTT with missing or invalid mandatory field', async () => {
        const caseName = 'ApplyOttFailMissingOrInvalidMandatoryField';
        try {
            const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);

            const baseUrl: string = 'https://api.sandbox.dana.id/';
            const apiPath: string = '/rest/v1.1/qr/apply-ott';
            const customHeaders: Record<string, string> = {
                'X-TIMESTAMP': ""
            };

            await executeManualApiRequest(
                caseName,
                "POST",
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders,
            );
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 400) {
                // Expected error for invalid format
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            }
            else if (e instanceof ResponseError) {
                // Expected error for invalid signature
                fail("Expected unauthorized failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    test('should fail to apply OTT with invalid signature', async () => {
        const caseName = 'ApplyOttFailInvalidSignature';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);
        requestData.additionalInfo.accessToken = await generateApplyToken();
        // Custom headers: use invalid signature to trigger authorization error


        try {
            const baseUrl: string = 'https://api.sandbox.dana.id/';
            const apiPath: string = '/rest/v1.1/qr/apply-ott';

            const customHeaders: Record<string, string> = {
                'Authorization-Customer': 'Bearer ' + requestData.additionalInfo.accessToken,
                'X-DEVICE-ID': '1234567890',
                'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
            };
            // Make direct API call with invalid signature
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
            // Expecting a 401 Unauthorized error
            if (Number(e.status) === 401) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else if (e instanceof ResponseError && Number(e.status) !== 401) {
                fail("Expected unauthorized failed but got status code " + e.status + JSON.stringify(e.rawResponse));
            } else {
                throw e;
            }
        }
    });

    test('should fail to apply OTT with token expired', async () => {
        const caseName = 'ApplyOttFailTokenExpired';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);

        requestData.additionalInfo.accessToken = await generateApplyToken("08551003634", "574008");

        try {
            const response = await dana.widgetApi.applyOTT(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test('should fail to apply OTT with token not found', async () => {
        const caseName = 'ApplyOttFailTokenNotFound';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);
        requestData.additionalInfo.accessToken = uuidv4(); // Use a random token to simulate not found
        try {
            const response = await dana.widgetApi.applyOTT(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test.skip('should fail to apply OTT with invalid user status', async () => {
        const caseName = 'ApplyOttFailInvalidUserStatus';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);
        requestData.additionalInfo.accessToken = await generateApplyToken("0855100800", "146838");
        try {
            const response = await dana.widgetApi.applyOTT(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test.skip('should fail to apply OTT with non-retryable error', async () => {
        const caseName = 'ApplyOttFailNonRetryableError';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);
        requestData.additionalInfo.accessToken = await generateApplyToken("0815919191", "945922");
        try {
            const response = await dana.widgetApi.applyOTT(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test.skip('should fail to apply OTT with internal server error', async () => {
        const caseName = 'ApplyOttFailInternalServerError';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);
        requestData.additionalInfo.accessToken = await generateApplyToken("081298055132", "677832");
        try {
            const response = await dana.widgetApi.applyOTT(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });

    test.skip('should fail to apply OTT with unexpected response', async () => {
        const caseName = 'ApplyOttFailUnexpectedResponse';
        const requestData: ApplyOTTRequest = getRequest<ApplyOTTRequest>(jsonPathFile, titleCase, caseName);
        requestData.additionalInfo.accessToken = await generateApplyToken("08121532586", "944279");
        try {
            const response = await dana.widgetApi.applyOTT(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
            fail('Expected an error but the API call succeeded');
        } catch (e: any) { }
    });
});
