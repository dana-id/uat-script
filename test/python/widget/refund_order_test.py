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
        ENV=Env.SANDBOX
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
def test_refund_fail_exceed_payment_amount(test_order_reference_number):
    case_name = "RefundFailExceedPaymentAmount"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    # Simulate API call and error assertion
    # try:
    #     api_client.refund_order(json_dict)
    #     pytest.fail("Expected error but API call succeeded")
    # except Exception as e:
    #     assert_fail_response(JSON_PATH_FILE, TITLE_CASE, case_name, str(e), {"partnerReferenceNo": test_order_reference_number})
    pytest.skip("SKIP: Waiting for create order test to be implemented")

@with_delay()
def test_refund_fail_not_allowed_by_agreement(test_order_reference_number):
    # Scenario: RefundFailNotAllowedByAgreement
    # Purpose: Verify that a refund is not allowed due to agreement restrictions.
    # Steps:
    #     1. Prepare a valid refund request payload that violates agreement terms.
    #     2. Call the refund API endpoint.
    #     3. Assert the response indicates the refund is not allowed by agreement.
    # Expected: The API returns an error indicating the refund is not allowed by agreement.
    """Should fail to process a refund that is not allowed by agreement."""
    # Case name and JSON request preparation
    case_name = "RefundFailNotAllowedByAgreement"
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
    except ForbiddenException as e:
        # Assert the error response matches the expected output for a refund not allowed by agreement
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except Exception as e:
        # Handle any other unexpected exceptions
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_refund_fail_exceed_refund_window_time(test_order_reference_number):
    # Scenario: RefundFailExceedRefundWindowTime
    # Purpose: Verify that a refund cannot be processed after the refund window has closed.
    # Steps:
    #     1. Prepare a valid refund request payload with a timestamp outside the refund window.
    #     2. Call the refund API endpoint.
    #     3. Assert the response indicates the refund is not allowed due to exceeding the refund window.
    # Expected: The API returns an error indicating the refund is not allowed due to exceeding the refund window.
    """Should fail to process a refund that exceeds the refund window time."""
    # Case name and JSON request preparation
    case_name = "RefundFailExceedRefundWindowTime"
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
    except ForbiddenException as e: 
        # Assert the error response matches the expected output for a refund exceeding the refund window time
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except Exception as e:
        # Handle any other unexpected exceptions
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_refund_fail_multiple_refund_not_allowed(test_order_reference_number):
    # Scenario: RefundFailMultipleRefundNotAllowed
    # Purpose: Verify that multiple refunds are not allowed for the same order.
    # Steps:
    #     1. Prepare a valid refund request payload for an order that has already been refunded.
    #     2. Call the refund API endpoint.
    #     3. Assert the response indicates multiple refunds are not allowed.
    # Expected: The API returns an error indicating multiple refunds are not allowed for the same order.
    """Should fail to process a refund when multiple refunds are not allowed."""
    # Case name and JSON request preparation
    case_name = "RefundFailMultipleRefundNotAllowed"
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
    except ForbiddenException as e:
        # Assert the error response matches the expected output for a refund when multiple refunds are not allowed
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except Exception as e:
        # Handle any other unexpected exceptions
        pytest.fail("Expected ForbiddenException but the API call give another exception")


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
def test_refund_fail_parameter_illegal(test_order_reference_number):
    # Scenario: RefundFailParameterIllegal
    # Purpose: Verify that a refund request with illegal parameters fails.
    # Steps:
    #     1. Prepare a refund request payload with illegal parameters (e.g., wrong data types, invalid values).
    #     2. Call the refund API endpoint.
    #     3. Assert the response indicates the parameters are illegal.
    # Expected: The API returns an error indicating the parameters are illegal.
    """Should fail to process a refund with illegal parameters."""
    # Case name and JSON request preparation
    case_name = "RefundFailParameterIllegal"
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
    except BadRequestException as e:
        # Assert the error response matches the expected output for a refund with illegal parameters
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except Exception as e:
        # Handle any other unexpected exceptions
        pytest.fail("Expected BadRequestException but the API call give another exception")

