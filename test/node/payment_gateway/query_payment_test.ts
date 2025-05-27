import Dana from 'dana-node-api-client';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';

// Import helper functions
import { getRequest, retryOnGeneralErrorSync } from '../helper/util';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CreateOrderByApiRequest, QueryPaymentRequest } from 'dana-node-api-client/dist/payment_gateway/v1';
import { ResponseError } from 'dana-node-api-client';

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

describe('Query Payment Tests', () => {
  // Shared variable to store the order reference


  // Create a shared order before all tests
  beforeAll(async () => {
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderApi");

    // Set a unique partner reference number for create order
    sharedOriginalPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalPartnerReference;
    createOrderRequestData.merchantId = merchantId;

    try {
      // Create the order
      await dana.paymentGatewayApi.createOrder(createOrderRequestData);

      // Wait to ensure the order is processed in the system
      await new Promise(resolve => setTimeout(resolve, 2000));

      console.log(`Shared order created with reference: ${sharedOriginalPartnerReference}`);
    } catch (e) {
      console.error('Failed to create shared order - tests cannot continue:', e);
    }
  });

  // Test successful query payment
  test('should successfully query payment', async () => {
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

  // Test invalid field format
  test('should fail when field format is invalid', async () => {
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

      if (e instanceof ResponseError && Number(e.status) === 400) {
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test missing mandatory field using manual API call
  test('should fail when mandatory field is missing (manual API call)', async () => {
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
      if (e instanceof ResponseError && Number(e.status) === 400) {
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

  // Test transaction not found using manual API call
  test('should fail when transaction is not found (manual API call)', async () => {
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

  // Test unauthorized access using manual API call
  test('should fail when authorization fails (manual API call)', async () => {
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
      if (e instanceof ResponseError && Number(e.status) === 401) {
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
});
