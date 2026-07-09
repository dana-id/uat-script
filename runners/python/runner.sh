run_pytest_checked() {
    k_pattern="$1"
    retry_on_failure="${2:-false}"
    shift 2
    python_run_pytest "$k_pattern" "$retry_on_failure" "$@"
    return $?
}

run_single_folder() {
    folder_name="$1"
    case_name="$2"
    run_pattern="$3"

    test_path="test/python/$folder_name"
    if [ ! -d "$test_path" ]; then
        echo "ERROR: Folder not found: $test_path" >&2
        exit 1
    fi

    if [ -n "$run_pattern" ]; then
        run_pytest_checked "$(pattern_for_pytest_k "$run_pattern")" "false" "$test_path"
        exit $?
    fi

    if [ -n "$case_name" ]; then
        run_pytest_checked "$case_name" "false" "$test_path"
        exit $?
    fi

    mandatory_pattern=$(get_mandatory_pattern_for_folder "$folder_name")
    if [ "$PYTHON_MANDATORY_ONLY" = "true" ] && [ -n "$mandatory_pattern" ]; then
        echo "Running mandatory $folder_name tests only"
        run_pytest_checked "$(pattern_for_pytest_k "$mandatory_pattern")" "true" "$test_path"
        exit $?
    fi

    run_pytest_checked "" "false" "$test_path"
    exit $?
}

run_all_folders() {
    test_root="test/python"

    folders=$(ls -d "$test_root"/*/ 2>/dev/null || true)
    if [ -z "$folders" ]; then
        echo "ERROR: No Python test folders found" >&2
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

        if [ "$PYTHON_MANDATORY_ONLY" = "true" ] && [ -n "$mandatory_pattern" ]; then
            echo "Running mandatory $folder_name tests only"
            if run_pytest_checked "$(pattern_for_pytest_k "$mandatory_pattern")" "true" "$folder"; then
                echo "✅ $folder_name tests PASSED"
                total_passed=$((total_passed + 1))
            else
                echo "❌ $folder_name tests FAILED"
                total_failed=$((total_failed + 1))
                failed_folders="$failed_folders $folder_name"
            fi
        elif run_pytest_checked "" "false" "$folder"; then
            echo "✅ $folder_name tests PASSED"
            total_passed=$((total_passed + 1))
        else
            echo "❌ $folder_name tests FAILED"
            total_failed=$((total_failed + 1))
            failed_folders="$failed_folders $folder_name"
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

run_python_runner_main() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"

    PYTHON_CMD=$(resolve_python_cmd)
    if [ -z "$PYTHON_CMD" ]; then
        echo "Python not available in this system. Please install Python 3."
        exit 1
    fi

    needs_playwright=$(resolve_needs_playwright "$folderName" "$caseName" "$runPattern")
    setup_python_env "$needs_playwright"

    if [ -n "$folderName" ]; then
        run_single_folder "$folderName" "$caseName" "$runPattern"
        exit $?
    fi

    if [ -n "$runPattern" ]; then
        run_pytest_checked "$(pattern_for_pytest_k "$runPattern")" "false"
        exit $?
    fi
    if [ -n "$caseName" ]; then
        run_pytest_checked "$caseName" "false"
        exit $?
    fi

    run_all_folders
}
