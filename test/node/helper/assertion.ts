/**
 * @fileoverview Assertion and Response Validation Utilities for DANA Integration Testing
 * 
 * This module provides comprehensive assertion utilities for validating API responses
 * in the DANA Payment Gateway and Widget API testing suite. It includes functions for
 * comparing JSON objects, variable replacement, and both positive and negative response
 * validation scenarios.
 * 
 * Key Features:
 * - Deep JSON object comparison with server value handling
 * - Dynamic variable replacement in test data
 * - Success and failure response assertions
 * - Test data extraction utilities
 * - Detailed error reporting and diagnostics
 */

/* eslint-disable */
import * as fs from 'fs';
import * as path from 'path';

/**
 * Compares JSON objects with special handling for server-generated values
 * 
 * This function performs deep comparison of JSON objects while handling special
 * server-generated values marked with '${valueFromServer}'. It's designed to
 * validate API responses against expected data while accommodating dynamic values
 * that cannot be predetermined in test cases.
 * 
 * @param {any} expected - Expected JSON object from test data
 * @param {any} actual - Actual JSON object from API response
 * @param {string} currentPath - Current path in the JSON structure for error reporting (default: '')
 * @param {Array<[string, any, any]>} diffPaths - Array to store differences found during comparison (default: [])
 * 
 * @example
 * ```typescript
 * const differences: Array<[string, any, any]> = [];
 * compareJsonObjects(expectedResponse, actualResponse, '', differences);
 * if (differences.length > 0) {
 *   console.error('Response validation failed:', differences);
 * }
 * ```
 */
function compareJsonObjects(expected: any, actual: any, currentPath = '', diffPaths: Array<[string, any, any]> = []): void {
  // Handle special server value patterns that cannot be predetermined
  if (typeof expected === 'string' && expected === '${valueFromServer}') {
    // Validate that the actual value exists (not null or undefined)
    if (actual === undefined || actual === null) {
      diffPaths.push([currentPath, expected, actual]);
    }
    return;
  }

  // Handle null value comparisons
  if (expected === null || actual === null || actual === undefined) {
    if (expected !== actual && !(expected === null && actual === undefined)) {
      diffPaths.push([currentPath, expected, actual]);
    }
    return;
  }

  // Validate type compatibility between expected and actual values
  if (typeof expected !== typeof actual) {
    diffPaths.push([currentPath, expected, actual]);
    return;
  }

  // Handle array comparisons with length and element validation
  if (Array.isArray(expected)) {
    if (!Array.isArray(actual)) {
      diffPaths.push([currentPath, expected, actual]);
      return;
    }

    if (expected.length !== actual.length) {
      diffPaths.push([currentPath, expected, actual]);
      return;
    }

    // Recursively compare each array element
    for (let i = 0; i < expected.length; i++) {
      compareJsonObjects(expected[i], actual[i], `${currentPath}[${i}]`, diffPaths);
    }
    return;
  }

  // Handle object comparisons with recursive property validation
  if (typeof expected === 'object') {
    for (const key in expected) {
      const newPath = currentPath ? `${currentPath}.${key}` : key;
      if (!(key in actual)) {
        diffPaths.push([newPath, expected[key], undefined]);
        continue;
      }
      // Recursively compare object properties
      compareJsonObjects(expected[key], actual[key], newPath, diffPaths);
    }
    return;
  }

  // For primitive values, perform direct comparison
  if (expected !== actual) {
    diffPaths.push([currentPath, expected, actual]);
  }
}

/**
 * Replaces variable placeholders in test data with actual values
 * 
 * This function processes test data to replace variable placeholders (${variableName})
 * with actual values from a variable dictionary. It's commonly used to inject
 * dynamic values like partner reference numbers or timestamps into test data.
 * 
 * @param {any} data - The test data object to process
 * @param {Record<string, any> | null} variableDict - Dictionary of variables to replace (can be null)
 * @returns {any} The processed data with variables replaced
 * 
 * @example
 * ```typescript
 * const testData = { 
 *   partnerReferenceNo: "${partnerReferenceNo}",
 *   amount: { value: "${amount}" }
 * };
 * const variables = { 
 *   partnerReferenceNo: "12345-67890",
 *   amount: "10000"
 * };
 * const processedData = replaceVariables(testData, variables);
 * // Result: { partnerReferenceNo: "12345-67890", amount: { value: "10000" } }
 * ```
 */
