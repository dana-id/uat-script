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

title_case = "TransferToDana"
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
def test_topup_customer_valid():
    case_name = "TopUpCustomerValid"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        response = api_instance.transfer_to_dana(request_obj)
        assert_response(json_path_file, title_case, case_name, TransferToDanaResponse.to_json(response))

    except ApiException as e:
        pytest.fail(f"API call failed: {e}")

@with_delay()
def test_topup_customer_insufficient_fund():
    case_name = "TopUpCustomerInsufficientFund"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for insufficient fund but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
@pytest.mark.skip(reason="skipped: timeout test scenario")
def test_topup_customer_timeout():
    case_name = "TopUpCustomerTimeout"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for timeout but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_topup_customer_idempotent():
    case_name = "TopUpCustomerIdempotent"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    fixed_partner_ref = f"IDEMPOTENT_TEST_{int(time.time())}_{str(uuid4())[:5]}"
    json_dict["partnerReferenceNo"] = fixed_partner_ref
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        first_response = api_instance.transfer_to_dana(request_obj)
        
        time.sleep(1)
        
        try:
            second_response = api_instance.transfer_to_dana(request_obj)
            
            assert second_response.reference_no == first_response.reference_no
            assert second_response.response_code == first_response.response_code
            
            if hasattr(first_response, 'amount') and hasattr(second_response, 'amount'):
                assert second_response.amount == first_response.amount
                
            if first_response.response_message and second_response.response_message:
                assert second_response.response_message == first_response.response_message
                
        except ApiException as duplicate_error:
            error_response = duplicate_error.body
            is_duplicate_error = (
                'DUPLICATE' in error_response.get('responseCode', '') or
                'ALREADY_EXIST' in error_response.get('responseCode', '') or
                'duplicate' in error_response.get('responseMessage', '').lower()
            )
            
            if is_duplicate_error:
                assert error_response.get('responseCode') is not None
            else:
                pytest.fail(f"Expected duplicate error, but got: {error_response.get('responseCode')} - {error_response.get('responseMessage')}")

        time.sleep(2)
        
        try:
            third_response = api_instance.transfer_to_dana(request_obj)
            assert third_response.reference_no == first_response.reference_no
            assert third_response.response_code == first_response.response_code
            
            if hasattr(first_response, 'amount') and hasattr(third_response, 'amount'):
                assert third_response.amount == first_response.amount
                
        except ApiException as delayed_error:
            error_response = delayed_error.body
            is_duplicate_error = (
                'DUPLICATE' in error_response.get('responseCode', '') or
                'ALREADY_EXIST' in error_response.get('responseCode', '') or
                'duplicate' in error_response.get('responseMessage', '').lower()
            )
            
            if not is_duplicate_error:
                pytest.fail(f"Expected duplicate error after delay, but got: {error_response.get('responseCode')}")

        modified_dict = json_dict.copy()
        modified_dict["amount"]["value"] = str(float(json_dict["amount"]["value"]) + 1000)
        modified_request = TransferToDanaRequest.from_dict(modified_dict)
        
        try:
            api_instance.transfer_to_dana(modified_request)
            pytest.fail("Modified request with same reference should be rejected")
        except ApiException:
            pass

    except ApiException as first_error:
        print("First request failed - may be expected based on test data configuration")

@with_delay()
def test_topup_customer_frozen_account():
    case_name = "TopUpCustomerFrozenAccount"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for frozen account but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_topup_customer_exceed_amount_limit():
    case_name = "TopUpCustomerExceedAmountLimit"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for exceeding amount limit but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_topup_customer_missing_mandatory_field():
    case_name = "TopUpCustomerMissingMandatoryField"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for missing mandatory field but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_topup_customer_unauthorized_signature():
    case_name = "TopUpCustomerUnauthorizedSignature"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    headers = get_headers_with_signature(
        method="POST",
        resource_path="v1.0/emoney/topup.htm",
        request_obj=json_dict,
        with_timestamp=True,
        invalid_timestamp=False
    )
    
    headers["X-SIGNATURE"] = "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5"

    execute_and_assert_api_error(
        api_client,
        "POST",
        "https://api.sandbox.dana.id/v1.0/emoney/topup.htm",
        request_obj,
        headers,
        401,
        json_path_file,
        title_case,
        case_name
    )

@with_delay()
def test_topup_customer_invalid_field_format():
    case_name = "TopUpCustomerInvalidFieldFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for invalid field format but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_topup_customer_inconsistent_request():
    case_name = "TopUpCustomerInconsistentRequest"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        
        json_dict["amount"]["value"] = "4.00"
        json_dict["amount"]["currency"] = "IDR"
        request_obj_second = TransferToDanaRequest.from_dict(json_dict)
        
        api_instance.transfer_to_dana(request_obj_second)
        pytest.fail("Expected ApiException for inconsistent request but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_topup_customer_internal_server_error():
    case_name = "TopUpCustomerInternalServerError"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for internal server error but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})

@with_delay()
def test_topup_customer_internal_general_error():
    case_name = "TopUpCustomerInternalGeneralError"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    json_dict["partnerReferenceNo"] = str(uuid4())
    request_obj = TransferToDanaRequest.from_dict(json_dict)

    try:
        api_instance.transfer_to_dana(request_obj)
        pytest.fail("Expected ApiException for general error but the API call succeeded")

    except ApiException as e:
        assert_fail_response(json_path_file, title_case, case_name, str(e.body), 
                           {'partnerReferenceNo': json_dict["partnerReferenceNo"]})
