/**
 * @fileoverview Utility Functions for DANA Integration Testing
 * 
 * This module provides core utility functions for the DANA Payment Gateway and Widget API testing suite.
 * It includes functions for JSON data manipulation, request/response handling, retry mechanisms,
 * and payment automation capabilities using browser automation.
 * 
 * Key Features:
 * - JSON test data parsing and extraction
 * - Request/response data handling with type safety
 * - Retry mechanisms for handling transient failures
 * - Browser automation for payment flows
 */

/* eslint-disable */
import * as fs from 'fs';
import * as path from 'path';

/**
 * Splits a string into parts using the specified delimiter
 * 
 * This utility function provides a simple string splitting functionality
 * for parsing delimited data in test scenarios.
 *
 * @param {string} inputString - The string to split
 * @param {string} delimiter - The delimiter to use for splitting
 * @returns {string[]} Array of substrings after splitting
 * @example
 * ```typescript
 * const parts = getRequestWithDelimiter("a,b,c", ",");
 * // Returns: ["a", "b", "c"]
 * ```
 */
function getRequestWithDelimiter(inputString: string, delimiter: string): string[] {
  return inputString.split(delimiter);
}

/**
 * Reads a JSON file and retrieves the request data based on the given title and data keys
 * 
 * This function is central to the test data management system. It loads test cases from
 * JSON files and automatically injects environment-specific merchant ID when present.
 * The function provides type safety through generics and graceful error handling.
 *
 * @template T - The type of the request data to be returned
 * @param {string} jsonPathFile - Absolute path to the JSON test data file
 * @param {string} title - The top-level category key to locate the test data (e.g., "CreateOrder", "Payment")
 * @param {string} caseName - The specific test case key under the title (e.g., "CreateOrderValidScenario")
 * @returns {T} A typed object representing the request data for the specified test case
 * @throws {Error} Logs error and returns empty object if file reading or parsing fails
 * 
 * @example
 * ```typescript
 * const createOrderData = getRequest<CreateOrderRequest>(
 *   "/path/to/PaymentGateway.json", 
 *   "CreateOrder", 
 *   "CreateOrderRedirect"
 * );
 * ```
 */
function getRequest<T = Record<string, any>>(jsonPathFile: string, title: string, caseName: string): T {
  try {
    const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
    const request = (jsonData[title]?.[caseName]?.request || {}) as T;

    // Auto-inject merchant ID from environment if available and applicable
    const merchantId = process.env.MERCHANT_ID;
    if (merchantId && typeof request === 'object' && request !== null && 'merchantId' in request) {
      (request as any).merchantId = merchantId;
    }
    return request;
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    console.error(`Error reading request data from ${jsonPathFile}: ${errorMessage}`);
    return {} as T; // Return empty object as fallback
  }
}

/**
 * Reads a JSON file and retrieves the response data based on the given title and data keys
 * 
 * This function extracts expected response data from test data files for validation purposes.
 * It's commonly used in assertion functions to compare actual API responses against expected results.
 *
 * @param {string} jsonPathFile - Absolute path to the JSON test data file
 * @param {string} title - The top-level category key to locate the test data
 * @param {string} data - The specific test case key under the title
 * @returns {Record<string, any>} A dictionary representing the expected response data
 * @throws {Error} Logs error and returns empty object if file reading or parsing fails
 * 
 * @example
 * ```typescript
 * const expectedResponse = getResponse(
 *   "/path/to/PaymentGateway.json", 
 *   "CreateOrder", 
 *   "CreateOrderValidScenario"
 * );
 * ```
 */
function getResponse(jsonPathFile: string, title: string, data: string): Record<string, any> {
  try {
    const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
    return jsonData[title]?.[data]?.response || {};
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    console.error(`Error reading response data from ${jsonPathFile}: ${errorMessage}`);
    return {};
  }
}

