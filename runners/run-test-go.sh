#!/bin/sh

# Fail on error
set -e

has_test_files() {
    dir="$1"
    [ -d "$dir" ] && find "$dir" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .
}

resolve_mandatory_only() {
    if [ -n "${GO_MANDATORY_ONLY:-}" ]; then
        echo "$GO_MANDATORY_ONLY"
    elif [ -z "${CI:-}" ]; then
        echo "true"
    else
        echo "false"
    fi
}

resolve_go_retry_failed_only() {
    if [ "${GO_RETRY_FAILED_ONLY:-}" = "false" ]; then
        echo "false"
    elif [ "${GO_RETRY_FAILED_ONLY:-}" = "true" ]; then
        echo "true"
    elif [ -n "${CI:-}" ] || [ -n "${GITLAB_CI:-}" ]; then
        echo "true"
    else
        echo "false"
    fi
}

go_retry_sleep_seconds() {
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
}

extract_failed_go_tests_from_json() {
    json_log="$1"
    local failed result

    if [ ! -f "$json_log" ]; then
        return 2
    fi

    if ! command -v jq >/dev/null 2>&1; then
        return 2
    fi

    failed=$(jq -r 'select(.Action=="fail") | .Test' "$json_log" 2>/dev/null | sort -u)
    if [ -z "$failed" ]; then
        return 2
    fi

    result=$(printf '%s\n' "$failed" | paste -sd'|' -)
    if [ -z "$result" ]; then
        return 2
    fi

    printf '%s\n' "$result"
}

run_go_test_once() {
    package_path="$1"
    timeout="$2"
    run_pattern="$3"
    json_log="$4"

    if [ -n "$run_pattern" ]; then
        go test -json -v -timeout="$timeout" -run "$run_pattern" "$package_path" > "$json_log" 2>&1
    else
        go test -json -v -timeout="$timeout" "$package_path" > "$json_log" 2>&1
    fi
}

# CI: attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_go_test_cmd() {
    package_path="$1"
    timeout="$2"
    initial_run_pattern="${3:-}"

    if [ "$(resolve_go_retry_failed_only)" != "true" ]; then
        set +e
        if [ -n "$initial_run_pattern" ]; then
            go test -v -timeout="$timeout" -run "$initial_run_pattern" "$package_path" 2>&1
        else
            go test -v -timeout="$timeout" "$package_path" 2>&1
        fi
        exit_code=$?
        set -e
        return "$exit_code"
    fi

    max_attempts="${RETRY_MAX_ATTEMPTS:-5}"
    attempt=1
    current_run_pattern="$initial_run_pattern"
    last_exit_code=1
    json_log=""

    while [ "$attempt" -le "$max_attempts" ]; do
        switch_disbursement_credentials_if_needed "$attempt"

        json_log=$(mktemp "${TMPDIR:-/tmp}/go-test-results.XXXXXX.jsonl")
        if [ "$attempt" -eq 1 ]; then
            echo "Go test attempt 1/$max_attempts (full suite) for $package_path"
        else
            echo "Go test attempt $attempt/$max_attempts (failed tests only) for $package_path"
        fi
        if [ -n "$current_run_pattern" ]; then
            echo "Filter: $current_run_pattern"
        fi

        set +e
        run_go_test_once "$package_path" "$timeout" "$current_run_pattern" "$json_log"
        last_exit_code=$?
        set -e
        cat "$json_log"

        if [ "$last_exit_code" -eq 0 ]; then
            rm -f "$json_log"
            return 0
        fi

        if [ "$attempt" -eq "$max_attempts" ]; then
            rm -f "$json_log"
            return "$last_exit_code"
        fi

        failed_run_pattern=""
        failed_run_pattern=$(extract_failed_go_tests_from_json "$json_log") || failed_run_pattern=""
        rm -f "$json_log"

        if [ -z "$failed_run_pattern" ]; then
            echo "Could not determine failed tests for retry; stopping."
            return "$last_exit_code"
        fi

        echo "Retrying failed/error tests only: $failed_run_pattern"
        current_run_pattern="$failed_run_pattern"

        sleep_delay=$(go_retry_sleep_seconds "$attempt")
        echo "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    return "$last_exit_code"
}

run_go_test_package() {
    package_path="$1"
    timeout="$2"
    run_pattern="$3"
    run_go_test_cmd "$package_path" "$timeout" "$run_pattern"
}

