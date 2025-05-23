/* eslint-disable */
import * as fs from 'fs';
import * as path from 'path';

/**
 * Compares JSON objects with special handling for server values
 * 
 * @param {Object} expected - Expected JSON object
 * @param {Object} actual - Actual JSON object
 * @param {string} currentPath - Current path in the JSON structure
 * @param {Array} diffPaths - Array to store differences found
 */
function compareJsonObjects(expected: any, actual: any, currentPath = '', diffPaths: Array<[string, any, any]> = []): void {
  // If expected is a special value pattern, handle it
  if (typeof expected === 'string' && expected === '${valueFromServer}') {
    // Just check if the actual value exists
    if (actual === undefined || actual === null) {
      diffPaths.push([currentPath, expected, actual]);
    }
    return;
  }

  // If types don't match, record difference
  if (typeof expected !== typeof actual) {
    diffPaths.push([currentPath, expected, actual]);
    return;
  }

  // Handle different types appropriately
  if (expected === null || actual === null) {
    if (expected !== actual) {
      diffPaths.push([currentPath, expected, actual]);
    }
    return;
  }

  if (Array.isArray(expected)) {
    if (!Array.isArray(actual)) {
      diffPaths.push([currentPath, expected, actual]);
      return;
    }

    if (expected.length !== actual.length) {
      diffPaths.push([currentPath, expected, actual]);
      return;
    }

    for (let i = 0; i < expected.length; i++) {
      compareJsonObjects(expected[i], actual[i], `${currentPath}[${i}]`, diffPaths);
    }
    return;
  }

  if (typeof expected === 'object') {
    for (const key in expected) {
      const newPath = currentPath ? `${currentPath}.${key}` : key;
      if (!(key in actual)) {
        diffPaths.push([newPath, expected[key], undefined]);
        continue;
      }
      compareJsonObjects(expected[key], actual[key], newPath, diffPaths);
    }
    return;
  }

  // For primitive values, compare directly
  if (expected !== actual) {
    diffPaths.push([currentPath, expected, actual]);
  }
}

/**
 * Replaces variables in the expected data with values from variable_dict
 * 
 * @param {Object} data - The data object to process
 * @param {Object} variableDict - Dictionary of variables to replace
 * @returns {Object} The processed data with variables replaced
 */
