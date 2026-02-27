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

title_case = "ApplyOtt"
json_path_file = "resource/request/components/Widget.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        ENV=Env.SANDBOX,
        DANA_ENV=Env.SANDBOX,
        X_DEBUG="true"
    )
)

with ApiClient(configuration) as api_client:
    api_instance = WidgetApi(api_client)

def generate_partner_reference_no():
    return str(uuid4())

def get_access_token(phone_number=None, pin=None):
    if os.environ.get("CI") == "true":
        pytest.skip("Skipped in CI/CD")
    auth_code = asyncio.run(automate_oauth(phone_number=phone_number, pin=pin))
    print(auth_code)
    access_token = apply_token(auth_code)
    return access_token

@pytest.fixture(scope="module")
def test_access_token_success():
    return get_access_token(phone_number="083811223355", pin="181818")

@pytest.fixture(scope="module")
def test_access_token_user_abnormal():
    return get_access_token(phone_number="0855100800", pin="146838")

@with_delay()
def test_apply_ott_success(test_access_token_success):
    if os.environ.get("CI") == "true":
        pytest.skip("Skipped in CI/CD")
    case_name = "ApplyOttSuccess"
    access_token = test_access_token_success
    json_dict = get_request(json_path_file, title_case, case_name)
    applyOTTRequestAdditionalInfo = ApplyOTTRequestAdditionalInfo.from_dict(json_dict.get("additionalInfo", {}))
    applyOTTRequestAdditionalInfo.access_token = access_token
    applyOTTRequestAdditionalInfo.device_id = "deviceid123"
    json_dict["additionalInfo"] = applyOTTRequestAdditionalInfo.to_dict()
    apply_ott_request_obj = ApplyOTTRequest.from_dict(json_dict)
    api_response = api_instance.apply_ott(apply_ott_request_obj)
    assert_response(json_path_file, title_case, case_name, ApplyOTTResponse.to_json(api_response))

@with_delay()
def test_apply_ott_fail_token_not_found():
    case_name = "ApplyOttFailTokenNotFound"
    json_dict = get_request(json_path_file, title_case, case_name)
    applyOTTRequestAdditionalInfo = ApplyOTTRequestAdditionalInfo.from_dict(json_dict.get("additionalInfo", {}))
    applyOTTRequestAdditionalInfo.access_token = "GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800"
    applyOTTRequestAdditionalInfo.device_id = "deviceid123"
    json_dict["additionalInfo"] = applyOTTRequestAdditionalInfo.to_dict()
    apply_ott_request_obj = ApplyOTTRequest.from_dict(json_dict)

    try:
        api_response = api_instance.apply_ott(apply_ott_request_obj)
        assert_response(json_path_file, title_case, case_name, ApplyOTTResponse.to_json(api_response))
    except Exception as e:
        print("Error occurred while processing request:", e)
        
@with_delay()
def test_apply_ott_fail_token_user_abnormal(test_access_token_user_abnormal):
    if os.environ.get("CI") == "true":
        pytest.skip("Skipped in CI/CD")
    case_name = "ApplyOttCustomerAccountUserStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)
    applyOTTRequestAdditionalInfo = ApplyOTTRequestAdditionalInfo.from_dict(json_dict.get("additionalInfo", {}))
    applyOTTRequestAdditionalInfo.access_token = test_access_token_user_abnormal
    applyOTTRequestAdditionalInfo.device_id = "deviceid123"
    json_dict["additionalInfo"] = applyOTTRequestAdditionalInfo.to_dict()
    apply_ott_request_obj = ApplyOTTRequest.from_dict(json_dict)

    try:
        api_response = api_instance.apply_ott(apply_ott_request_obj)
        assert_response(json_path_file, title_case, case_name, ApplyOTTResponse.to_json(api_response))
    except ForbiddenException as e:
        print("Error occurred while processing request:", e)

def apply_token(auth_code):
    json_dict = get_request(json_path_file, "ApplyToken", "ApplyTokenSuccess")
    # Use the provided authorization code from the fixture
    json_dict["authCode"] = auth_code

    # Create the request object from the JSON dictionary
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)
    response = api_instance.apply_token(request_obj)
    return response.accessToken