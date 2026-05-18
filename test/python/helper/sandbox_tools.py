import json
import urllib.error
import urllib.request

SANDBOX_TOOLS_EXECUTE_URL = "https://dashboard-sandbox.dana.id/merchant-portal-app/api/sandbox-tools/execute"
TRANSFER_VA_PAYMENT_ENDPOINT = "/v1.0/transfer-va/payment.htm"


def payment_code_from_create_order_response(api_response) -> str:
    additional_info = getattr(api_response, "additional_info", None)
    payment_code = getattr(additional_info, "payment_code", None) if additional_info else None
    if not payment_code:
        raise ValueError("paymentCode missing in create order response")
    return payment_code


def pay_virtual_account_sandbox(virtual_account_no: str) -> None:
    payload = json.dumps(
        {
            "urlEndpoint": TRANSFER_VA_PAYMENT_ENDPOINT,
            "requestBody": {"virtualAccountNo": virtual_account_no},
        }
    ).encode("utf-8")
    req = urllib.request.Request(
        SANDBOX_TOOLS_EXECUTE_URL,
        data=payload,
        method="POST",
        headers={
            "accept": "application/json",
            "accept-language": "en,id-ID;q=0.9,id;q=0.8,en-US;q=0.7",
            "content-type": "application/json",
            "origin": "https://dashboard.dana.id",
            "referer": "https://dashboard.dana.id/",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            if resp.status < 200 or resp.status >= 300:
                body = resp.read().decode("utf-8", errors="replace")
                raise RuntimeError(f"sandbox VA payment failed: status={resp.status} body={body}")
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"sandbox VA payment failed: status={e.code} body={body}") from e
