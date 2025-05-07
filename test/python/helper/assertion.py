from pprint import pprint
from helper.util import *  # Import the Invoke class

def assert_response(json_path_file: str, title: str, data: str, api_response_json: dict) -> None:
    """
    Asserts that the API response matches the expected data from a JSON file.

    :param json_path_file: Path to the JSON file containing the expected data.
    :param title: The title or key in the JSON file to locate the expected data.
    :param data: The specific data key to compare within the JSON file.
    :param api_response: The actual API response to compare against.
    :raises AssertionError: If the API response does not match the expected data.
    """
    # Fetch the expected data from the JSON file using the Invoke class
    expected_data = get_response(json_path_file, title, data)

    # Assert that the API response matches the expected data
    try:
        actual_response_json = json.loads(api_response_json)
        
        assert actual_response_json == expected_data, (
        f"Assertion failed: Expected response '{expected_data}', but got '{actual_response_json}'"
    )
    except json.JSONDecodeError as e:
        raise ValueError(f"Failed to parse JSON: {e}")

    # Optionally, print success message for debugging
    print("Assertion passed: API response matches the expected data.")
    
def assert_fail_response(json_path_file: str, title: str, data: str, error_string: str) -> None:
    """
    Asserts that the API response matches the expected data from a JSON file.

    :param json_path_file: Path to the JSON file containing the expected data.
    :param title: The title or key in the JSON file to locate the expected data.
    :param data: The specific data key to compare within the JSON file.
    :param api_response: The actual API response to compare against.
    :raises AssertionError: If the API response does not match the expected data.
    """
    # Fetch the expected data from the JSON file using the Invoke class
    expected_data = get_response(json_path_file, title, data)
    
    # Extract the relevant part of the error string
    temp_error_string = str(error_string).replace("HTTP response body: ", "", 1)
    temp_error = str(temp_error_string).split("\n")[3]
    
    # Assert that the API response matches the expected data
    try:
        actual_response_json = json.loads(temp_error)
        
        assert actual_response_json == expected_data, (
        f"Assertion failed: Expected response '{expected_data}', but got '{actual_response_json}'"
    )
    except json.JSONDecodeError as e:
        raise ValueError(f"Failed to parse JSON: {e}")