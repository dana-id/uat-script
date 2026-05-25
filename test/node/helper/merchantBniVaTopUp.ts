import Dana from 'dana-node';
import {
  QueryAssetCardListRequestAssetTypeListEnum,
  QueryAssetCardListRequestEnableOnlyEnum,
} from 'dana-node/merchant_management/v1';
import dotenv from 'dotenv';
import { hashData } from './bniHashUtil';

dotenv.config();

const DANA_SANDBOX_BASE_URL = 'https://api.sandbox.dana.id';
const BNI_VA_TOP_UP_PATH = '/ifcsupergw/bni/topup/merchant/request.htm';

const DEFAULT_BNI_CLIENT_ID = '910';
const DEFAULT_BNI_SECRET_KEY = '9546d5f69af2ed3bc603834446985628';
const BNI_INST_ID = 'BNIC1ID';
const MERCHANT_DEPOSIT_ACCOUNT_TYPE = 'MERCHANT_DEPOSIT_ACCOUNT';
const MERCHANT_QUERY_LOGIN_TYPE_ROLE = 'ROLE';
const MERCHANT_DEPOSIT_TOP_UP_THRESHOLD = 1_000_000;

let done = false;
let failure: Error | null = null;
let ensurePromise: Promise<void> | null = null;
let danaClient: Dana | null = null;

function getDanaClient(): Dana {
  if (!danaClient) {
    danaClient = new Dana({
      partnerId: process.env.X_PARTNER_ID || '',
      privateKey: process.env.PRIVATE_KEY || '',
      origin: process.env.ORIGIN || '',
      env: process.env.ENV || 'sandbox',
      clientSecret: process.env.CLIENT_SECRET || '',
    });
  }
  return danaClient;
}

export async function ensureMerchantBniVaTopUp(): Promise<void> {
  if (done) {
    if (failure) {
      throw failure;
    }
    return;
  }
  if (!ensurePromise) {
    ensurePromise = (async () => {
      try {
        await bniVaTopUpMerchantOnce();
      } catch (err) {
        failure = err instanceof Error ? err : new Error(String(err));
        throw failure;
      } finally {
        done = true;
      }
    })();
  }
  await ensurePromise;
}

async function bniVaTopUpMerchantOnce(): Promise<void> {
  const depositBalance = await queryMerchantDepositTotalAmount();
  if (depositBalance >= MERCHANT_DEPOSIT_TOP_UP_THRESHOLD) {
    console.log(
      `Skipping BNI VA top-up: merchant deposit balance=${depositBalance} >= threshold=${MERCHANT_DEPOSIT_TOP_UP_THRESHOLD}`,
    );
    return;
  }
  console.log(
    `Merchant deposit balance=${depositBalance} < threshold=${MERCHANT_DEPOSIT_TOP_UP_THRESHOLD}; proceeding with BNI VA top-up`,
  );

  const virtualAccount = await queryBniMerchantVirtualAccount();
  await postBniVaTopUpMerchant(virtualAccount);
}

async function queryMerchantDepositTotalAmount(): Promise<number> {
  const merchantId = process.env.MERCHANT_ID || '';
  if (!merchantId) {
    throw new Error('MERCHANT_ID is required to query merchant info');
  }

  const response = await getDanaClient().merchantManagementApi.queryMerchantInfo({
    roleId: merchantId,
    loginType: MERCHANT_QUERY_LOGIN_TYPE_ROLE,
    isQueryAccount: true,
  });

  const resultInfo = response?.response?.body?.resultInfo;
  if (resultInfo?.resultStatus !== 'S') {
    throw new Error(`queryMerchantInfo failed: ${JSON.stringify(resultInfo)}`);
  }

  const accounts = response?.response?.body?.merchantInformation?.accounts || [];
  for (const account of accounts) {
    if (account?.accountType === MERCHANT_DEPOSIT_ACCOUNT_TYPE) {
      return parseAccountMappedTotalAmount(account as Record<string, unknown>);
    }
  }

  throw new Error(`queryMerchantInfo: ${MERCHANT_DEPOSIT_ACCOUNT_TYPE} account not found`);
}

