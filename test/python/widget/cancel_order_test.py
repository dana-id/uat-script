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
import asyncio
from datetime import datetime, timedelta, timezone
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
from widget.payment_widget_util import automate_payment_widget
from helper.assertion import assert_response, assert_fail_response

# Test configuration and constants

title_case = "CancelOrder"
json_path_file = "resource/request/components/Widget.json"
user_phone_number = "083811223355"
user_pin = "181818"

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
    
def _valid_up_to_max_15_minutes():
    """validUpTo must be at most 15 minutes from now (API requirement)."""
    return (datetime.now().astimezone(timezone(timedelta(hours=7))) + timedelta(minutes=15)).strftime("%Y-%m-%dT%H:%M:%S+07:00")
    
def create_test_order_init():
    data_order = []
    """Helper function to create a test order"""
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, "Payment", "PaymentSuccess")
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = generate_partner_reference_no()
    json_dict["validUpTo"] = _valid_up_to_max_15_minutes()

    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.widget_payment(create_order_request_obj)
    data_order.append(api_response.partner_reference_no)
    data_order.append(api_response.web_redirect_url)
    print(f"Created order with reference number: {api_response.partner_reference_no}")
    print(f"Web redirect URL: {api_response.web_redirect_url}")
    return data_order

def create_test_order_paid():
    """Helper function to create a test order with short expiration date to have paid status"""
    data_order = create_test_order_init()
    asyncio.run(automate_payment_widget(
        phone_number=user_phone_number,
        pin=user_pin,
        redirectUrlPayment=data_order[1],
        show_log=True
    ))
    return data_order[0]

def create_test_order_refunded():
    """Helper function to create a test order with refunded status"""
    partner_reference_no = create_test_order_paid()
    # Refund the order
    case_name = "RefundOrderValidScenario"
    json_dict = get_request(json_path_file, "RefundOrder", case_name)
    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    json_dict["partnerRefundNo"] = partner_reference_no
    
    # Create the request object from the JSON dictionary
    refund_order_request = RefundOrderRequest.from_dict(json_dict)

    # Call the refund API endpoint with the request object
    api_instance.refund_order(refund_order_request)
    return partner_reference_no

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
def test_cancel_order_valid_scenario():
    # Case name and JSON request preparation
    data_order = create_test_order_init()
    
    # Hit the cancel order API with the created order's reference number
    case_name = "CancelOrderValidScenario"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = data_order[0]
    
    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    api_response = api_instance.cancel_order(cancel_order_request_obj)
    assert_response(json_path_file, title_case, case_name, RefundOrderResponse.to_json(api_response), {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})

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
            {"originalPartnerReferenceNo": test_cancel_order_reference_number}
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
            {"originalPartnerReferenceNo": test_cancel_order_reference_number}
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
    json_dict["originaloriginalPartnerReferenceNo"] = test_cancel_order_reference_number
    
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
        {"originalPartnerReferenceNo": test_cancel_order_reference_number}  
    )

@with_delay()
def test_cancel_order_fail_order_not_exist():
    # Case name and JSON request preparation
    case_name = "CancelOrderFailOrderNotExist"
    json_dict = get_request(json_path_file, title_case, case_name)
    print(json_dict)

    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    try:
        # Call the cancel_order API endpoint with the request object
        api_response = api_instance.cancel_order(cancel_order_request_obj)
        print(api_response)
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected NotFoundException but API call succeeded")
    except NotFoundException as e:
        # If the API call fails with NotFoundException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {'originalPartnerReferenceNo': json_dict["originalPartnerReferenceNo"]}
        )
    except Exception as e:
        # If any other exception occurs, fail the test
        pytest.fail("Expected NotFoundException but got a different exception")

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
            {"originalPartnerReferenceNo": "4035715"}
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
            {'originalPartnerReferenceNo': json_dict["originalPartnerReferenceNo"]}
        )
    except Exception as e:
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
            {'originalPartnerReferenceNo': json_dict["originalPartnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

@with_delay()
def test_cancel_order_fail_invalid_status():
    # Prepare data order paid
    test_order_reference_number = create_test_order_paid()

    """Should fail to cancel the order when the order has already been refunded."""
    case_name = "CancelOrderFailOrderInvalidStatus"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    
    # Create the CancelOrderRequest object from the JSON dictionary
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    try:
        # Call the cancel_order API endpoint with the request object
        api_instance.cancel_order(cancel_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected NotFoundException but API call succeeded")
    except NotFoundException as e:
        # If the API call fails with NotFoundException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {'originalPartnerReferenceNo': json_dict["originalPartnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected NotFoundException but got a different exception")

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
            {'originalPartnerReferenceNo': json_dict["originalPartnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ServiceException but got a different exception")
