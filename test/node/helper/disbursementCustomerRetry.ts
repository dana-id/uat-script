import { ResponseError } from 'dana-node';

export const CUSTOMER_NUMBERS = ['62811742234', '62817345544', '62817345545'] as const;

export function isForbiddenResponseCode(code: string | undefined | null): boolean {
  return !!code && (String(code).startsWith('403') || String(code).startsWith('404'));
}

export function isForbiddenError(error: unknown): boolean {
  if (error instanceof ResponseError && (Number(error.status) === 403 || Number(error.status) === 404)) {
    return true;
  }
  const body = (error as { body?: unknown })?.body ?? (error as { response?: { body?: unknown } })?.response?.body;
  if (typeof body === 'string') {
    try {
      const parsed = JSON.parse(body) as { responseCode?: string };
      return isForbiddenResponseCode(parsed.responseCode);
    } catch {
      return body.includes('403') || body.includes('404');
    }
  }
  if (body && typeof body === 'object' && 'responseCode' in body) {
    return isForbiddenResponseCode(String((body as { responseCode?: string }).responseCode));
  }
  return false;
}

export function responseCodeFromPayload(payload: unknown): string | undefined {
  if (!payload || typeof payload !== 'object') {
    return undefined;
  }
  return (payload as { responseCode?: string }).responseCode;
}

export async function withCustomerNumberRetry<T>(
  operation: (customerNumber: string) => Promise<T>,
  getResponseCode?: (result: T) => string | undefined,
): Promise<{ result: T; customerNumber: string }> {
  let lastError: unknown;
  for (const customerNumber of CUSTOMER_NUMBERS) {
    try {
      const result = await operation(customerNumber);
      const code = getResponseCode?.(result) ?? responseCodeFromPayload(result);
      if (isForbiddenResponseCode(code)) {
        lastError = new Error(`responseCode=${code}`);
        continue;
      }
      return { result, customerNumber };
    } catch (error) {
      if (isForbiddenError(error)) {
        lastError = error;
        continue;
      }
      throw error;
    }
  }
  throw lastError ?? new Error(`All customer numbers returned 403/404: ${CUSTOMER_NUMBERS.join(', ')}`);
}
