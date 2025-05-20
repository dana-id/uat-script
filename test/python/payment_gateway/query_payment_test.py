import os
import pytest
import time
from uuid import uuid4
from datetime import datetime, timedelta, timezone
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1.models import QueryPaymentRequest, CreateOrderByApiRequest
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.exceptions import *
from dana.utils.snap_header import SnapHeader

from helper.util import get_request, with_delay
from helper.api_helpers import execute_api_request_directly, get_standard_headers, execute_and_assert_api_error, get_headers_with_signature
from helper.assertion import *

title_case = "QueryPayment"
create_order_title_case = "CreateOrder"
json_path_file = "resource/request/components/PaymentGateway.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        CHANNEL_ID=os.environ.get("CHANNEL_ID"),
        ENV=Env.SANDBOX
    )
)

with ApiClient(configuration) as api_client:
    api_instance = PaymentGatewayApi(api_client)


def generate_partner_reference_no():
    return str(uuid4())


def create_test_order(partner_reference_no):
    """Helper function to create a test order"""
    case_name = "CreateOrderApi"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.create_order(create_order_request_obj)
    except Exception as e:
        pytest.fail(f"Fail to call create order API {e}")

def create_test_order_canceled(partner_reference_no):
    """Helper function to create a test order with short expiration date to have canceled status"""
    case_name = "CreateOrderApi"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = partner_reference_no

    # Set the expiration time to 1 second from now
    json_dict["validUpTo"] = (datetime.now().astimezone(timezone(timedelta(hours=7))) + timedelta(seconds=1)).strftime('%Y-%m-%dT%H:%M:%S+07:00')
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.create_order(create_order_request_obj)
    except Exception as e:
        pytest.fail(f"Fail to call create order API {e}")


@pytest.fixture(scope="module")
def test_order_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = generate_partner_reference_no()
    print(f"\nCreating shared test order with reference number: {partner_reference_no}")
    create_test_order(partner_reference_no)
    return partner_reference_no    

@with_delay()
def test_query_payment_created_order(test_order_reference_number):
    """Should query the payment with status created but not paid"""
    # Get the partner reference number from the fixture
    partner_reference_no = test_order_reference_number
    
    # Query payment
    case_name = "QueryPaymentCreatedOrder"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})

# @with_delay()
# def test_query_payment_canceled_order():

#     partner_reference_no = generate_partner_reference_no()
#     create_test_order_canceled(partner_reference_no)
#     time.sleep(2)
    
#     """Should query the payment with status canceled"""    
#     # Query payment
#     case_name = "QueryPaymentCanceledOrder"
    
#     # Get the request data from the JSON file
#     json_dict = get_request(json_path_file, title_case, case_name)
    
#     # Set the correct partner reference number
#     json_dict["originalPartnerReferenceNo"] = partner_reference_no
    
#     # Convert the request data to a QueryPaymentRequest object
#     query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
#     # Make the API call
#     api_response = api_instance.query_payment(query_payment_request_obj)
    
#     # Assert the API response
#     assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": test_order_reference_number_canceled})


@with_delay()
def test_query_payment_invalid_format(test_order_reference_number):
    """Should fail when query uses invalid format"""    
    # Query payment
    case_name = "QueryPaymentInvalidFormat"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Prepare headers with invalid timestamp format using our helper
    # This now handles the signature generation internally
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/status.htm",
        request_obj=json_dict,
        invalid_timestamp=True
    )
    
    # Use our helper function to execute API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm",
        query_payment_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )



@with_delay()
def test_query_payment_invalid_mandatory_field(test_order_reference_number):
    """Should fail when query is missing mandatory field"""
    
    # Query payment
    case_name = "QueryPaymentInvalidMandatoryField"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Prepare headers without X-TIMESTAMP to trigger mandatory field error
    # This now handles the signature generation internally
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/status.htm",
        request_obj=json_dict,
        with_timestamp=False
    )
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm",
        query_payment_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )


@with_delay()
def test_query_payment_transaction_not_found(test_order_reference_number):
    """Should fail when transaction is not found"""
    # Query payment
    case_name = "QueryPaymentTransactionNotFound"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Modify the reference number to ensure it's not found
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number + "_NOT_FOUND"
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)

        pytest.fail("Expected NotFoundException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})


@with_delay()
def test_query_payment_general_error(test_order_reference_number):
    """Should handle general server error"""
    # Query payment
    case_name = "QueryPaymentGeneralError"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)

        pytest.fail("Expected ServiceException but the API call succeeded")
    except ServiceException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": test_order_reference_number})


@with_delay()
def test_query_payment_unauthorized(test_order_reference_number):
    """Should fail when unauthorized due to invalid signature"""
    # Query payment
    case_name = "QueryPaymentUnauthorized"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, "QueryPaymentCreatedOrder")
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Prepare headers with invalid signature to trigger authorization error
    # Since we're only using the invalid signature flag, we don't need to pass any of the other parameters
    headers = get_headers_with_signature(invalid_signature=True)
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm",
        query_payment_request_obj,
        headers,
        401,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )
