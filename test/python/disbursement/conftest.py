import pytest

from helper.merchant_bni_va_topup import ensure_merchant_bni_va_topup


@pytest.fixture(scope="session", autouse=True)
def merchant_bni_va_topup_before_disbursement_tests():
    ensure_merchant_bni_va_topup()
