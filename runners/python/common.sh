# Shared Python test runner utilities (local + CI).

if [ -z "${PYTHON_RUNNERS_DIR:-}" ]; then
    echo "PYTHON_RUNNERS_DIR must be set before sourcing runners/python/common.sh" >&2
    exit 1
fi

PROJECT_ROOT="$(dirname "$PYTHON_RUNNERS_DIR")"

resolve_python_cmd() {
    if command -v python3 > /dev/null 2>&1; then
        echo "python3"
    elif command -v python > /dev/null 2>&1; then
        echo "python"
    else
        echo ""
    fi
}

pattern_for_pytest_k() {
    echo "$1" | sed 's/|/ or /g'
}

get_mandatory_pattern_for_folder() {
    folder_name="$1"
    case "$folder_name" in
        "payment_gateway")
            echo "test_create_order_redirect_scenario|test_create_order_invalid_field_format|test_create_order_inconsistent_request|test_create_order_invalid_mandatory_field|test_create_order_unauthorized|test_transaction_success_notify|test_internal_server_error_notify|test_expired_notify"
            ;;
        "widget")
            echo "test_payment_success|test_payment_fail_missing_or_invalid_mandatory_field|test_payment_fail_general_error|test_payment_fail_not_permitted|test_payment_fail_merchant_not_exist_or_status_abnormal|test_payment_fail_inconsistent_request|test_payment_fail_internal_server_error|test_payment_fail_invalid_format|test_payment_fail_invalid_signature|test_payment_fail_exceed_amount_limit|test_transaction_success_notify|test_internal_server_error_notify|test_expired_notify"
            ;;
        "disbursement")
            echo "test_topup_customer_valid|test_topup_customer_insufficient_fund|test_topup_customer_frozen_account|test_topup_customer_missing_mandatory_field|test_topup_customer_inconsistent_request|test_topup_customer_internal_server_error|test_topup_customer_internal_general_error|test_disbursement_to_bank_valid|test_disbursement_to_bank_valid_account_in_progress|test_disbursement_to_bank_inconsistent_request|test_disbursement_to_bank_insufficient_fund|test_disbursement_to_bank_inactive_account|test_disbursement_to_bank_invalid_field_format|test_disbursement_to_bank_missing_mandatory_field|test_transaction_success_notify|test_internal_server_error_notify|test_expired_notify"
            ;;
        *)
            echo ""
            ;;
    esac
}

resolve_needs_playwright() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"
    needs_playwright=true

    if [ "$PYTHON_MANDATORY_ONLY" != "true" ]; then
        echo "true"
        return 0
    fi

    needs_playwright=false
    case "$folderName" in
        ""|"widget")
            if [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                needs_playwright=false
            else
                caseNameLower=$(echo "$caseName" | tr '[:upper:]' '[:lower:]')
                runPatternLower=$(echo "$runPattern" | tr '[:upper:]' '[:lower:]')
                if echo "$caseNameLower $runPatternLower" | grep -Eq "automation|oauth|browser|playwright"; then
                    needs_playwright=true
                fi
            fi
            ;;
        "payment_gateway"|"disbursement")
            if [ -n "$caseName" ] || [ -n "$runPattern" ]; then
                caseNameLower=$(echo "$caseName" | tr '[:upper:]' '[:lower:]')
                runPatternLower=$(echo "$runPattern" | tr '[:upper:]' '[:lower:]')
                if echo "$caseNameLower $runPatternLower" | grep -Eq "automation|oauth|browser|playwright"; then
                    needs_playwright=true
                fi
            fi
            ;;
    esac

    echo "$needs_playwright"
}

setup_python_env() {
    needs_playwright="$1"

    cd "$PROJECT_ROOT"

    $PYTHON_CMD --version
    $PYTHON_CMD -m venv venv
    . venv/bin/activate

    $PYTHON_CMD -m pip install --upgrade pip
    if [ "$needs_playwright" = "true" ]; then
        $PYTHON_CMD -m pip install --upgrade -r test/python/requirements.txt
    else
        echo "Using test/python/requirements-core.txt (no Playwright) for local mandatory-only run."
        $PYTHON_CMD -m pip install --upgrade -r test/python/requirements-core.txt
    fi
    $PYTHON_CMD -m pip install --upgrade "dana-python>=2.1.7"

    if [ "$needs_playwright" = "true" ]; then
        $PYTHON_CMD -m playwright install --with-deps chromium
    else
        echo "Skipping Playwright browser install for local mandatory-only run."
    fi

    export PYTHONPATH="${PYTHONPATH:+$PYTHONPATH:}$PROJECT_ROOT/test/python:$PROJECT_ROOT/runner/python"
}
