import os
import pytest
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

# Widget-specific constants
title_case = "RefundOrder"
create_order_title_case = "CreateOrder"
json_path_file = "resource/request/components/Widget.json"

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

def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_order_reference_number():
    # In real tests, create an order and return its reference number
    return generate_partner_reference_no()

@with_delay()
def test_refund_valid_scenario(test_order_reference_number):
    case_name = "RefundOrderValidScenario"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    # Simulate API call and response assertion
    # api_response = api_client.refund_order(json_dict)
    # assert_response(JSON_PATH_FILE, TITLE_CASE, case_name, api_response, {"partnerReferenceNo": test_order_reference_number})
    pytest.skip("SKIP: Waiting for create order test to be implemented")

@with_delay()
def test_refund_in_process(test_order_reference_number):
    # # Scenario: RefundInProcess
    # # Purpose: Verify that a refund can be initiated and is in process.
    # # Steps:
    # #     1. Prepare a valid refund request payload.
    # #     2. Call the refund API endpoint.
    # #     3. Assert the response indicates the refund is in process.
    # # Expected: The API returns a response indicating the refund is in process.
    # """Should initiate a refund that is currently in process."""
    # #Case name and JSON request preparation
    # case_name = "RefundInProcess"
    # json_dict = get_request(json_path_file, title_case, case_name)
    
    # # Use the provided order reference number from the fixture
    # json_dict["partnerReferenceNo"] = test_order_reference_number
    
    # # Create the request object from the JSON dictionary
    # refund_order_request = RefundOrderRequest.from_dict(json_dict)

    # # Call the refund API endpoint with the request object
    # api_response = api_instance.refund_order(refund_order_request)
    # print("API Response here: ",api_response)

    # # Assert the response matches the expected output for an in-process refund
    # assert_response(json_path_file, title_case, case_name, RefundOrderResponse.to_json(api_response), {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    pytest.skip("SKIP: Response return is none")

@with_delay()
def test_refund_fail_duplicate_request(test_order_reference_number):
    case_name = "RefundFailDuplicateRequest"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_order_not_paid(test_order_reference_number):
    case_name = "RefundFailOrderNotPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_order_not_exist(test_order_reference_number):
    case_name = "RefundFailOrderNotExist"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_timeout(test_order_reference_number):
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
    json_dict["partnerReferenceNo"] = test_order_reference_number

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
def test_refund_fail_idempotent(test_order_reference_number):
    case_name = "RefundFailIdempotent"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Need to confirm the flow")

@with_delay()
def test_refund_fail_merchant_status_abnormal(test_order_reference_number):
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
    json_dict["partnerReferenceNo"] = test_order_reference_number

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