/**
 * Reads a JSON file and retrieves the response code based on the given title and data keys
 * 
 * This function extracts expected response codes from test data files for API validation.
 * Response codes are typically used to verify that APIs return the correct status indicators
 * for both successful operations and error scenarios.
 *
 * @param {string} jsonPathFile - Absolute path to the JSON test data file
 * @param {string} title - The top-level category key to locate the test data
 * @param {string} data - The specific test case key under the title
 * @returns {string} A string representing the expected response code (e.g., "2000000", "4000001")
 * @throws {Error} Logs error and returns empty string if file reading or parsing fails
 * 
 * @example
 * ```typescript
 * const expectedCode = getResponseCode(
 *   "/path/to/PaymentGateway.json", 
 *   "CreateOrder", 
 *   "CreateOrderValidScenario"
 * );
 * // Returns: "2000000" for success scenarios
 * ```
 */
function getResponseCode(jsonPathFile: string, title: string, data: string): string {
  try {
    const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
    return jsonData[title]?.[data]?.responseCode || '';
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    console.error(`Error reading response code from ${jsonPathFile}: ${errorMessage}`);
    return '';
  }
}

/**
 * Retries an async function call for handling inconsistent request scenarios
 * 
 * This function provides robust retry logic for API calls that may fail due to transient issues
 * such as network problems, server overload, or race conditions (e.g., duplicate partnerReferenceNo 
 * with different payloads). It automatically retries on 500 errors, 'General Error', or 
 * 'Internal Server Error', and supports custom retry conditions.
 * 
 * @template T - The return type of the API call function
 * @param {() => Promise<T>} apiCall - The async function to call (should throw on error or return result)
 * @param {number} maxAttempts - Maximum number of retry attempts (default: 3)
 * @param {number} delayMs - Delay between retries in milliseconds (default: 2000)
 * @param {(error: any) => boolean} [isRetryable] - Optional custom function to determine if error is retryable
 * @returns {Promise<T>} The result of the API call if successful
 * @throws {Error} Throws the last encountered error if all retry attempts fail
 * 
 * @example
 * ```typescript
 * const result = await retryOnInconsistentRequest(
 *   () => dana.paymentGatewayApi.createOrder(requestData),
 *   3,    // max attempts
 *   2000, // 2 second delay
 *   (error) => error.status === 502 // custom retry condition
 * );
 * ```
 */
async function retryOnInconsistentRequest<T>(
  apiCall: () => Promise<T>,
  maxAttempts = 3,
  delayMs = 2000,
  isRetryable?: (error: any) => boolean
): Promise<T> {
  let lastError: any;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      return await apiCall();
    } catch (error: any) {
      lastError = error;
      const errorMsg = error?.message || '';
      const status = error?.status || error?.response?.status;

      // Determine if this error is retryable based on built-in or custom conditions
      const shouldRetry =
        (status === 500 ||
          errorMsg.includes('General Error') ||
          errorMsg.includes('Internal Server Error') ||
          (typeof isRetryable === 'function' && isRetryable(error))) &&
        attempt < maxAttempts;

      if (shouldRetry) {
        console.warn(`Inconsistent request error encountered (attempt ${attempt}). Retrying in ${delayMs}ms...`);
        await new Promise(res => setTimeout(res, delayMs));
        continue;
      }
      throw lastError;
    }
  }
  throw lastError;
}

