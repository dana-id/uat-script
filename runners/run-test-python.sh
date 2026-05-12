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

pattern_for_pytest_k() {
    echo "$1" | sed 's/|/ or /g'
}

get_mandatory_pattern_for_folder() {
    folder_name="$1"
    case "$folder_name" in
        "payment_gateway")
            echo "test_create_order_redirect_scenario|test_create_order_invalid_field_format|test_create_order_inconsistent_request|test_create_order_invalid_mandatory_field|test_create_order_unauthorized"
            ;;
        "widget")
            echo "test_payment_success|test_payment_fail_missing_or_invalid_mandatory_field|test_payment_fail_general_error|test_payment_fail_not_permitted|test_payment_fail_merchant_not_exist_or_status_abnormal|test_payment_fail_inconsistent_request|test_payment_fail_internal_server_error|test_payment_fail_invalid_format|test_payment_fail_invalid_signature|test_payment_fail_exceed_amount_limit"
            ;;
        "disbursement")
            echo "test_topup_customer_valid|test_topup_customer_insufficient_fund|test_topup_customer_frozen_account|test_topup_customer_missing_mandatory_field|test_topup_customer_inconsistent_request|test_topup_customer_internal_server_error|test_topup_customer_internal_general_error|test_disbursement_to_bank_valid|test_disbursement_to_bank_valid_account_in_progress|test_disbursement_to_bank_inconsistent_request|test_disbursement_to_bank_insufficient_fund|test_disbursement_to_bank_inactive_account|test_disbursement_to_bank_invalid_field_format|test_disbursement_to_bank_missing_mandatory_field"
            ;;
        *)
            echo ""
            ;;
    esac
}

run_pytest_checked() {
    k_pattern="$1"
    shift
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
    $PYTHON_CMD -m pip install --upgrade "dana-python>=2.1.5"

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
