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

title_case = "BalanceInquiry"
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
def test_balance_inquiry_reference_number():
    return generate_partner_reference_no()

@with_delay()
def test_balance_inquiry_success(test_balance_inquiry_reference_number):
    case_name = "BalanceInquirySuccess"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_invalid_format(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailInvalidFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_missing_or_invalid_mandatory_field(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailMissingOrInvalidMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_invalid_signature(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailInvalidSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_token_expired(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailTokenExpired"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_token_not_found(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailTokenNotFound"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_invalid_user_status(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailInvalidUserStatus"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_internal_server_error(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailInternalServerError"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_balance_inquiry_fail_unexpected_response(test_balance_inquiry_reference_number):
    case_name = "BalanceInquiryFailUnexpectedResponse"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")