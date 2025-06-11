import { v4 as uuidv4 } from 'uuid';
import dotenv from 'dotenv';
import axios from 'axios';
import { BaseAPI, Configuration, DanaSignatureUtil } from 'dana-node/dist/runtime';
import { HTTPMethod } from 'dana-node/dist/runtime';

// Load environment variables
dotenv.config();

/**
 * Executes an API request directly with manually constructed headers and request object
 * Similar to Go's ExecuteAPIRequestWithCustomHeaders
 * 
 * @param {string} method - HTTP method (e.g., "POST", "GET")
 * @param {string} endpoint - Full API endpoint URL
 * @param {string} resourcePath - API resource path for signature generation
 * @param {Object} requestObj - Request object to send
 * @param {Object} customHeaders - Custom headers to include (will override defaults)
 * @returns {Promise<Object>} Response with data, status and headers
 */
async function executeManualApiRequest(caseName: string, method: string, endpoint: string, resourcePath: string, requestObj: Record<string, any>, customHeaders: Record<string, string> = {}): Promise<{ data: any; status: number; headers: any; rawResponse: any }> {
  // Setup default headers
  const headers: Record<string, string> = {
    'X-PARTNER-ID': process.env.X_PARTNER_ID || '',
    'CHANNEL-ID': process.env.CHANNEL_ID || '',
    'Content-Type': 'application/json',
    'ORIGIN': process.env.ORIGIN || '',
    'X-EXTERNAL-ID': uuidv4()
  };

  const now = new Date();
  const offset = '+07:00'; // Jakarta timezone
  const isoString = now.toISOString();
  const timeStamp = isoString.substring(0, 19).replace('Z', '') + offset;

  headers['X-TIMESTAMP'] = timeStamp;

  // Generate signature if resourcePath is provided
  const requestBody = JSON.stringify(requestObj);
  const snapGeneratedHeaders = DanaSignatureUtil.generateSnapB2BScenarioSignature(
    method,
    resourcePath,
    requestBody,
    process.env.PRIVATE_KEY || '',
    timeStamp
  );

  if (snapGeneratedHeaders) {
    headers['X-SIGNATURE'] = snapGeneratedHeaders;
  } else {
    throw new Error('Signature generation failed');
  }

  // Apply any custom headers (these will override defaults)
  if (customHeaders) {
    Object.keys(customHeaders).forEach(key => {
      headers[key] = customHeaders[key];
    });
  }

  // // Execute the API call using axios (direct HTTP request)
  // const response = await axios({
  //   method: method,
  //   url: endpoint,
  //   headers: headers,
  //   data: requestObj,
  //   validateStatus: function (status) {
  //     // Return true for any status code (don't throw errors)
  //     return true;
  //   }
  // });

  // Create configuration parameters as an object literal
  const configParams = {
    basePath: 'https://api.sandbox.dana.id'
  };

  const config = new Configuration(
    configParams
  );

  const api_client = new BaseAPI(
    config
  );

  // @ts-ignore - Ignore private property access warnings
  const response = await api_client.request({
    method: method as HTTPMethod,
    path: resourcePath,
    headers: headers,
    body: requestObj
  });



  // Always return the response object regardless of status code
  return {
    data: response.body,
    status: response.status,
    headers: response.headers,
    rawResponse: response.body
  };
}

export {
  executeManualApiRequest,
};