function replaceVariables(data: any, variableDict: Record<string, any> | null): any {
  if (!variableDict) return data;
  
  const processValue = (value: any): any => {
    if (typeof value === 'string' && value.startsWith('${') && value.endsWith('}')) {
      const key = value.substring(2, value.length - 1);
      if (key in variableDict) {
        return variableDict[key];
      }
    }
    return value;
  };

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
 * Gets request data from a JSON file
 * 
 * @param {string} jsonPathFile - Path to the JSON file
 * @param {string} title - Title key in the JSON file
 * @param {string} data - Data key under the title
 * @returns {Object} Request data object
 */
function getRequest(jsonPathFile: string, title: string, data: string): Record<string, any> {
  const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
  return jsonData[title]?.[data]?.request || {};
}

/**
 * Gets response data from a JSON file
 * 
 * @param {string} jsonPathFile - Path to the JSON file
 * @param {string} title - Title key in the JSON file
 * @param {string} data - Data key under the title
 * @returns {Object} Response data object
 */
function getResponse(jsonPathFile: string, title: string, data: string): Record<string, any> {
  const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
  return jsonData[title]?.[data]?.response || {};
}

/**
 * Gets response code from a JSON file
 * 
 * @param {string} jsonPathFile - Path to the JSON file
 * @param {string} title - Title key in the JSON file
 * @param {string} data - Data key under the title
 * @returns {string} Response code
 */
function getResponseCode(jsonPathFile: string, title: string, data: string): string {
  const jsonData: Record<string, any> = JSON.parse(fs.readFileSync(jsonPathFile, 'utf8'));
  return jsonData[title]?.[data]?.responseCode || '';
}

/**
 * Asserts that the API error response matches the expected data
 * 
 * @param {string} jsonPathFile - Path to the JSON file with expected responses
 * @param {string} title - Title in the JSON file
 * @param {string} data - Specific data key under the title
 * @param {string} errorBody - Error response string from the API
 * @param {Object} variableDict - Dictionary of variables for replacement
 */
function assertFailResponse(jsonPathFile: string, title: string, data: string, errorBody: string | Record<string, any>, variableDict: Record<string, any> | null = null): boolean {
  // Get expected data from JSON file
  const expectedData = getResponse(jsonPathFile, title, data);
  
  // Replace variables if provided
  const processedExpectedData = variableDict ? replaceVariables(expectedData, variableDict) : expectedData;
  
  try {
    // Parse error response
    const actualResponse: Record<string, any> = typeof errorBody === 'string' ? JSON.parse(errorBody) : errorBody;
    
    // Compare JSON objects and collect differences
    const diffPaths: Array<[string, any, any]> = [];
    compareJsonObjects(processedExpectedData, actualResponse, '', diffPaths);
    
    // If there are differences, throw an assertion error
    if (diffPaths.length > 0) {
      let errorMsg = 'Assertion failed. Differences found in error response:\n';
      for (const [path, expectedVal, actualVal] of diffPaths) {
        errorMsg += `  Path: ${path}\n`;
        errorMsg += `    Expected: ${JSON.stringify(expectedVal)}\n`;
        errorMsg += `    Actual: ${JSON.stringify(actualVal)}\n`;
      }
      throw new Error(errorMsg);
    }
    
    // Print success message
    console.log(`Assertion passed: API error response matches the expected data ${JSON.stringify(processedExpectedData)}`);
    return true;
  } catch (e: unknown) {
    if (e instanceof SyntaxError) {
      throw new Error(`Failed to parse JSON error response: ${e.message}`);
    }
    throw e;
  }
}

/**
 * Asserts that the API response matches the expected data
 * 
 * @param {string} jsonPathFile - Path to the JSON file with expected responses
 * @param {string} title - Title in the JSON file
 * @param {string} data - Specific data key under the title
 * @param {string|Object} responseBody - Response from the API
 * @param {Object} variableDict - Dictionary of variables for replacement
 */
function assertResponse(jsonPathFile: string, title: string, data: string, responseBody: string | Record<string, any>, variableDict: Record<string, any> | null = null): boolean {
  // Same implementation as assertFailResponse but with different logging
  const expectedData = getResponse(jsonPathFile, title, data);
  const processedExpectedData = variableDict ? replaceVariables(expectedData, variableDict) : expectedData;
  
  try {
    const actualResponse: Record<string, any> = typeof responseBody === 'string' ? JSON.parse(responseBody) : responseBody;
    const diffPaths: Array<[string, any, any]> = [];
    compareJsonObjects(processedExpectedData, actualResponse, '', diffPaths);
    
    if (diffPaths.length > 0) {
      let errorMsg = 'Assertion failed. Differences found in response:\n';
      for (const [path, expectedVal, actualVal] of diffPaths) {
        errorMsg += `  Path: ${path}\n`;
        errorMsg += `    Expected: ${JSON.stringify(expectedVal)}\n`;
        errorMsg += `    Actual: ${JSON.stringify(actualVal)}\n`;
      }
      throw new Error(errorMsg);
    }
    
    console.log(`Assertion passed: API response matches the expected data ${JSON.stringify(processedExpectedData)}`);
    return true;
  } catch (e: unknown) {
    if (e instanceof SyntaxError) {
      throw new Error(`Failed to parse JSON response: ${e.message}`);
    }
    throw e;
  }
}

export {
  compareJsonObjects,
  replaceVariables,
  getRequest,
  getResponse,
  getResponseCode,
  assertFailResponse,
  assertResponse
};
