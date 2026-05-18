const SANDBOX_TOOLS_EXECUTE_URL = 'https://dashboard-sandbox.dana.id/merchant-portal-app/api/sandbox-tools/execute';
const TRANSFER_VA_PAYMENT_ENDPOINT = '/v1.0/transfer-va/payment.htm';

export function paymentCodeFromCreateOrderResponse(response: any): string {
  const paymentCode = response?.additionalInfo?.paymentCode;
  if (!paymentCode || typeof paymentCode !== 'string') {
    throw new Error('paymentCode missing in create order response');
  }
  return paymentCode;
}

export async function payVirtualAccountSandbox(virtualAccountNo: string): Promise<void> {
  const res = await fetch(SANDBOX_TOOLS_EXECUTE_URL, {
    method: 'POST',
    headers: {
      accept: 'application/json',
      'accept-language': 'en,id-ID;q=0.9,id;q=0.8,en-US;q=0.7',
      'content-type': 'application/json',
      origin: 'https://dashboard.dana.id',
      referer: 'https://dashboard.dana.id/',
    },
    body: JSON.stringify({
      urlEndpoint: TRANSFER_VA_PAYMENT_ENDPOINT,
      requestBody: { virtualAccountNo },
    }),
  });

  const body = await res.text();
  if (!res.ok) {
    throw new Error(`sandbox VA payment failed: status=${res.status} body=${body}`);
  }
}
