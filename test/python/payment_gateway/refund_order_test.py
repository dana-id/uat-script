import os
import pytest
import time
from uuid import uuid4
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1.models import RefundOrderRequest
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.exceptions import *
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error
from helper.util import get_request, with_delay, retry_on_inconsistent_request
from helper.assertion import *

title_case = "RefundOrder"
create_order_title_case = "CreateOrder"
json_path_file = "resource/request/components/PaymentGateway.json"

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
    api_instance = PaymentGatewayApi(api_client)

# Function to generate a unique partner reference number
def generate_partner_reference_no():
    return str(uuid4())

# Function to create an order request
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

@pytest.fixture(scope="module")
def test_order_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = generate_partner_reference_no()
    print(f"\nCreating shared test order with reference number: {partner_reference_no}")
    create_test_order(partner_reference_no)
    return partner_reference_no 

@with_delay()
def test_refund_order_in_progress(test_order_reference_number):
    """Test refund order in progress"""
    case_name = "RefundOrderInProgress"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    api_response = api_instance.refund_order(refund_order_request_obj)

    # Assert the response
    assert_response(json_path_file, title_case, case_name, RefundOrderResponse.to_json(api_response), {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})

@with_delay()
def test_refund_order_not_allowed(test_order_reference_number):
    """Test refund order when refund is not allowed by agreement"""
    case_name = "RefundOrderNotAllowed"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number
    
    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    
    # Make the API call and assert the response
    try:
        api_instance.refund_order(refund_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_refund_order_due_to_exceed_refund_window_time(test_order_reference_number):
    """Test refund order due to exceed refund window time"""
    case_name = "RefundOrderDueToExceedRefundWindowTime"
   
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
   
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number
   
    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
   
    # Make the API call and assert the response
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_refund_order_multiple_refund(test_order_reference_number):
    """Test refund order multiple refund"""
    case_name = "RefundOrderMultipleRefund"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")

# @with_delay()
# def test_refund_order_not_paid(test_order_reference_number):
#     """Test refund order not paid"""
#     case_name = "RefundOrderNotPaid"
#     json_dict = get_request(json_path_file, title_case, case_name)
#     json_dict["partnerReferenceNo"] = test_order_reference_number
#     refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
#     try:
#         api_instance.refund_order(refund_order_request_obj)
#         pytest.fail("Expected ServiceException but the API call succeeded")
#     except ServiceException as e:
#         assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
#     except:
#         pytest.fail("Expected ServiceException but the API call give another exception")

@with_delay()
def test_refund_order_illegal_parameter(test_order_reference_number):
    """Test refund order illegal parameter"""
    case_name = "RefundOrderIllegalParameter"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected BadRequestException but the API call succeeded")
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected BadRequestException but the API call give another exception")

@with_delay()
def test_refund_order_invalid_mandatory_field(test_order_reference_number):
    """Test refund order when mandatory field is invalid"""
    # Refund order
    case_name = "RefundOrderInvalidMandatoryParameter"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/status.htm",
        request_obj=json_dict,
        with_timestamp=False
    )

    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm",
        refund_order_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )

# @with_delay()
# def test_refund_order_not_exist(test_order_reference_number):
#     """Test refund when order not exist"""
#     case_name = "RefundOrderInvalidBill"
#     json_dict = get_request(json_path_file, title_case, case_name)
#     json_dict["partnerReferenceNo"] = test_order_reference_number
#     refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
#     try:
#         api_instance.refund_order(refund_order_request_obj)
#         pytest.fail("Expected NotFoundException but the API call succeeded")
#     except NotFoundException as e:
#         assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
#     except:
#         pytest.fail("Expected NotFoundException but the API call give another exception")


@with_delay()
def test_refund_order_insufficient_funds(test_order_reference_number):
    """Test refund order insufficient funds"""
    case_name = "RefundOrderInsufficientFunds"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_refund_order_unauthorized(test_order_reference_number):
    """Test refund order when authorization is invalid"""
    # Refund order
    case_name = "RefundOrderUnauthorized"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    
    headers = get_headers_with_signature(invalid_signature=True)

    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm",
        refund_order_request_obj,
        headers,
        401,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )

@with_delay()
def test_refund_order_merchant_status_abnormal(test_order_reference_number):
    """Test refund order merchant status abnormal"""
    case_name = "RefundOrderMerchantStatusAbnormal"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected NotFoundException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected NotFoundException but the API call give another exception")

@with_delay()
def test_refund_order_timeout(test_order_reference_number):
    """Test refund order timeout"""
    case_name = "RefundOrderTimeout"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected ServiceException but the API call succeeded")
    except ServiceException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected ServiceException but the API call give another exception")
