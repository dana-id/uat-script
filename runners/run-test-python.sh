#!/bin/sh

# Fail on error
set -e

resolve_python_cmd() {
    if command -v python3 > /dev/null 2>&1; then
        echo "python3"
    elif command -v python > /dev/null 2>&1; then
        echo "python"
    else
        echo ""
    fi
}

resolve_mandatory_only() {
    if [ -n "${PYTHON_MANDATORY_ONLY:-}" ]; then
        echo "$PYTHON_MANDATORY_ONLY"
    elif [ -z "${CI:-}" ]; then
        echo "true"
    else
        echo "false"
    fi
}

resolve_python_retry_failed_only() {
    if [ "${PYTHON_RETRY_FAILED_ONLY:-}" = "false" ]; then
        echo "false"
    elif [ "${PYTHON_RETRY_FAILED_ONLY:-}" = "true" ]; then
        echo "true"
    elif [ -n "${CI:-}" ] || [ -n "${GITLAB_CI:-}" ]; then
        echo "true"
    else
        echo "false"
    fi
}

python_retry_sleep_seconds() {
    failed_attempt="$1"
    initial_delay="${RETRY_INITIAL_DELAY_SECONDS:-10}"
    delay_before_attempt_4="${RETRY_DELAY_BEFORE_ATTEMPT_4_SECONDS:-120}"
    delay_before_attempt_5="${RETRY_DELAY_BEFORE_ATTEMPT_5_SECONDS:-300}"

    case "$failed_attempt" in
        1) echo "$initial_delay" ;;
        2) echo "$((initial_delay * 2))" ;;
        3) echo "$delay_before_attempt_4" ;;
        4) echo "$delay_before_attempt_5" ;;
        *) echo "$delay_before_attempt_5" ;;
    esac
}

switch_disbursement_credentials_if_needed() {
    attempt="$1"
    if [ -z "${USE_DISBURSEMENT_CREDENTIALS:-}" ] || [ "$attempt" -lt 3 ] || [ "$attempt" -gt 5 ]; then
        return 0
    fi

    echo "Attempt $attempt: switching to disbursement credentials (retries 3-5)..."
    export MERCHANT_ID="${DISBURSEMENT_MERCHANT_ID}"
    export X_PARTNER_ID="${DISBURSEMENT_X_PARTNER_ID}"
    export PRIVATE_KEY="${DISBURSEMENT_PRIVATE_KEY}"
    export CLIENT_SECRET="${DISBURSEMENT_CLIENT_SECRET}"

    if [ -f ".env" ]; then
        printf 'MERCHANT_ID=%s\n' "${DISBURSEMENT_MERCHANT_ID}" > .env
        printf 'X_PARTNER_ID=%s\n' "${DISBURSEMENT_X_PARTNER_ID}" >> .env
        printf 'CHANNEL_ID=%s\n' "${CHANNEL_ID}" >> .env
        printf "PRIVATE_KEY='%s'\n" "${DISBURSEMENT_PRIVATE_KEY}" >> .env
        printf 'ORIGIN=%s\n' "${ORIGIN}" >> .env
        printf "CLIENT_SECRET='%s'\n" "${DISBURSEMENT_CLIENT_SECRET}" >> .env
        printf "REDIRECT_URL_OAUTH='%s'\n" "${REDIRECT_URL_OAUTH}" >> .env
        printf "EXTERNAL_SHOP_ID='%s'\n" "${EXTERNAL_SHOP_ID}" >> .env
    fi
}

