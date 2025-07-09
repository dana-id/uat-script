import os
import pytest
import asyncio
from dana.utils.snap_configuration import SnapConfiguration, AuthSettings, Env
from dana.ipg.v1.api import IPGApi
from dana.api_client import ApiClient
from helper.util import with_delay
from automate_oauth import automate_oauth

configuration = SnapConfiguration(
    api_key=AuthSettings(
        PRIVATE_KEY=os.environ.get("PRIVATE_KEY"),
        ORIGIN=os.environ.get("ORIGIN"),
        X_PARTNER_ID=os.environ.get("X_PARTNER_ID"),
        ENV=Env.SANDBOX
    )
)

with ApiClient(configuration) as api_client:
    api_instance = IPGApi(api_client)

@pytest.fixture(scope="module")
def test_get_auth_reference_number():
    from uuid import uuid4
    return str(uuid4())

@with_delay()
def test_get_auth_success():
    output = asyncio.run(automate_oauth())
    if not output:
        pytest.fail("automate_oauth() did not return any value.")
    auth_code = output
    if not auth_code:
        pytest.fail("Auth Code was not returned by automate_oauth().")
