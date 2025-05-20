# import os
# import pytest
# import time
# from uuid import uuid4
# from datetime import datetime, timedelta, timezone
# from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
# from dana.payment_gateway.v1.enum import *
# from dana.payment_gateway.v1.models import *
# from dana.payment_gateway.v1.models import CancelOrderRequest, CreateOrderByApiRequest
# from dana.payment_gateway.v1 import *
# from dana.api_client import ApiClient
# from dana.exceptions import *

# from helper.util import get_request, with_delay
# from helper.assertion import *

# title_case = "QueryPayment"
# create_order_title_case = "CreateOrder"
# json_path_file = "resource/request/components/PaymentGateway.json"

# configuration = SnapConfiguration(
#     api_key=AuthSettings(
#         PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
#         ORIGIN=os.environ.get("ORIGIN"),
#         X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
#         CHANNEL_ID=os.environ.get("CHANNEL_ID"),
#         ENV=Env.SANDBOX
#     )
# )

# with ApiClient(configuration) as api_client:
#     api_instance = PaymentGatewayApi(api_client)


# def generate_partner_reference_no():
#     return str(uuid4())


# def create_test_order(partner_reference_no):
#     """Helper function to create a test order"""
#     case_name = "CreateOrderApi"
    
#     # Get the request data from the JSON file
#     json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
#     # Set the partner reference number
#     json_dict["partnerReferenceNo"] = partner_reference_no
    
#     # Convert the request data to a CreateOrderRequest object
#     create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
#     # Make the API call
#     try:
#         api_instance.create_order(create_order_request_obj)
#     except Exception as e:
#         pytest.fail(f"Fail to call create order API {e}")

# def create_test_order_canceled(partner_reference_no):
#     """Helper function to create a test order with short expiration date to have canceled status"""
#     case_name = "CreateOrderApi"
    
#     # Get the request data from the JSON file
#     json_dict = get_request(json_path_file, create_order_title_case, case_name)
    
#     # Set the partner reference number
#     json_dict["partnerReferenceNo"] = partner_reference_no

#     # Set the expiration time to 1 second from now
#     json_dict["validUpTo"] = (datetime.now().astimezone(timezone(timedelta(hours=7))) + timedelta(seconds=1)).strftime('%Y-%m-%dT%H:%M:%S+07:00')
    
#     # Convert the request data to a CreateOrderRequest object
#     create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    
#     # Make the API call
#     try:
#         api_instance.create_order(create_order_request_obj)
#     except Exception as e:
#         pytest.fail(f"Fail to call create order API {e}")


# @pytest.fixture(scope="module")
# def test_order_reference_number():
#     """Fixture that creates a test order once per module and shares the reference number"""
#     partner_reference_no = generate_partner_reference_no()
#     print(f"\nCreating shared test order with reference number: {partner_reference_no}")
#     create_test_order(partner_reference_no)
#     return partner_reference_no    

# @with_delay()
# def test_cancel_order(test_order_reference_number):
#     """Should cancel the order"""
#     # Cancel order
#     case_name = "CancelCreatedOrder"
    
#     # Get the request data from the JSON file
#     json_dict = get_request(json_path_file, title_case, case_name)
    
#     # Set the correct partner reference number
#     json_dict["originalPartnerReferenceNo"] = test_order_reference_number
    
#     # Convert the request data to a CancelOrderRequest object
#     cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    
#     # Make the API call
#     api_response = api_instance.query_payment(query_payment_request_obj)
    
#     # Assert the API response
#     assert_response(json_path_file, title_case, case_name, QueryPaymentResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})