get_mandatory_pattern_for_module() {
    module_name="$1"
    case "$module_name" in
        "payment_gateway")
            echo 'TestCreateOrderRedirectScenario|TestCreateOrderInvalidFieldFormat|TestCreateOrderInconsistentRequest|TestCreateOrderInvalidMandatoryField|TestCreateOrderUnauthorized|TestTransactionSuccessNotify|TestInternalServerErrorNotify|TestExpiredNotify'
            ;;
        "widget")
            # Mandatory widget payment-host-to-host scenarios from devsite checklist.
            echo 'TestPaymentSuccess|TestPaymentFailMissingOrInvalidMandatoryField|TestPaymentFailGeneralError|TestPaymentFailTransactionNotPermitted|TestPaymentFailMerchantNotExistOrStatusAbnormal|TestPaymentFailInconsistentRequest|TestPaymentFailInternalServerError|TestPaymentFailInvalidFormat|TestPaymentFailInvalidSignature|TestPaymentFailExceedsTransactionAmountLimit|TestTransactionSuccessNotify|TestInternalServerErrorNotify|TestExpiredNotify'
            ;;
        "disbursement")
            # Mandatory disbursement scenarios (topup + transfer-bank) from devsite checklist.
            echo 'TestTopUpCustomerValid|TestTopUpCustomerInsufficientFund|TestTopUpCustomerFrozenAccount|TestTopUpCustomerMissingMandatoryField|TestTopUpCustomerInconsistentRequest|TestTopUpCustomerInternalServerError|TestTopUpCustomerInternalGeneralError|TestDisbursementBankValidAccount|TestDisbursementBankValidAccountInProgress|TestDisbursementBankInconsistentRequest|TestDisbursementBankInsufficientFund|TestDisbursementBankInactiveAccount|TestDisbursementBankInvalidFieldFormat|TestDisbursementBankMissingMandatoryField|TestTransactionSuccessNotify|TestInternalServerErrorNotify|TestExpiredNotify'
            ;;
        *)
            echo ""
            ;;
    esac
}

load_env_if_exists() {
    if [ -f "../.env" ]; then
        set -a
        . ../.env
        set +a
        echo "Environment variables loaded from .env file"
    elif [ -f "../../.env" ]; then
        set -a
        . ../../.env
        set +a
        echo "Environment variables loaded from .env file"
    fi
}

prepare_go_deps() {
    echo "Updating Go dependencies..."
    go get -u github.com/dana-id/dana-go/v2 > /dev/null 2>&1 || true
    go get github.com/mxschmitt/playwright-go@v0.6100.0 > /dev/null 2>&1 || true
    go mod tidy > /dev/null 2>&1
    go clean -testcache > /dev/null 2>&1
}

run_module_files_individually() {
    test_dir="$1"
    sub_case="$2"

    if [ -n "$sub_case" ]; then
        if echo "$sub_case" | grep -q "_test$"; then
            found_files=$(find "$test_dir" -type f -name "${sub_case}.go" 2>/dev/null || true)
        else
            found_files=$(find "$test_dir" -type f -name "*${sub_case}*_test.go" 2>/dev/null || true)
        fi
    else
        found_files=$(find "$test_dir" -type f -name "*_test.go" 2>/dev/null || true)
    fi

    if [ -z "$found_files" ]; then
        echo "ERROR: No Go test files were found in directory: $test_dir" >&2
        exit 1
    fi

    echo "Test files to run:"
    for f in $found_files; do
        echo "  - $f"
    done
    echo ""

    total_passed=0
    total_failed=0
    total_tests=0
    failed_files=""

    for test_file in $found_files; do
        test_name=$(basename "$test_file" .go)
        echo "=== Running $test_name ==="

        if run_go_test_cmd "$test_file" "600s" ""; then
            echo "✅ $test_name PASSED"
            total_passed=$((total_passed + 1))
        else
            echo "❌ $test_name FAILED"
            total_failed=$((total_failed + 1))
            failed_files="$failed_files $test_name"
        fi
        total_tests=$((total_tests + 1))
        echo ""
    done

    echo "=== Test Results Summary ==="
    echo "Total test files: $total_tests"
    echo "Passed: $total_passed"
    echo "Failed: $total_failed"

    if [ "$total_failed" -gt 0 ]; then
        echo ""
        echo "Failed test files:"
        for file in $failed_files; do
            echo "  - $file"
        done
        echo ""
        echo "❌ Some tests failed"
        exit 1
    fi

    echo ""
    echo "✅ All tests passed!"
}

