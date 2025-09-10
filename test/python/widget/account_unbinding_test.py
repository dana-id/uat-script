import os
import pytest
import asyncio
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
from widget.automate_oauth import automate_oauth

title_case = "AccountUnbinding"
json_path_file = "resource/request/components/Widget.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        ENV=Env.SANDBOX,
        DANA_ENV=Env.SANDBOX
    )
)

with ApiClient(configuration) as api_client:
    api_instance = WidgetApi(api_client)

def generate_partner_reference_no():
    return str(uuid4())

def get_auth_code():
    auth_code = asyncio.run(automate_oauth())
    return auth_code

@pytest.fixture(scope="module")
def test_account_unbinding_reference_number():
    auth_code = get_auth_code()
    return get_access_token(auth_code)

@with_delay()
def test_account_unbind_success(test_account_unbinding_reference_number):
    case_name = "AccountUnbindSuccess"
    access_token = test_account_unbinding_reference_number
    json_dict = get_request(json_path_file, title_case, case_name)
    additional_info = AccountUnbindingRequestAdditionalInfo(
        access_token=access_token,
        device_id="123123"
    )
    json_dict["additionalInfo"] = additional_info.to_dict()
    account_unbinding_request_obj = AccountUnbindingRequest.from_dict(json_dict)
    api_response = api_instance.account_unbinding(account_unbinding_request_obj)
    assert_response(json_path_file, title_case, case_name, AccountUnbindingResponse.to_json(api_response))

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

def get_access_token(auth_code):
    json_dict = get_request(json_path_file, "ApplyToken", "ApplyTokenSuccess")
    # Use the provided authorization code from the fixture
    json_dict["authCode"] = auth_code

    # Create the request object from the JSON dictionary
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)
    response = api_instance.apply_token(request_obj)
    return response.access_token