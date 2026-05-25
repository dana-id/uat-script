import json
import os
import threading
from datetime import datetime, timedelta, timezone

import urllib.request

from dana.api_client import ApiClient
from dana.merchant_management.v1 import MerchantManagementApi
from dana.merchant_management.v1.models.query_asset_card_list_request import QueryAssetCardListRequest
from dana.merchant_management.v1.models.query_merchant_info_request import QueryMerchantInfoRequest
from dana.utils.snap_configuration import AuthSettings, Env, SnapConfiguration
from helper.bni_hash_util import hash_data

DANA_SANDBOX_BASE_URL = "https://api.sandbox.dana.id"
BNI_VA_TOP_UP_PATH = "/ifcsupergw/bni/topup/merchant/request.htm"

DEFAULT_BNI_CLIENT_ID = "910"
DEFAULT_BNI_SECRET_KEY = "9546d5f69af2ed3bc603834446985628"
BNI_INST_ID = "BNIC1ID"
MERCHANT_DEPOSIT_ACCOUNT_TYPE = "MERCHANT_DEPOSIT_ACCOUNT"
MERCHANT_QUERY_LOGIN_TYPE_ROLE = "ROLE"
MERCHANT_DEPOSIT_TOP_UP_THRESHOLD = 1_000_000

_lock = threading.Lock()
_done = False
_failure: Exception | None = None
_merchant_api: MerchantManagementApi | None = None


def ensure_merchant_bni_va_topup() -> None:
    global _done, _failure
    if _done:
        if _failure is not None:
            raise _failure
        return
    with _lock:
        if _done:
            if _failure is not None:
                raise _failure
            return
        try:
            _bni_va_topup_merchant_once()
        except Exception as exc:
            _failure = exc
            raise
        finally:
            _done = True


def _get_merchant_management_api() -> MerchantManagementApi:
    global _merchant_api
    if _merchant_api is None:
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
        _merchant_api = MerchantManagementApi(ApiClient(configuration))
    return _merchant_api


def _bni_va_topup_merchant_once() -> None:
    deposit_balance = _query_merchant_deposit_total_amount()
    if deposit_balance >= MERCHANT_DEPOSIT_TOP_UP_THRESHOLD:
        print(
            f"Skipping BNI VA top-up: merchant deposit balance={deposit_balance} "
            f">= threshold={MERCHANT_DEPOSIT_TOP_UP_THRESHOLD}"
        )
        return
    print(
        f"Merchant deposit balance={deposit_balance} < threshold={MERCHANT_DEPOSIT_TOP_UP_THRESHOLD}; "
        "proceeding with BNI VA top-up"
    )

    virtual_account = _query_bni_merchant_virtual_account()
    _post_bni_va_topup_merchant(virtual_account)


def _query_merchant_deposit_total_amount() -> int:
    merchant_id = os.getenv("MERCHANT_ID", "")
    if not merchant_id:
        raise RuntimeError("MERCHANT_ID is required to query merchant info")

    request = QueryMerchantInfoRequest(
        role_id=merchant_id,
        login_type=MERCHANT_QUERY_LOGIN_TYPE_ROLE,
        is_query_account=True,
    )
    response = _get_merchant_management_api().query_merchant_info(request)

    result_info = response.response.body.result_info
    if result_info.result_status != "S":
        raise RuntimeError(f"queryMerchantInfo failed: {result_info}")

    accounts = getattr(response.response.body.merchant_information, "accounts", None) or []
    for account in accounts:
        if account.account_type == MERCHANT_DEPOSIT_ACCOUNT_TYPE:
            return _parse_account_mapped_total_amount(account)

    raise RuntimeError(
        f"queryMerchantInfo: {MERCHANT_DEPOSIT_ACCOUNT_TYPE} account not found"
    )


def _parse_account_mapped_total_amount(account) -> int:
    mapped_total = getattr(account, "mapped_total_amount", None)
    if mapped_total is not None and getattr(mapped_total, "amount", None) is not None:
        return _parse_amount_value(mapped_total.amount)

    account_dict = account.to_dict() if hasattr(account, "to_dict") else {}
    mapped_total = account_dict.get("mappedTotalAmount")
    if isinstance(mapped_total, dict) and "amount" in mapped_total:
        return _parse_amount_value(mapped_total["amount"])

    total_amount = getattr(account, "total_amount", None) or account_dict.get("totalAmount", "")
    if isinstance(total_amount, str) and total_amount:
        parsed = json.loads(total_amount)
        return _parse_amount_value(parsed.get("amount"))

    raise RuntimeError("deposit account amount missing in queryMerchantInfo response")


def _parse_amount_value(value) -> int:
    if isinstance(value, str):
        return int(value)
    if isinstance(value, bool):
        raise RuntimeError(f"unsupported amount format: {value!r}")
    if isinstance(value, (int, float)):
        return int(value)
    raise RuntimeError(f"unsupported amount format: {value!r}")


def _query_bni_merchant_virtual_account() -> str:
    member_id = os.getenv("MERCHANT_ID", "")
    if not member_id:
        raise RuntimeError("MERCHANT_ID is required to query merchant BNI VA")

    request = QueryAssetCardListRequest(
        member_id=member_id,
        enable_only="true",
        asset_type_list=["VA_ACCOUNT"],
    )
    response = _get_merchant_management_api().query_asset_card_list(request)

    result_info = response.response.body.result_info
    if result_info.result_status != "S":
        raise RuntimeError(f"queryAssetCardList failed: {result_info}")

    for card in response.response.body.asset_card_list or []:
        if card.asset_type == "VA_ACCOUNT" and card.inst_id == BNI_INST_ID:
            if card.card_index_no:
                return card.card_index_no

    raise RuntimeError(
        f"BNI VA card not found in assetCardList (assetType=VA_ACCOUNT instId={BNI_INST_ID})"
    )


def _post_bni_va_topup_merchant(virtual_account: str) -> None:
    jakarta = timezone(timedelta(hours=7))
    now = datetime.now(jakarta)
    integration_body = {
        "trx_amount": "1000",
        "trx_id": now.strftime("%Y%m%d%H%M%S"),
        "virtual_account": virtual_account,
        "customer_name": "rudy",
        "payment_amount": "1000000000",
        "cumulative_payment_amount": "1000",
        "payment_ntb": "233171",
        "datetime_payment": now.strftime("%Y-%m-%d %H:%M:%S"),
        "datetime_payment_iso8601": now.strftime("%Y-%m-%dT%H:%M:%S%z"),
    }

    client_id = os.getenv("BNI_VA_TOP_UP_CLIENT_ID", DEFAULT_BNI_CLIENT_ID)
    secret_key = os.getenv("BNI_VA_TOP_UP_SECRET_KEY", DEFAULT_BNI_SECRET_KEY)
    integration_json = json.dumps(integration_body, separators=(",", ":"))
    data = hash_data(integration_json, client_id, secret_key)

    payload = json.dumps({"client_id": client_id, "data": data})
    _http_post_json(DANA_SANDBOX_BASE_URL + BNI_VA_TOP_UP_PATH, payload)


def _http_post_json(url: str, body: str) -> str:
    req = urllib.request.Request(
        url,
        data=body.encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=30) as resp:
        response_text = resp.read().decode("utf-8")
        if resp.status < 200 or resp.status >= 300:
            raise RuntimeError(f"HTTP {resp.status}: {response_text}")

    try:
        parsed = json.loads(response_text)
        status = parsed.get("status")
        if status and status != "000":
            raise RuntimeError(f"BNI VA top-up rejected: {response_text}")
    except json.JSONDecodeError:
        pass

    return response_text
