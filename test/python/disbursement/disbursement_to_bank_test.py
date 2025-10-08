import os
import pytest
import asyncio
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

title_case = "TransferToBank"
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
def test_disbursement_to_bank_valid():
    case_name = "DisbursementBankValidAccount"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        response = api_instance.transfer_to_bank(request_obj)
        assert_response(json_path_file, title_case, case_name, TransferToBankResponse.to_json(response))

    except ApiException as e:
        # If the API call fails, log the error and fail the test
        pytest.fail(f"API call failed: {e}")

@with_delay()
def test_disbursement_to_bank_unauthorized_signature():
    case_name = "DisbursementBankUnauthorizedSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    # Intentionally remove a mandatory field to trigger the error
    headers = get_headers_with_signature(
        method="POST",
        resource_path="v1.0/emoney/transfer-bank.htm",
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
        "http://api.sandbox.dana.id/v1.0/emoney/transfer-bank.htm",
        request_obj,
        headers,
        401,
        json_path_file,
        title_case,
        case_name
    )

@with_delay()
@pytest.mark.skip(reason="skipped: skip because there is a mandatory input request in library")
def test_disbursement_to_bank_missing_mandatory_field():
    case_name = "DisbursementBankMissingMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_bank(request_obj)
        
        pytest.fail("Expected ApiException for invalid field format but the API call succeeded")
    except ApiException as e:
        # Expected failure case - assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_invalid_field_format():
    case_name = "DisbursementBankInvalidFieldFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_bank(request_obj)
        pytest.fail("Expected ApiException for invalid field format but the API call succeeded")

    except ApiException as e:
        # Expected failure case - assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_insufficient_fund():
    case_name = "DisbursementBankInsufficientFund"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_bank(request_obj)
        pytest.fail("Expected ApiException for insufficient fund but the API call succeeded")

    except ApiException as e:
        # Expected failure case - assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_inactive_account():
    case_name = "DisbursementBankInactiveAccount"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_bank(request_obj)
        pytest.fail("Expected ApiException for inactive account but the API call succeeded")

    except ApiException as e:
        # Expected failure case - assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_inconsistent_request():
    case_name = "DisbursementBankInconsistentRequest"
    json_dict = get_request(json_path_file, title_case, case_name)
    partner_reference_no = str(uuid4())

    json_dict["partnerReferenceNo"] = partner_reference_no
    request_obj = TransferToBankRequest.from_dict(json_dict)
# Make the API call and expect an exception
    try:
        api_instance.transfer_to_bank(request_obj)
    except:
        pytest.fail("Fail to call first API")

    time.sleep(1)  # Adding a short delay to ensure the first request is processed
    
    try:
        # Preparing request with the same partner reference number but different amount
        json_dict["amount"]["value"] = "2000.00"
        json_dict["amount"]["currency"] = "IDR"

        request_obj_second = TransferToBankRequest.from_dict(json_dict)

        api_instance.transfer_to_bank(request_obj_second)

        pytest.fail("Expected NotFoundException but the API call succeeded")

    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": partner_reference_no})
    except:
        pytest.fail("Expected NotFoundException but the API call give another exception")

@with_delay()
def test_disbursement_to_bank_suspected_fraud():
    case_name = "DisbursementBankSuspectedFraud"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_bank(request_obj)
        pytest.fail("Expected ApiException for suspected fraud but the API call succeeded")

    except ApiException as e:
        # Expected failure case - assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_unknown_error():
    case_name = "DisbursementBankUnknownError"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_bank(request_obj)
        pytest.fail("Expected ApiException for unknown error but the API call succeeded")

    except ApiException as e:
        # Expected failure case - assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_general_error():
    case_name = "DisbursementBankGeneralError"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_bank(request_obj)
        pytest.fail("Expected ApiException for general error but the API call succeeded")

    except ApiException as e:
        # Expected failure case - assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_valid_account_in_progress():
    case_name = "DisbursementBankValidAccountInProgress"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    try:
        response = api_instance.transfer_to_bank(request_obj)
        # This case might succeed with in-progress status
        assert_response(json_path_file, title_case, case_name, TransferToBankResponse.to_json(response))

    except ApiException as e:
        # If it fails, assert the error response
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_disbursement_to_bank_invalid_mandatory_field_format():
    case_name = "DisbursementBankInvalidMandatoryFieldFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToBankRequest.from_dict(json_dict)

    # Intentionally remove a mandatory field to trigger the error
    headers = get_headers_with_signature(
        method="POST",
        resource_path="v1.0/emoney/transfer-bank.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False
    )
    
    headers["X-CLIENT-KEY"] = os.getenv("X_PARTNER_ID")
    headers["X-SIGNATURE"] = ""

    # Manually call the API with the request object and headers
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/v1.0/emoney/transfer-bank.htm",
        request_obj,
        headers,
        400,
        json_path_file,
        title_case,
        case_name
    )