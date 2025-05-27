import Dana from 'dana-node-api-client';
import { v4 as uuidv4 } from 'uuid';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { fail } from 'assert';

// Import helper functions
import { getRequest, retryOnInconsistentRequest } from '../helper/util';
import { executeManualApiRequest } from '../helper/apiHelpers';
import { assertResponse, assertFailResponse } from '../helper/assertion';
import { CreateOrderByApiRequest, CancelOrderRequest } from 'dana-node-api-client/dist/payment_gateway/v1';
import { ResponseError } from 'dana-node-api-client';

// Load environment variables
dotenv.config();

// Setup constants
const titleCase = "CancelOrder";
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

describe('Cancel Order Tests', () => {
  // Create a shared order before all tests
  beforeAll(async () => {
    const createOrderRequestData: CreateOrderByApiRequest = getRequest<CreateOrderByApiRequest>(jsonPathFile, "CreateOrder", "CreateOrderApi");
    
    // Set a unique partner reference number for create order
    sharedOriginalPartnerReference = generatePartnerReferenceNo();
    createOrderRequestData.partnerReferenceNo = sharedOriginalPartnerReference;
    createOrderRequestData.merchantId = merchantId;
    
    try {
      // Create the order
      await retryOnInconsistentRequest(() => dana.paymentGatewayApi.createOrder(createOrderRequestData), 3, 2000);
      
      // Wait to ensure the order is processed in the system
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      console.log(`Shared order created with reference: ${sharedOriginalPartnerReference}`);
    } catch (e) {
      console.error('Failed to create shared order - tests cannot continue:', e);
    }
  });
  
  // Test successful cancel order
  test('should successfully cancel order', async () => {    
    const cancelOrderCaseName = "CancelOrderValidScenario";
    
    try {
      // Now cancel the order
      const cancelRequestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, cancelOrderCaseName);
      cancelRequestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
      
      const response = await dana.paymentGatewayApi.cancelOrder(cancelRequestData);
      
      // Assert the response matches the expected data using our helper function
      await assertResponse(jsonPathFile, titleCase, cancelOrderCaseName, response, { 'partnerReferenceNo': sharedOriginalPartnerReference });
    } catch (e) {
      console.error('Cancel order test failed:', e);
      throw e;
    }
  });

  //Test cancel order with transaction not found
  test('should fail when transaction not found', async () => {
    const caseName = "CancelOrderTransactionNotFound";
    try {
      // Get the request data from the JSON file
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);
      // Set the partner reference number
      // requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
      // Make the API call
      await dana.paymentGatewayApi.cancelOrder(requestData);
      fail("Expected an error but the API call succeeded");
    } catch (e: any) {
      if (e instanceof ResponseError && Number(e.status) === 404) {
        // Assert the error response matches expected format
        await assertFailResponse(jsonPathFile, titleCase, caseName, JSON.stringify(e.rawResponse),
          { 'partnerReferenceNo': sharedOriginalPartnerReference });
      } else if (e instanceof ResponseError && Number(e.status) !== 404) {
        fail("Expected transaction not found failed but got status code " + e.status);
      } else {
        throw e;
      }
    }
  });

  // Test missing mandatory field using manual API call
  test('should fail when mandatory field is missing (manual API call)', async () => {
    const caseName = "CancelOrderInvalidMandatoryField";
    
    try {
      // Get the request data from the JSON file
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);
      
      // Set the partner reference number
      requestData.originalPartnerReferenceNo = sharedOriginalPartnerReference;
      
      // Define base URL and API path
      const baseUrl: string = 'https://api.sandbox.dana.id';
      const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';
      
      // Define custom headers without X-TIMESTAMP to trigger mandatory field error
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

  // Test unauthorized access using manual API call
    test('should fail when authorization fails (manual API call)', async () => {
      const caseName = "CancelOrderUnauthorized";
  
      // Get the request data from the JSON file
      const requestData: CancelOrderRequest = getRequest<CancelOrderRequest>(jsonPathFile, titleCase, caseName);
      const partnerReferenceNo = generatePartnerReferenceNo();
      requestData.originalPartnerReferenceNo = partnerReferenceNo;
  
      // Define custom headers with invalid signature to trigger authorization error
      const customHeaders: Record<string, string> = {
        'X-SIGNATURE': '85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5'
      };
  
      try {
        // Define base URL based on environment
        const baseUrl: string = 'https://api.sandbox.dana.id';
        const apiPath: string = '/payment-gateway/v1.0/debit/cancel.htm';
  
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