function replaceVariables(data: any, variableDict: Record<string, any> | null): any {
  if (!variableDict) return data;

  /**
   * Processes individual values to replace variable placeholders
   */
  const processValue = (value: any): any => {
    if (typeof value === 'string' && value.startsWith('${') && value.endsWith('}')) {
      const key = value.substring(2, value.length - 1);
      if (key in variableDict) {
        return variableDict[key];
      }
    }
    return value;
  };

  /**
   * Recursively processes objects and arrays to replace variables
   */
  const processObject = (obj: any): any => {
    if (Array.isArray(obj)) {
      return obj.map(item => processObject(item));
    } else if (obj !== null && typeof obj === 'object') {
      const result = {};
      for (const key in obj) {
        result[key] = processObject(obj[key]);
      }
      return result;
    } else if (typeof obj === 'string') {
      return processValue(obj);
    }
    return obj;
  };

  return processObject(data);
}

/**
 * Gets request data from a JSON test data file
 * 
 * This function extracts request data from structured JSON test files for use in test cases.
 * It provides a consistent way to access test data across different test scenarios.
 * 
 * @param {string} jsonPathFile - Absolute path to the JSON test data file
 * @param {string} title - Top-level category key in the JSON file (e.g., "CreateOrder", "Payment")
 * @param {string} data - Specific test case key under the title (e.g., "CreateOrderValidScenario")
 * @returns {Record<string, any>} Request data object for the specified test case
 * @throws {Error} Throws error if JSON parsing fails
 */
function getRequest(jsonPathFile: string, title: string, data: string): Record<string, any> {
  const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
  return jsonData[title]?.[data]?.request || {};
}

/**
 * Gets expected response data from a JSON test data file
 * 
 * This function extracts expected response data for validation purposes in test assertions.
 * It's used to compare actual API responses against predefined expected results.
 * 
 * @param {string} jsonPathFile - Absolute path to the JSON test data file
 * @param {string} title - Top-level category key in the JSON file
 * @param {string} data - Specific test case key under the title
 * @returns {Record<string, any>} Expected response data object
 * @throws {Error} Throws error if JSON parsing fails
 */
function getResponse(jsonPathFile: string, title: string, data: string): Record<string, any> {
  const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
  return jsonData[title]?.[data]?.response || {};
}

/**
 * Gets expected response code from a JSON test data file
 * 
 * This function extracts expected response codes for API validation scenarios.
 * Response codes are used to verify that APIs return the correct status indicators.
 * 
 * @param {string} jsonPathFile - Absolute path to the JSON test data file
 * @param {string} title - Top-level category key in the JSON file
 * @param {string} data - Specific test case key under the title
 * @returns {string} Expected response code (e.g., "2000000" for success, "4000001" for errors)
 * @throws {Error} Throws error if JSON parsing fails
 */
function getResponseCode(jsonPathFile: string, title: string, data: string): string {
  const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
  return jsonData[title]?.[data]?.responseCode || '';
}

/**
 * Asserts that an API error response matches the expected error data
 * 
 * This function validates error responses from API calls against expected error patterns
 * defined in test data files. It's specifically designed for negative test scenarios
 * where the API is expected to return error responses with specific error codes and messages.
 * 
 * @param {string} jsonPathFile - Absolute path to the JSON file containing expected error responses
 * @param {string} title - Top-level category key in the JSON file
 * @param {string} data - Specific error test case key under the title
 * @param {string | Record<string, any>} errorBody - Error response from the API (JSON string or object)
 * @param {Record<string, any> | null} variableDict - Dictionary of variables for dynamic value replacement (default: null)
 * @returns {boolean} Returns true if assertion passes
 * @throws {Error} Throws detailed error if validation fails or JSON parsing fails
 * 
 * @example
 * ```typescript
 * try {
 *   await dana.paymentGatewayApi.createOrder(invalidRequest);
 *   fail("Expected an error but API call succeeded");
 * } catch (error) {
 *   await assertFailResponse(
 *     jsonPathFile, 
 *     "CreateOrder", 
 *     "CreateOrderInvalidRequest", 
 *     JSON.stringify(error.rawResponse),
 *     { partnerReferenceNo: "12345" }
 *   );
 * }
 * ```
 */
