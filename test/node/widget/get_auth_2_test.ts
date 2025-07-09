import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';
import { getRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';

dotenv.config();

const titleCase = 'GetAuth';
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

describe.skip('GetAuth Tests', () => {
    test.skip('should successfully get auth', async () => {
        const caseName = 'GetAuthSuccess';
        const requestData: any = getRequest(jsonPathFile, titleCase, caseName);
        try {
            fail('GetAuth test is a placeholder.');
        } catch (e: any) { }
    });
});