run_single_module() {
    module="$1"
    sub_case="$2"
    run_pattern="$3"
    mandatory_only="$4"
    mandatory_pattern="$5"

    echo "Running Go test file(s) for module: $module ${sub_case:-} ${run_pattern:-}"
    test_dir="./$module"

    if ! has_test_files "$test_dir"; then
        echo "ERROR: Directory '$module' not found or contains no test files" >&2
        exit 1
    fi

    if [ -n "$run_pattern" ]; then
        if run_go_test_package "$test_dir" "600s" "$run_pattern"; then
            echo "✅ Tests passed"
            exit 0
        fi
        echo "❌ Tests failed"
        exit 1
    fi

    if [ "$mandatory_only" = "true" ] && [ -z "$sub_case" ] && [ -n "$mandatory_pattern" ]; then
        echo "Non-CI mode: running mandatory $module tests only"
        if run_go_test_package "$test_dir" "600s" "$mandatory_pattern"; then
            echo "✅ Tests passed"
            exit 0
        fi
        echo "❌ Tests failed"
        exit 1
    fi

    run_module_files_individually "$test_dir" "$sub_case"
}

run_all_modules() {
    mandatory_only="$1"

    test_dirs=""
    for dir in */; do
        dir_name=$(basename "$dir")
        if has_test_files "$dir_name"; then
            test_dirs="$test_dirs ./$dir_name"
        fi
    done

    if [ -z "$test_dirs" ]; then
        echo "ERROR: No test directories found"
        exit 1
    fi

    echo "Running all Go tests from directories: $(echo "$test_dirs" | sed 's|./||g')..."
    echo ""

    total_passed=0
    total_failed=0
    total_dirs=0
    failed_dirs=""

    for test_dir in $test_dirs; do
        dir_name=$(basename "$test_dir")
        echo "=== Running tests in $dir_name ==="
        module_mandatory_pattern=$(get_mandatory_pattern_for_module "$dir_name")

        if [ "$mandatory_only" = "true" ] && [ -n "$module_mandatory_pattern" ]; then
            echo "Non-CI mode: running mandatory $dir_name tests only"
            if run_go_test_package "$test_dir" "60s" "$module_mandatory_pattern"; then
                echo "✅ $dir_name tests PASSED"
                total_passed=$((total_passed + 1))
            else
                echo "❌ $dir_name tests FAILED"
                total_failed=$((total_failed + 1))
                failed_dirs="$failed_dirs $dir_name"
            fi
        elif run_go_test_package "$test_dir/..." "60s" ""; then
            echo "✅ $dir_name tests PASSED"
            total_passed=$((total_passed + 1))
        else
            echo "❌ $dir_name tests FAILED"
            total_failed=$((total_failed + 1))
            failed_dirs="$failed_dirs $dir_name"
        fi

        total_dirs=$((total_dirs + 1))
        echo ""
    done

    echo "=== Overall Test Results Summary ==="
    echo "Total directories: $total_dirs"
    echo "Passed: $total_passed"
    echo "Failed: $total_failed"

    if [ "$total_failed" -gt 0 ]; then
        echo ""
        echo "Failed directories:"
        for dir in $failed_dirs; do
            echo "  - $dir"
        done
        echo ""
        echo "❌ Some tests failed"
        exit 1
    fi

    echo ""
    echo "✅ All tests passed!"
}

run_go_runner() {
    # run-test.sh calls: sh run-test-go.sh "$2" "$3" "$4"
    module="$1"
    subCase="$2"
    runPattern="$3"

    mandatory_only=$(resolve_mandatory_only)

    if ! command -v go > /dev/null 2>&1; then
        echo "ERROR: Go not available in this system. Please install Go."
        exit 1
    fi

    echo "Running Go tests..."
    go version

    load_env_if_exists
    cd test/go

    if [ ! -f "go.mod" ]; then
        echo "Error: go.mod file not found in test/go directory"
        exit 1
    fi

    prepare_go_deps

    if [ -n "$module" ]; then
        mandatory_pattern=$(get_mandatory_pattern_for_module "$module")
        run_single_module "$module" "$subCase" "$runPattern" "$mandatory_only" "$mandatory_pattern"
    else
        run_all_modules "$mandatory_only"
    fi
}

# Always execute the runner
run_go_runner "$@"