/**
 * Executes payment automation using Playwright browser automation
 * 
 * This function orchestrates the complete payment flow automation by launching a separate
 * Node.js process that controls a browser to simulate user interactions with the DANA payment
 * interface. It's designed to handle payment scenarios that require user authentication and
 * PIN entry in a web environment.
 * 
 * The function spawns the automate-payment.js script as a child process and communicates
 * through JSON serialization. This approach provides isolation and better error handling
 * for browser automation tasks.
 * 
 * @param {string} phoneNumber - User's phone number for payment authentication (default: '0811742234')
 * @param {string} pin - User's PIN for payment completion (default: '123321')
 * @param {string} redirectUrl - Payment redirect URL obtained from create order response
 * @param {number} maxRetries - Maximum number of retry attempts for payment automation (default: 3)
 * @param {number} retryDelay - Delay between retries in milliseconds (default: 2000)
 * @param {boolean} headless - Whether to run browser in headless mode for CI/CD environments (default: false)
 * @returns {Promise<PaymentAutomationResult>} Payment automation result with success status and details
 * @throws {Error} Throws error if automation script fails to start or complete
 * 
 * @example
 * ```typescript
 * const result = await automatePayment(
 *   '0811742234',           // phone number
 *   '123321',               // PIN
 *   response.webRedirectUrl, // redirect URL from create order
 *   3,                      // max retries
 *   2000,                   // retry delay
 *   true                    // headless mode
 * );
 * 
 * if (result.success) {
 *   console.log(`Payment completed after ${result.attempts} attempts`);
 *   console.log(`Auth code: ${result.authCode}`);
 * } else {
 *   console.error(`Payment failed: ${result.error}`);
 * }
 * ```
 */
async function automatePayment(
  phoneNumber: string = '0811742234',
  pin: string = '123321',
  redirectUrl: string,
  maxRetries: number = 3,
  retryDelay: number = 2000,
  headless: boolean = false
): Promise<{
  success: boolean;
  authCode: string | null;
  error: string | null;
  attempts: number;
}> {
  try {
    // Import required Node.js modules for process spawning
    const { spawn } = require('child_process');
    const path = require('path');
    const automationScriptPath = path.resolve(__dirname, '../automate-payment.js');
    console.log(`Loading automation script from: ${automationScriptPath}`);

    // Prepare parameters for the automation script
    const params = {
      phoneNumber,
      pin,
      redirectUrl,
      maxRetries,
      retryDelay,
      headless
    };

    console.log(`Starting payment automation with params:`, JSON.stringify(params, null, 2));

    return new Promise((resolve, reject) => {
      // Spawn the automation script as a child process
      const child = spawn('node', [automationScriptPath, JSON.stringify(params)], {
        stdio: ['pipe', 'pipe', 'pipe']
      });

      let stdout = '';
      let stderr = '';

      // Collect stdout data from the child process
      child.stdout.on('data', (data) => {
        stdout += data.toString();
      });

      // Collect stderr data and log errors in real-time
      child.stderr.on('data', (data) => {
        stderr += data.toString();
        console.error('Payment automation stderr:', data.toString());
      });

      // Handle process completion
      child.on('close', (code) => {
        if (code === 0) {
          try {
            // Parse the JSON result from the last line of stdout
            const lines = stdout.trim().split('\n');
            const lastLine = lines[lines.length - 1];
            const result = JSON.parse(lastLine);
            console.log(`Payment automation result:`, JSON.stringify(result, null, 2));
            resolve(result);
          } catch (parseError) {
            console.error('Failed to parse automation result:', parseError);
            console.error('Stdout was:', stdout);
            reject(new Error(`Failed to parse automation result: ${parseError}`));
          }
        } else {
          console.error(`Payment automation process exited with code ${code}`);
          console.error('Stderr:', stderr);
          console.error('Stdout:', stdout);
          reject(new Error(`Payment automation process exited with code ${code}`));
        }
      });

      // Handle process startup errors
      child.on('error', (error) => {
        console.error('Failed to start payment automation process:', error);
        reject(new Error(`Failed to start payment automation process: ${error.message}`));
      });
    });
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    console.error('Payment automation error:', errorMessage);
    console.error('Error stack:', error instanceof Error ? error.stack : 'No stack trace');
    throw new Error(`Payment automation failed: ${errorMessage}`);
  }
}

/**
 * Export all utility functions for use in test files
 * 
 * These exports provide the core functionality needed for DANA API integration testing:
 * - Data extraction and manipulation functions
 * - Retry mechanisms for robust testing
 * - Payment automation capabilities
 */
export {
  getRequestWithDelimiter,
  getRequest,
  getResponse,
  getResponseCode,
  retryOnInconsistentRequest,
  automatePayment,
};
