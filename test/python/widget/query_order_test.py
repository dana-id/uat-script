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
from helper.util import get_request, retry_on_inconsistent_request, with_delay
from helper.assertion import assert_response
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error, assert_fail_response

# Widget-specific constants
title_case = "QueryOrder"
json_path_file = "resource/request/components/Widget.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        ENV=Env.SANDBOX
    )
)

with ApiClient(configuration) as api_client:
    api_instance = WidgetApi(api_client)

def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_order_reference_number():
    return generate_partner_reference_no()

@with_delay()
def test_query_order_success_paid(test_order_reference_number):
    # Scenario: QueryOrderSuccessPaid
    # Purpose: Verify that querying an order with a status of 'paid' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for a paid order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for a paid order.
    # Expected: The API returns the correct order details with status 'paid'.
    case_name = "QueryOrderSuccessPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    # api_response = api_instance.query_order(json_dict)
    # assert_response(json_path_file, title_case, case_name, api_response, {"partnerReferenceNo": test_order_reference_number})
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_success_initiated(test_order_reference_number):
    # Scenario: QueryOrderSuccessInitiated
    # Purpose: Verify that querying an order with a status of 'initiated' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for an initiated order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for an initiated order.
    # Expected: The API returns the correct order details with status 'initiated'.
    case_name = "QueryOrderSuccessInitiated"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_success_paying(test_order_reference_number):
    # Scenario: QueryOrderSuccessPaying
    # Purpose: Verify that querying an order with a status of 'paying' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for a paying order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for a paying order.
    # Expected: The API returns the correct order details with status 'paying'.
    case_name = "QueryOrderSuccessPaying"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_success_cancelled(test_order_reference_number):
    # Scenario: QueryOrderSuccessCancelled
    # Purpose: Verify that querying an order with a status of 'cancelled' returns the correct response.
    # Steps:
    #   1. Prepare a request with a valid partner reference number for a cancelled order.
    #   2. Call the query_order API endpoint.
    #   3. Assert the response matches the expected output for a cancelled order.
    # Expected: The API returns the correct order details with status 'cancelled'.
    case_name = "QueryOrderSuccessCancelled"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_not_found(test_order_reference_number):
    # Scenario: QueryOrderNotFound
    # Purpose: Verify that querying a non-existent order returns the correct error response.
    # Steps:
    #   1. Prepare a request with a partner reference number that does not exist.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns a not found error.
    # Expected: The API returns an error indicating the order was not found (404 Not Found or similar).
    case_name = "QueryOrderNotFound"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Need to confirm the expected behavior for not found orders")

@with_delay()
@pytest.mark.skip(reason="Skipped for now")
def test_query_order_fail_invalid_field(test_order_reference_number):
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
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

    # Convert the dictionary to the appropriate request object
    query_order_request_obj = QueryPaymentRequest.from_dict(json_dict)

    # Prepare headers with an invalid timestamp to trigger the error
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/status.htm",
        request_obj=json_dict,
        invalid_timestamp=True
    )

    try:
        # Attempt to query the order with invalid field
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
            {"partnerReferenceNo": test_order_reference_number}
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
@pytest.mark.skip(reason="Skipped for now")
def test_query_order_fail_invalid_mandatory_field(test_order_reference_number):
    # Scenario: QueryOrderFailInvalidMandatoryField
    # Purpose: Ensure the API rejects requests missing mandatory fields (e.g., missing timestamp).
    # Steps:
    #   1. Prepare a request missing a required field.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns a 400 Bad Request error.
    # Expected: The API returns a 400 Bad Request error for missing mandatory fields.
    case_name = "QueryOrderFailInvalidMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

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
            {"partnerReferenceNo": test_order_reference_number}
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
@pytest.mark.skip(reason="Skipped for now")
def test_query_order_fail_unauthorized(test_order_reference_number):
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
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

    # Convert the dictionary to the appropriate request object
    query_order_request_obj = QueryPaymentRequest.from_dict(json_dict)

    # Prepare headers with invalid signature to trigger authorization error
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/status.htm",
        request_obj=json_dict,
        invalid_signature=True
    )

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
        {"partnerReferenceNo": test_order_reference_number}
    )

@with_delay()
@pytest.mark.skip(reason="Skipped for now")
def test_query_order_fail_transaction_not_found(test_order_reference_number):
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
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number + "_NOT_FOUND"

    # Convert the dictionary to the appropriate request object
    query_order_request_obj = QueryPaymentRequest.from_dict(json_dict)

    try:
        # Attempt to query the order
        api_response = api_instance.query_payment(query_order_request_obj)
        # If no exception, assert the failure response structure
        
    except ApiException as e:
       # Assert that the API returns a 404 Not Found error
       assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body
        )
    except Exception as e:
        # Fail the test if any unexpected exception occurs
        pytest.fail(f"Unexpected exception occurred: {str(e)}")

@with_delay()
def test_query_order_fail_general_error(test_order_reference_number):
    # Scenario: QueryOrderFailGeneralError
    # Purpose: Placeholder for testing general/unexpected errors from the API.
    # Steps (to be implemented):
    #   1. Prepare a request that triggers a general error.
    #   2. Call the query_order API endpoint.
    #   3. Assert the API returns the expected error response.
    # Expected: The API returns an appropriate error for general failures.
    case_name = "QueryOrderFailGeneralError"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")
