import json
import time
import functools
from pprint import pprint
import random
import os
from uuid import uuid4
from datetime import datetime, timezone, timedelta

def with_delay(delay_seconds=random.uniform(0.5, 1.5)):
    """
    Decorator that adds a delay after a function completes.
    Useful for rate limiting API calls in tests.
    
    :param delay_seconds: Delay in seconds (default: 0.5)
    :return: Decorated function
    """
    def decorator(func):
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            result = func(*args, **kwargs)
            time.sleep(delay_seconds)
            return result
        return wrapper
    return decorator

def retry_test(max_retries=3, delay_seconds=2):
    """Decorator that retries a test function if it fails"""
    def decorator(func):
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            for attempt in range(1, max_retries + 1):  # Start from 1, go up to max_retries
                try:
                    return func(*args, **kwargs)
                except Exception as e:
                    if attempt < max_retries:
                        print(f"Test failed on attempt {attempt}/{max_retries}. Retrying in {delay_seconds} seconds...")
                        time.sleep(delay_seconds)
                    else:
                        print(f"Test failed after {max_retries} attempts.")
                        raise e
        return wrapper
    return decorator

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
    
    return response_data

def retry_on_inconsistent_request(max_retries=3, delay_seconds=2.0, is_retryable=None):
    """
    Decorator to retry a function for inconsistent request scenarios (e.g., duplicate partnerReferenceNo with different payloads).
    Retries on 500, 'General Error', or 'Internal Server Error', and allows custom retry condition.

    :param max_retries: Maximum number of retries.
    :param delay_seconds: Delay between retries in seconds.
    :param is_retryable: Optional custom function to determine if error is retryable.
    :return: Decorated function.
    """
    def decorator(func):
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            last_error = None
            for attempt in range(max_retries):
                try:
                    response = func(*args, **kwargs)
                    # Check for status_code or message in response
                    status_code = getattr(response, 'status_code', None)
                    if status_code is None and isinstance(response, dict):
                        status_code = response.get('status_code')
                    message = getattr(response, 'message', None)
                    if message is None and isinstance(response, dict):
                        message = response.get('message')
                    if (
                        (status_code == 500 or (isinstance(message, str) and (
                            'General Error' in message or 'Internal Server Error' in message))) or
                        (is_retryable and is_retryable(response))
                    ):
                        if attempt < max_retries - 1:
                            print(f"Inconsistent request error encountered (attempt {attempt + 1}). Retrying in {delay_seconds}s...")
                            time.sleep(delay_seconds)
                            continue
                        else:
                            return response
                    return response
                except Exception as e:
                    last_error = e
                    error_msg = str(e)
                    if (
                        attempt < max_retries - 1 and (
                            'General Error' in error_msg or 'Internal Server Error' in error_msg or
                            (is_retryable and is_retryable(e))
                        )
                    ):
                        print(f"Inconsistent request exception encountered (attempt {attempt + 1}). Retrying in {delay_seconds}s...")
                        time.sleep(delay_seconds)
                        continue
                    else:
                        raise
            if last_error:
                raise last_error
        return wrapper
    return decorator

# Example usage:
#
# @retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
# def call_api():
#     return api_instance.create_order(request_obj)
#
# Or for inline usage:
# decorated_call = retry_on_inconsistent_request()(lambda: api_instance.create_order(request_obj))
# result = decorated_call()