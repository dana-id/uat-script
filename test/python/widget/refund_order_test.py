import os
import pytest
import asyncio
from datetime import datetime, timedelta, timezone
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.widget.v1.enum import *
from dana.widget.v1.models import *
from dana.widget.v1.models import RefundOrderRequest
from dana.widget.v1 import *
from dana.widget.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error
from helper.util import get_request, with_delay
from helper.assertion import assert_fail_response
from widget.payment_widget_util import automate_payment_widget
from helper.assertion import assert_response, assert_fail_response

# Widget-specific constants
title_case = "RefundOrder"
json_path_file = "resource/request/components/Widget.json"
user_phone_number = "083811223355"
user_pin = "181818"

# Set up configuration for the API client
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

# Create an instance of the API client
with ApiClient(configuration) as api_client:
    api_instance = WidgetApi(api_client)

def _valid_up_to_max_15_minutes():
    """validUpTo must be at most 15 minutes from now (API requirement)."""
    return (datetime.now().astimezone(timezone(timedelta(hours=7))) + timedelta(minutes=15)).strftime("%Y-%m-%dT%H:%M:%S+07:00")
    
def generate_partner_reference_no():
    """
    Generate a unique partner reference number for use in test requests.
    Returns:
        str: A new UUID string.
    """
    return str(uuid4())

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

@with_delay()
def test_refund_valid_scenario():
    # Case name and JSON request preparation
    data_order = create_test_order_paid()
    
    case_name = "RefundOrderValidScenario"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = data_order
    json_dict["partnerRefundNo"] = data_order
    
    # Simulate API call and response assertion
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    api_response = api_instance.refund_order(refund_order_request_obj)
    assert_response(json_path_file, title_case, case_name, RefundOrderResponse.to_json(api_response), {"originalPartnerReferenceNo": json_dict["originalPartnerReferenceNo"]})

@with_delay()
def test_refund_fail_duplicate_request():
    # Case name and JSON request preparation
    data_order = create_test_order_paid()
    
    case_name = "RefundFailDuplicateRequest"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = data_order
    json_dict["partnerRefundNo"] = data_order
    
    try:
        refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
        # Call the refund_order API endpoint with the request object
        api_instance.refund_order(refund_order_request_obj)
        refund_order_request_obj.refund_amount = Money(currency="IDR", value="2.00")
        api_instance.refund_order(refund_order_request_obj)

        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected NotFoundException but API call succeeded")
    except NotFoundException as e:
        # Assert the response matches the expected failure case
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"originalPartnerReferenceNo": json_dict["originalPartnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected NotFoundException but got a different exception")

@with_delay()
def test_refund_fail_order_not_paid():
    # Prepare test data
    data_order = create_test_order_init()
    
    case_name = "RefundFailOrderNotPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = data_order[0]
    json_dict["partnerRefundNo"] = data_order[0]
    
    try:
        refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
        
        # Call the refund_order API endpoint with the request object
        api_instance.refund_order(refund_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected NotFoundException but API call succeeded")

    except NotFoundException as e:
        
        # Assert the response matches the expected failure case
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"originalPartnerReferenceNo": json_dict["originalPartnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")
        
@with_delay()
def test_refund_fail_invalid_mandatory_field():
    # Prepare test data
    data_order = create_test_order_init()
    
    case_name = "RefundFailMandatoryParameterInvalid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = data_order[0]
    json_dict["partnerRefundNo"] = data_order[0]
    
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
        
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
        "https://api.sandbox.dana.id/v1.0/debit/refund.htm",
        refund_order_request_obj,
        headers,
        400,  # Bad Request
        json_path_file,
        title_case,
        case_name,
        {"originalPartnerReferenceNo": data_order[0]}  
    )

@with_delay()
def test_refund_fail_order_not_exist():
    case_name = "RefundFailOrderNotExist"
    json_dict = get_request(json_path_file, title_case, case_name)
   
    try:
        refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
        
        # Call the refund_order API endpoint with the request object
        api_instance.refund_order(refund_order_request_obj)
        
        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected NotFoundException but API call succeeded")

    except NotFoundException as e:
        
        # Assert the response matches the expected failure case
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {"originalPartnerReferenceNo": json_dict["originalPartnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

@with_delay()
def test_refund_fail_timeout():
    # Scenario: RefundFailTimeout
    # Purpose: Verify that a refund request fails when it times out.
    # Steps:
    #     1. Prepare a refund request payload with a partnerReferenceNo that is known to cause a timeout.
    #     2. Call the refund API endpoint.
    #     3. Assert the response indicates a timeout error.
    # Expected: The API returns an error indicating a timeout.
    """Should fail to process a refund due to timeout."""
    # Case name and JSON request preparation
    case_name = "RefundFailTimeout"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Use the provided order reference number from the fixture
    json_dict["originalPartnerReferenceNo"] = generate_partner_reference_no()
    json_dict["partnerRefundNo"] = generate_partner_reference_no()

    # Create the request object from the JSON dictionary
    refund_order_request = RefundOrderRequest.from_dict(json_dict)
    try:
        # Call the refund API endpoint with the request object
        api_instance.refund_order(refund_order_request)

        # If the API call succeeds, fail the test as we expect an error
        pytest.fail("Expected error but API call succeeded")
    except ServiceException as e:
        # Assert the error response matches the expected output for a refund timeout
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except Exception as e:
        # Handle any other unexpected exceptions
        pytest.fail("Expected ServiceException but the API call give another exception")

@with_delay()
def test_refund_fail_merchant_status_abnormal():
    # Scenario: RefundFailMerchantStatusAbnormal
    # Purpose: Verify that a refund request fails when the merchant status is abnormal.
    # Steps:
    #     1. Prepare a refund request payload with a partnerReferenceNo that is known to cause an abnormal merchant status.
    #     2. Call the refund API endpoint.
    #     3. Assert the response indicates the merchant status is abnormal.
    # Expected: The API returns an error indicating the merchant status is abnormal.
    """Should fail to process a refund due to abnormal merchant status."""
    # Case name and JSON request preparation
    case_name = "RefundFailMerchantStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Use the provided order reference number from the fixture
    json_dict["originalPartnerReferenceNo"] = generate_partner_reference_no()
    json_dict["partnerRefundNo"] = generate_partner_reference_no()

    # Create the request object from the JSON dictionary
    refund_order_request = RefundOrderRequest.from_dict(json_dict)
    try:
        # Call the refund API endpoint with the request object
        api_instance.refund_order(refund_order_request)

        # If the API call succeeds, fail the test as we expect an error
        pytest.fail("Expected error but API call succeeded")
    except NotFoundException as e:
        # Assert the error response matches the expected output for a refund with abnormal merchant status
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except Exception as e:
        # Handle any other unexpected exceptions
        pytest.fail("Expected NotFoundException but the API call give another exception")