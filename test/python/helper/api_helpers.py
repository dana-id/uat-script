import json
import os
from uuid import uuid4
from datetime import datetime, timezone, timedelta
import pytest

from helper.assertion import assert_fail_response

def execute_api_request_directly(api_client, method, endpoint, request_obj, headers):
    """
    Executes a direct API call with the provided headers and request object.
    This is a direct wrapper around api_client.call_api that preserves the exact behavior.
    
    :param api_client: The API client to use for the request
    :param method: HTTP method (e.g., "POST", "GET")
    :param endpoint: The API endpoint URL
    :param request_obj: The request object to send
    :param headers: The exact headers to use
    :return: The API response
    """
    # Convert request object to dictionary if needed
    request_data = request_obj
    if hasattr(request_obj, "to_dict"):
        request_data = request_obj.to_dict()
    
    # HTTP requires header values to be strings; env-based headers (e.g. CLIENT_SECRET) may be None
    headers = {k: (v if v is not None else "") for k, v in headers.items()}
    
    # Execute the API call with exactly the provided headers
    return api_client.call_api(method, endpoint, headers, request_data)


def get_standard_headers(with_timestamp=True):
    """
    Returns standard headers used in API requests.
    
    :param with_timestamp: Whether to include X-TIMESTAMP (defaults to True)
    :return: Dict of standard headers
    """
    headers = {
        "X-PARTNER-ID": os.getenv("X_PARTNER_ID"),
        "CHANNEL-ID": "95221",
        "ORIGIN": os.getenv("ORIGIN"),
        "X-EXTERNAL-ID": str(uuid4()),
        "CLIENT_SECRET": os.getenv("CLIENT_SECRET"),
        "Content-Type": "application/json"
    }
    
    if with_timestamp:
        headers["X-TIMESTAMP"] = datetime.now().astimezone(
            timezone(timedelta(hours=7))
        ).strftime('%Y-%m-%dT%H:%M:%S+07:00')
    
    return headers


def get_headers_with_signature(method=None, resource_path=None, request_obj=None, with_timestamp=True, invalid_timestamp=False, invalid_signature=False):
    """
    Creates headers including the signature, handling all the signature generation internally
    
    :param method: HTTP method (e.g., "POST", "GET") - required unless invalid_signature=True
    :param resource_path: API resource path (e.g., "/payment-gateway/v1.0/debit/status.htm") - required unless invalid_signature=True
    :param request_obj: Request object or dictionary - required unless invalid_signature=True
    :param with_timestamp: Whether to include X-TIMESTAMP (defaults to True)
    :param invalid_timestamp: If True, uses an invalid timestamp format
    :param invalid_signature: If True, uses an invalid signature
    :return: Dict of headers including the signature
    """
    from dana.utils.snap_header import SnapHeader
    import json
    import os
    
    # Get the standard headers first
    headers = get_standard_headers(with_timestamp=False)  # We'll handle timestamp specially
    
    # Add the signature
    if invalid_signature:
        headers["X-SIGNATURE"] = "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5"  # Invalid signature
    elif invalid_signature is None:
        headers["X-SIGNATURE"] = ""
        print("Warning: Invalid signature, using None")
    else:
        if method is None or resource_path is None or request_obj is None:
            raise ValueError("Method, resource_path, and request_obj are required unless invalid_signature=True")
            
        # Convert request object to dict if needed
        request_dict = request_obj
        if hasattr(request_obj, "to_dict"):
            request_dict = request_obj.to_dict()
            
        # Generate snap headers
        snap_generated_headers = SnapHeader.get_snap_generated_auth(
            method=method,
            resource_path=resource_path,
            body=json.dumps(request_dict),
            private_key=os.getenv("PRIVATE_KEY")
        )
        
        headers["X-SIGNATURE"] = snap_generated_headers["X-SIGNATURE"]["value"]
    
    # Add timestamp if needed
    if with_timestamp:
        if invalid_timestamp:
            headers["X-TIMESTAMP"] = datetime.now().astimezone(
                timezone(timedelta(hours=7))
            ).strftime('%Y-%m-%d %H:%M:%S+07:00')  # Invalid format (space instead of T)
        else:
            headers["X-TIMESTAMP"] = datetime.now().astimezone(
                timezone(timedelta(hours=7))
            ).strftime('%Y-%m-%dT%H:%M:%S+07:00')  # Valid format
    
    return headers


def execute_and_assert_api_error(api_client, method, endpoint, request_obj, 
                                headers, expected_status, 
                                json_path_file, title_case, case_name, 
                                variable_dict=None):
    """
    Executes an API request and asserts the error response matches expectations.
    This maintains the exact same implementation that was originally in the test files.
    
    :param api_client: The API client instance
    :param method: HTTP method (POST, GET, etc)
    :param endpoint: API endpoint URL
    :param request_obj: Request object or dictionary 
    :param headers: Complete headers dictionary to use
    :param expected_status: Expected HTTP status code
    :param json_path_file: Path to JSON file with expected responses
    :param title_case: Title case for the test (e.g. "QueryPayment")
    :param case_name: Specific test case name
    :param variable_dict: Optional dictionary for variable substitution
    """
    try:
        # Make the API call with the exact provided headers
        response = execute_api_request_directly(
            api_client, 
            method, 
            endpoint, 
            request_obj, 
            headers
        )

        # Assert the response status matches expectations
        try:
            assert response.status == expected_status
        except:
            pytest.fail(f"Expected status {expected_status} but got {response.status}")
        
        # Read the response data and decode from bytes to string
        response_data = response.read().decode('utf-8')
        
        # Assert the API response
        assert_fail_response(json_path_file, title_case, case_name, response_data, variable_dict)

    except Exception as e:
        # If the exception has a desired attribute, use it directly
        if hasattr(e, "body") and e.body:
            assert_fail_response(json_path_file, title_case, case_name, e.body, variable_dict)
        else:
            pytest.fail(f"Failed to call API: {str(e)}")
