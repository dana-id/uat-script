import json
from pprint import pprint

def get_request_with_delimiter(input_string: str, delimiter: str) -> list:
        """
        Splits a string into parts using the specified delimiter.

        :param input_string: The string to split.
        :param delimiter: The delimiter to use for splitting the string.
        :return: A list of substrings.
        """
        return input_string.split(delimiter)

def get_request(json_path_file: str, title: str, data: str) -> dict:
    """
    Reads a JSON file and retrieves the request data based on the given title and data keys.

    :param json_path_file: Path to the JSON file.
    :param title: The title key to locate the request data (e.g., "ConsultPay").
    :param data: The specific data key under the title (e.g., "ConsultPayBalancedSuccess").
    :return: A dictionary representing the request data.
    """
    with open(json_path_file, 'r') as file:
        json_data = json.load(file)
    
    # Navigate to the specific request data
    request_data = json_data.get(title, {}).get(data, {}).get("request", {})
    print("Request Data:")
    pprint(data)
    pprint(request_data)
    
    return request_data

def get_response(json_path_file: str, title: str, data: str) -> dict:
    """
    Reads a JSON file and retrieves the request data based on the given title and data keys.

    :param json_path_file: Path to the JSON file.
    :param title: The title key to locate the request data (e.g., "ConsultPay").
    :param data: The specific data key under the title (e.g., "ConsultPayBalancedSuccess").
    :return: A dictionary representing the request data.
    """
    with open(json_path_file, 'r') as file:
        json_data = json.load(file)
    
    # Navigate to the specific request data
    response_data = json_data.get(title, {}).get(data, {}).get("response", {})
    print("Response Data:")
    pprint(response_data)
    
    return response_data

def get_response_code(json_path_file: str, title: str, data: str) -> dict:
    """
    Reads a JSON file and retrieves the request data based on the given title and data keys.

    :param json_path_file: Path to the JSON file.
    :param title: The title key to locate the request data (e.g., "ConsultPay").
    :param data: The specific data key under the title (e.g., "ConsultPayBalancedSuccess").
    :return: A dictionary representing the request data.
    """
    with open(json_path_file, 'r') as file:
        json_data = json.load(file)
    
    # Navigate to the specific request data
    response_data = json_data.get(title, {}).get(data, {}).get("responseCode", {})
    print("Response Data:")
    pprint(response_data)
    
    return response_data