import os
from uuid import uuid4
import pytest

from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.exceptions import *

from helper.util import get_request, with_delay
from helper.api_helpers import execute_and_assert_api_error, get_headers_with_signature
from helper.assertion import *

title_case = "CreateOrder"
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

@with_delay()
def test_create_order_redirect_scenario():
    """Should create an order using redirect scenario and pay with DANA"""
    case_name = "CreateOrderRedirect"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByRedirectRequest.from_dict(json_dict)
    
    # Make the API call

    try:
        api_response = api_instance.create_order(create_order_request_obj)
    
        # Assert the API response
        assert_response(json_path_file, title_case, case_name, CreateOrderResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})
    except ServiceException as e:
        try:
            api_response = api_instance.create_order(create_order_request_obj)
        
            # Assert the API response
            assert_response(json_path_file, title_case, case_name, CreateOrderResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})
        except Exception as e:
            pytest.fail(f"Fail to call create order API {e}")
    except Exception as e:
        pytest.fail(f"Fail to call create order API {e}")

@with_delay()
def test_create_order_api_scenario():
    """Should create an order using API scenario with BALANCE payment method"""
    case_name = "CreateOrderApi"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.create_order(create_order_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, CreateOrderResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_create_order_network_pay_pg_qris():
    """Should create an order using API scenario with QRIS payment method"""
    case_name = "CreateOrderNetworkPayPgQris"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.create_order(create_order_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, CreateOrderResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_create_order_network_pay_pg_other_wallet():
    """Should create an order using API scenario with wallet payment method"""
    case_name = "CreateOrderNetworkPayPgOtherWallet"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.create_order(create_order_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, CreateOrderResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})


@with_delay()
def test_create_order_network_pay_pg_other_va_bank():
    """Should create an order with API scenario using VA bank payment method"""
    case_name = "CreateOrderNetworkPayPgOtherVaBank"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.create_order(create_order_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, CreateOrderResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_create_order_invalid_field_format():
    """Should fail when field format is invalid (ex: amount without decimal)"""
    case_name = "CreateOrderInvalidFieldFormat"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.create_order(create_order_request_obj)
        pytest.fail("Expected BadRequestException but the API call succeeded")

    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": partner_reference_no})
    except:
        pytest.fail("Expected BadRequestException but the API call give another exception")

@with_delay()
def test_create_order_inconsistent_request():
    """Should fail when request is inconsistent, for example duplicated partner_reference_no with different amount"""
    case_name = "CreateOrderInconsistentRequest"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.create_order(create_order_request_obj)
    except:
        pytest.fail("Fail to call first API")

    time.sleep(1)
    
    try:
        # Preparing request with the same partner reference number but different amount
        json_dict["amount"]["value"] = "100000.00"
        json_dict["payOptionDetails"][0]["transAmount"]["value"] = "100000.00"

        create_order_request_obj_second = CreateOrderByApiRequest.from_dict(json_dict)

        api_instance.create_order(create_order_request_obj_second)

        pytest.fail("Expected NotFoundException but the API call succeeded")

    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": partner_reference_no})
    except:
        pytest.fail("Expected NotFoundException but the API call give another exception")

@with_delay()
def test_create_order_invalid_mandatory_field():
    """Should fail when mandatory field is missing (ex: X-TIMESTAMP in header)"""
    case_name = "CreateOrderInvalidMandatoryField"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no

    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Prepare headers without timestamp to trigger mandatory field error
    # This now handles the signature generation internally
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/payment-gateway/v1.0/debit/payment-host-to-host.htm",
        request_obj=json_dict,
        with_timestamp=False
    )
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/payment-host-to-host.htm",
        create_order_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": partner_reference_no}
    )

@with_delay()
def test_create_order_unauthorized():
    """Should fail when authorization fails (wrong X-SIGNATURE)"""
    case_name = "CreateOrderUnauthorized"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)

    # Prepare headers with invalid signature to trigger authorization error
    # Since we're only using the invalid signature flag, we don't need to pass any of the other parameters
    headers = get_headers_with_signature(invalid_signature=True)
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/payment-gateway/v1.0/debit/payment-host-to-host.htm",
        create_order_request_obj,
        headers,
        401,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": partner_reference_no}
    )
