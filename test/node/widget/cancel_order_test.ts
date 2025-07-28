import Dana, { ResponseError } from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CancelOrderRequest } from 'dana-node/widget/v1';
import { executeManualApiRequest } from '../helper/apiHelpers';

dotenv.config();

const titleCase = 'CancelOrder';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/Widget.json');

const dana = new Dana({
    partnerId: process.env.X_PARTNER_ID || '',
    privateKey: process.env.PRIVATE_KEY || '',
    origin: process.env.ORIGIN || '',
    env: process.env.ENV || 'sandbox',
});

function generateReferenceNo(): string {
    return uuidv4();
}

describe('CancelOrder Tests', () => {
    test.skip('should successfully cancel order (in process)', async () => {
        const caseName = 'CancelOrderSuccessInProcess';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('CancelOrder test is a placeholder.');
        } catch (e: any) { }
    });

    test('should fail with user status abnormal', async () => {
        const caseName = 'CancelOrderFailUserStatusAbnormal';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);

        try {
            const response = await dana.widgetApi.cancelOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('CancelOrder test failed: ' + (e.message || e));
            }
        }
    });

    test('should fail with merchant status abnormal', async () => {
        const caseName = 'CancelOrderFailMerchantStatusAbnormal';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const response = await dana.widgetApi.cancelOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('CancelOrder test failed: ' + (e.message || e));
            }
        }
    });

    test.skip('should fail with missing parameter', async () => {
        const caseName = 'CancelOrderFailMissingParameter';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const customHeaders: Record<string, string> = {
                'X-TIMESTAMP': '' // Use an invalid signature for testing
            };

            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';

            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders
            );
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 400) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else if( e instanceof ResponseError && Number(e.status) === 400) {
                fail("Expected unauthorized failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    test.skip('should fail with order not exist', async () => {
        const caseName = 'CancelOrderFailOrderNotExist';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('CancelOrder test is a placeholder.');
        } catch (e: any) { }
    });

    test('should fail with exceed cancel window time', async () => {
        const caseName = 'CancelOrderFailExceedCancelWindowTime';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        
        try {
            const response = await dana.widgetApi.cancelOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('CancelOrder test failed: ' + (e.message || e));
            }
        }
    });

    test('should fail not allowed by agreement', async () => {
        const caseName = 'CancelOrderFailNotAllowedByAgreement';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const response = await dana.widgetApi.cancelOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('CancelOrder test failed: ' + (e.message || e));
            }
        }
    });

    test('should fail with account status abnormal', async () => {
        const caseName = 'CancelOrderFailAccountStatusAbnormal';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const response = await dana.widgetApi.cancelOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('CancelOrder test failed: ' + (e.message || e));
            }
        }
    });

    test('should fail with insufficient merchant balance', async () => {
        const caseName = 'CancelOrderFailInsufficientMerchantBalance';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const response = await dana.widgetApi.cancelOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
        }
        catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('CancelOrder test failed: ' + (e.message || e));
            }
        }
    });

    test.skip('should fail with order refunded', async () => {
        const caseName = 'CancelOrderFailOrderRefunded';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('CancelOrder test is a placeholder.');
        } catch (e: any) { }
    });

    test('should fail with invalid signature', async () => {
        const caseName = 'CancelOrderFailInvalidSignature';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        
        try {
            const customHeaders: Record<string, string> = {
                'X-SIGNATURE': 'invalid_signature' // Use an invalid signature for testing
            };

            const baseUrl: string = 'https://api.sandbox.dana.id';
            const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';

            await executeManualApiRequest(
                caseName,
                'POST',
                baseUrl + apiPath,
                apiPath,
                requestData,
                customHeaders
            );
            fail('Expected an error but the API call succeeded');
        } catch (e: any) {
            if (Number(e.status) === 401) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else if( e instanceof ResponseError && Number(e.status) === 401) {
                fail("Expected unauthorized failed but got status code " + e.status);
            } else {
                throw e;
            }
        }
    });

    test('should fail with timeout', async () => {
        const caseName = 'CancelOrderFailTimeout';
        const requestData: CancelOrderRequest = getRequest(jsonPathFile, titleCase, caseName);
        try {
            const response = await dana.widgetApi.cancelOrder(requestData);
            await assertFailResponse(jsonPathFile, titleCase, caseName, response);
        } catch (e: any) {
            if (e instanceof ResponseError) {
                await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse));
            } else {
                fail('CancelOrder test failed: ' + (e.message || e));
            }
        }
    });
});
