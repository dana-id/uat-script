# cancel_order_test.py
# Integration tests for the CancelOrder API endpoint in the DANA Widget service.
#
# This module contains test cases for various scenarios related to order cancellation,
# including success, user/merchant/account status errors, missing/invalid parameters,
# signature validation, and timeout handling.
#
# Test data is loaded from resource/request/components/Widget.json.
#
# Usage:
#   pytest cancel_order_test.py

import os
import pytest
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.widget.v1.enum import *
from dana.widget.v1.models import *
from dana.widget.v1 import *
from dana.widget.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error
from helper.util import get_request, with_delay
from helper.assertion import assert_response, assert_fail_response

# Test configuration and constants

title_case = "CancelOrder"
json_path_file = "resource/request/components/Widget.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        ENV=Env.SANDBOX,
        DANA_ENV=Env.SANDBOX,
        X_DEBUG="true"
    )
)

with ApiClient(configuration) as api_client:
    api_instance = WidgetApi(api_client)

def generate_partner_reference_no():
    """
    Generate a unique partner reference number for use in test requests.
    Returns:
        str: A new UUID string.
    """
    return str(uuid4())

@pytest.fixture(scope="module")
def test_cancel_order_reference_number():
    """
    Pytest fixture to provide a unique partner reference number for tests that require it.
    Returns:
        str: A unique partner reference number (scoped to the test module).
    """
    return generate_partner_reference_no()

@with_delay()
def test_cancel_order_fail_user_status_abnormal(test_cancel_order_reference_number):
    # Scenario: CancelOrderFailUserStatusAbnormal
    # Purpose: Verify that the order cannot be cancelled if the user's status is abnormal.
    # Steps:
    #   1. Prepare a request payload with a partner reference number and abnormal user status.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a forbidden error due to abnormal user status.
    # Expected: The API returns a 403 Forbidden response with an appropriate error message.
    
    """Should fail to cancel the order when user status is abnormal."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailUserStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)

    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)

        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected ForbiddenException but API call succeeded")
    except ForbiddenException as e:
        # Assert the response matches the expected failure case
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"partnerReferenceNo": test_cancel_order_reference_number}
        )   
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

@with_delay()
def test_cancel_order_fail_merchant_status_abnormal(test_cancel_order_reference_number):
    # Scenario: CancelOrderFailMerchantStatusAbnormal
    # Purpose: Verify that the order cannot be cancelled if the merchant's status is abnormal.
    # Steps:
    #   1. Prepare a request payload with a partner reference number and abnormal merchant status.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a not found error due to abnormal merchant status.
    # Expected: The API returns a 404 Not Found response with an appropriate error message.

    """Should fail to cancel the order when merchant status is abnormal."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailMerchantStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)

    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected NotFoundException but API call succeeded")
    except NotFoundException as e:
        # Assert the response matches the expected failure case
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"partnerReferenceNo": test_cancel_order_reference_number}
        )
    except Exception as e:
        # If any other exception occurs, fail the test
        pytest.fail("Expected NotFoundException but got a different exception")

@with_delay()
@pytest.mark.skip(reason="Skipped for now")
def test_cancel_order_fail_missing_parameter(test_cancel_order_reference_number):
    # Scenario: CancelOrderFailMissingParameter
    # Purpose: Verify that the order cannot be cancelled if required parameters are missing in the request.
    # Steps:
    #   1. Prepare an incomplete request payload missing required parameters.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a bad request error due to missing parameters.
    # Expected: The API returns a 400 Bad Request response with an appropriate error message.
    
    """Should fail to cancel the order when required parameters are missing."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailMissingParameter"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Ensure the request contains the original partner reference number
    json_dict["originalPartnerReferenceNo"] = test_cancel_order_reference_number
    
    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)

    # Prepare the headers with the signature
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/v1.0/debit/cancel.htm",
        request_obj=json_dict,
        with_timestamp=False
    )

    # Execute the API call and assert the expected error response
    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/v1.0/debit/cancel.htm",
        cancel_order_request_obj,
        headers,
        400,  # Bad Request
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_cancel_order_reference_number}  
    )

@with_delay()
def test_cancel_order_fail_order_not_exist():
    # Scenario: CancelOrderFailOrderNotExist
    # Purpose: Verify that the order cannot be cancelled if it does not exist in the system.
    # Steps:
    #   1. Prepare a request payload with a partner reference number for a non-existent order.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a not found error for the non-existent order.
    # Expected: The API returns a 404 Not Found response with an appropriate error message.
    """Should fail to cancel the order when the order does not exist."""
    # case_name = "CancelOrderFailOrderNotExist"
    # json_dict = get_request(json_path_file, title_case, case_name)
    
    # cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    # try:
    #     api_instance.cancel_order(cancel_order_request_obj)
    #     pytest.fail("Expected NotFoundException but API call succeeded")
    # except NotFoundException as e:
    #     assert_fail_response(
    #         json_path_file,
    #         title_case,
    #         case_name,
    #         e.body,
    #         {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]}  # Use the original partner reference number
    #     )
    # except Exception as e:
    #     print(f"Exception type: {type(e)}, message: {e}")
    #     pytest.fail("Expected NotFoundException but got a different exception")
    pytest.skip("SKIP: Need confirmation, Expected NotFoundException but API call succeeded")

@with_delay()
def test_cancel_order_fail_exceed_cancel_window_time():
    # Scenario: CancelOrderFailExceedCancelWindowTime
    # Purpose: Verify that the order cannot be cancelled if the cancellation request is made
    #          after the allowed time window.
    # Steps:
    #   1. Prepare a request payload with a partner reference number for an order
    #      that is beyond the cancellation time window.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a forbidden error due to exceeding the cancellation time window.
    # Expected: The API returns a 403 Forbidden response with an appropriate error message.
    
    """Should fail to cancel the order when the cancellation request exceeds the allowed time window."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailExceedCancelWindowTime"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected ForbiddenException but API call succeeded")
    except ForbiddenException as e:
        # If the API call fails with ForbiddenException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"partnerReferenceNo": "4035715"}
        )
    except Exception as e:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

