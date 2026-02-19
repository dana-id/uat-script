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
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error
from helper.assertion import assert_response, assert_fail_response
from widget.automate_oauth import automate_oauth

title_case = "ApplyToken"
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

@pytest.fixture(scope="module")
def test_apply_token_auth_code():
    auth_code = asyncio.run(automate_oauth())
    print(f"Auth code for testing: {auth_code}")
    return auth_code

@with_delay()
def test_apply_token_success(test_apply_token_auth_code):
    if os.environ.get("CI") == "true":
        pytest.skip("Skipped in CI/CD")
    # Scenario: ApplyTokenSuccess
    # Purpose: Verify that a valid authorization code can be exchanged for an access token.
    # Steps:
    #   1. Prepare a valid request payload with a fresh auth code.
    #   2. Call the apply_token API endpoint.
    #   3. Assert the response matches the expected output for a successful case.
    # Expected: The API returns a valid access token and the response matches the expected schema.
    
    """Should successfully apply a token using a valid authorization code."""
    # Case name and JSON request preparation
    case_name = "ApplyTokenSuccess"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Use the provided authorization code from the fixture
    json_dict["authCode"] = test_apply_token_auth_code

    # Create the request object from the JSON dictionary
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)
    
    try:
        # Call the apply_token API endpoint with the request object
        response = api_instance.apply_token(request_obj)

        # Assert the response matches the expected schema and content
        assert_response(json_path_file, title_case, case_name, ApplyTokenResponse.to_json(response))

    except ApiException as e:
        # If the API call fails, log the error and fail the test
        pytest.fail(f"API call failed: {e}")

@with_delay()
def test_apply_token_fail_authcode_used():
    if os.environ.get("CI") == "true":
        pytest.skip("Skipped in CI/CD")
    # Get a fresh auth code for this test (don't reuse fixture as it might be consumed)
    auth_code = asyncio.run(automate_oauth())
    # Consume the auth code by calling apply_token once (success path)
    consume_dict = get_request(json_path_file, title_case, "ApplyTokenSuccess")
    consume_dict["authCode"] = auth_code
    api_instance.apply_token(ApplyTokenAuthorizationCodeRequest.from_dict(consume_dict))
    # Now try to use the same auth code again - should fail with 401
    case_name = "ApplyTokenFailAuthcodeUsed"
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["authCode"] = auth_code
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)
    try:
        api_instance.apply_token(request_obj)
        pytest.fail("Expected UnauthorizedException (401) for used auth code, but the API call succeeded.")
    except UnauthorizedException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body)

@with_delay()
def test_apply_token_fail_invalid_signature(test_apply_token_auth_code):
    # Scenario: ApplyTokenFailInvalidMandatoryFields
    # Purpose: Ensure missing or invalid mandatory fields in the request are properly rejected.
    # Steps:
    #   1. Prepare a request with missing/invalid required fields.
    #   2. Call the apply_token API endpoint.
    #   3. Assert the API returns a 400 Bad Request error.
    # Expected: The API returns a 400 Bad Request error for missing/invalid mandatory fields.

    """Should fail to apply a token with invalid mandatory fields."""
    # Case name and JSON request preparation
    case_name = "ApplyTokenFailInvalidSignature"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Use the provided authorization code from the fixture
    json_dict["authCode"] = test_apply_token_auth_code

    # Create the request object from the JSON dictionary
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)

    # Intentionally remove a mandatory field to trigger the error
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/v1.0/access-token/b2b2c.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False
    )
    
    headers["X-CLIENT-KEY"] = os.getenv("X_PARTNER_ID")
    headers["X-SIGNATURE"] = "xyzu"

    # Manually call the API with the request object and headers
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/v1.0/access-token/b2b2c.htm",
        request_obj,
        headers,
        401,
        json_path_file,
        title_case,
        case_name
    )
            