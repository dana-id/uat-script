"""Shared helpers for disbursement SDK error assertions (aligned with Go/Node/PHP)."""

from uuid import uuid4

from dana.exceptions import ApiException

from helper.assertion import assert_sdk_error_response
from helper.util import get_request


def assert_disbursement_error_via_sdk(
    api_instance,
    method_name: str,
    json_path_file: str,
    title_case: str,
    case_name: str,
    request_model_class,
    *,
    request_mutator=None,
) -> None:
    """Call disbursement SDK with fixture payload and assert enriched error response."""
    json_dict = get_request(json_path_file, title_case, case_name)
    partner_reference_no = str(uuid4())
    json_dict["partnerReferenceNo"] = partner_reference_no
    if request_mutator is not None:
        request_mutator(json_dict, case_name)

    request_obj = request_model_class.from_dict(json_dict)
    variable_dict = {"partnerReferenceNo": partner_reference_no}
    api_response = None

    try:
        api_response = getattr(api_instance, method_name)(request_obj)
        assert_sdk_error_response(
            json_path_file,
            title_case,
            case_name,
            api_response,
            None,
            variable_dict,
        )
    except ApiException as exc:
        assert_sdk_error_response(
            json_path_file,
            title_case,
            case_name,
            api_response,
            exc,
            variable_dict,
        )
