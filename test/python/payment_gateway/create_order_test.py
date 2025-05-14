import json
import os
from uuid import uuid4
import pytest
from datetime import datetime, timedelta
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.rest import ApiException
from dana.exceptions import *

from helper.util import get_request, with_delay
from helper.assertion import *

title_case = "CreateOrder"
json_path_file = "resource/request/components/PaymentGateway.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        CHANNEL_ID=os.environ.get("CHANNEL_ID"),
        ENV="sandbox"
    )
)

with ApiClient(configuration) as api_client:
    api_instance = PaymentGatewayApi(api_client)


def generate_partner_reference_no():
    """Generate a unique partner reference number based on current time"""
    return str(uuid4())

@with_delay()
def test_create_order_balance():
    """Should create an order using balance payment method"""
    case_name = "CreateOrderBalance"
    
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
    """Should create an order using VA bank payment method"""
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
    """Should fail when field format is invalid"""
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
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e, {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_create_order_inconsistent_request():
    """Should fail when request is inconsistent, for example duplicated partner_reference_no"""
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
        time.sleep(2)
        api_instance.create_order(create_order_request_obj)
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e, {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_create_order_invalid_mandatory_field():
    """Should fail when mandatory field is missing"""
    case_name = "CreateOrderInvalidMandatoryField"
    
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
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e, {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_create_order_unauthorized():
    """Should fail when authorization fails"""
    case_name = "CreateOrderUnauthorized"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)

    # Make the API call and expect an exception
    try:
        api_instance.create_order(create_order_request_obj, _headers={"X-SIGNATURE": ""})
    except UnauthorizedException as e:
        assert_fail_response(json_path_file, title_case, case_name, e, {"partnerReferenceNo": partner_reference_no})

