import os
import pytest
import time
import asyncio
from uuid import uuid4
from datetime import datetime, timedelta, timezone
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1.models import CancelOrderRequest, CreateOrderByApiRequest
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.exceptions import *
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error
from helper.util import get_request, with_delay, retry_on_inconsistent_request
from helper.assertion import *
from payment_gateway.payment_pg_util import automate_payment_pg

title_case = "CancelOrder"
create_order_title_case = "CreateOrder"
json_path_file = "resource/request/components/PaymentGateway.json"
user_phone_number = "0811742234"
user_pin = "123321"
merchant_id = os.environ.get("MERCHANT_ID", "1234567890")  # Default to a test merchant ID if not set

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

@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def create_test_order_init():
    data_order = []
    """Helper function to create a test order"""
    case_name = "CreateOrderApi"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = generate_partner_reference_no()
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

@pytest.fixture(scope="module")
def test_order_reference_number():
    """Fixture that creates a test order once per module and shares the reference number"""
    data_order = create_test_order_init()
    print(f"\nCreating shared test order with reference number: {data_order[0]}")
    return data_order[0]

@pytest.fixture(scope="module")
def test_order_reference_number_refund():
    """Fixture that creates a test order once per module and shares the reference number"""
    partner_reference_no = create_test_order_refunded()
    print(f"\nCreating shared test order with reference number: {partner_reference_no}")
    return partner_reference_no

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

def create_test_order_refunded():
    """Helper function to create a test order with refunded status"""
    partner_reference_no = create_test_order_paid()
    # Refund the order
    case_name = "RefundOrderValidScenario"
    json_dict = get_request(json_path_file, "RefundOrder", case_name)
    # Set the partner reference number
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    json_dict["partnerRefundNo"] = partner_reference_no
    
    # Create the request object from the JSON dictionary
    refund_order_request = RefundOrderRequest.from_dict(json_dict)

    # Call the refund API endpoint with the request object
    api_instance.refund_order(refund_order_request)
    return partner_reference_no

@with_delay()
@retry_on_inconsistent_request(max_retries=3, delay_seconds=1)
def test_cancel_order(test_order_reference_number):
    """Should cancel the order"""
    # Cancel order
    case_name = "CancelOrderValidScenario"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.cancel_order(cancel_order_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, CancelOrderResponse.to_json(api_response), {"partnerReferenceNo": test_order_reference_number})

@with_delay()
def test_cancel_order_in_progress():
    """Should cancel the order"""
    # Cancel order
    case_name = "CancelOrderInProgress"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.cancel_order(cancel_order_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, CancelOrderResponse.to_json(api_response), {"partnerReferenceNo": "2025700"})    

@with_delay()
def test_cancel_order_with_user_status_abnormal():
    """Should fail to cancel the order when user status is abnormal"""
    # Cancel order
    case_name = "CancelOrderUserStatusAbnormal"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "4035705"})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_cancel_order_with_merchant_status_abnormal():
    """Should fail to cancel the order when merchant status is abnormal"""
    # Cancel order
    case_name = "CancelOrderMerchantStatusAbnormal"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected NotFoundException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "4045708"})
    except:
        pytest.fail("Expected NotFoundException but the API call give another exception") 


@with_delay()
def test_cancel_order_invalid_mandatory_field(test_order_reference_number):
    """Should fail to cancel the order when mandatory field is invalid (ex: request without X-TIMESTAMP header)"""
    # Cancel order
    case_name = "CancelOrderInvalidMandatoryField"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
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
        cancel_order_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )

@with_delay()
def test_cancel_order_transaction_not_found():
    """Should fail to cancel the order when transaction is not found"""
    # Cancel order
    case_name = "CancelOrderTransactionNotFound"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalPartnerReferenceNo"] = "123wererw"
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected NotFoundException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})
    except:
        pytest.fail("Expected NotFoundException but the API call give another exception")

@with_delay()
def test_cancel_order_with_expired_transaction():
    """Should fail to cancel the order when transaction is expired"""
    # Cancel order
    case_name = "CancelOrderTransactionExpired"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "4035700"})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception") 

@with_delay()
def test_cancel_order_with_agreement_not_allowed():
    """Should fail to cancel the order when agreement is not allowed"""
    # Cancel order
    case_name = "CancelOrderNotAllowed"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "4035715"})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")

@with_delay()
def test_cancel_order_with_invalid_transaction_status(test_order_reference_number_refund):
    """Should fail to cancel the order when transaction status is invalid"""
    # Cancel order
    case_name = "CancelOrderInvalidTransactionStatus"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number_refund
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "4045700"})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception")



@with_delay()
def test_cancel_order_with_account_status_abnormal():
    """Should fail to cancel the order when account status is abnormal"""
    # Cancel order
    case_name = "CancelOrderAccountStatusAbnormal"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "4035705"})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception") 

@with_delay()
def test_cancel_order_with_insufficient_funds():
    """Should fail to cancel the order when funds are insufficient"""
    # Cancel order
    case_name = "CancelOrderInsufficientFunds"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["originalReferenceNo"] = "ajfbhifbifibwifuun92"
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected ForbiddenException but the API call succeeded")
    except ForbiddenException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "4035714"})
    except:
        pytest.fail("Expected ForbiddenException but the API call give another exception") 

@with_delay()
def test_cancel_order_unauthorized(test_order_reference_number):
    """Should fail to cancel the order when authorization is invalid"""
    # Cancel order
    case_name = "CancelOrderUnauthorized"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = test_order_reference_number

    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    headers = get_headers_with_signature(invalid_signature=True)

    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm",
        cancel_order_request_obj,
        headers,
        401,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )       

@with_delay()
def test_cancel_order_timeout():
    """Should fail to cancel the order resulting timeout"""
    # Cancel order
    case_name = "CancelOrderRequestTimeout"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a CancelOrderRequest object
    cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.cancel_order(cancel_order_request_obj)

        pytest.fail("Expected ServiceException but the API call succeeded")
    except ServiceException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": "5005701"})
    except:
        pytest.fail("Expected ServiceException but the API call give another exception")       
