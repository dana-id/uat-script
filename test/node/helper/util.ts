/* eslint-disable */
import * as fs from 'fs';
import * as path from 'path';

/**
 * Splits a string into parts using the specified delimiter
 *
 * @param {string} inputString - The string to split
 * @param {string} delimiter - The delimiter to use for splitting
 * @returns {Array} Array of substrings
 */
function getRequestWithDelimiter(inputString: string, delimiter: string): string[] {
  return inputString.split(delimiter);
}

/**
 * Reads a JSON file and retrieves the request data based on the given title and data keys
 *
 * @template T - The type of the request data
 * @param {string} jsonPathFile - Path to the JSON file
 * @param {string} title - The title key to locate the request data
 * @param {string} caseName - The specific data key under the title
 * @returns {T} A dictionary representing the request data
 */
function getRequest<T = Record<string, any>>(jsonPathFile: string, title: string, caseName: string): T {
  try {
    const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
    const request = (jsonData[title]?.[caseName]?.request || {}) as T;
    const merchantId = process.env.MERCHANT_ID;
    if (merchantId && typeof request === 'object' && request !== null && 'merchantId' in request) {
      (request as any).merchantId = merchantId;
    }
    return request;
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    console.error(`Error reading request data from ${jsonPathFile}: ${errorMessage}`);
    return {} as T;
  }
}

/**
 * Reads a JSON file and retrieves the response data based on the given title and data keys
 *
 * @param {string} jsonPathFile - Path to the JSON file
 * @param {string} title - The title key to locate the response data
 * @param {string} data - The specific data key under the title
 * @returns {Object} A dictionary representing the response data
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
 * @param {string} jsonPathFile - Path to the JSON file
 * @param {string} title - The title key to locate the data
 * @param {string} data - The specific data key under the title
 * @returns {string} A string representing the response code
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
 * Retries an async function call for inconsistent request scenarios (e.g., duplicate partnerReferenceNo with different payloads).
 * Retries on 500, 'General Error', or 'Internal Server Error', and allows custom retry condition.
 *
 * @param apiCall - The async function to call (should throw or return an object with status/message).
 * @param maxAttempts - Number of times to retry (default: 3).
 * @param delayMs - Delay between retries in milliseconds (default: 2000).
 * @param isRetryable - Optional custom function to determine if error is retryable.
 * @returns The result of the API call if successful, otherwise throws the last error.
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
 * Creates a decorator to add a delay after a function completes
 * In JavaScript, this is implemented as a higher-order function
 *
 * @param {number} delayMs - Delay in milliseconds (default: random between 500-1500ms)
 * @returns {Function} Function that adds delay to the original function
 */

/**
 * Execute payment automation using playwright
 *
 * @param {string} phoneNumber - User's phone number for payment
 * @param {string} pin - User's PIN for payment
 * @param {string} redirectUrl - Redirect URL from create order response
 * @param {number} maxRetries - Maximum number of retry attempts (default: 3)
 * @param {number} retryDelay - Delay between retries in milliseconds (default: 2000)
 * @param {boolean} headless - Whether to run browser in headless mode (default: false)
 * @returns {Promise<Object>} Payment automation result
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
    // Use child_process.spawn to call the script with proper arguments
    const { spawn } = require('child_process');
    const path = require('path');
    const automationScriptPath = path.resolve(__dirname, '../automate-payment.js');
    console.log(`Loading automation script from: ${automationScriptPath}`);

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
      const child = spawn('node', [automationScriptPath, JSON.stringify(params)], {
        stdio: ['pipe', 'pipe', 'pipe']
      });

      let stdout = '';
      let stderr = '';

      child.stdout.on('data', (data) => {
        stdout += data.toString();
      });

      child.stderr.on('data', (data) => {
        stderr += data.toString();
        console.error('Payment automation stderr:', data.toString());
      });

      child.on('close', (code) => {
        if (code === 0) {
          try {
            // Parse the JSON result from stdout
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

export {
  getRequestWithDelimiter,
  getRequest,
  getResponse,
  getResponseCode,
  retryOnInconsistentRequest,
  automatePayment,
};
