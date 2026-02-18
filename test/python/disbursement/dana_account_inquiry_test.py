import os
import pytest
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.disbursement.v1.enum import *
from dana.disbursement.v1.models import *
from dana.disbursement.v1 import *
from dana.disbursement.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from uuid import uuid4
from helper.util import get_request, with_delay
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error
from helper.assertion import assert_response, assert_fail_response

title_case = "DanaAccountInquiry"
json_path_file = "resource/request/components/Disbursement.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        DANA_ENV=Env.SANDBOX,
        X_DEBUG="true",
        CLIENT_SECRET=os.environ.get("CLIENT_SECRET"),
    )
)

with ApiClient(configuration) as api_client:
    api_instance = DisbursementApi(api_client)

@with_delay()
def test_inquiry_customer_valid_data():
    case_name = "InquiryCustomerValidData"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = DanaAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.dana_account_inquiry(request_obj)
        assert_response(json_path_file, title_case, case_name, DanaAccountInquiryResponse.to_json(response))

    except ApiException as e:
        pytest.fail(f"API call failed: {e}")

@with_delay()
def test_inquiry_customer_unauthorized_signature():
    case_name = "InquiryCustomerUnauthorizedSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = DanaAccountInquiryRequest.from_dict(json_dict)

    headers = get_headers_with_signature(
        method="POST",
        resource_path="v1.0/emoney/account-inquiry.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False
    )
    
    headers["X-SIGNATURE"] = "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5"

    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/rest/v1.0/emoney/account-inquiry",
        request_obj,
        headers,
        401,
        json_path_file,
        title_case,
        case_name
    )

@with_delay()
def test_inquiry_customer_frozen_account():
    case_name = "InquiryCustomerFrozenAccount"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = DanaAccountInquiryRequest.from_dict(json_dict)

    try:
        api_instance.dana_account_inquiry(request_obj)
        pytest.fail("Expected ApiException for frozen account but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_customer_unregistered_account():
    case_name = "InquiryCustomerUnregisteredAccount"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = DanaAccountInquiryRequest.from_dict(json_dict)

    try:
        api_instance.dana_account_inquiry(request_obj)
        pytest.fail("Expected ApiException for unregistered account but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_customer_exceeded_limit():
    case_name = "InquiryCustomerExceededLimit"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = DanaAccountInquiryRequest.from_dict(json_dict)

    try:
        api_instance.dana_account_inquiry(request_obj)
        pytest.fail("Expected ApiException for exceeded limit but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})
