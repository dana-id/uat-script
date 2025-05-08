import json
import os
import pytest
from datetime import datetime, timedelta
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.enum import *
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1.models import QueryPaymentRequest, CreateOrderByApiRequest
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient
from dana.rest import ApiException
from dana.exceptions import *

from helper.util import get_request
from helper.assertion import *

title_case = "QueryPayment"
create_order_title_case = "CreateOrder"
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
    return str((datetime.now() + timedelta(days=1)).strftime("%Y%m%d%H%M%S0700"))


def create_test_order(partner_reference_no):
    """Helper function to create a test order"""
    case_name = "CreateOrderNetworkPayPgOtherVaBank"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
    # Set the partner reference number
    json_dict["partnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a CreateOrderRequest object
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
    # Make the API call
    return api_instance.create_order(create_order_request_obj)


def test_query_payment_valid_format():
    """Should query the payment status successfully"""
    # Create order first
    partner_reference_no = generate_partner_reference_no()
    create_test_order(partner_reference_no)
    
    # Query payment
    case_name = "QueryPaymentValidFormat"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call
    api_response = api_instance.query_payment(query_payment_request_obj)
    
    # Assert the API response
    assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response))


def test_query_payment_invalid_format():
    """Should fail when query has invalid format"""
    # Create order first
    partner_reference_no = generate_partner_reference_no()
    create_test_order(partner_reference_no)
    
    # Query payment
    case_name = "QueryPaymentInvalidFormat"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e)


def test_query_payment_invalid_mandatory_field():
    """Should fail when query is missing mandatory field"""
    # Create order first
    partner_reference_no = generate_partner_reference_no()
    create_test_order(partner_reference_no)
    
    # Query payment
    case_name = "QueryPaymentInvalidMandatoryField"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e)


def test_query_payment_transaction_not_found():
    """Should fail when transaction is not found"""
    # Create order first
    partner_reference_no = generate_partner_reference_no()
    create_test_order(partner_reference_no)
    
    # Query payment
    case_name = "QueryPaymentTransactionNotFound"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number with modification to ensure it's not found
    json_dict["originalPartnerReferenceNo"] = partner_reference_no + "test"
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e)


def test_query_payment_general_error():
    """Should handle general server error"""
    # Create order first
    partner_reference_no = generate_partner_reference_no()
    create_test_order(partner_reference_no)
    
    # Query payment
    case_name = "QueryPaymentGeneralError"
    
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set the correct partner reference number
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    
    # Convert the request data to a QueryPaymentRequest object
    query_payment_request_obj = QueryPaymentRequest.from_dict(json_dict)
    
    # Make the API call and expect an exception
    try:
        api_instance.query_payment(query_payment_request_obj)
    except ServiceException as e:
        assert_fail_response(json_path_file, title_case, case_name, e)