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

from helper.util import get_request, with_delay, retry_on_inconsistent_request
from helper.api_helpers import execute_api_request_directly, get_standard_headers, execute_and_assert_api_error, get_headers_with_signature
from helper.assertion import *

title_case = "QueryPayment"
create_order_title_case = "CreateOrder"
cancel_order_title_case = "CancelOrder"
json_path_file = "resource/request/components/PaymentGateway.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        ENV=Env.SANDBOX
    )
)

with ApiClient(configuration) as api_client:
    api_instance = PaymentGatewayApi(api_client)


def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_order_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = generate_partner_reference_no()
    print(f"\nCreating shared test order with reference number: {partner_reference_no}")
    create_test_order(partner_reference_no)
    return partner_reference_no

@pytest.fixture(scope="module")
def test_order_paid_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = generate_partner_reference_no()
    print(f"\nCreating shared test order with reference number: {partner_reference_no}")
    create_test_order_paid(partner_reference_no)
    return partner_reference_no

@pytest.fixture(scope="module")
def test_order_canceled_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = generate_partner_reference_no()
    print(f"\nCreating shared test order with reference number: {partner_reference_no}")
    create_test_order_canceled(partner_reference_no)
    return partner_reference_no

@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
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
    api_instance.create_order(create_order_request_obj)


@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def create_test_order_paid(partner_reference_no):
    """Helper function to create a test order"""
    case_name = "CreateOrderNetworkPayPgOtherWallet"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
    # Set the partner reference number and amount mock
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["amount"]["value"] = "50001.00"
    json_dict["payOptionDetails"][0]["transAmount"]["value"] = "50001.00"
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_instance.create_order(create_order_request_obj)

def create_test_order_canceled(partner_reference_no):
    """Helper function to create a test order with short expiration date to have canceled status"""
    case_name = "CreateOrderApi"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_instance.create_order(create_order_request_obj)

    # Get the request data for canceling the order
    json_dict_cancel = get_request(json_path_file, cancel_order_title_case, "CancelOrderValidScenario")
    
    # Set the original partner reference number
    json_dict_cancel["originalPartnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict_cancel)
    
    # Make the API call to cancel the order
    api_instance.cancel_order(cancel_order_request_obj)

    # Cancel order
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, "CancelOrder", "CancelOrderValidScenario")
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)
    except Exception as e:
        pytest.fail(f"Fail to call cancel order API {e}")


@with_delay()
def test_query_payment_created_order(test_order_reference_number):
    """Should query the payment with status created but not paid (INIT)"""
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
# def test_query_payment_paid_order(test_order_paid_reference_number):
    
#     """Should query the payment with status paid (PAID)"""    
#     # Query payment
#     case_name = "QueryPaymentPaidOrder"
    
#     # Get the request data from the JSON file
#     json_dict = get_request(json_path_file, title_case, case_name)
    
#     # Set the correct partner reference number
#     json_dict["originalPartnerReferenceNo"] = test_order_paid_reference_number
    
#     # Convert the request data to a QueryPaymentRequest object
#     query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
#     print("Query payment request object: ", query_payment_request_obj)
    
#     # Make the API call
#     api_response = api_instance.query_payment(query_payment_request_obj)
    
#     # Assert the API response
#     assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": test_order_paid_reference_number})

@with_delay()
def test_query_payment_canceled_order(test_order_canceled_reference_number):
    
    """Should query the payment with status canceled (CANCELLED)"""    
    # Query payment
    case_name = "QueryPaymentCanceledOrder"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_canceled_reference_number
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": test_order_canceled_reference_number})

@with_delay()
def test_query_payment_invalid_format(test_order_reference_number):
    """Should fail when query uses invalid format (ex: X-TIMESTAMP header format not correct)"""    
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
    """Should fail when query is missing mandatory field (ex: request without X-TIMESTAMP header)"""
    
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

    except Exception as e:
        pytest.fail("Expected NotFoundException but the API call give another exception")


# @with_delay()
# def test_query_payment_general_error(test_order_query_general_error):
#     """Should handle general server error"""
#     # Query payment
#     case_name = "QueryPaymentGeneralError"
    
#     # Get the request data from the JSON file
#     json_dict = get_request(json_path_file, title_case, case_name)
    
#     # Set the correct partner reference number
#     json_dict["originalPartnerReferenceNo"] = test_order_query_general_error
    
#     # Convert the request data to a QueryPaymentRequest object
#     query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
#     # Make the API call and expect an exception
#     try:
#         api_instance.query_payment(query_payment_request_obj)

#         pytest.fail("Expected ServiceException but the API call succeeded")
#     except ServiceException as e:

#         assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": test_order_reference_number})

#     except Exception as e:
#         pytest.fail("Expected ServiceException but the API call give another exception")
