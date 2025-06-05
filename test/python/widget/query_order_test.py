import os
import pytest
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.ipg.v1.enum import *
from dana.ipg.v1.models import *
from dana.ipg.v1 import *
from dana.ipg.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.util import get_request, retry_on_inconsistent_request, with_delay
from helper.assertion import assert_response, assert_fail_response

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
    api_instance = IPGApi(api_client)

def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_order_reference_number():
    return generate_partner_reference_no()

@with_delay()
def test_query_order_success_paid(test_order_reference_number):
    case_name = "QueryOrderSuccessPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    # api_response = api_instance.query_order(json_dict)
    # assert_response(json_path_file, title_case, case_name, api_response, {"partnerReferenceNo": test_order_reference_number})
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_success_initiated(test_order_reference_number):
    case_name = "QueryOrderSuccessInitiated"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_success_paying(test_order_reference_number):
    case_name = "QueryOrderSuccessPaying"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_success_cancelled(test_order_reference_number):
    case_name = "QueryOrderSuccessCancelled"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_not_found(test_order_reference_number):
    """
    Should fail when transaction is not found.

    This test attempts to query an order using a non-existent partner reference number.
    It expects the API to return a 404 Not Found error.
    """
    case_name = "QueryOrderNotFound"

    # Prepare request data with a deliberately invalid reference number
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number + "_NOT_FOUND"

    # Convert the dictionary to the appropriate request object
    query_order_request_obj = QueryPaymentRequest.from_dict(json_dict)

    try:
        # Attempt to query the order
        api_response = api_instance.query_payment(query_order_request_obj)
        # If no exception, assert the failure response structure
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            api_response,
            {"partnerReferenceNo": test_order_reference_number + "_NOT_FOUND"}
        )
    except ApiException as e:
        # Assert that the API returns a 404 Not Found error
        assert e.status == 404, f"Expected status code 404, but got {e.status}"
        assert e.reason == "Not Found", f"Expected reason 'Not Found', but got {e.reason}"
        assert e.body is not None, "Expected response body to be not None"
        # Additional assertions can be added here based on the expected error response structure
    except Exception as e:
        # Fail the test if any unexpected exception occurs
        pytest.fail(f"Unexpected exception occurred: {str(e)}")

@with_delay()
def test_query_order_fail_invalid_field(test_order_reference_number):
    case_name = "QueryOrderFailInvalidField"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_fail_invalid_mandatory_field(test_order_reference_number):
    case_name = "QueryOrderFailInvalidMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_fail_not_allowed(test_order_reference_number):
    case_name = "QueryOrderFailNotAllowed"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_fail_transaction_not_found(test_order_reference_number):
    case_name = "QueryOrderFailTransactionNotFound"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number + "_NOT_FOUND"
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_query_order_fail_general_error(test_order_reference_number):
    case_name = "QueryOrderFailGeneralError"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")
