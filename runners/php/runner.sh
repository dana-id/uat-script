run_php_tests_in_path() {
    test_path="$1"
    filter="$2"
    php_run_phpunit "$test_path" "$filter" "$PHPUNIT_BIN" "$PHPUNIT_CONFIG"
}

run_php_runner_main() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"

    setup_php_runner "$folderName" "$caseName" "$runPattern"

    if [ -n "$runPattern" ]; then
        if [ -n "$folderName" ]; then
            echo "Running PHP tests matching '$runPattern' in folder 'test/php/$folderName'..."
            run_php_tests_in_path "$PHP_TEST_DIR/$folderName" "$runPattern"
        else
            echo "Running PHP tests matching '$runPattern' in all folders..."
            TEST_DIRS=$(find "$PHP_TEST_DIR" -type d -mindepth 1 -maxdepth 1 -not -path "*/helper" -not -path "*/vendor")
            for dir in $TEST_DIRS; do
                echo "Running tests matching '$runPattern' in $dir..."
                run_php_tests_in_path "$dir" "$runPattern"
                sleep 3
            done
        fi
    elif [ -n "$folderName" ] && [ -n "$caseName" ]; then
        echo "Running test '$caseName' in folder 'test/php/$folderName'..."
        run_php_tests_in_path "$PHP_TEST_DIR/$folderName" "^.*\\\\$caseName.*$"
    elif [ -n "$folderName" ]; then
        echo "Running all tests in folder 'test/php/$folderName'..."
        mandatory_pattern=$(get_mandatory_pattern_for_folder "$folderName")
        if [ "$PHP_MANDATORY_ONLY" = "true" ] && [ -n "$mandatory_pattern" ]; then
            echo "Running mandatory $folderName tests only"
            run_php_tests_in_path "$PHP_TEST_DIR/$folderName" "$mandatory_pattern"
        else
            run_php_tests_in_path "$PHP_TEST_DIR/$folderName" ""
        fi
    elif [ -n "$caseName" ]; then
        TEST_DIRS=$(find "$PHP_TEST_DIR" -type d -mindepth 1 -maxdepth 1 -not -path "*/helper" -not -path "*/vendor")
        if [ -z "$TEST_DIRS" ]; then
            echo "ERROR: No test directories found under test/php" >&2
            exit 1
        fi
        for dir in $TEST_DIRS; do
            echo "Running test '$caseName' in $dir..."
            run_php_tests_in_path "$dir" "^.*\\\\$caseName.*$"
            sleep 3
        done
    else
        echo "Running all PHP tests..."
        TEST_DIRS=$(find "$PHP_TEST_DIR" -type d -mindepth 1 -maxdepth 1 -not -path "*/helper" -not -path "*/vendor")
        if [ -z "$TEST_DIRS" ]; then
            echo "ERROR: No test directories found under test/php" >&2
            exit 1
        fi
        for dir in $TEST_DIRS; do
            echo "Running tests in $dir..."
            if [ "$PHP_MANDATORY_ONLY" = "true" ]; then
                folder_name=$(basename "$dir")
                mandatory_pattern=$(get_mandatory_pattern_for_folder "$folder_name")
                if [ -n "$mandatory_pattern" ]; then
                    echo "Running mandatory $folder_name tests only"
                    run_php_tests_in_path "$dir" "$mandatory_pattern"
                else
                    run_php_tests_in_path "$dir" ""
                fi
            else
                run_php_tests_in_path "$dir" ""
            fi
            sleep 3
        done
    fi
}
