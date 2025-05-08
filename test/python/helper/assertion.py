from pprint import pprint
from helper.util import *  # Import the Invoke class

def replace_variables(data, variable_dict):
    """
    Recursively replaces placeholders in the format ${key} with values from variable_dict.
    
    :param data: The data structure to process (can be dict, list, or primitive)
    :param variable_dict: Dictionary of variables to replace
    :return: The data structure with variables replaced
    """
    if isinstance(data, dict):
        return {key: replace_variables(value, variable_dict) for key, value in data.items()}
    
    elif isinstance(data, list):
        return [replace_variables(item, variable_dict) for item in data]
    
    elif isinstance(data, str):
        # Check if this string matches any variable pattern ${key}
        for var_name, var_value in variable_dict.items():
            placeholder = f"${{{var_name}}}"
            if data == placeholder:
                return var_value
        # Return the original string if no replacement found
        return data
    
    # Return other types unchanged
    return data


def assert_response(json_path_file: str, title: str, data: str, api_response_json: str, variable_dict: dict = None) -> None:
    """
    Asserts that the API response matches the expected data from a JSON file.

    For fields with value "${valueFromServer}", only validates that the field exists in the response,
    without checking the actual value.
    For fields with value "${key}" where key exists in variable_dict, substitutes the actual value.
    For all other fields, performs an exact match.

    :param json_path_file: Path to the JSON file containing the expected data.
    :param title: The title or key in the JSON file to locate the expected data.
    :param data: The specific data key to compare within the JSON file.
    :param api_response_json: The actual API response to compare against (as a JSON string).
    :param variable_dict: Dictionary of variables to replace in the format {"key": "value"}
    :raises AssertionError: If the API response does not match the expected data.
    """
    # Fetch the expected data from the JSON file using the Invoke class
    expected_data = get_response(json_path_file, title, data)
    
    # Replace variables in the expected data if a dictionary is provided
    if variable_dict:
        expected_data = replace_variables(expected_data, variable_dict)

    # Parse the API response JSON
    try:
        actual_response = json.loads(api_response_json)
    except json.JSONDecodeError as e:
        raise ValueError(f"Failed to parse JSON: {e}")

    # Recursively compare actual and expected, with special handling for ${valueFromServer}
    diff_paths = []
    compare_json_objects(expected_data, actual_response, "", diff_paths)
    
    # If there are differences, raise an assertion error
    if diff_paths:
        error_msg = "Assertion failed. Differences found at:\n"
        for path, expected_val, actual_val in diff_paths:
            error_msg += f"  Path: {path}\n"
            error_msg += f"    Expected: {expected_val}\n"
            error_msg += f"    Actual: {actual_val}\n"
        raise AssertionError(error_msg)

    # Print success message
    print("Assertion passed: API response matches the expected data.")
    
def assert_fail_response(json_path_file: str, title: str, data: str, error_string: str, variable_dict: dict = None) -> None:
    """
    Asserts that the API error response matches the expected data from a JSON file.

    For fields with value "${valueFromServer}", only validates that the field exists in the response,
    without checking the actual value.
    For fields with value "${key}" where key exists in variable_dict, substitutes the actual value.
    For all other fields, performs an exact match.

    :param json_path_file: Path to the JSON file containing the expected data.
    :param title: The title or key in the JSON file to locate the expected data.
    :param data: The specific data key to compare within the JSON file.
    :param error_string: The error response string from the API exception.
    :param variable_dict: Dictionary of variables to replace in the format {"key": "value"}
    :raises AssertionError: If the API response does not match the expected data.
    """
    # Fetch the expected data from the JSON file using the Invoke class
    expected_data = get_response(json_path_file, title, data)
    
    # Replace variables in the expected data if a dictionary is provided
    if variable_dict:
        expected_data = replace_variables(expected_data, variable_dict)
    
    # Extract the relevant part of the error string
    temp_error_string = str(error_string).replace("HTTP response body: ", "", 1)
    temp_error = str(temp_error_string).split("\n")[3]
    
    try:
        # Parse the error response JSON
        actual_response = json.loads(temp_error)
        
        # Recursively compare actual and expected, with special handling for ${valueFromServer}
        diff_paths = []
        compare_json_objects(expected_data, actual_response, "", diff_paths)
        
        # If there are differences, raise an assertion error
        if diff_paths:
            error_msg = "Assertion failed. Differences found in error response:\n"
            for path, expected_val, actual_val in diff_paths:
                error_msg += f"  Path: {path}\n"
                error_msg += f"    Expected: {expected_val}\n"
                error_msg += f"    Actual: {actual_val}\n"
            raise AssertionError(error_msg)

        # Print success message
        print("Assertion passed: API error response matches the expected data.")
    except json.JSONDecodeError as e:
        raise ValueError(f"Failed to parse JSON error response: {e}")

def compare_json_objects(expected, actual, path, diff_paths):
    """
    Recursively compares JSON objects with special handling for "${valueFromServer}" placeholder.
    
    :param expected: The expected JSON object or value
    :param actual: The actual JSON object or value
    :param path: Current JSON path for error reporting
    :param diff_paths: List to collect differences found
    :return: None
    """
    # Handle the case where expected is a dict
    if isinstance(expected, dict):
        if not isinstance(actual, dict):
            diff_paths.append((path, expected, actual))
            return
        
        # Check that all expected keys exist in actual
        for key in expected:
            new_path = f"{path}.{key}" if path else key
            
            if key not in actual:
                diff_paths.append((new_path, expected[key], "MISSING"))
                continue
            
            # Recursively compare nested values
            compare_json_objects(expected[key], actual[key], new_path, diff_paths)
    
    # Handle the case where expected is a list
    elif isinstance(expected, list):
        if not isinstance(actual, list):
            diff_paths.append((path, expected, actual))
            return
        
        if len(expected) != len(actual):
            diff_paths.append((f"{path}[length]", len(expected), len(actual)))
            return
        
        # Compare each item in the list
        for i, (exp_item, act_item) in enumerate(zip(expected, actual)):
            new_path = f"{path}[{i}]"
            compare_json_objects(exp_item, act_item, new_path, diff_paths)
    
    # Handle the case where expected is a string with the special placeholder
    elif isinstance(expected, str) and expected == "${valueFromServer}":
        # Only verify that the actual value exists (is not None or empty)
        if actual is None or (isinstance(actual, str) and not actual):
            diff_paths.append((path, expected, actual))
    
    # For all other cases, do an exact comparison
    elif expected != actual:
        diff_paths.append((path, expected, actual))