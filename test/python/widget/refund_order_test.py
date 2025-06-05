import os
import pytest
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.ipg.v1.enum import *
from dana.ipg.v1.models import *
from dana.ipg.v1.models import RefundOrderRequest
from dana.ipg.v1 import *
from dana.ipg.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.util import get_request, retry_on_inconsistent_request, with_delay
from helper.assertion import assert_response, assert_fail_response

# Widget-specific constants
title_case = "RefundOrder"
create_order_title_case = "CreateOrder"
json_path_file = "resource/request/components/Widget.json"

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
    api_instance = IPGApi(api_client)

def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_order_reference_number():
    # In real tests, create an order and return its reference number
    return generate_partner_reference_no()

@with_delay()
def test_refund_in_process(test_order_reference_number):
    case_name = "RefundInProcess"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    # Simulate API call and response assertion
    # api_response = api_client.refund_order(json_dict)
    # assert_response(JSON_PATH_FILE, TITLE_CASE, case_name, api_response, {"partnerReferenceNo": test_order_reference_number})
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_exceed_payment_amount(test_order_reference_number):
    case_name = "RefundFailExceedPaymentAmount"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    # Simulate API call and error assertion
    # try:
    #     api_client.refund_order(json_dict)
    #     pytest.fail("Expected error but API call succeeded")
    # except Exception as e:
    #     assert_fail_response(JSON_PATH_FILE, TITLE_CASE, case_name, str(e), {"partnerReferenceNo": test_order_reference_number})
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_not_allowed_by_agreement(test_order_reference_number):
    case_name = "RefundFailNotAllowedByAgreement"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_exceed_refund_window_time(test_order_reference_number):
    case_name = "RefundFailExceedRefundWindowTime"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_multiple_refund_not_allowed(test_order_reference_number):
    case_name = "RefundFailMultipleRefundNotAllowed"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_duplicate_request(test_order_reference_number):
    case_name = "RefundFailDuplicateRequest"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_order_not_paid(test_order_reference_number):
    case_name = "RefundFailOrderNotPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_parameter_illegal(test_order_reference_number):
    case_name = "RefundFailParameterIllegal"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_mandatory_parameter_invalid(test_order_reference_number):
    case_name = "RefundFailMandatoryParameterInvalid"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_order_not_exist(test_order_reference_number):
    case_name = "RefundFailOrderNotExist"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_insufficient_merchant_balance(test_order_reference_number):
    case_name = "RefundFailInsufficientMerchantBalance"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_invalid_signature(test_order_reference_number):
    case_name = "RefundFailInvalidSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_timeout(test_order_reference_number):
    case_name = "RefundFailTimeout"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_idempotent(test_order_reference_number):
    case_name = "RefundFailIdempotent"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_refund_fail_merchant_status_abnormal(test_order_reference_number):
    case_name = "RefundFailMerchantStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = test_order_reference_number
    pytest.skip("SKIP: Placeholder test")
