run_go_test_package() {
    package_path="$1"
    timeout="$2"
    run_pattern="$3"
    retry_on_failure="${4:-false}"
    go_run_tests "$package_path" "$timeout" "$run_pattern" "$retry_on_failure"
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

        if run_go_test_package "$test_file" "600s" ""; then
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
    mandatory_pattern="$4"

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

    if [ "$GO_MANDATORY_ONLY" = "true" ] && [ -z "$sub_case" ] && [ -n "$mandatory_pattern" ]; then
        echo "Running mandatory $module tests only"
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

        if [ "$GO_MANDATORY_ONLY" = "true" ] && [ -n "$module_mandatory_pattern" ]; then
            echo "Running mandatory $dir_name tests only"
            if run_go_test_package "$test_dir" "60s" "$module_mandatory_pattern" "true"; then
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

run_go_runner_main() {
    module="$1"
    sub_case="$2"
    run_pattern="$3"

    setup_go_runner

    if [ -n "$module" ]; then
        mandatory_pattern=$(get_mandatory_pattern_for_module "$module")
        run_single_module "$module" "$sub_case" "$run_pattern" "$mandatory_pattern"
    else
        run_all_modules
    fi
}