extract_failed_pytest_tests_from_junit() {
    junit_file="$1"
    "$PYTHON_CMD" - "$junit_file" <<'PY'
import sys
import xml.etree.ElementTree as ET

path = sys.argv[1]
try:
    root = ET.parse(path).getroot()
except ET.ParseError:
    sys.exit(2)

names = []
for testcase in root.iter("testcase"):
    if testcase.find("failure") is not None or testcase.find("error") is not None:
        name = (testcase.get("name") or "").strip()
        if name:
            names.append(name)

if not names:
    sys.exit(2)

print(" or ".join(names))
PY
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

run_pytest_once() {
    k_pattern="$1"
    shift
    junit_file="$1"
    shift

    if [ -n "$k_pattern" ]; then
        $PYTHON_CMD -m pytest -v -s --junitxml="$junit_file" "$@" -k "$k_pattern"
    else
        $PYTHON_CMD -m pytest -v -s --junitxml="$junit_file" "$@"
    fi
}

# CI: attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_pytest_cmd() {
    k_pattern="$1"
    shift

    if [ "$(resolve_python_retry_failed_only)" != "true" ]; then
        set +e
        if [ -n "$k_pattern" ]; then
            $PYTHON_CMD -m pytest -v -s "$@" -k "$k_pattern"
        else
            $PYTHON_CMD -m pytest -v -s "$@"
        fi
        exit_code=$?
        set -e
        if [ "$exit_code" -eq 5 ]; then
            echo "\033[31mERROR: No tests were collected\033[0m" >&2
            exit 1
        fi
        return "$exit_code"
    fi

    max_attempts="${RETRY_MAX_ATTEMPTS:-5}"
    attempt=1
    current_k_pattern="$k_pattern"
    last_exit_code=1
    junit_file=""

    while [ "$attempt" -le "$max_attempts" ]; do
        switch_disbursement_credentials_if_needed "$attempt"

        junit_file=$(mktemp "${TMPDIR:-/tmp}/pytest-results.XXXXXX.xml")
        if [ "$attempt" -eq 1 ]; then
            echo "Pytest attempt 1/$max_attempts (full suite) for $*"
        else
            echo "Pytest attempt $attempt/$max_attempts (failed tests only) for $*"
        fi
        if [ -n "$current_k_pattern" ]; then
            echo "Filter: $current_k_pattern"
        fi

        set +e
        run_pytest_once "$current_k_pattern" "$junit_file" "$@"
        last_exit_code=$?
        set -e

        if [ "$last_exit_code" -eq 0 ]; then
            rm -f "$junit_file"
            return 0
        fi
        if [ "$last_exit_code" -eq 5 ]; then
            rm -f "$junit_file"
            echo "\033[31mERROR: No tests were collected\033[0m" >&2
            return 1
        fi

        if [ "$attempt" -eq "$max_attempts" ]; then
            rm -f "$junit_file"
            return "$last_exit_code"
        fi

        failed_k_pattern=""
        failed_k_pattern=$(extract_failed_pytest_tests_from_junit "$junit_file") || failed_k_pattern=""
        rm -f "$junit_file"

        if [ -z "$failed_k_pattern" ]; then
            echo "Could not determine failed tests for retry; stopping."
            return "$last_exit_code"
        fi

        echo "Retrying failed/error tests only: $failed_k_pattern"
        current_k_pattern="$failed_k_pattern"

        sleep_delay=$(python_retry_sleep_seconds "$attempt")
        echo "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    return "$last_exit_code"
}

run_pytest_checked() {
    k_pattern="$1"
    shift
    run_pytest_cmd "$k_pattern" "$@"
    return $?
}

setup_python_env() {
    needs_playwright="$1"

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
    # Ensure at least the required SDK version is used.
    $PYTHON_CMD -m pip install --upgrade "dana-python>=2.1.7"

    if [ "$needs_playwright" = "true" ]; then
        $PYTHON_CMD -m playwright install --with-deps chromium
    else
        echo "Skipping Playwright browser install for local mandatory-only run."
    fi
    export PYTHONPATH=$PYTHONPATH:$(pwd)/test/python:$(pwd)/runner/python
}

