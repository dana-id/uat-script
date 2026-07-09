run_specific_test() {
    local module="$1"
    local test_name="$2"
    local method_name="${3:-}"

    local actual_module
    actual_module=$(find_module "$module")
    if [ -z "$actual_module" ]; then
        print_error "Module '$module' not found"
        local business_modules
        business_modules=$(discover_business_modules)
        echo "Available modules: $(echo "$business_modules" | tr ' ' ', ')"
        exit 1
    fi

    local module_display
    module_display=$(get_module_display_name "$actual_module")
    print_header "Running $module_display Test: $test_name${method_name:+#$method_name}"

    local test_files
    test_files=$(find_test_files "$actual_module" "$test_name")
    if [ -z "$test_files" ]; then
        print_error "Test '$test_name' not found in $module_display"
        echo
        echo "Available tests:"
        find_test_files "$actual_module" "" | sed 's|.*/||' | sed 's|\.java$||' | sed 's/^/  /'
        exit 1
    fi

    echo "Found test files:"
    echo "$test_files" | sed 's/^/   /'
    echo

    cd "$JAVA_TEST_DIR"
    clear_surefire_reports

    local test_class test_arg method_arg
    test_class=$(echo "$test_files" | head -1 | sed "s|$JAVA_TEST_DIR/src/test/java/||" | sed 's|/|.|g' | sed 's|\.java$||')
    test_arg="$test_class"
    if [ -n "$method_name" ]; then
        method_arg=$(echo "$method_name" | tr '|' '+')
        test_arg="$test_class#$method_arg"
    fi
    print_info "Executing: $test_arg"
    echo

    if ! run_mvn -q -DskipTests clean test-compile; then
        print_warning "Fresh test compilation failed"
    fi

    if java_run_mvn_test_cmd "$test_arg"; then
        print_success "Test execution completed successfully!"
    else
        print_warning "Test execution completed with issues"
    fi

    cd - > /dev/null

    local simple_class_name
    simple_class_name=$(echo "$test_class" | sed 's/.*\.//')
    show_test_results "$simple_class_name" || exit 1
}

run_module_tests() {
    local module="$1"
    local run_pattern="${2:-}"

    local actual_module
    actual_module=$(find_module "$module")
    if [ -z "$actual_module" ]; then
        print_error "Module '$module' not found"
        local business_modules
        business_modules=$(discover_business_modules)
        echo "Available modules: $(echo "$business_modules" | tr ' ' ', ')"
        exit 1
    fi

    local module_display
    module_display=$(get_module_display_name "$actual_module")
    if [ -n "$run_pattern" ]; then
        print_header "Running Tests in $module_display (filter: $run_pattern)"
    else
        print_header "Running All Tests in $module_display"
    fi

    cd "$JAVA_TEST_DIR"
    clear_surefire_reports

    local test_arg module_mandatory_pattern class_list count test_count retry_on_failure="false"
    module_mandatory_pattern=$(get_mandatory_pattern_for_module "$actual_module")
    if [ -z "$run_pattern" ] && [ "$JAVA_MANDATORY_ONLY" = "true" ] && [ -n "$module_mandatory_pattern" ]; then
        test_arg="$module_mandatory_pattern"
        retry_on_failure="true"
        print_info "Running mandatory $module_display tests only"
    elif [ -n "$run_pattern" ]; then
        class_list=$(echo "$run_pattern" | tr '|' '\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | grep -v '^$')
        test_arg=$(echo "$class_list" | sed "s|^|id.dana.$actual_module.|" | tr '\n' ',' | sed 's/,$//')
        count=$(echo "$class_list" | wc -l | tr -d ' ')
        print_info "Running $count test class(es): $run_pattern"
    else
        test_arg="id.dana.$actual_module.*Test"
        test_count=$(find_test_files "$actual_module" "" | wc -l | tr -d ' ')
        print_info "Found $test_count test file(s) in $module_display"
    fi
    echo

    print_info "Executing tests..."
    echo

    if java_run_mvn_test_cmd "$test_arg" "$retry_on_failure"; then
        print_success "Module test execution completed successfully!"
    else
        print_warning "Module test execution completed with issues"
    fi

    cd - > /dev/null
    show_test_results || exit 1
}

run_all_tests() {
    print_header "Running All Tests Across All Modules"

    cd "$JAVA_TEST_DIR"
    clear_surefire_reports

    local business_modules total_tests module count module_display mandatory_classes module_pattern
    business_modules=$(discover_business_modules)
    total_tests=0
    print_info "Scanning modules for tests:"
    for module in $business_modules; do
        count=$(find_test_files "$module" "" | wc -l | tr -d ' ')
        module_display=$(get_module_display_name "$module")
        echo "   $module_display: $count test(s)"
        total_tests=$((total_tests + count))
    done

    echo
    print_info "Total tests to execute: $total_tests"
    echo
    print_info "Executing all tests..."
    echo

    if [ "$JAVA_MANDATORY_ONLY" = "true" ]; then
        print_info "Running mandatory tests per module only"
        mandatory_classes=""
        for module in $business_modules; do
            module_pattern=$(get_mandatory_pattern_for_module "$module")
            if [ -n "$module_pattern" ]; then
                if [ -n "$mandatory_classes" ]; then
                    mandatory_classes="$mandatory_classes,$module_pattern"
                else
                    mandatory_classes="$module_pattern"
                fi
            fi
        done
        if [ -n "$mandatory_classes" ] && java_run_mvn_test_cmd "$mandatory_classes" "true"; then
            print_success "Mandatory tests execution completed successfully!"
        else
            print_warning "Mandatory tests execution completed with issues"
        fi
    elif java_run_mvn_test_cmd "" "false"; then
        print_success "All tests execution completed successfully!"
    else
        print_warning "All tests execution completed with issues"
    fi

    cd - > /dev/null
    show_test_results || exit 1
}

run_java_runner_main() {
    folder_name="$1"
    case_name="$2"
    run_pattern="$3"

    compute_maven_playwright_profile_args
    validate_environment
    echo

    if [ -n "$folder_name" ] && [ -n "$case_name" ]; then
        run_specific_test "$folder_name" "$case_name" "$run_pattern"
    elif [ -n "$folder_name" ]; then
        run_module_tests "$folder_name" "$run_pattern"
    else
        run_all_tests
    fi
}
