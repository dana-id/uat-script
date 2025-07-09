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

title_case = "ConsultPay"
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


@with_delay()
def test_consult_pay_success():
    """Should give success response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedSuccess"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.consult_pay(consult_pay_request_obj)
    
    # Validate the API response
    api_response_json = json.loads(ConsultPayResponse.to_json(api_response))
    
    # Check if response code and message are successful
    assert api_response_json.get('responseCode') == '2005700', "Expected success response code"
    assert api_response_json.get('responseMessage') == 'Successful', "Expected success response message"
    
    # Only check if paymentInfos array has at least one item
    payment_infos = api_response_json.get('paymentInfos', [])
    assert len(payment_infos) > 0, "Expected at least one payment info item"
    
    
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
@pytest.mark.skip(reason="Skipping this test temporarily.")
def test_consult_pay_invalid_mandatory_field():
    """Should give fail response code and message and correct mandatory fields"""
    case_name = "ConsultPayBalancedInvalidMandatoryField"
        
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a ConsultPayRequest object
    consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
    
    # Make the API call
    try:
        api_instance.consult_pay(consult_pay_request_obj)
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body)
