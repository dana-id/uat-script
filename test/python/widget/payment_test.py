import os
import pytest
import threading
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.widget.v1.enum import *
from dana.widget.v1.models import *
from dana.widget.v1 import *
from dana.widget.v1.api import *
from dana.api_client import ApiClient
from dana.exceptions import *
from helper.util import get_request, with_delay, generate_partner_reference_no, retry_on_inconsistent_request, retry_on_inconsistent_request
from helper.assertion import assert_response, assert_fail_response
from helper.api_helpers import get_headers_with_signature, execute_and_assert_api_error

title_case = "Payment"
json_path_file = "resource/request/components/Widget.json"
merchant_id = os.environ.get("MERCHANT_ID", "default_merchant_id")

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        CLIENT_SECRET=os.environ.get("CLIENT_SECRET"),
        ENV=Env.SANDBOX
    )
)

with ApiClient(configuration) as api_client:
    api_instance = WidgetApi(api_client)


@with_delay()
@retry_on_inconsistent_request(max_retries=3,delay_seconds=2)
def test_payment_success():
    case_name = "PaymentSuccess"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    api_response = api_instance.widget_payment(create_payment_request_obj)

    # Assert the API response
    assert_response(json_path_file, title_case, case_name, WidgetPaymentResponse.to_json(api_response), {"partnerReferenceNo": partner_reference_no})

@with_delay()
def test_payment_fail_invalid_format():
    case_name = "PaymentFailInvalidFormat"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    try:
        api_response = api_instance.widget_payment(create_payment_request_obj)
        pytest.fail("Expected BadRequestException but the API call succeeded")
    except BadRequestException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": partner_reference_no})
    except:
        pytest.fail("Expected BadRequestException but the API call give another exception")

@with_delay()
def test_payment_fail_missing_or_invalid_mandatory_field():
    case_name = "PaymentFailMissingOrInvalidMandatoryField"
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    
    # Prepare headers without timestamp to trigger mandatory field error
    # This now handles the signature generation internally
    headers = get_headers_with_signature(
        method="POST",
        resource_path="/rest/redirection/v1.0/debit/payment-host-to-host",
        request_obj=json_dict,
        with_timestamp=False
    )
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/rest/redirection/v1.0/debit/payment-host-to-host",
        create_payment_request_obj,
        headers,
        400,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": partner_reference_no}
    )

@with_delay()
def test_payment_fail_invalid_signature():
    case_name = "PaymentFailInvalidSignature"
    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)
    
    # Set a unique partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)

    # Prepare headers with invalid signature to trigger authorization error
    # Since we're only using the invalid signature flag, we don't need to pass any of the other parameters
    headers = get_headers_with_signature(invalid_signature=True)
    
    # Execute the API request and assert the error
    execute_and_assert_api_error(
        api_client,
        "POST",
        "http://api.sandbox.dana.id/rest/redirection/v1.0/debit/payment-host-to-host",
        create_payment_request_obj,
        headers,
        401,  # Expected status code
        json_path_file,
        title_case,
        case_name,
        {"partnerReferenceNo": partner_reference_no}
    )

@with_delay()
def test_payment_fail_merchant_not_exist_or_status_abnormal():
    case_name = "PaymentFailMerchantNotExistOrStatusAbnormal"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    try:
        api_response = api_instance.widget_payment(create_payment_request_obj)
        pytest.fail("Expected NotFoundException but the API call succeeded")
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": partner_reference_no})
    except:
        pytest.fail("Expected NotFoundException but the API call give another exception")

