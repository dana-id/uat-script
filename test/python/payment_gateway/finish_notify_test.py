import os
from uuid import uuid4
from datetime import datetime, timedelta, timezone

from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.payment_gateway.v1.models import *
from dana.payment_gateway.v1 import *
from dana.api_client import ApiClient

from helper.util import get_request, with_delay, retry_on_inconsistent_request
from helper.assertion import assert_response
from helper.sandbox_tools import payment_code_from_create_order_response, pay_virtual_account_sandbox

title_case = "CreateOrder"
json_path_file = "resource/request/components/PaymentGateway.json"
create_order_request_case_finish_notify = "CreateOrderApi"
create_order_assert_case_finish_notify = "CreateOrderApi"
notification_n8n_url = "https://n8n.automation.dana.id/webhook/3676a08f-b06e-416c-b6cd-bea04f71c4d5"
finish_notify_default_valid_up_to_offset_seconds = 360
finish_notify_valid_up_to_offset_expired_seconds = 2 * 60 + 15
wib = timezone(timedelta(hours=7))

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        CLIENT_SECRET=os.environ.get("CLIENT_SECRET"),
        ENV=Env.SANDBOX,
        DANA_ENV=Env.SANDBOX,
        X_DEBUG="true",
    )
)

with ApiClient(configuration) as api_client:
    api_instance = PaymentGatewayApi(api_client)


def generate_formatted_date(offset_seconds: int = 0) -> str:
    dt = datetime.now(wib) + timedelta(seconds=offset_seconds)
    return dt.strftime("%Y-%m-%dT%H:%M:%S+07:00")


def patch_create_order_api_for_finish_notify(json_dict: dict, amount: str):
    if isinstance(json_dict.get("amount"), dict):
        json_dict["amount"]["value"] = amount
    json_dict["payOptionDetails"] = [
        {
            "payMethod": "VIRTUAL_ACCOUNT",
            "payOption": "VIRTUAL_ACCOUNT_CIMB",
            "transAmount": {"value": amount, "currency": "IDR"},
        }
    ]
    if isinstance(json_dict.get("urlParams"), list):
        for u in json_dict["urlParams"]:
            if isinstance(u, dict) and u.get("type") == "NOTIFICATION":
                u["url"] = notification_n8n_url


def create_order_api_finish_notify_once(amount: str, valid_up_to: str = ""):
    json_dict = get_request(json_path_file, title_case, create_order_request_case_finish_notify)
    partner_reference_no = str(uuid4())
    json_dict["partnerReferenceNo"] = partner_reference_no
    json_dict["validUpTo"] = (
        valid_up_to if valid_up_to else generate_formatted_date(finish_notify_default_valid_up_to_offset_seconds)
    )
    patch_create_order_api_for_finish_notify(json_dict, amount)
    create_order_request_obj = CreateOrderByApiRequest.from_dict(json_dict)
    api_response = api_instance.create_order(create_order_request_obj)
    return partner_reference_no, api_response


@with_delay()
@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def test_transaction_success_notify():
    partner_reference_no, api_response = create_order_api_finish_notify_once("11011.00", "")
    assert_response(
        json_path_file,
        title_case,
        create_order_assert_case_finish_notify,
        CreateOrderResponse.to_json(api_response),
        {"partnerReferenceNo": partner_reference_no},
    )
    pay_virtual_account_sandbox(payment_code_from_create_order_response(api_response))


@with_delay()
@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def test_internal_server_error_notify():
    partner_reference_no, api_response = create_order_api_finish_notify_once("11012.00", "")
    assert_response(
        json_path_file,
        title_case,
        create_order_assert_case_finish_notify,
        CreateOrderResponse.to_json(api_response),
        {"partnerReferenceNo": partner_reference_no},
    )
    pay_virtual_account_sandbox(payment_code_from_create_order_response(api_response))


@with_delay()
@retry_on_inconsistent_request(max_retries=3, delay_seconds=2)
def test_expired_notify():
    valid_up_to = generate_formatted_date(finish_notify_valid_up_to_offset_expired_seconds)
    partner_reference_no, api_response = create_order_api_finish_notify_once("11013.00", valid_up_to)
    assert_response(
        json_path_file,
        title_case,
        create_order_assert_case_finish_notify,
        CreateOrderResponse.to_json(api_response),
        {"partnerReferenceNo": partner_reference_no},
    )
