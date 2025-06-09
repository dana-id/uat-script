import os
import pytest
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.ipg.v1.enum import *
from dana.ipg.v1.models import *
from dana.ipg.v1 import *
from dana.ipg.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.util import get_request, with_delay
from helper.assertion import assert_response, assert_fail_response

title_case = "CancelOrder"
json_path_file = "resource/request/components/Widget.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        ENV=Env.SANDBOX
    )
)

with ApiClient(configuration) as api_client:
    api_instance = IPGApi(api_client)

def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_cancel_order_reference_number():
    return generate_partner_reference_no()

@with_delay()
def test_cancel_order_success_in_process(test_cancel_order_reference_number):
    """Should successfully cancel the order in process state"""
    case_name = "CancelOrderSuccessInProcess"
    json_dict = get_request(json_path_file, title_case, case_name)
    # Set the correct partner reference number if needed
    # if "originalPartnerReferenceNo" in json_dict:
    #     json_dict["originalPartnerReferenceNo"] = test_cancel_order_reference_number
    # else:
    #     json_dict["partnerReferenceNo"] = test_cancel_order_reference_number
    # cancel_order_request_obj = CancelOrderRequest.from_dict(json_dict)
    # api_response = api_instance.cancel_order(cancel_order_request_obj)
    # assert_response(
    #     json_path_file,
    #     title_case,
    #     case_name,
    #     CancelOrderResponse.to_json(api_response),
    #     {"partnerReferenceNo": test_cancel_order_reference_number}
    # )
    pytest.skip("SKIP: Need confirmation on responseMessage. Expected: 'Request is in process', Actual: 'Successful'")

@with_delay()
def test_cancel_order_fail_user_status_abnormal(test_cancel_order_reference_number):
    case_name = "CancelOrderFailUserStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_merchant_status_abnormal(test_cancel_order_reference_number):
    case_name = "CancelOrderFailMerchantStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_missing_parameter(test_cancel_order_reference_number):
    case_name = "CancelOrderFailMissingParameter"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_order_not_exist(test_cancel_order_reference_number):
    case_name = "CancelOrderFailOrderNotExist"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_exceed_cancel_window_time(test_cancel_order_reference_number):
    case_name = "CancelOrderFailExceedCancelWindowTime"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_not_allowed_by_agreement(test_cancel_order_reference_number):
    case_name = "CancelOrderFailNotAllowedByAgreement"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_account_status_abnormal(test_cancel_order_reference_number):
    case_name = "CancelOrderFailAccountStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_insufficient_merchant_balance(test_cancel_order_reference_number):
    case_name = "CancelOrderFailInsufficientMerchantBalance"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_order_refunded(test_cancel_order_reference_number):
    case_name = "CancelOrderFailOrderRefunded"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_invalid_signature(test_cancel_order_reference_number):
    case_name = "CancelOrderFailInvalidSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_cancel_order_fail_timeout(test_cancel_order_reference_number):
    case_name = "CancelOrderFailTimeout"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")