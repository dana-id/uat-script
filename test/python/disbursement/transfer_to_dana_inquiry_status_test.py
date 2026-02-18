import os
import pytest
import time
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

transfer_to_dana_title_case = "TransferToDana"
title_case = "TransferToDanaInquiryStatus"
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

# Shared test data for cross-test dependencies
original_partner_reference_paid = None
original_partner_reference_failed = None

def create_disbursement_paid():
    """Creates a successful disbursement transaction for testing status inquiry"""
    global original_partner_reference_paid
    
    disbursement_request = get_request(json_path_file, "TransferToDana", "TopUpCustomerValid")
    original_partner_reference_paid = str(uuid4())
    disbursement_request["partnerReferenceNo"] = original_partner_reference_paid
    
    request_obj = TransferToDanaRequest.from_dict(disbursement_request)
    api_instance.transfer_to_dana(request_obj)

def create_disbursement_failed():
    """Creates a failed disbursement transaction for testing status inquiry"""
    global original_partner_reference_failed
    
    disbursement_request = get_request(json_path_file, "TransferToDana", "TopUpCustomerExceedAmountLimit")
    original_partner_reference_failed = str(uuid4())
    disbursement_request["partnerReferenceNo"] = original_partner_reference_failed
    
    request_obj = TransferToDanaRequest.from_dict(disbursement_request)
    api_instance.transfer_to_dana(request_obj)

def setup_module():
    """Test setup hook that creates prerequisite disbursement transactions"""
    try:
        create_disbursement_paid()
        print(f"Shared order created with reference: {original_partner_reference_paid}")
        create_disbursement_failed()
        print(f"Shared order created with reference: {original_partner_reference_failed}")
    except Exception as e:
        print(f"Failed to create shared order - tests cannot continue: {e}")

@with_delay()
def test_inquiry_topup_status_valid_paid():
    case_name = "InquiryTopUpStatusValidPaid"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = original_partner_reference_paid
    request_obj = TransferToDanaInquiryStatusRequest.from_dict(json_dict)

    try:
        response = api_instance.transfer_to_dana_inquiry_status(request_obj)
        
        variable_dict = {
            'originalPartnerReferenceNo': original_partner_reference_paid,
            'originalReferenceNo': response.original_reference_no
        }
        
        assert_response(json_path_file, title_case, case_name, TransferToDanaInquiryStatusResponse.to_json(response), variable_dict)

    except ApiException as e:
        pytest.fail(f"API call failed: {e}")

@with_delay()
def test_inquiry_topup_status_valid_failed():
    case_name = "InquiryTopUpStatusValidFail"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = original_partner_reference_failed
    request_obj = TransferToDanaInquiryStatusRequest.from_dict(json_dict)

    try:
        response = api_instance.transfer_to_dana_inquiry_status(request_obj)
        
        variable_dict = {
            'originalPartnerReferenceNo': original_partner_reference_failed,
            'originalReferenceNo': response.original_reference_no
        }
        
        assert_response(json_path_file, title_case, case_name, TransferToDanaInquiryStatusResponse.to_json(response), variable_dict)

    except ApiException as e:
        pytest.fail(f"API call failed: {e}")

@with_delay()
def test_inquiry_topup_status_invalid_field_format():
    case_name = "InquiryTopUpStatusInvalidFieldFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = original_partner_reference_paid
    request_obj = TransferToDanaInquiryStatusRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana_inquiry_status(request_obj)
        pytest.fail("Expected ApiException for invalid field format but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': original_partner_reference_paid})

@with_delay()
def test_inquiry_topup_status_not_found_transaction():
    case_name = "InquiryTopUpStatusNotFoundTransaction"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = "test123123"
    request_obj = TransferToDanaInquiryStatusRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana_inquiry_status(request_obj)
        pytest.fail("Expected ApiException for transaction not found but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'originalPartnerReferenceNo': "test123123"})

@with_delay()
def test_inquiry_topup_status_missing_mandatory_field():
    case_name = "InquiryTopUpStatusMissingMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = original_partner_reference_paid
    request_obj = TransferToDanaInquiryStatusRequest.from_dict(json_dict)

    headers = get_headers_with_signature(
        method="POST",
        resource_path="v1.0/emoney/topup-status.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False
    )
    
    headers["X-TIMESTAMP"] = ""

    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/rest/v1.0/emoney/topup-status",
        request_obj,
        headers,
        400,
        json_path_file,
        title_case,
        case_name
    )

@with_delay()
def test_inquiry_topup_status_unauthorized_signature():
    case_name = "InquiryTopUpStatusUnauthorizedSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["originalPartnerReferenceNo"] = original_partner_reference_paid
    request_obj = TransferToDanaInquiryStatusRequest.from_dict(json_dict)

    headers = get_headers_with_signature(
        method="POST",
        resource_path="v1.0/emoney/topup-status.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False
    )
    
    headers["X-SIGNATURE"] = "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5"

    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/rest/v1.0/emoney/topup-status",
        request_obj,
        headers,
        401,
        json_path_file,
        title_case,
        case_name
    )
