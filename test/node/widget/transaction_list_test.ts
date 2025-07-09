import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';

dotenv.config();

const titleCase = 'TransactionList';
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

describe.skip('TransactionList Tests', () => {
    test.skip('should successfully get transaction list', async () => {
        const caseName = 'TransactionListSuccess';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('TransactionList test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with invalid param', async () => {
        const caseName = 'TransactionListFailInvalidParam';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('TransactionList test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with data not available', async () => {
        const caseName = 'TransactionListFailDataNotAvailable';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('TransactionList test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with system error', async () => {
        const caseName = 'TransactionListFailSystemError';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('TransactionList test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with invalid signature', async () => {
        const caseName = 'TransactionListFailInvalidSignature';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('TransactionList test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with invalid token', async () => {
        const caseName = 'TransactionListFailInvalidToken';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('TransactionList test is a placeholder.');
        } catch (e: any) { }
    });

    test.skip('should fail with invalid mandatory parameter', async () => {
        const caseName = 'TransactionListFailInvalidMandatoryParameter';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('TransactionList test is a placeholder.');
        } catch (e: any) { }
    });
});