run_single_folder() {
    folder_name="$1"
    case_name="$2"
    run_pattern="$3"
    mandatory_only="$4"

    test_path="test/python/$folder_name"
    if [ ! -d "$test_path" ]; then
        echo "\033[31mERROR: Folder not found: $test_path\033[0m" >&2
        exit 1
    fi

    if [ -n "$run_pattern" ]; then
        run_pytest_checked "$(pattern_for_pytest_k "$run_pattern")" "$test_path"
        exit $?
    fi

    if [ -n "$case_name" ]; then
        run_pytest_checked "$case_name" "$test_path"
        exit $?
    fi

    mandatory_pattern=$(get_mandatory_pattern_for_folder "$folder_name")
    if [ "$mandatory_only" = "true" ] && [ -n "$mandatory_pattern" ]; then
        echo "Non-CI mode: running mandatory $folder_name tests only"
        run_pytest_checked "$(pattern_for_pytest_k "$mandatory_pattern")" "$test_path"
        exit $?
    fi

    run_pytest_checked "" "$test_path"
    exit $?
}

run_all_folders() {
    mandatory_only="$1"
    test_root="test/python"

    folders=$(ls -d "$test_root"/*/ 2>/dev/null || true)
    if [ -z "$folders" ]; then
        echo "\033[31mERROR: No Python test folders found\033[0m" >&2
        exit 1
    fi

    total_passed=0
    total_failed=0
    total_folders=0
    failed_folders=""

    for folder in $folders; do
        folder_name=$(basename "$folder")
        mandatory_pattern=$(get_mandatory_pattern_for_folder "$folder_name")
        echo "=== Running tests in $folder_name ==="

        if [ "$mandatory_only" = "true" ] && [ -n "$mandatory_pattern" ]; then
            echo "Non-CI mode: running mandatory $folder_name tests only"
            if run_pytest_checked "$(pattern_for_pytest_k "$mandatory_pattern")" "$folder"; then
                echo "✅ $folder_name tests PASSED"
                total_passed=$((total_passed + 1))
            else
                echo "❌ $folder_name tests FAILED"
                total_failed=$((total_failed + 1))
                failed_folders="$failed_folders $folder_name"
            fi
        else
            if run_pytest_checked "" "$folder"; then
                echo "✅ $folder_name tests PASSED"
                total_passed=$((total_passed + 1))
            else
                echo "❌ $folder_name tests FAILED"
                total_failed=$((total_failed + 1))
                failed_folders="$failed_folders $folder_name"
            fi
        fi

        total_folders=$((total_folders + 1))
        echo ""
    done

    echo "=== Overall Python Test Results Summary ==="
    echo "Total folders: $total_folders"
    echo "Passed: $total_passed"
    echo "Failed: $total_failed"

    if [ "$total_failed" -gt 0 ]; then
        echo ""
        echo "Failed folders:"
        for folder in $failed_folders; do
            echo "  - $folder"
        done
        echo ""
        echo "❌ Some tests failed"
        exit 1
    fi

    echo ""
    echo "✅ All tests passed!"
}

run_python_runner() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"

    PYTHON_CMD=$(resolve_python_cmd)
    if [ -z "$PYTHON_CMD" ]; then
        echo "Python not available in this system. Please install Python 3."
        exit 1
    fi

    mandatory_only=$(resolve_mandatory_only)

    # CI unchanged: always install Playwright. Local + mandatory-only: mirror Node (skip unless scoped to browser tests).
    needs_playwright=true
    if [ -z "${CI:-}" ] && [ "$mandatory_only" = "true" ]; then
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
    fi

    setup_python_env "$needs_playwright"

    if [ -n "$folderName" ]; then
        run_single_folder "$folderName" "$caseName" "$runPattern" "$mandatory_only"
        exit $?
    fi

    # Fallbacks when folder is not provided.
    if [ -n "$runPattern" ]; then
        run_pytest_checked "$(pattern_for_pytest_k "$runPattern")"
        exit $?
    fi
    if [ -n "$caseName" ]; then
        run_pytest_checked "$caseName"
        exit $?
    fi

    run_all_folders "$mandatory_only"
}

# Always execute the runner
run_python_runner "$@"
