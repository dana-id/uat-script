import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';

dotenv.config();

const titleCase = 'FinishNotify';
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

describe.skip('FinishNotify Tests', () => {
    test.skip('should successfully notify finish', async () => {
        const caseName = 'FinishNotifySuccess';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('FinishNotify test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with internal server error', async () => {
        const caseName = 'FinishNotifyFailInternalServerError';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('FinishNotify test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with order not paid', async () => {
        const caseName = 'FinishNotifyFailOrderNotPaid';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('FinishNotify test is a placeholder.');
        } catch (e: any) { }
    });
});
