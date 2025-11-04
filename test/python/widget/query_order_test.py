import os
import pytest
import asyncio
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.widget.v1.enum import *
from dana.widget.v1.models import *
from dana.widget.v1 import *
from dana.widget.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.util import get_request, retry_on_inconsistent_request, generate_partner_reference_no, with_delay, generate_partner_reference_no
from helper.assertion import assert_response
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error, assert_fail_response
from widget.payment_widget_util import automate_payment_widget

# Widget-specific constants
title_case = "QueryOrder"
create_payment_title_case = "Payment"
user_phone_number = "0811742234"
user_pin = "123321"
json_path_file = "resource/request/components/Widget.json"
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
    api_instance = WidgetApi(api_client)

@pytest.fixture(scope="module")
def widget_order_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    data_order = create_widget_order_init()
    print(f"\nCreating shared test order with reference number: {data_order[0]}")
    return data_order[0]

@pytest.fixture(scope="module")
def widget_order_paying_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    data_order = create_widget_order_paying()
    print(f"\nCreating shared test order with reference number: {data_order[0]}")
    return data_order[0]

@pytest.fixture(scope="module")
def widget_order_canceled_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = create_widget_order_canceled()
    print(f"\nCreating shared test order with reference number cancel: {partner_reference_no}")
    return partner_reference_no

@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def create_widget_order_init():
    data_order = []
    """Helper function to create a test order"""
    case_name = "PaymentSuccess"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_payment_title_case, case_name)

    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    api_response = api_instance.widget_payment(create_payment_request_obj)
    data_order.append(api_response.partner_reference_no)
    data_order.append(api_response.web_redirect_url)
    print(f"Created order widget with reference number: {api_response.partner_reference_no}")
    print(f"Web redirect URL: {api_response.web_redirect_url}")
    return data_order

@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def create_widget_order_paying():
    data_order = []
    """Helper function to create a test order"""
    case_name = "PaymentPaying"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_payment_title_case, case_name)

    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    api_response = api_instance.widget_payment(create_payment_request_obj)
    data_order.append(api_response.partner_reference_no)
    data_order.append(api_response.web_redirect_url)
    print(f"Created order widget with reference number: {api_response.partner_reference_no}")
    print(f"Web redirect URL: {api_response.web_redirect_url}")
    return data_order


def create_widget_order_canceled():
    """Helper function to create a test order with short expiration date to have canceled status"""
    # Cancel order
    data_order = create_widget_order_init()
    # Get the request data for canceling the order
    json_dict_cancel = get_request(json_path_file, create_payment_title_case, "CancelOrderValidScenario")
    
    # Set the original partner reference number
    json_dict_cancel["originalPartnerReferenceNo"] = data_order[0]

    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict_cancel)
    print(f"Cancel order request object: {cancel_order_request_obj.to_json()}")
    
    # Make the API call
    api_instance.cancel_order(cancel_order_request_obj)
    return data_order[0]
    
def create_widget_order_paid():
    """Helper function to create a test order with short expiration date to have paid status"""
    data_order = create_widget_order_init()
    asyncio.run(automate_payment_widget(
        phone_number=user_phone_number,
        pin=user_pin,
        redirectUrlPayment=data_order[1],
        show_log=True
    ))
    return data_order[0]

@with_delay()
def test_query_order_success_paid():
    # Scenario: QueryOrderSuccessPaid
    # Purpose: Verify that querying an order with a status of 'paid' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for a paid order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for a paid order.
    # Expected: The API returns the correct order details with status 'paid'.
    widget_order_paid_reference_number = create_widget_order_paid()
    case_name = "QueryOrderSuccessPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = widget_order_paid_reference_number
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": widget_order_paid_reference_number})

@with_delay()
def test_query_order_success_initiated(widget_order_reference_number):
    # Scenario: QueryOrderSuccessInitiated
    # Purpose: Verify that querying an order with a status of 'initiated' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for an initiated order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for an initiated order.
    # Expected: The API returns the correct order details with status 'initiated'.
    case_name = "QueryOrderSuccessInitiated"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = widget_order_reference_number
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": widget_order_reference_number})

@with_delay()
def test_query_order_success_paying(widget_order_paying_reference_number):
    # Scenario: QueryOrderSuccessPaying
    # Purpose: Verify that querying an order with a status of 'paying' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for a paying order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for a paying order.
    # Expected: The API returns the correct order details with status 'paying'.
    case_name = "QueryOrderSuccessPaying"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = widget_order_paying_reference_number
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": widget_order_paying_reference_number})

@with_delay()
def test_query_order_success_cancelled(widget_order_canceled_reference_number):
    # Scenario: QueryOrderSuccessCancelled
    # Purpose: Verify that querying an order with a status of 'cancelled' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for a cancelled order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for a cancelled order.
    # Expected: The API returns the correct order details with status 'cancelled'.
    case_name = "QueryOrderSuccessCancelled"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = widget_order_canceled_reference_number
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": widget_order_canceled_reference_number})

