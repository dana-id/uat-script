import Dana from 'dana-node-api-client';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Import helper functions
import { getRequest, retryOnInconsistentRequest } from '../helper/util';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { fail } from 'assert';
import { ResponseError } from 'dana-node-api-client';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { CreateOrderByApiRequest, CreateOrderByRedirectRequest } from 'dana-node-api-client/dist/payment_gateway/v1';

// Load environment variables
dotenv.config();

// Setup constants
const titleCase = "CreateOrder";
const jsonPathFile = path.resolve(__dirname, '../../../resource/request/components/PaymentGateway.json');

// Initialize DANA client
const dana = new Dana({
  partnerId: process.env.X_PARTNER_ID || '',
  privateKey: process.env.PRIVATE_KEY || '',
  origin: process.env.ORIGIN || '',
  env: process.env.ENV || 'sandbox'
});

const merchantId = process.env.MERCHANT_ID || "216620010016033632482";

// Utility function to generate unique reference numbers
function generatePartnerReferenceNo(): string {
  return uuidv4();
}

describe('Create Order Tests', () => {
  // Test successful create order with redirection
  test('should successfully create order with redirection', async () => {
    const caseName = "CreateOrderRedirect";

    // Get the request data from the JSON file
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    try {
      const response = await dana.paymentGatewayApi.createOrder(requestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, caseName, response, { partnerReferenceNo });
    } catch (e) {
      console.error('Create order test failed:', e);
      throw e;
    }
  });

  // Test successful create order with api
  test('should successfully create order with api', async () => {
    const caseName = "CreateOrderApi";

    // Get the request data from the JSON file
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    try {
      const response = await retryOnInconsistentRequest(() => dana.paymentGatewayApi.createOrder(requestData), 3, 1000);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, caseName, response, { partnerReferenceNo });
    } catch (e) {
      console.error('Create order test failed:', e);
      throw e;
    }
  });

  // Test successful create order using VA bank payment method
  test('should successfully create order with VA bank payment method', async () => {
    const caseName = "CreateOrderNetworkPayPgOtherVaBank";

    // Get the request data from the JSON file
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    try {
      const response = await dana.paymentGatewayApi.createOrder(requestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, caseName, response, { partnerReferenceNo });
    } catch (e) {
      console.error('Create order test failed:', e);
      throw e;
    }
  });

  // Test successful create order using QRIS payment method
  test('should successfully create order with QRIS payment method', async () => {
    const caseName = "CreateOrderNetworkPayPgQris";

    // Get the request data from the JSON file
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    try {
      const response = await dana.paymentGatewayApi.createOrder(requestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, caseName, response, { partnerReferenceNo });
    } catch (e) {
      console.error('Create order test failed:', e);
      throw e;
    }
  });

  // Test successful create order using wallet payment method
  test('should successfully create order with wallet payment method', async () => {
    const caseName = "CreateOrderNetworkPayPgOtherWallet";

    // Get the request data from the JSON file
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    try {
      const response = await dana.paymentGatewayApi.createOrder(requestData);

      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, caseName, response, { partnerReferenceNo });
    } catch (e: any) {
      if (e instanceof ResponseError) {
        console.error('Create order test failed:', e, '\nResponse:', JSON.stringify(e.rawResponse, null, 2));
        // } else {
        // console.error('Create order test failed:', e);
      }
      throw e;
    }
  });

  // Test invalid field format
  test('should fail when field format is invalid', async () => {
    const caseName = "CreateOrderInvalidFieldFormat";

    // Get the request data from the JSON file
    const requestData: CreateOrderByRedirectRequest = getRequest<CreateOrderByRedirectRequest>(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    try {
      await dana.paymentGatewayApi.createOrder(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 400) {
        // Assert the error response matches the expected data using our helper function
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { partnerReferenceNo });

      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test inconsistent request
  test('should fail when request is inconsistent, for example duplicated partner_reference_no', async () => {
    const caseName = "CreateOrderInconsistentRequest";

    // Get the request data from the JSON file
    const requestData: any = getRequest(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    try {
      const response = await retryOnInconsistentRequest(() => dana.paymentGatewayApi.createOrder(requestData), 3, 2000);
    } catch (e) {
      console.error('Fail to call first API', e);
    }

    await new Promise(resolve => setTimeout(resolve, 500));

    try {
      // Preparing request with the same partner reference number but different amount
      requestData.amount.value = "100000.00";
      requestData.payOptionDetails[0].transAmount.value = "100000.00";

      const response = await retryOnInconsistentRequest(() => dana.paymentGatewayApi.createOrder(requestData), 3, 2000);

      fail("Expected NotFoundException but the API call succeeded");
    } catch (e) {
      if (e instanceof ResponseError && Number(e.status) === 404) {
        // Assert the error response matches the expected data using our helper function
        await assertFailResponse(jsonPathFile, titleCase, caseName, e.rawResponse, { partnerReferenceNo });
      } else if (e instanceof ResponseError && Number(e.status) !== 404) {
        fail(`Expected bad request failed but got status code ${e.status}. Response:\n${JSON.stringify(e.rawResponse, null, 2)}`);
      } else {
        throw e;
      }
    }
  });

  // Test missing mandatory field using manual API call
  test('should fail when mandatory field is missing (manual API call)', async () => {
    const caseName = "CreateOrderInvalidMandatoryField";

    // Get the request data from the JSON file
    const requestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, titleCase, caseName);

    // Set a unique partner reference number
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;
    requestData.merchantId = merchantId;

    // Define custom headers without X-TIMESTAMP to trigger mandatory field error
    const customHeaders: Record<string, string> = {
      // Omit X-TIMESTAMP to trigger mandatory field error
      'X-TIMESTAMP': ''
    };

    try {
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/payment-host-to-host.htm';

      // Make direct API call with custom headers - this should fail
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
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse), { partnerReferenceNo });
      } else if (e instanceof ResponseError && Number(e.status) !== 400) {
        fail("Expected bad request failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test unauthorized access using manual API call
  test('should fail when authorization fails (manual API call)', async () => {
    const caseName = "CreateOrderUnauthorized";

    // Get the request data from the JSON file
    const requestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, titleCase, caseName);
    const partnerReferenceNo = generatePartnerReferenceNo();
    requestData.partnerReferenceNo = partnerReferenceNo;

    // Define custom headers with invalid signature to trigger authorization error
    const customHeaders: Record<string, string> = {
      'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
    };

    try {
      // Define base URL based on environment
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/payment-host-to-host.htm';

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
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse), { partnerReferenceNo });
      } else if (e instanceof ResponseError && Number(e.status) !== 401) {
        fail("Expected unauthorized failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });
});
