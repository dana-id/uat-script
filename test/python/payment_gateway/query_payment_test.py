import os
import pytest
import asyncio
from datetime import datetime, timedelta, timezone
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
from payment_gateway.payment_pg_util import automate_payment_pg

from helper.util import get_request, with_delay, retry_on_inconsistent_request
from helper.api_helpers import execute_api_request_directly, get_standard_headers, execute_and_assert_api_error, get_headers_with_signature
from helper.assertion import *

title_case = "QueryPayment"
create_order_title_case = "CreateOrder"
cancel_order_title_case = "CancelOrder"
json_path_file = "resource/request/components/PaymentGateway.json"
user_phone_number = "0811742234"
user_pin = "123321"
merchant_id = os.environ.get("MERCHANT_ID", "216620010016033632482")

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
    api_instance = PaymentGatewayApi(api_client)


def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_order_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    data_order = create_test_order_init()
    print(f"\nCreating shared test order with reference number: {data_order[0]}")
    return data_order[0]

@pytest.fixture(scope="module")
def test_order_canceled_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = create_test_order_canceled()
    print(f"\nCreating shared test order with reference number cancelllll: {partner_reference_no}")
    return partner_reference_no

@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def create_test_order_init():
    data_order = []
    """Helper function to create a test order"""
    case_name = "CreateOrderApi"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = generate_partner_reference_no()
    json_dict["merchantId"] = merchant_id
    json_dict["validUpTo"] = (datetime.now().astimezone(timezone(timedelta(hours=7))) + timedelta(seconds=300)).strftime('%Y-%m-%dT%H:%M:%S+07:00')

    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.create_order(create_order_request_obj)
    data_order.append(api_response.partner_reference_no)
    data_order.append(api_response.web_redirect_url)
    print(f"Created order with reference number: {api_response.partner_reference_no}")
    print(f"Web redirect URL: {api_response.web_redirect_url}")
    return data_order


def create_test_order_canceled():
    """Helper function to create a test order with short expiration date to have canceled status"""
    # Cancel order
    data_order = create_test_order_init()
    # Get the request data for canceling the order
    json_dict_cancel = get_request(json_path_file, cancel_order_title_case, "CancelOrderValidScenario")
    
    # Set the original partner reference number
    json_dict_cancel["originalPartnerReferenceNo"] = data_order[0]
    json_dict_cancel["merchantId"] = merchant_id

    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict_cancel)
    print(f"Cancel order request object: {cancel_order_request_obj.to_json()}")
    
    # Make the API call
    api_response = api_instance.cancel_order(cancel_order_request_obj)
    return data_order[0]
    
def create_test_order_paid():
    """Helper function to create a test order with short expiration date to have paid status"""
    data_order = create_test_order_init()
    asyncio.run(automate_payment_pg(
        phone_number=user_phone_number,
        pin=user_pin,
        redirectUrlPayment=data_order[1],
        show_log=True
    ))
    return data_order[0]

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
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})

@with_delay()
@retry_on_inconsistent_request(max_retries=3, delay_seconds=5)
def test_query_payment_paid_order():
    # Create a test order paid and get the reference number
    test_order_paid_reference_number = create_test_order_paid()
    print(f"\nCreating shared test order with reference number: {test_order_paid_reference_number}")

    """Should query the payment with status paid (PAID)"""    
    # Query payment
    case_name = "QueryPaymentPaidOrder"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_paid_reference_number
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": test_order_paid_reference_number})

@with_delay()
def test_query_payment_canceled_order(test_order_canceled_reference_number):
    
    """Should query the payment with status canceled (CANCELLED)"""    
    # Query payment
    case_name = "QueryPaymentCanceledOrder"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_canceled_reference_number
    json_dict["merchantId"] = merchant_id
    
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
    json_dict["merchantId"] = merchant_id
    
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
    json_dict["merchantId"] = merchant_id
    
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
    json_dict["merchantId"] = merchant_id
    
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
    json_dict["merchantId"] = merchant_id
    
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


@with_delay()
def test_query_payment_general_error(test_order_reference_number):
    """Should handle general server error"""
    # Query payment
    case_name = "QueryPaymentGeneralError"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)

        pytest.fail("Expected ServiceException but the API call succeeded")
    except ServiceException as e:

        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": test_order_reference_number})

    except Exception as e:
        pytest.fail("Expected ServiceException but the API call give another exception")