# @with_delay()
# def test_refund_fail_mandatory_parameter_invalid(test_order_reference_number):
#     # Scenario: RefundFailMandatoryParameterInvalid
#     # Purpose: Verify that a refund request with a missing mandatory parameter fails.
#     # Steps:
#     #     1. Prepare a refund request payload with a missing mandatory parameter.
#     #     2. Call the refund API endpoint.
#     #     3. Assert the response indicates the mandatory parameter is missing.
#     # Expected: The API returns an error indicating the mandatory parameter is missing.
#     """Should fail to process a refund with a missing mandatory parameter."""
#     # Case name and JSON request preparation
#     case_name = "RefundFailMandatoryParameterInvalid"
#     json_dict = get_request(json_path_file, title_case, case_name)
    
#     # Use the provided order reference number from the fixture
#     json_dict["partnerReferenceNo"] = test_order_reference_number

#     # Create the request object from the JSON dictionary
#     refund_order_request = RefundOrderRequest.from_dict(json_dict)
    
#     # Prepare headers with a valid signature
#     headers = get_headers_with_signature(
#         method="POST",
#         resource_path="/payment-gateway/v1.0/debit/status.htm",
#         request_obj=json_dict,
#         with_timestamp=False
#     )

#     # Execute the API call and assert the expected error response
#     execute_and_assert_api_error(
#         api_client,
#         "POST",
#         "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm",
#         refund_order_request,
#         headers,
#         400,  # Expected status code for bad request
#         json_path_file,
#         title_case,
#         case_name,
#         {"partnerReferenceNo": test_order_reference_number}
#     )

@with_delay()
def test_refund_fail_order_not_exist(test_order_reference_number):
    case_name = "RefundFailOrderNotExist"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_insufficient_merchant_balance(test_order_reference_number):
    # Scenario: RefundFailInsufficientMerchantBalance
    # Purpose: Verify that a refund request fails when the merchant has insufficient balance.
    # Steps:
    #     1. Prepare a refund request payload with a partnerReferenceNo that is known to cause insufficient balance.
    #     2. Call the refund API endpoint.
    #     3. Assert the response indicates insufficient balance.
    # Expected: The API returns an error indicating insufficient merchant balance.
    """Should fail to process a refund due to insufficient merchant balance."""
    # Case name and JSON request preparation
    case_name = "RefundFailInsufficientMerchantBalance"
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
    except ForbiddenException as e:
        # Assert the error response matches the expected output for a refund with insufficient balance
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except Exception as e:
        # Handle any other unexpected exceptions
        pytest.fail("Expected ForbiddenException but the API call give another exception")

# @with_delay()
# def test_refund_fail_invalid_signature(test_order_reference_number):
#     # Scenario: RefundFailInvalidSignature
#     # Purpose: Verify that a refund request fails when the signature is invalid.
#     # Steps:
#     #     1. Prepare a refund request payload with a partnerReferenceNo that is known to cause an invalid signature.
#     #     2. Call the refund API endpoint.
#     #     3. Assert the response indicates an invalid signature error.
#     # Expected: The API returns an error indicating an invalid signature.
#     """Should fail to process a refund due to invalid signature."""
#     # Case name and JSON request preparation
#     case_name = "RefundFailInvalidSignature"
#     json_dict = get_request(json_path_file, title_case, case_name)

#     # Use the provided order reference number from the fixture
#     json_dict["partnerReferenceNo"] = test_order_reference_number

#     # Create the request object from the JSON dictionary
#     refund_order_request = RefundOrderRequest.from_dict(json_dict)

#     # Prepare headers with an invalid signature
#     headers = get_headers_with_signature(invalid_signature=True    )

#     # Execute the API call and assert the expected error response
#     execute_and_assert_api_error(
#         api_client,
#         "POST",
#         "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm",
#         refund_order_request,
#         headers,
#         401,  # Expected status code
#         json_path_file,
#         title_case,
#         case_name,
#         {"partnerReferenceNo": test_order_reference_number}
#     )


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