function assertFailResponse(jsonPathFile: string, title: string, data: string, errorBody: string | Record<string, any>, variableDict: Record<string, any> | null = null): boolean {
  // Extract expected error data from test data file
  const expectedData = getResponse(jsonPathFile, title, data);

  // Process variable replacements if provided
  const processedExpectedData = variableDict ? replaceVariables(expectedData, variableDict) : expectedData;

  try {
    // Parse error response if it's a JSON string
    const actualResponse: Record<string, any> = typeof errorBody === 'string' ? JSON.parse(errorBody) : errorBody;

    // Compare expected vs actual error response and collect differences
    const diffPaths: Array<[string, any, any]> = [];
    compareJsonObjects(processedExpectedData, actualResponse, '', diffPaths);

    // If there are differences, report detailed error information
    if (diffPaths.length > 0) {
      let errorMsg = 'Error response assertion failed. Differences found:\n';
      for (const [path, expectedVal, actualVal] of diffPaths) {
        errorMsg += `  Path: ${path}\n`;
        errorMsg += `    Expected: ${JSON.stringify(expectedVal)}\n`;
        errorMsg += `    Actual: ${JSON.stringify(actualVal)}\n`;
      }
      throw new Error(errorMsg);
    }

    // Log successful error response validation
    console.log(`Error response assertion passed: API error response matches expected data`);
    console.log(`Expected: ${JSON.stringify(processedExpectedData)}`);
    console.log(`Actual: ${JSON.stringify(actualResponse)}`);
    return true;
  } catch (e: unknown) {
    if (e instanceof SyntaxError) {
      throw new Error(`Failed to parse JSON error response: ${e.message}`);
    }
    throw e;
  }
}

/**
 * Asserts that an API success response matches the expected data
 * 
 * This function validates successful API responses against expected response patterns
 * defined in test data files. It's designed for positive test scenarios where the API
 * is expected to return successful responses with specific data structures and values.
 * 
 * @param {string} jsonPathFile - Absolute path to the JSON file containing expected responses
 * @param {string} title - Top-level category key in the JSON file
 * @param {string} data - Specific success test case key under the title
 * @param {string | Record<string, any>} responseBody - Success response from the API (JSON string or object)
 * @param {Record<string, any> | null} variableDict - Dictionary of variables for dynamic value replacement (default: null)
 * @returns {boolean} Returns true if assertion passes
 * @throws {Error} Throws detailed error if validation fails or JSON parsing fails
 * 
 * @example
 * ```typescript
 * const response = await dana.paymentGatewayApi.createOrder(validRequest);
 * await assertResponse(
 *   jsonPathFile, 
 *   "CreateOrder", 
 *   "CreateOrderValidScenario", 
 *   response,
 *   { partnerReferenceNo: validRequest.partnerReferenceNo }
 * );
 * ```
 */
function assertResponse(jsonPathFile: string, title: string, data: string, responseBody: string | Record<string, any>, variableDict: Record<string, any> | null = null): boolean {
  // Extract expected response data from test data file
  const expectedData = getResponse(jsonPathFile, title, data);

  // Process variable replacements if provided
  const processedExpectedData = variableDict ? replaceVariables(expectedData, variableDict) : expectedData;

  try {
    // Parse response if it's a JSON string
    const actualResponse: Record<string, any> = typeof responseBody === 'string' ? JSON.parse(responseBody) : responseBody;

    // Compare expected vs actual response and collect differences
    const diffPaths: Array<[string, any, any]> = [];
    compareJsonObjects(processedExpectedData, actualResponse, '', diffPaths);

    // If there are differences, report detailed error information
    if (diffPaths.length > 0) {
      let errorMsg = 'Response assertion failed. Differences found:\n';
      for (const [path, expectedVal, actualVal] of diffPaths) {
        errorMsg += `  Path: ${path}\n`;
        errorMsg += `    Expected: ${JSON.stringify(expectedVal)}\n`;
        errorMsg += `    Actual: ${JSON.stringify(actualVal)}\n`;
      }
      throw new Error(errorMsg);
    }

    // Log successful response validation
    console.log(`Response assertion passed: API response matches expected data`);
    console.log(`Request Data File: ${path.basename(jsonPathFile)}`);
    console.log(`Expected: ${JSON.stringify(processedExpectedData)}`);
    console.log(`Actual: ${JSON.stringify(actualResponse)}`);
    return true;
  } catch (e: unknown) {
    if (e instanceof SyntaxError) {
      throw new Error(`Failed to parse JSON response: ${e.message}`);
    }
    throw e;
  }
}

/**
 * Export all assertion and validation utilities
 * 
 * These exports provide comprehensive functionality for test response validation:
 * - JSON object comparison with server value handling
 * - Variable replacement for dynamic test data
 * - Test data extraction from JSON files
 * - Success and failure response assertions
 */
export {
  compareJsonObjects,
  replaceVariables,
  getRequest,
  getResponse,
  getResponseCode,
  assertFailResponse,
  assertResponse
};
