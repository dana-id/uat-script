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

title_case = "BankAccountInquiry"
json_path_file = "resource/request/components/Disbursement.json"

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        DANA_ENV=Env.SANDBOX,
        CLIENT_SECRET=os.environ.get("CLIENT_SECRET"),
    )
)

with ApiClient(configuration) as api_client:
    api_instance = DisbursementApi(api_client)

@with_delay()
def test_inquiry_bank_account_valid_data_amount():
    case_name = "InquiryBankAccountValidDataAmount"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.bank_account_inquiry(request_obj)
        assert_response(json_path_file, title_case, case_name, BankAccountInquiryResponse.to_json(response), {"partnerReferenceNo": json_dict["partnerReferenceNo"]})

    except ApiException as e:
        pytest.fail(f"API call failed: {e}")

@with_delay()
def test_inquiry_bank_account_insufficient_fund():
    case_name = "InquiryBankAccountInsufficientFund"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.bank_account_inquiry(request_obj)
        assert_fail_response(json_path_file, title_case, case_name, BankAccountInquiryResponse.to_json(response), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_bank_account_inactive_account():
    case_name = "InquiryBankAccountInactiveAccount"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.bank_account_inquiry(request_obj)
        assert_fail_response(json_path_file, title_case, case_name, BankAccountInquiryResponse.to_json(response), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_bank_account_invalid_merchant():
    case_name = "InquiryBankAccountInvalidMerchant"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.bank_account_inquiry(request_obj)
        assert_fail_response(json_path_file, title_case, case_name, BankAccountInquiryResponse.to_json(response), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_bank_account_invalid_card():
    case_name = "InquiryBankAccountInvalidCard"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.bank_account_inquiry(request_obj)
        assert_fail_response(json_path_file, title_case, case_name, BankAccountInquiryResponse.to_json(response), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_bank_account_invalid_field_format():
    case_name = "InquiryBankAccountInvalidFieldFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.bank_account_inquiry(request_obj)
        assert_fail_response(json_path_file, title_case, case_name, BankAccountInquiryResponse.to_json(response), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_bank_account_missing_mandatory_field():
    case_name = "InquiryBankAccountMissingMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    try:
        response = api_instance.bank_account_inquiry(request_obj)
        assert_fail_response(json_path_file, title_case, case_name, BankAccountInquiryResponse.to_json(response), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_inquiry_bank_account_unauthorized_signature():
    case_name = "InquiryBankAccountUnauthorizedSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    test_order_reference_number = str(uuid4())
    json_dict["partnerReferenceNo"] = test_order_reference_number
    request_obj = BankAccountInquiryRequest.from_dict(json_dict)

    headers = get_headers_with_signature(
        method="POST",
        resource_path="v1.0/emoney/bank-account-inquiry.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False
    )
    
    headers["X-SIGNATURE"] = "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5"

    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/v1.0/emoney/bank-account-inquiry.htm",
        request_obj,
        headers,
        401,
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": test_order_reference_number}
    )