function parseAccountMappedTotalAmount(account: Record<string, unknown>): number {
  const mappedTotal = account.mappedTotalAmount;
  if (mappedTotal && typeof mappedTotal === 'object' && mappedTotal !== null && 'amount' in mappedTotal) {
    return parseAmountValue((mappedTotal as { amount: unknown }).amount);
  }

  const totalAmount = account.totalAmount;
  if (typeof totalAmount === 'string' && totalAmount) {
    const parsed = JSON.parse(totalAmount) as { amount?: unknown };
    return parseAmountValue(parsed.amount);
  }

  throw new Error('deposit account amount missing in queryMerchantInfo response');
}

function parseAmountValue(value: unknown): number {
  if (typeof value === 'string') {
    return Number.parseInt(value, 10);
  }
  if (typeof value === 'number') {
    return Math.trunc(value);
  }
  throw new Error(`unsupported amount format: ${String(value)}`);
}

async function queryBniMerchantVirtualAccount(): Promise<string> {
  const memberId = process.env.MERCHANT_ID || '';
  if (!memberId) {
    throw new Error('MERCHANT_ID is required to query merchant BNI VA');
  }

  const response = await getDanaClient().merchantManagementApi.queryAssetCardList({
    memberId,
    enableOnly: QueryAssetCardListRequestEnableOnlyEnum.True,
    assetTypeList: [QueryAssetCardListRequestAssetTypeListEnum.VaAccount],
  });

  const resultInfo = response?.response?.body?.resultInfo;
  if (resultInfo?.resultStatus !== 'S') {
    throw new Error(`queryAssetCardList failed: ${JSON.stringify(resultInfo)}`);
  }

  for (const card of response?.response?.body?.assetCardList || []) {
    if (card?.assetType === 'VA_ACCOUNT' && card?.instId === BNI_INST_ID) {
      const cardIndexNo = card?.cardIndexNo || '';
      if (cardIndexNo) {
        return cardIndexNo;
      }
    }
  }

  throw new Error(`BNI VA card not found in assetCardList (assetType=VA_ACCOUNT instId=${BNI_INST_ID})`);
}

async function postBniVaTopUpMerchant(virtualAccount: string): Promise<void> {
  const { trxId, datetimePayment, datetimePaymentIso } = jakartaTimestamps();

  const integrationBody = {
    trx_amount: '1000',
    trx_id: trxId,
    virtual_account: virtualAccount,
    customer_name: 'rudy',
    payment_amount: '1000000000',
    cumulative_payment_amount: '1000',
    payment_ntb: '233171',
    datetime_payment: datetimePayment,
    datetime_payment_iso8601: datetimePaymentIso,
  };

  const clientId = process.env.BNI_VA_TOP_UP_CLIENT_ID || DEFAULT_BNI_CLIENT_ID;
  const secretKey = process.env.BNI_VA_TOP_UP_SECRET_KEY || DEFAULT_BNI_SECRET_KEY;
  const integrationJson = JSON.stringify(integrationBody);
  const data = hashData(integrationJson, clientId, secretKey);

  const payload = JSON.stringify({ client_id: clientId, data });
  await httpPostJson(DANA_SANDBOX_BASE_URL + BNI_VA_TOP_UP_PATH, payload, true);
}

async function httpPostJson(url: string, body: string, checkBniStatus: boolean): Promise<string> {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body,
  });

  const responseText = await res.text();
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}: ${responseText}`);
  }

  if (checkBniStatus) {
    try {
      const parsed = JSON.parse(responseText);
      if (parsed?.status && parsed.status !== '' && parsed.status !== '000') {
        throw new Error(`BNI VA top-up rejected: ${responseText}`);
      }
    } catch (err) {
      if (err instanceof SyntaxError) {
        return responseText;
      }
      throw err;
    }
  }

  return responseText;
}

function jakartaTimestamps(): { trxId: string; datetimePayment: string; datetimePaymentIso: string } {
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });

  const parts = formatter.formatToParts(new Date());
  const get = (type: Intl.DateTimeFormatPartTypes) =>
    parts.find((p) => p.type === type)?.value || '00';

  const year = get('year');
  const month = get('month');
  const day = get('day');
  const hour = get('hour');
  const minute = get('minute');
  const second = get('second');

  return {
    trxId: `${year}${month}${day}${hour}${minute}${second}`,
    datetimePayment: `${year}-${month}-${day} ${hour}:${minute}:${second}`,
    datetimePaymentIso: `${year}-${month}-${day}T${hour}:${minute}:${second}+07:00`,
  };
}
