import os
import pytest
import time
import asyncio
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
from payment_gateway.payment_pg_util import automate_payment_pg
from concurrent.futures import ThreadPoolExecutor

title_case = "RefundOrder"
create_order_title_case = "CreateOrder"
json_path_file = "resource/request/components/PaymentGateway.json"
user_phone_number = "0811742234"
user_pin = "123321"
merchant_id = os.environ.get("MERCHANT_ID", "216620010016033632482")

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
def create_test_order_init():
    data_order = []
    """Helper function to create a test order"""
    case_name = "CreateOrderApi"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, "CreateOrder", "CreateOrderApi")
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = generate_partner_reference_no()
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.create_order(create_order_request_obj)
    data_order.append(api_response.partner_reference_no)
    data_order.append(api_response.web_redirect_url)
    print(f"Created order with reference number: {api_response.partner_reference_no}")
    print(f"Web redirect URL: {api_response.web_redirect_url}")
    return data_order

@pytest.fixture(scope="module")
def test_order_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    data_order = create_test_order_init()
    print(f"\nCreating shared test order with reference number: {data_order[0]}")
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
def test_refund_order_in_progress(test_order_reference_number):
    """Test refund order in progress"""
    case_name = "RefundOrderInProgress"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    api_response = api_instance.refund_order(refund_order_request_obj)

    # Assert the response
    assert_response(json_path_file, title_case, case_name, RefundOrderResponse.to_json(api_response), {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})

@with_delay()
@retry_test(max_retries=3,delay_seconds=10)
def test_refund_order_valid(test_order_reference_number):
    # Create a test order paid and get the reference number
    test_order_reference_number = create_test_order_paid()
    
    """Test refund order in progress"""
    case_name = "RefundOrderValidScenario"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    api_response = api_instance.refund_order(refund_order_request_obj)

    # Assert the response
    assert_response(json_path_file, title_case, case_name, RefundOrderResponse.to_json(api_response), {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})

@with_delay()
@retry_test(max_retries=3, delay_seconds=10)
@pytest.mark.skip(reason="skipped by request: scenario RefundOrderExceedsTransactionAmountLimit")
def test_refund_order_due_to_exceed():
    # Create a test order paid and get the reference number
    test_order_reference_number = create_test_order_paid()
    
    """Test refund order in progress"""
    case_name = "RefundOrderExceedsTransactionAmountLimit"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    try:
        api_instance.refund_order(refund_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})

@with_delay()
def test_refund_order_not_allowed(test_order_reference_number):
    """Test refund order when refund is not allowed by agreement"""
    case_name = "RefundOrderNotAllowed"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id
    
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
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id
   
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
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_refund_order_not_paid(test_order_reference_number):
    """Test refund order not paid"""
    case_name = "RefundOrderNotPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id
    
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    print(f"Refund order request object: {refund_order_request_obj.to_json()}")
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected ServiceException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected ServiceException but the API call give another exception")

@with_delay()
@retry_test(max_retries=3,delay_seconds=10)
def test_refund_order_duplicate_request():
    """Test refund duplicate request"""
    case_name = "RefundOrderDuplicateRequest"
    
    # Create a test order paid and get the reference number
    test_order_reference_number = create_test_order_paid()

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)

    # Make the API call and assert the response
    try:
        # First hit API
        api_instance.refund_order(refund_order_request_obj)
        refund_order_request_obj.refund_amount = Money(currency="IDR", value="10000.00")
        # Second hit API with same data (duplicate)
        api_instance.refund_order(refund_order_request_obj)
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})


@with_delay()
def test_refund_order_illegal_parameter(test_order_reference_number):
    """Test refund order illegal parameter"""
    case_name = "RefundOrderIllegalParameter"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["partnerReferenceNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

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
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/refund.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_signature=None
    )

    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/refund.htm",
        refund_order_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )

@with_delay()
def test_refund_order_not_exist(test_order_reference_number):
    """Test refund when order not exist"""
    case_name = "RefundOrderInvalidBill"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = "reference_number_not_exist"
    json_dict["partnerRefundNo"] = "reference_number_not_exist"
    json_dict["merchantId"] = merchant_id
    
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    try:
        api_instance.refund_order(refund_order_request_obj)
        pytest.fail("Expected NotFoundException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected NotFoundException but the API call give another exception")


@with_delay()
def test_refund_order_insufficient_funds(test_order_reference_number):
    """Test refund order insufficient funds"""
    case_name = "RefundOrderInsufficientFunds"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

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
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/refund.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_signature=True
    )
    
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/refund.htm",
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
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

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
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    json_dict["partnerRefundNo"] = test_order_reference_number
    json_dict["merchantId"] = merchant_id

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
        
@with_delay()
@retry_test(max_retries=3,delay_seconds=2)
def test_refund_order_idempotent():
    """Test refund order idempotent"""
    case_name = "RefundOrderIdempotent"
    
    # Create a test order paid and get the reference number
    order_reference_number = create_test_order_paid()

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = order_reference_number
    json_dict["partnerRefundNo"] = order_reference_number
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    refund_order_request_obj = RefundOrderRequest.from_dict(json_dict)
    
    # First hit API
    api_instance.refund_order(refund_order_request_obj)
    # Second hit API with same data
    api_response = api_instance.refund_order(refund_order_request_obj)
    # Assert the response
    assert_response(json_path_file, title_case, case_name, RefundOrderResponse.to_json(api_response), {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})