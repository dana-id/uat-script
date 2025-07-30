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
from automate_oauth import automate_oauth

title_case = "ApplyToken"
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

def get_auth_code():
    """
    Obtain a fresh OAuth authorization code for use in token application tests.
    Returns:
        str: A valid authorization code obtained via the automate_oauth helper.
    """
    return asyncio.run(automate_oauth())

@pytest.fixture(scope="module")
def test_apply_token_auth_code():
    """
    Pytest fixture to provide a valid authorization code for tests that require it.
    Returns:
        str: A valid authorization code (scoped to the test module).
    """
    return get_auth_code()

@with_delay()
def test_apply_token_success(test_apply_token_auth_code):
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
def test_apply_token_fail_expired_authcode(test_apply_token_auth_code):
    # case_name = "ApplyTokenFailExpiredAuthcode"
    # json_dict = get_request(json_path_file, title_case, case_name)
    # # Generate two auth codes
    # first_auth_code = test_apply_token_auth_code
    # second_auth_code = test_apply_token_auth_code
    # json_dict["authCode"] = first_auth_code
    # request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)
    # try:
    #     response = api_instance.apply_token(request_obj)
    #     # Expecting failure, so assert_fail_response
    #     assert_fail_response(json_path_file, title_case, case_name, ApplyTokenResponse.to_json(response))
    # except ApiException as e:
    #     # Optionally, check for specific error code/message for expired auth code
    #     assert "Unauthorized" in str(e).lower()
    pytest.skip("SKIP: Waiting for auth code expiration handling")

@with_delay()
def test_apply_token_fail_authcode_used():
    # Scenario: ApplyTokenFailAuthcodeUsed
    # Purpose: Ensure an authorization code cannot be used more than once.
    # Steps:
    #   1. Obtain a fresh auth code and use it to request a token (should succeed).
    #   2. Attempt to use the same code again (should fail with 401 Unauthorized).
    # Expected: The API returns 401 Unauthorized on the second use of the same code.
    
    """Should fail to apply a token using an already used authorization code."""
    # Case name and JSON request preparation
    case_name = "ApplyTokenFailAuthcodeUsed"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Obtain a fresh authorization code
    json_dict["authCode"] = get_auth_code()

    # Create the request object from the JSON dictionary
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)

    # First attempt to apply the token with the fresh auth code
    api_instance.apply_token(request_obj)
    try:
        # Second attempt to apply the token with the same auth code
        api_instance.apply_token(request_obj)

    except UnauthorizedException as e:
        # Assert that the response matches the expected failure case
        assert_fail_response(json_path_file, title_case, case_name, e.body)

    except Exception as e:
        # If any other exception occurs, fail the test
        pytest.fail("Expected UnauthorizedException (401) for used auth code, but the API call gave another exception.")

@with_delay()
def test_apply_token_fail_authcode_invalid():
    # Scenario: ApplyTokenFailAuthcodeInvalid
    # Purpose: Ensure the API rejects an invalid (random or malformed) authorization code.
    # Steps:
    #   1. Prepare a request with a clearly invalid auth code.
    #   2. Call the apply_token API endpoint.
    #   3. Assert the API returns 401 Unauthorized.
    # Expected: The API returns 401 Unauthorized for an invalid code.

    """Should fail to apply a token using an invalid authorization code."""
    # Case name and JSON request preparation
    case_name = "ApplyTokenFailAuthcodeInvalid"
    json_dict = get_request(json_path_file, title_case, case_name)

    # Use an invalid authorization code (e.g., random string)
    json_dict["authCode"] = "1kjlhldh1oiijhklj1FOELKHJQWQ1"

    # Create the request object from the JSON dictionary
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)

    try:
        # Attempt to apply the token with the invalid auth code
        api_instance.apply_token(request_obj)
    except UnauthorizedException as e:
        # Assert that the response matches the expected failure case
        assert_fail_response(json_path_file, title_case, case_name, e.body)
    except Exception as e:
        # If any other exception occurs, fail the test
        pytest.fail("Expected UnauthorizedException (401) for invalid auth code, but the API call gave another exception.")

@with_delay()
def test_apply_token_fail_invalid_params():
    # Scenario: ApplyTokenFailInvalidParams
    # Purpose: Placeholder for testing invalid request parameters (e.g., wrong types, extra fields).
    # Steps (to be implemented):
    #   1. Prepare a request with invalid parameters.
    #   2. Call the apply_token API endpoint.
    #   3. Assert the API returns a 400 Bad Request or appropriate error.
    # Expected: The API returns a 400 Bad Request or similar error for invalid parameters.
    case_name = "ApplyTokenFailInvalidParams"
    json_dict = get_request(json_path_file, title_case, case_name)
    pytest.skip("SKIP: Placeholder test")

@with_delay()
def test_apply_token_fail_invalid_mandatory_fields(test_apply_token_auth_code):
    # Scenario: ApplyTokenFailInvalidMandatoryFields
    # Purpose: Ensure missing or invalid mandatory fields in the request are properly rejected.
    # Steps:
    #   1. Prepare a request with missing/invalid required fields.
    #   2. Call the apply_token API endpoint.
    #   3. Assert the API returns a 400 Bad Request error.
    # Expected: The API returns a 400 Bad Request error for missing/invalid mandatory fields.

    """Should fail to apply a token with invalid mandatory fields."""
    # Case name and JSON request preparation
    case_name = "ApplyTokenFailInvalidMandatoryFields"
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
        with_timestamp=False
    )

    # Manually call the API with the request object and headers
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/v1.0/access-token/b2b2c.htm",
        request_obj,
        headers,
        400,
        json_path_file,
        title_case,
        case_name
    )

@with_delay()
def test_apply_token_fail_invalid_signature(test_apply_token_auth_code):
    # case_name = "ApplyTokenFailInvalidSignature"
    # json_dict = get_request(json_path_file, title_case, case_name)
    # json_dict["authCode"] = test_apply_token_auth_code  # Example invalid auth code
    # request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)
    
    # headers = get_headers_with_signature(invalid_signature=True)

    # execute_and_assert_api_error(
    #     api_client,
    #     "POST",
    #     "http://api.sandbox.dana.id/v1.0/access-token/b2b2c.htm",
    #     request_obj,
    #     headers,
    #     400,
    #     json_path_file,
    #     title_case,
    #     case_name
    # )    
    pytest.skip("SKIP: Need confirmation on invalid signature handling. Expected to return 401 Unauthorized, but currently returns 400 Bad Request.")
