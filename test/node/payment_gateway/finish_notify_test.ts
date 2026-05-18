import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';

import { getRequest, retryOnInconsistentRequest, generateFormattedDate } from '../helper/util';
import { assertResponse } from '../helper/assertion';
import { paymentCodeFromCreateOrderResponse, payVirtualAccountSandbox } from '../helper/sandboxTools';

dotenv.config();

const titleCase = 'CreateOrder';
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json');
const createOrderRequestCaseFinishNotify = 'CreateOrderApi';
const createOrderAssertCaseFinishNotify = 'CreateOrderApi';
const notificationN8nURL = 'https://n8n.automation.dana.id/webhook/3676a08f-b06e-416c-b6cd-bea04f71c4d5';
const finishNotifyDefaultValidUpToOffsetSeconds = 360;
const finishNotifyValidUpToOffsetExpiredSeconds = 2 * 60 + 15;

const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox',
});

function patchCreateOrderAPIForFinishNotify(requestData: any, amount: string) {
  if (requestData?.amount) {
    requestData.amount.value = amount;
  }
  requestData.payOptionDetails = [
    {
      payMethod: 'VIRTUAL_ACCOUNT',
      payOption: 'VIRTUAL_ACCOUNT_CIMB',
      transAmount: { value: amount, currency: 'IDR' },
    },
  ];
  if (Array.isArray(requestData?.urlParams)) {
    requestData.urlParams.forEach((u: any) => {
      if (u?.type === 'NOTIFICATION') {
        u.url = notificationN8nURL;
      }
    });
  }
}

async function createOrderAPIFinishNotifyOnce(amount: string, validUpTo: string = '') {
  return retryOnInconsistentRequest(async () => {
    const requestData: any = getRequest(jsonPathFile, titleCase, createOrderRequestCaseFinishNotify);
    const partnerReferenceNo = uuidv4();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.validUpTo =
      validUpTo !== '' ? validUpTo : generateFormattedDate(finishNotifyDefaultValidUpToOffsetSeconds, 7);
    patchCreateOrderAPIForFinishNotify(requestData, amount);
    const response = await dana.paymentGatewayApi.createOrder(requestData);
    return { partnerReferenceNo, response };
  }, 3, 2000);
}

describe('Payment Gateway - Finish Notify Tests', () => {
  test('TransactionSuccessNotify - create order API with NOTIFICATION url and amount 11011.00', async () => {
    const { partnerReferenceNo, response } = await createOrderAPIFinishNotifyOnce('11011.00', '');
    await assertResponse(jsonPathFile, titleCase, createOrderAssertCaseFinishNotify, response, { partnerReferenceNo });
    await payVirtualAccountSandbox(paymentCodeFromCreateOrderResponse(response));
  });

  test('InternalServerErrorNotify - create order API with NOTIFICATION url and amount 11012.00', async () => {
    const { partnerReferenceNo, response } = await createOrderAPIFinishNotifyOnce('11012.00', '');
    await assertResponse(jsonPathFile, titleCase, createOrderAssertCaseFinishNotify, response, { partnerReferenceNo });
    await payVirtualAccountSandbox(paymentCodeFromCreateOrderResponse(response));
  });

  test('ExpiredNotify - create order API with NOTIFICATION url, amount 11013.00, validUpTo now+2m15s', async () => {
    const validUpTo = generateFormattedDate(finishNotifyValidUpToOffsetExpiredSeconds, 7);
    const { partnerReferenceNo, response } = await createOrderAPIFinishNotifyOnce('11013.00', validUpTo);
    await assertResponse(jsonPathFile, titleCase, createOrderAssertCaseFinishNotify, response, { partnerReferenceNo });
  });
});
