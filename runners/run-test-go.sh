#!/bin/sh

# Fail on error
set -e

has_test_files() {
    dir="$1"
    [ -d "$dir" ] && find "$dir" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .
}

run_go_test_package() {
    package_path="$1"
    timeout="$2"
    run_pattern="$3"

    if [ -n "$run_pattern" ]; then
        echo "Running tests matching -run: $run_pattern"
        go test -v -timeout="$timeout" -run "$run_pattern" "$package_path" 2>&1
    else
        go test -v -timeout="$timeout" "$package_path" 2>&1
    fi
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
    go get -u github.com/playwright-community/playwright-go > /dev/null 2>&1 || true
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

        if go test -v -timeout=600s "$test_file" 2>&1; then
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
    mandatory_payment_gateway_pattern="$5"

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

    if [ "$mandatory_only" = "true" ] && [ "$module" = "payment_gateway" ] && [ -z "$sub_case" ]; then
        echo "Non-CI mode: running mandatory payment_gateway tests only"
        if run_go_test_package "$test_dir" "600s" "$mandatory_payment_gateway_pattern"; then
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
    mandatory_payment_gateway_pattern="$2"

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

        if [ "$mandatory_only" = "true" ] && [ "$dir_name" = "payment_gateway" ]; then
            echo "Non-CI mode: running mandatory payment_gateway tests only"
            if run_go_test_package "$test_dir" "60s" "$mandatory_payment_gateway_pattern"; then
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
    mandatory_payment_gateway_pattern='TestCreateOrderRedirectScenario|TestCreateOrderInvalidFieldFormat|TestCreateOrderInconsistentRequest|TestCreateOrderInvalidMandatoryField|TestCreateOrderUnauthorized|TestQueryPaymentCreatedOrder|TestQueryPaymentPaidOrder|TestQueryPaymentCanceledOrder|TestQueryPaymentTransactionNotFound|TestQueryPaymentInvalidMandatoryField|TestQueryPaymentGeneralError|TestQueryPaymentUnauthorized'

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
        run_single_module "$module" "$subCase" "$runPattern" "$mandatory_only" "$mandatory_payment_gateway_pattern"
    else
        run_all_modules "$mandatory_only" "$mandatory_payment_gateway_pattern"
    fi
}

# Always execute the runner
run_go_runner "$@"
