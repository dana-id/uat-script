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

title_case = "AccountUnbinding"
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
def test_account_unbinding_reference_number():
    return generate_partner_reference_no()

@with_delay()
def test_account_unbind_success(test_account_unbinding_reference_number):
    case_name = "AccountUnbindSuccess"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_account_unbind_fail_access_token_not_exist(test_account_unbinding_reference_number):
    case_name = "AccountUnbindFailAccessTokenNotExist"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_account_unbind_fail_invalid_user_status(test_account_unbinding_reference_number):
    case_name = "AccountUnbindFailInvalidUserStatus"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_account_unbind_fail_invalid_params(test_account_unbinding_reference_number):
    case_name = "AccountUnbindFailInvalidParams"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")