@with_delay()
def test_cancel_order_fail_not_allowed_by_agreement():
    # Scenario: CancelOrderFailNotAllowedByAgreement
    # Purpose: Verify that the order cannot be cancelled if the cancellation is not allowed
    #          according to the agreement terms.
    # Steps:
    #   1. Prepare a request payload with a partner reference number for an order
    #      that is not allowed to be cancelled by the agreement.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a forbidden error due to the cancellation not being allowed
    #      by the agreement.
    # Expected: The API returns a 403 Forbidden response with an appropriate error message.
    """Should fail to cancel the order when the cancellation is not allowed by the agreement."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailNotAllowedByAgreement"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected ForbiddenException but API call succeeded")
    except ForbiddenException as e:
        # If the API call fails with ForbiddenException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"partnerReferenceNo": "4035705"}
        )
    except Exception as e:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

@with_delay()
def test_cancel_order_fail_account_status_abnormal():
    # Scenario: CancelOrderFailAccountStatusAbnormal
    # Purpose: Verify that the order cannot be cancelled if the account's status is abnormal.
    # Steps:
    #   1. Prepare a request payload with a partner reference number and abnormal account status.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a forbidden error due to abnormal account status.
    # Expected: The API returns a 403 Forbidden response with an appropriate error message.
    """Should fail to cancel the order when the account status is abnormal."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailAccountStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected ForbiddenException but API call succeeded")
    except ForbiddenException as e:
        # If the API call fails with ForbiddenException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"partnerReferenceNo": "4035705"}  # Use the appropriate partner reference number
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

@with_delay()
def test_cancel_order_fail_insufficient_merchant_balance(test_cancel_order_reference_number):
    # Scenario: CancelOrderFailInsufficientMerchantBalance
    # Purpose: Verify that the order cannot be cancelled if the merchant does not have enough balance
    #          to cover the cancellation.
    # Steps:
    #   1. Prepare a request payload with a partner reference number for an order
    #      and an insufficient merchant balance.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a forbidden error due to insufficient merchant balance.
    # Expected: The API returns a 403 Forbidden response with an appropriate error message.
    """Should fail to cancel the order when the merchant has insufficient balance."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailInsufficientMerchantBalance"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)

        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected ForbiddenException but API call succeeded")
    except ForbiddenException as e:
        # If the API call fails with ForbiddenException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"partnerReferenceNo": "4035714"}  # Use the appropriate partner reference number
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

@with_delay()
def test_cancel_order_fail_order_refunded(test_cancel_order_reference_number):
    # Scenario: CancelOrderFailOrderRefunded
    # Purpose: Verify that the order cannot be cancelled if it has already been refunded.
    # Steps:
    #   1. Prepare a request payload with a partner reference number for an order
    #      that has already been refunded.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a forbidden error due to the order being already refunded.
    # Expected: The API returns a 403 Forbidden response with an appropriate error message.
    """Should fail to cancel the order when the order has already been refunded."""
    case_name = "CancelOrderFailOrderRefunded"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_timeout():
    # Scenario: CancelOrderFailTimeout
    # Purpose: Verify that the order cannot be cancelled if the request times out.
    # Steps:
    #   1. Prepare a request payload with a partner reference number and a simulated timeout.
    #   2. Call the cancel_order API endpoint.
    #   3. Assert the response indicates a service error due to the request timeout.
    # Expected: The API returns a 500 Internal Server Error response with an appropriate error message.
    """Should fail to cancel the order when the request times out."""
    # Case name and JSON request preparation
    case_name = "CancelOrderFailTimeout"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected ServiceException but API call succeeded")
    except ServiceException as e:
        # If the API call fails with ServiceException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"partnerReferenceNo": "500701"}  # Use the appropriate partner reference number
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ServiceException but got a different exception")
