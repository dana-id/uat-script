/**
 * @fileoverview Manual API Request Utilities for DANA Integration Testing
 * 
 * This module provides utilities for making direct API requests with custom headers
 * and authentication mechanisms. It's primarily used for testing edge cases where
 * the standard SDK behavior needs to be bypassed, such as testing invalid signatures,
 * missing headers, or custom authentication scenarios.
 * 
 * Key Features:
 * - Direct HTTP API calls bypassing SDK validation
 * - Custom header injection capabilities
 * - Manual signature generation and validation
 * - Support for negative testing scenarios
 */

import { v4 as uuidv4 } from 'uuid';
import dotenv from 'dotenv';
import axios from 'axios';
import { BaseAPI, Configuration, DanaSignatureUtil } from 'dana-node/runtime';
import { HTTPMethod } from 'dana-node/runtime';

// Load environment variables from .env file
dotenv.config();

/**
 * Executes an API request directly with manually constructed headers and request parameters
 * 
 * This function provides direct access to the DANA API by constructing HTTP requests
 * with custom headers and authentication. It's designed for testing scenarios where
 * the standard SDK behavior needs to be bypassed or modified, such as:
 * - Testing with invalid or missing authentication headers
 * - Validating API behavior with malformed requests
 * - Testing edge cases and error handling scenarios
 * 
 * The function handles signature generation using the DANA signature utility and
 * allows for custom header injection to override default values.
 * 
 * @param {string} caseName - Test case name for logging and debugging purposes
 * @param {string} method - HTTP method (e.g., "POST", "GET", "PUT", "DELETE")
 * @param {string} endpoint - Complete API endpoint URL including domain
 * @param {string} resourcePath - API resource path used for signature generation (e.g., "/payment-gateway/v1.0/debit/create.htm")
 * @param {Record<string, any>} requestObj - Request payload object to send in the request body
 * @param {Record<string, string>} customHeaders - Custom headers to include or override defaults (default: {})
 * @returns {Promise<ManualApiResponse>} Response object containing data, status, headers, and raw response
 * @throws {Error} Throws error if signature generation fails or request execution fails
 * 
 * @example
 * ```typescript
 * // Test with invalid signature
 * const response = await executeManualApiRequest(
 *   "TestInvalidSignature",
 *   "POST",
 *   "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/create.htm",
 *   "/payment-gateway/v1.0/debit/create.htm",
 *   requestData,
 *   { 'X-SIGNATURE': 'invalid_signature_for_testing' }
 * );
 * 
 * // Test with missing timestamp
 * const response = await executeManualApiRequest(
 *   "TestMissingTimestamp",
 *   "POST",
 *   "https://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm",
 *   "/payment-gateway/v1.0/debit/cancel.htm",
 *   requestData,
 *   { 'X-TIMESTAMP': '' }
 * );
 * ```
 */
async function executeManualApiRequest(
  caseName: string,
  method: string,
  endpoint: string,
  resourcePath: string,
  requestObj: Record<string, any>,
  customHeaders: Record<string, string> = {}
): Promise<{ data: any; status: number; headers: any; rawResponse: any }> {

  // Initialize default headers required for DANA API authentication
  const headers: Record<string, string> = {
    'X-PARTNER-ID': process.env.X_PARTNER_ID || '',
    'CHANNEL-ID': process.env.CHANNEL_ID || '',
    'Content-Type': 'application/json',
    'ORIGIN': process.env.ORIGIN || '',
    'X-EXTERNAL-ID': uuidv4() // Generate unique external ID for request tracking
  };

  // Generate timestamp in Jakarta timezone (UTC+7) format
  const now = new Date();
  const offset = '+07:00'; // Jakarta timezone offset
  const isoString = now.toISOString();
  const timeStamp = isoString.substring(0, 19).replace('Z', '') + offset;

  headers['X-TIMESTAMP'] = timeStamp;

  // Generate DANA signature for authentication using the SDK utility
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
    throw new Error('Signature generation failed - check private key and parameters');
  }

  // Apply custom headers (these will override default values for testing purposes)
  if (customHeaders) {
    Object.keys(customHeaders).forEach(key => {
      headers[key] = customHeaders[key];
    });
  }

  // Log request details for debugging
  console.log(`Executing manual API request for case: ${caseName}`);
  console.log(`Method: ${method}, Endpoint: ${endpoint}`);
  console.log(`Headers:`, headers);

  // Alternative HTTP implementation using axios (commented out but available for reference)
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

  // Create DANA SDK configuration for API client
  const configParams = {
    basePath: 'https://api.sandbox.dana.id'
  };

  const config = new Configuration(configParams);
  const api_client = new BaseAPI(config);

  // Execute the API request using the DANA SDK's underlying HTTP client
  // @ts-ignore - Ignore private property access warnings for testing purposes
  const response = await api_client.request({
    method: method as HTTPMethod,
    path: resourcePath,
    headers: headers,
    body: requestObj
  });

  // Return standardized response object for test validation
  return {
    data: response.body,
    status: response.status,
    headers: response.headers,
    rawResponse: response.body // Include raw response for detailed error analysis
  };
}

/**
 * Export manual API request utilities
 * 
 * These exports provide direct API access capabilities for advanced testing scenarios:
 * - Manual request execution with custom headers
 * - Signature generation bypassing
 * - Error scenario simulation
 */
export {
  executeManualApiRequest,
};
