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
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error

title_case = "BalanceInquiry"
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

def generate_partner_reference_no():
    return str(uuid4())

@pytest.fixture(scope="module")
def test_access_token_success():
    return get_access_token(phone_number="083811223355", pin="181818")

@pytest.fixture(scope="module")
def test_access_token_user_abnormal():
    return get_access_token(phone_number="0855100800", pin="146838")

@pytest.fixture(scope="module")
def test_access_token_invalid_customer():
    return get_access_token(phone_number="0815919191", pin="631642")

@with_delay()
def test_balance_inquiry_success(test_access_token_success):
    if os.environ.get("CI") == "true":
        pytest.skip("Skipped in CI/CD")
        
    case_name = "BalanceInquirySuccess"
    partner_reference_no = generate_partner_reference_no()
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = partner_reference_no
    balanceInquiryRequestAdditionalInfo = BalanceInquiryRequestAdditionalInfo.from_dict(json_dict.get("additionalInfo", {}))
    balanceInquiryRequestAdditionalInfo.access_token = test_access_token_success
    json_dict["additionalInfo"] = balanceInquiryRequestAdditionalInfo.to_dict()
    balanceInquiryRequest = BalanceInquiryRequest.from_dict(json_dict)
    api_response = api_instance.balance_inquiry(balanceInquiryRequest)
    assert_response(json_path_file, title_case, case_name, WidgetPaymentResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_balance_inquiry_fail_invalid_format():
    case_name = "BalanceInquiryFailInvalidFormat"
    partner_reference_no = generate_partner_reference_no()
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = partner_reference_no
    balance_inquiry_request = BalanceInquiryRequest.from_dict(json_dict)
    
    # Prepare the headers with the signature
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/v1.0/balance-inquiry.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=True
    )

    # Execute the API call and assert the expected error response
    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/v1.0/balance-inquiry.htm",
        balance_inquiry_request,
        headers,
        400,  # Bad Request
        json_path_file,
        title_case,
        case_name,
        {"originalPartnerReferenceNo": partner_reference_no}  
    )

@with_delay()
def test_balance_inquiry_fail_missing_or_invalid_mandatory_field():
    case_name = "BalanceInquiryFailInvalidMandatoryField"
    partner_reference_no = generate_partner_reference_no()
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = partner_reference_no
    balance_inquiry_request = BalanceInquiryRequest.from_dict(json_dict)
    
    # Prepare the headers with the signature
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/v1.0/balance-inquiry.htm",
        request_obj=json_dict,
        with_timestamp=False
    )

    # Execute the API call and assert the expected error response
    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/v1.0/balance-inquiry.htm",
        balance_inquiry_request,
        headers,
        400,
        json_path_file,
        title_case,
        case_name,
        {"originalPartnerReferenceNo": partner_reference_no}  
    )

@with_delay()
def test_balance_inquiry_fail_invalid_signature():
    case_name = "BalanceInquiryFailUnauthorizedError"
    partner_reference_no = generate_partner_reference_no()
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = partner_reference_no
    balance_inquiry_request = BalanceInquiryRequest.from_dict(json_dict)
    
    # Prepare the headers with the signature
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/v1.0/balance-inquiry.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False,
        invalid_signature=True
    )

    # Execute the API call and assert the expected error response
    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/v1.0/balance-inquiry.htm",
        balance_inquiry_request,
        headers,
        401,
        json_path_file,
        title_case,
        case_name,
        {"originalPartnerReferenceNo": partner_reference_no}  
    )

@with_delay()
def test_balance_inquiry_fail_user_status_abnormal(test_access_token_user_abnormal):
    case_name = "BalanceInquiryFailAccountUserStatusAbnormal"
    partner_reference_no = generate_partner_reference_no()
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = partner_reference_no
    balanceInquiryRequestAdditionalInfo = BalanceInquiryRequestAdditionalInfo.from_dict(json_dict.get("additionalInfo", {}))
    balanceInquiryRequestAdditionalInfo.access_token = test_access_token_user_abnormal
    json_dict["additionalInfo"] = balanceInquiryRequestAdditionalInfo.to_dict()
    balanceInquiryRequest = BalanceInquiryRequest.from_dict(json_dict)
    try:
        # Call the balance_inquiry API endpoint with the request object
        api_instance.balance_inquiry(balanceInquiryRequest)

        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected NotFoundException but API call succeeded")
    except ForbiddenException as e:
        # If the API call fails with NotFoundException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {'partnerReferenceNo': json_dict["partnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected NotFoundException but got a different exception")
    
@with_delay()
def test_balance_inquiry_fail_invalid_customer_token(test_access_token_invalid_customer):
    case_name = "BalanceInquiryFailAccessTokenInvalid"
    partner_reference_no = generate_partner_reference_no()
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = partner_reference_no
    balanceInquiryRequestAdditionalInfo = BalanceInquiryRequestAdditionalInfo.from_dict(json_dict.get("additionalInfo", {}))
    balanceInquiryRequestAdditionalInfo.access_token = test_access_token_invalid_customer
    json_dict["additionalInfo"] = balanceInquiryRequestAdditionalInfo.to_dict()
    balanceInquiryRequest = BalanceInquiryRequest.from_dict(json_dict)
    try:
        # Call the balance_inquiry API endpoint with the request object
        api_instance.balance_inquiry(balanceInquiryRequest)

        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected UnauthorizedException but API call succeeded")
    except UnauthorizedException as e:
        # If the API call fails with UnauthorizedException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {'partnerReferenceNo': json_dict["partnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected NotFoundException but got a different exception")
        
@with_delay()
def test_balance_inquiry_fail_inactive_account():
    case_name = "BalanceInquiryFailInactiveAccount"
    partner_reference_no = generate_partner_reference_no()
    json_dict = get_request(json_path_file, title_case, case_name)
    json_dict["partnerReferenceNo"] = partner_reference_no
    balanceInquiryRequestAdditionalInfo = BalanceInquiryRequestAdditionalInfo.from_dict(json_dict.get("additionalInfo", {}))
    balanceInquiryRequestAdditionalInfo.access_token = test_access_token_invalid_customer
    json_dict["additionalInfo"] = balanceInquiryRequestAdditionalInfo.to_dict()
    balanceInquiryRequest = BalanceInquiryRequest.from_dict(json_dict)
    try:
        # Call the balance_inquiry API endpoint with the request object
        api_instance.balance_inquiry(balanceInquiryRequest)

        # If the API call succeeds, fail the test as we expect an exception
        pytest.fail("Expected ForbiddenException but API call succeeded")
    except ForbiddenException as e:
        # If the API call fails with ForbiddenException, assert the error response
        assert_fail_response(
            json_path_file,
            title_case,
            case_name,
            e.body,
            {'partnerReferenceNo': json_dict["partnerReferenceNo"]}
        )
    except:
        # If any other exception occurs, fail the test
        pytest.fail("Expected ForbiddenException but got a different exception")

def apply_token(auth_code):
    json_dict = get_request(json_path_file, "ApplyToken", "ApplyTokenSuccess")
    # Use the provided authorization code from the fixture
    json_dict["authCode"] = auth_code

    # Create the request object from the JSON dictionary
    request_obj = ApplyTokenAuthorizationCodeRequest.from_dict(json_dict)
    response = api_instance.apply_token(request_obj)
    return response.accessToken