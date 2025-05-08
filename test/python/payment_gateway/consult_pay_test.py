import json
import os
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1.models import ConsultPayRequest
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.rest import ApiException
from dana.exceptions import *

from helper.util import get_request  # Import the Invoke class
from helper.assertion import *

title_case = "ConsultPay"
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


def test_consult_pay_success():
    """Should give success response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedSuccess"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.consult_pay(consult_pay_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, ConsultPayResponse.to_json(api_response))
    
    
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
        assert_fail_response(json_path_file, title_case, case_name, e)
        
        
def test_consult_pay_invalid_mandatory_field():
    """Should give fail response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedInvalidMandatoryField"
        
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_response = api_instance.consult_pay(consult_pay_request_obj)
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e)

def test_consult_pay_unauthorized():
    """Should give fail response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedUnauthorized"
        
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_response = api_instance.consult_pay(consult_pay_request_obj)
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e)