import os
import pytest
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1.models import ConsultPayRequest
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.exceptions import *

from helper.util import get_request, with_delay
from helper.assertion import *
from helper.api_helpers import execute_and_assert_api_error, get_headers_with_signature

title_case = "ConsultPay"
json_path_file = "resource/request/components/PaymentGateway.json"

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


@with_delay()
def test_consult_pay_success():
    """Should give success response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedSuccess"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    consult_pay_request_obj.merchant_id = os.environ.get("MERCHANT_ID")
    
    # Make the API call
    api_response = api_instance.consult_pay(consult_pay_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, CancelOrderResponse.to_json(api_response), None)


@with_delay()
def test_consult_pay_invalid_field_format():
    """Should give fail response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedInvalidFieldFormat"
        
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_response = api_instance.consult_pay(consult_pay_request_obj)
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body)
        
        
@with_delay()
def test_consult_pay_invalid_mandatory_field():
    """Should give fail response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedInvalidMandatoryField"
        
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    
    # Prepare headers without timestamp to trigger mandatory field error
    # This now handles the signature generation internally
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/v1.0/payment-gateway/consult-pay.htm",
        request_obj=json_dict,
        with_timestamp=False
    )
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/v1.0/payment-gateway/consult-pay.htm",
        consult_pay_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name
    )