@with_delay()
def test_query_order_not_found():
    # Scenario: QueryOrderNotFound
    # Purpose: Verify that querying a non-existent order returns the correct error response.
    # Steps:
    #   1. Prepare a request with a partner reference number that does not exist.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns a not found error.
    # Expected: The API returns an error indicating the order was not found (404 Not Found or similar).
    case_name = "QueryOrderNotFound"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = "test123"
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
def test_query_order_fail_invalid_field(widget_order_reference_number):
    # Scenario: QueryOrderFailInvalidField
    # Purpose: Ensure the API rejects requests with invalid fields (e.g., invalid timestamp).
    # Steps:
    #   1. Prepare a request with an invalid field.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns a 400 Bad Request error.
    # Expected: The API returns a 400 Bad Request error for invalid fields.

    """Should fail to query an order with an invalid field in the request."""
    # Case name and JSON request preparation
    case_name = "QueryOrderFailInvalidField"
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = widget_order_reference_number
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Prepare headers with an invalid timestamp to trigger the error
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
        {"partnerReferenceNo": widget_order_reference_number}
    )

@with_delay()
def test_query_order_fail_invalid_mandatory_field(widget_order_reference_number):
    # Scenario: QueryOrderFailInvalidMandatoryField
    # Purpose: Ensure the API rejects requests missing mandatory fields (e.g., missing timestamp).
    # Steps:
    #   1. Prepare a request missing a required field.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns a 400 Bad Request error.
    # Expected: The API returns a 400 Bad Request error for missing mandatory fields.
    case_name = "QueryOrderFailInvalidMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = widget_order_reference_number

    # Convert the dictionary to the appropriate request object
    query_order_request_obj = QueryPaymentRequest.from_dict(json_dict)

    # Prepare headers without the timestamp to trigger the error
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/status.htm",
        request_obj=json_dict,
        with_timestamp=False
    )

    try:
        # Attempt to query the order with missing mandatory field
        execute_and_assert_api_error(
            api_client,
            "POST",
            "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm",
            query_order_request_obj,
            headers,
            400,  # Expected status code
            json_path_file,
            title_case,
            case_name,
            {"partnerReferenceNo": widget_order_reference_number}
        )
    except ApiException as e:
        # Assert that the API returns a 400 Bad Request error
        assert e.status == 400, f"Expected status code 400, but got {e.status}"
        assert e.reason == "Bad Request", f"Expected reason 'Bad Request', but got {e.reason}"
        assert e.body is not None, "Expected response body to be not None"
        # Additional assertions can be added here based on the expected error response structure
    except Exception as e:
        # Fail the test if any unexpected exception occurs
        pytest.fail(f"Unexpected exception occurred: {str(e)}")

@with_delay()
def test_query_order_fail_unauthorized(widget_order_reference_number):
    # Scenario: QueryOrderFailUnauthorized
    # Purpose: Ensure the API rejects requests with an invalid signature (authorization failure).
    # Steps:
    #   1. Prepare a request with an invalid signature.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns a 401 Unauthorized error.
    # Expected: The API returns a 401 Unauthorized error for invalid signature.

    """Should fail to query an order with an invalid signature in the request."""
    # Case name and JSON request preparation
    case_name = "QueryOrderFailUnauthorized"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = widget_order_reference_number

    # Convert the dictionary to the appropriate request object
    query_order_request_obj = QueryPaymentRequest.from_dict(json_dict)

    # Prepare headers with invalid signature to trigger authorization error
    # Since we're only using the invalid signature flag, we don't need to pass any of the other parameters
    headers = get_headers_with_signature(invalid_signature=True)
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm",
        query_order_request_obj,
        headers,
        401,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": widget_order_reference_number}
    )

@with_delay()
def test_query_order_fail_transaction_not_found(widget_order_reference_number):
    # Scenario: QueryOrderFailTransactionNotFound
    # Purpose: Ensure the API returns a not found error when querying a non-existent transaction.
    # Steps:
    #   1. Prepare a request with a deliberately invalid partner reference number.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns a 404 Not Found error.
    # Expected: The API returns a 404 Not Found error for non-existent transactions.

    """Should fail to query an order with a transaction not found in the request."""
    # Case name and JSON request preparation
    case_name = "QueryOrderFailTransactionNotFound"
    json_dict = get_request(json_path_file, title_case, case_name)
    # Modify the reference number to ensure it's not found
    json_dict["originalPartnerReferenceNo"] = widget_order_reference_number + "_NOT_FOUND"
    
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
def test_query_order_fail_general_error(widget_order_reference_number):
    # Scenario: QueryOrderFailGeneralError
    # Purpose: Placeholder for testing general/unexpected errors from the API.
    # Steps (to be implemented):
    #   1. Prepare a request that triggers a general error.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns the expected error response.
    # Expected: The API returns an appropriate error for general failures.
    case_name = "QueryOrderFailGeneralError"
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = widget_order_reference_number
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)

        pytest.fail("Expected ServiceException but the API call succeeded")
    except ServiceException as e:

        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": widget_order_reference_number})

    except Exception as e:
        pytest.fail("Expected ServiceException but the API call give another exception")