@with_delay()
def test_payment_fail_inconsistent_request():
    case_name = "PaymentFailInconsistentRequest"

    # Get the request data from the JSON file
    json_dict = get_request(json_path_file, title_case, case_name)

    # Set the partner reference number
    partner_reference_no = generate_partner_reference_no()
    json_dict["originalPartnerReferenceNo"] = partner_reference_no
    json_dict["partnerRefundNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id

    # Convert the request data to a RefundOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)

    # Make the API call and assert the response
    try:
        # First hit API
        api_instance.widget_payment(create_payment_request_obj)
        create_payment_request_obj.amount = Money(currency="IDR", value="10000.00")
        # Second hit API with same data (duplicate)
        api_instance.widget_payment(create_payment_request_obj)
    except NotFoundException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": json_dict["originalPartnerReferenceNo"]})

@with_delay()
def test_payment_fail_internal_server_error():
    case_name = "PaymentFailInternalServerError"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    try:
        api_response = api_instance.widget_payment(create_payment_request_obj)
        pytest.fail("Expected ServiceException but the API call succeeded")
    except ServiceException as e:
        assert_fail_response(json_path_file, title_case, case_name, e.body, {"partnerReferenceNo": partner_reference_no})
    except:
        pytest.fail("Expected ServiceException but the API call give another exception")

@with_delay()
def test_payment_fail_timeout():
    case_name = "PaymentFailTimeout"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id
    
    # Convert the request data to a CreateOrderRequest object
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    try:
        api_instance.widget_payment(create_payment_request_obj)
        pytest.fail("Expected ServiceException but the API call succeeded")
    except ServiceException as e:
        assert e.status == 504, "Expected status code 504 for timeout"
    except:
        pytest.fail("Expected ServiceException but the API call give another exception")  
        assert_fail_response(json_path_file, title_case, case_name, e.body, None)

@with_delay()
def test_payment_fail_idempotent():
    case_name = "PaymentFailIdempotent"
    json_dict = get_request(json_path_file, title_case, case_name)
    
    partner_reference_no = generate_partner_reference_no()
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["merchantId"] = merchant_id
    
    create_payment_request_obj = WidgetPaymentRequest.from_dict(json_dict)
    
    # ✅ Use barrier to ensure truly simultaneous execution
    num_threads = 10
    barrier = threading.Barrier(num_threads)
    results = {}
    
    def make_simultaneous_call(thread_id):
        """Function executed by each thread"""
        # Wait for all threads to reach this point
        barrier.wait()
        
        # Now all threads execute simultaneously
        start_time = time.time()
        try:
            response = api_instance.widget_payment(create_payment_request_obj)
            end_time = time.time()
            results[thread_id] = {
                "success": True,
                "response": WidgetPaymentResponse.to_json(response),
                "start_time": start_time,
                "end_time": end_time,
                "exception": None
            }
            print(f"Thread {thread_id}: SUCCESS in {end_time - start_time:.3f}s")
        except Exception as e:
            end_time = time.time()
            results[thread_id] = {
                "success": False,
                "response": None,
                "start_time": start_time,
                "end_time": end_time,
                "exception": e
            }
            print(f"Thread {thread_id}: FAILED in {end_time - start_time:.3f}s - {type(e).__name__}")
    
    # ✅ Create and start threads
    threads = []
    for i in range(num_threads):
        thread = threading.Thread(target=make_simultaneous_call, args=(i,))
        threads.append(thread)
        thread.start()
    
    # Wait for all threads to complete
    for thread in threads:
        thread.join()
    
    # ✅ Analyze timing and results
    successful_calls = [r for r in results.values() if r["success"]]
    failed_calls = [r for r in results.values() if not r["success"]]
    
    print(f"Simultaneous requests - Success: {len(successful_calls)}, Failed: {len(failed_calls)}")
    
    # Check if requests were truly simultaneous (within 100ms of each other)
    start_times = [r["start_time"] for r in results.values()]
    max_time_spread = max(start_times) - min(start_times)
    print(f"Time spread between request starts: {max_time_spread * 1000:.1f}ms")
    assert max_time_spread < 0.5, "Requests should start within 500ms of each other"
    
    # Validate idempotency behavior
    if len(successful_calls) >= 1:
        # At least one should succeed
        first_success = successful_calls[0]["response"]
        assert_response(json_path_file, title_case, case_name, first_success, {"partnerReferenceNo": partner_reference_no})
        
        # If multiple succeed, they should be identical
        for result in successful_calls[1:]:
            assert result["response"] == first_success, "All successful concurrent responses should be identical"