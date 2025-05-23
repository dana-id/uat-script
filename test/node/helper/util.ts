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
    return (jsonData[title]?.[caseName]?.request || {}) as T;
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
 * Creates a decorator to add a delay after a function completes
 * In JavaScript, this is implemented as a higher-order function
 *
 * @param {number} delayMs - Delay in milliseconds (default: random between 500-1500ms)
 * @returns {Function} Function that adds delay to the original function
 */

export {
  getRequestWithDelimiter,
  getRequest,
  getResponse,
  getResponseCode,
};
