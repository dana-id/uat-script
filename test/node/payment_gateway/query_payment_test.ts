import Dana from 'dana-node';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';

// Import helper functions
import { getRequest } from '../helper/util';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CreateOrderByApiRequest, QueryPaymentRequest, CancelOrderRequest } from 'dana-node/dist/payment_gateway/v1';
import { ResponseError } from 'dana-node';

// Load environment variables
dotenv.config();

// Setup constants
const titleCase = "QueryPayment";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json');

// Initialize DANA client
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

const merchantId = process.env.MERCHANT_ID || "";

// Utility function to generate unique reference numbers
function generatePartnerReferenceNo(): string {
  return uuidv4();
}

let sharedOriginalPartnerReference: string;
let sharedOriginalCanceledPartnerReference: string;
let sharedOriginalPaidPartnerReference: string;

describe('Query Payment Tests', () => {

  async function createOrder() {
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderApi");
    createOrderRequestData.merchantId = merchantId;
    sharedOriginalPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalPartnerReference
    await dana.paymentGatewayApi.createOrder(createOrderRequestData);
  }

  async function createPaidOrder() {
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderApi");
    createOrderRequestData.merchantId = merchantId;
    sharedOriginalPaidPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalPaidPartnerReference;
    createOrderRequestData.amount.value = "50001.00"; // Set a valid amount to simulate a paid order
    await dana.paymentGatewayApi.createOrder(createOrderRequestData);
  }

  async function createCanceledOrder() {
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderApi");
    createOrderRequestData.merchantId = merchantId;
    sharedOriginalCanceledPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalCanceledPartnerReference;
    await dana.paymentGatewayApi.createOrder(createOrderRequestData);

    const cancelOrderRequestData = getRequest<CancelOrderRequest>(jsonPathFile, "CancelOrder", "CancelOrderValidScenario");
    cancelOrderRequestData.originalPartnerReferenceNo = sharedOriginalCanceledPartnerReference;
    await dana.paymentGatewayApi.cancelOrder(cancelOrderRequestData);
  }

  // Create a shared order before all tests
  beforeAll(async () => {

    try {
      await createOrder()

      console.log(`Shared order created with reference: ${sharedOriginalPartnerReference}`);
    } catch (e) {
      console.error('Failed to create shared order - tests cannot continue:', e);
    }

    try {
      await createPaidOrder()
      console.log(`Shared paid order created with reference: ${sharedOriginalPartnerReference}`);
    } catch (e) {
      console.error('Failed to create shared paid order - tests cannot continue:', e);
    }

    try {
      await createCanceledOrder()

      console.log(`Shared canceled order created with reference: ${sharedOriginalCanceledPartnerReference}`);
    } catch (e) {
      console.error('Failed to create shared canceled order - tests cannot continue:', e);
    }
  });

  // Test successful query pending
  test('should successfully query payment with status created but not paid (INIT)', async () => {
    const queryPaymentCaseName = "QueryPaymentCreatedOrder";

    try {
      // Now query that same order
      const queryRequestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, queryPaymentCaseName);
      queryRequestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;

      const response = await dana.paymentGatewayApi.queryPayment(queryRequestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, queryPaymentCaseName, response, { 'partnerReferenceNo': sharedOriginalPartnerReference });
    } catch (e) {
      console.error('Query payment test failed:', e);
      throw e;
    }
  });

  // Test successful query payment
  test('should successfully query payment with status canceled (CANCELLED)', async () => {
    const queryPaymentCaseName = "QueryPaymentCanceledOrder";

    try {
      // Now query that same order
      const queryRequestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, queryPaymentCaseName);
      queryRequestData.originalPartnerReferenceNo = sharedOriginalCanceledPartnerReference;

      const response = await dana.paymentGatewayApi.queryPayment(queryRequestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, queryPaymentCaseName, response, { 'partnerReferenceNo': sharedOriginalCanceledPartnerReference });
    } catch (e) {
      console.error('Query payment test failed:', e);
      throw e;
    }
  });

  // Test invalid field format
  test('should fail when field format is invalid (ex: invalid format for X-TIMESTAMP)', async () => {
    const caseName = "QueryPaymentInvalidFormat";

    try {
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/status.htm';
      // await dana.paymentGatewayApi.queryPayment(requestData);

      const customHeaders: Record<string, string> = {
        // Override the signature with an invalid one
        'X-TIMESTAMP': new Date(Date.now() + 7 * 60 * 60 * 1000)
          .toISOString()
          .replace('T', ' ')
          .replace(/\.\d{3}Z$/, '+07:00')
          .replace(/-/g, '-')
          .replace(/:/g, ':')
      };

      // Make direct API call - this should fail
      await executeManualApiRequest(
        caseName,
        "POST",
        baseUrl + apiPath,
        apiPath,
        requestData,
        customHeaders
      );

      fail("Expected an error but the API call succeeded ");
    } catch (e: any) {

      if (Number(e.status) === 400) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test missing mandatory field using manual API call
  test('should fail when mandatory field is missing (ex: missing X-TIMESTAMP header in request)', async () => {
    const caseName = "QueryPaymentInvalidMandatoryField";

    try {
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Intentionally set empty originalPartnerReferenceNo to trigger mandatory field error
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/status.htm';

      // Define custom headers with invalid signature to trigger authorization error
      const customHeaders: Record<string, string> = {
        'X-TIMESTAMP': ''
      };

      // Make direct API call - this should fail
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
      if (Number(e.status) === 400) {
        // Assert the error response matches expected format
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test unauthorized access using manual API call
  test('should fail when authorization fails (ex: wrong signature)', async () => {
    const caseName = "QueryPaymentUnauthorized";
    const titleCase = "QueryPaymentApi";

    try {
      // We'll query using the shared original partner reference
      // that was created in the beforeAll hook
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Use the shared original partner reference number
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;

      // Define base URL and API path
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/status.htm';

      // Define custom headers with invalid signature to trigger authorization error
      const customHeaders: Record<string, string> = {
        // Override the signature with an invalid one
        'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
      };

      // Make direct API call with custom headers
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
      if (Number(e.status) === 401) {
        // Assert the error response matches expected format
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 401) {
        fail("Expected unauthorized failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test transaction not found using manual API call
  test('should fail when transaction is not found', async () => {
    const caseName = "QueryPaymentTransactionNotFound";
    const titleCase = "QueryPayment";

    try {
      // Get the request data from the JSON file
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Set a unique partner reference number for the query with _NOT_FOUND suffix to ensure it doesn't exist
      // This matches the pattern used in the Python/Go tests
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference + "_NOT_FOUND";

      // Make the API call
      await dana.paymentGatewayApi.queryPayment(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {

      if (e instanceof ResponseError && Number(e.status) === 404) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 404) {
        fail("Expected nout found request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test general error scenario
  test('should handle general error scenario (QueryPaymentGeneralError)', async () => {
    const caseName = "QueryPaymentGeneralError";
    const titleCase = "QueryPayment";

    try {
      // Prepare request data that will trigger a general error
      const requestData: QueryPaymentRequest = getRequest<QueryPaymentRequest>(jsonPathFile, titleCase, caseName);

      // Use a deliberately malformed or problematic reference number to simulate a general error
      requestData.originalPartnerReferenceNo = sharedOriginalPaidPartnerReference;

      // Make the API call
      await dana.paymentGatewayApi.queryPayment(requestData);

      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      // Expecting a 500 or general error
      if (e instanceof ResponseError && Number(e.status) === 500) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { 'partnerReferenceNo': "INVALID_GENERAL_ERROR_REF" });
      } else if (e instanceof ResponseError && Number(e.status) !== 500) {
        fail("Expected general error but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });
});
