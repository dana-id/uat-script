import os
import pytest
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.widget.v1.enum import *
from dana.widget.v1.models import *
from dana.widget.v1 import *
from dana.widget.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.util import get_request, with_delay
from helper.assertion import assert_response, assert_fail_response

title_case = "Payment"
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
    api_instance = WidgetApi(api_client)

def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_payment_reference_number():
    return generate_partner_reference_no()

@with_delay()
def test_payment_success(test_payment_reference_number):
    case_name = "PaymentSuccess"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_invalid_format(test_payment_reference_number):
    case_name = "PaymentFailInvalidFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_missing_or_invalid_mandatory_field(test_payment_reference_number):
    case_name = "PaymentFailMissingOrInvalidMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_invalid_signature(test_payment_reference_number):
    case_name = "PaymentFailInvalidSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_general_error(test_payment_reference_number):
    case_name = "PaymentFailGeneralError"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_transaction_not_permitted(test_payment_reference_number):
    case_name = "PaymentFailTransactionNotPermitted"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_merchant_not_exist_or_status_abnormal(test_payment_reference_number):
    case_name = "PaymentFailMerchantNotExistOrStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_inconsistent_request(test_payment_reference_number):
    case_name = "PaymentFailInconsistentRequest"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_internal_server_error(test_payment_reference_number):
    case_name = "PaymentFailInternalServerError"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_exceeds_transaction_amount_limit(test_payment_reference_number):
    case_name = "PaymentFailExceedsTransactionAmountLimit"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_timeout(test_payment_reference_number):
    case_name = "PaymentFailTimeout"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_payment_fail_idempotent(test_payment_reference_number):
    case_name = "PaymentFailIdempotent"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")