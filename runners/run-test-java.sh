#!/bin/bash

# ============================================================================
# DANA Java Test Automation Runner
# Human-readable, maintainable, and dynamic test execution script
# 
# Supports three execution modes:
# 1. Specific test case: sh run-test.sh java widget ApplyToken
# 2. Business module: sh run-test.sh java widget  
# 3. All tests: sh run-test.sh java
# ============================================================================

set -e

# ============================================================================
# CONFIGURATION & STYLING
# ============================================================================

# Colors for better visual experience
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly BLUE='\033[0;34m'
readonly YELLOW='\033[1;33m'
readonly CYAN='\033[0;36m'
readonly BOLD='\033[1m'
readonly RESET='\033[0m'

# Directory configuration
readonly SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
readonly PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
readonly JAVA_TEST_DIR="$PROJECT_ROOT/test/java"

# Dynamic business module discovery
discover_business_modules() {
    local dana_path="$JAVA_TEST_DIR/src/test/java/id/dana"
    
    if [ ! -d "$dana_path" ]; then
        echo ""
        return 1
    fi
    
    # Find all directories, excluding utility/helper directories
    find "$dana_path" -maxdepth 1 -type d ! -name "dana" ! -name "util" ! -name "helper" ! -name "interceptor" ! -name "common" | \
        sed "s|$dana_path/||" | \
        grep -v '^$' | \
        sort
}

# Module aliases for flexible matching (using portable shell syntax)
MODULE_ALIAS_KEYS="pg payment_gateway paymentgateway payment gateway w wid d disb disburse"
MODULE_ALIAS_VALUES="paymentgateway paymentgateway paymentgateway paymentgateway paymentgateway widget widget disbursement disbursement disbursement"

# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

# Apply color to text
color_text() {
    local text="$1"
    local color="${2:-$RESET}"
    printf "${color}%s${RESET}" "$text"
}

# Print styled messages
print_success() {
    printf "[SUCCESS] %s\n" "$(color_text "$1" $GREEN)"
}

print_error() {
    printf "[ERROR] %s\n" "$(color_text "$1" $RED)"
}

print_warning() {
    printf "[WARNING] %s\n" "$(color_text "$1" $YELLOW)"
}

print_info() {
    printf "[INFO] %s\n" "$(color_text "$1" $BLUE)"
}

print_header() {
    echo
    printf "%s\n" "$(color_text "============================================" $CYAN)"
    printf "%s\n" "$(color_text "$1" $BOLD)"
    printf "%s\n" "$(color_text "============================================" $CYAN)"
}

# ============================================================================
# ENVIRONMENT VALIDATION
# ============================================================================

validate_environment() {
    print_info "Validating Java environment..."
    
    # Check if we're in the correct directory
    if [ ! -d "$JAVA_TEST_DIR" ]; then
        print_error "Java test directory not found: $JAVA_TEST_DIR"
        print_info "Please ensure you're running this script from the project root"
        exit 1
    fi
    
    # Check for Java
    if ! command -v java > /dev/null 2>&1; then
        print_error "Java is not installed or not in PATH"
        exit 1
    fi
    
    # Check for Maven
    if ! command -v mvn > /dev/null 2>&1; then
        print_error "Maven is not installed or not in PATH"
        exit 1
    fi
    
    # Check if pom.xml exists
    if [ ! -f "$JAVA_TEST_DIR/pom.xml" ]; then
        print_error "Maven pom.xml not found in $JAVA_TEST_DIR"
        exit 1
    fi
    
    local java_version=$(java -version 2>&1 | head -n 1 | cut -d'"' -f2 | cut -d'.' -f1-2)
    local maven_version=$(mvn -version 2>&1 | head -n 1 | cut -d' ' -f3)
    
    print_success "Java version: $java_version"
    print_success "Maven version: $maven_version"
    print_success "Test directory: $JAVA_TEST_DIR"
}
# ============================================================================
# MODULE & TEST DISCOVERY
# ============================================================================

# Find module by name (supports aliases) - portable shell version
find_module() {
    local input_module="$1"
    local normalized_input=$(echo "$input_module" | tr '[:upper:]' '[:lower:]')
    local business_modules=$(discover_business_modules)
    
    # First, check if it's a direct match
    for module in $business_modules; do
        if [ "$module" = "$normalized_input" ]; then
            echo "$module"
            return 0
        fi
    done
    
    # Then check aliases using portable method
    local alias_key
    local alias_value
    local i=1
    for alias_key in $MODULE_ALIAS_KEYS; do
        alias_value=$(echo "$MODULE_ALIAS_VALUES" | cut -d' ' -f$i)
        if [ "$alias_key" = "$normalized_input" ]; then
            # Verify the alias target actually exists
            for module in $business_modules; do
                if [ "$module" = "$alias_value" ]; then
                    echo "$alias_value"
                    return 0
                fi
            done
        fi
        i=$((i + 1))
    done
    
    # Finally, try partial matching
    for module in $business_modules; do
        case "$module" in
            *"$normalized_input"*) 
                echo "$module"
                return 0
                ;;
        esac
    done
    
    return 1
}

# Get display name for module
get_module_display_name() {
    local module="$1"
    case "$module" in
        paymentgateway) echo "Payment Gateway" ;;
        disbursement) echo "Disbursement" ;;
        widget) echo "Widget" ;;
        *) echo "${module^}" ;;  # Capitalize first letter
    esac
}

# Show usage information
show_usage() {
    print_header "DANA Java Test Runner Usage"
    echo
    echo "$(color_text "Three execution modes:" $BOLD)"
    echo
    echo "$(color_text "1. Run specific test case (optionally single method):" $CYAN)"
    echo "   sh run-test.sh java widget ApplyToken"
    echo "   sh run-test.sh java pg CreateOrder"
    echo "   sh run-test.sh java paymentgateway CreateOrderTest createOrderRedirectScenario"
    echo
    echo "$(color_text "2. Run all tests in a business module:" $CYAN)"
    echo "   sh run-test.sh java widget"
    echo "   sh run-test.sh java payment_gateway"
    echo "   sh run-test.sh java disbursement"
    echo
    echo "$(color_text "3. Run all tests across all modules:" $CYAN)"
    echo "   sh run-test.sh java"
    echo
    echo "$(color_text "Available modules:" $YELLOW)"
    local business_modules=$(discover_business_modules)
    for module in $business_modules; do
        local display_name=$(get_module_display_name "$module")
        echo "   - $display_name ($module)"
    done
    echo
    print_info "You can also use aliases like: pg, widget, disbursement"
}

# Find test files by pattern
find_test_files() {
    local module="$1"
    local test_pattern="$2"
    local dana_path="$JAVA_TEST_DIR/src/test/java/id/dana"
    
    if [ -z "$test_pattern" ]; then
        find "$dana_path/$module" -name "*.java" -type f 2>/dev/null | head -20
    else
        # Try multiple patterns for flexibility
        find "$dana_path/$module" -name "*$test_pattern*.java" -type f 2>/dev/null || \
        find "$dana_path/$module" -name "*${test_pattern}Test*.java" -type f 2>/dev/null || \
        find "$dana_path/$module" -name "*Test${test_pattern}*.java" -type f 2>/dev/null
    fi
}

# ============================================================================
# TEST EXECUTION FUNCTIONS
# ============================================================================

# Run specific test case (optional 3rd arg = single method name for -Dtest=Class#method)
run_specific_test() {
    local module="$1"
    local test_name="$2"
    local method_name="${3:-}"
    
    # Find the actual module (handles aliases)
    local actual_module=$(find_module "$module")
    if [ -z "$actual_module" ]; then
        print_error "Module '$module' not found"
        local business_modules=$(discover_business_modules)
        echo "Available modules: $(echo $business_modules | tr ' ' ', ')"
        exit 1
    fi
    
    local module_display=$(get_module_display_name "$actual_module")
    print_header "Running $module_display Test: $test_name${method_name:+#$method_name}"
    
    # Find test files matching the pattern
    local test_files=$(find_test_files "$actual_module" "$test_name")
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
    
    # Change to test directory and execute
    cd "$JAVA_TEST_DIR"
    
    # Clean up old test reports to ensure we only see current test results
    if [ -d "target/surefire-reports" ]; then
        rm -f target/surefire-reports/TEST-*.xml 2>/dev/null || true
    fi
    
    # Convert file path to test class pattern
    local test_class=$(echo "$test_files" | head -1 | sed "s|$JAVA_TEST_DIR/src/test/java/||" | sed 's|/|.|g' | sed 's|\.java$||')
    local test_arg="$test_class"
    if [ -n "$method_name" ]; then
        # Multiple methods: use | (e.g. method1|method2) → Maven uses + for same class
        method_arg=$(echo "$method_name" | tr '|' '+')
        test_arg="$test_class#$method_arg"
    fi
    print_info "Executing: $test_arg"
    echo
    
    # Run Maven test with proper error handling
    if mvn test -Dtest="$test_arg" -q; then
        print_success "Test execution completed successfully!"
    else
        print_warning "Test execution completed with issues"
    fi
    
    cd - > /dev/null

    # Show detailed results for only this specific test class
    local simple_class_name=$(echo "$test_class" | sed 's/.*\.//')
    show_test_results "$simple_class_name" || exit 1
}

# Run all tests in a module (optional 2nd arg = class filter, e.g. CreateOrderTest|CancelOrderTest → only those classes)
run_module_tests() {
    local module="$1"
    local run_pattern="${2:-}"
    
    # Find the actual module (handles aliases)
    local actual_module=$(find_module "$module")
    if [ -z "$actual_module" ]; then
        print_error "Module '$module' not found"
        local business_modules=$(discover_business_modules)
        echo "Available modules: $(echo $business_modules | tr ' ' ', ')"
        exit 1
    fi
    
    local module_display=$(get_module_display_name "$actual_module")
    if [ -n "$run_pattern" ]; then
        print_header "Running Tests in $module_display (filter: $run_pattern)"
    else
        print_header "Running All Tests in $module_display"
    fi
    
    cd "$JAVA_TEST_DIR"
    
    local test_arg
    if [ -n "$run_pattern" ]; then
        # Restrict to specified class names: Foo|Bar → id.dana.module.Foo+id.dana.module.Bar (Maven uses + for multiple)
        local class_list
        class_list=$(echo "$run_pattern" | tr '|' '\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | grep -v '^$')
        test_arg=$(echo "$class_list" | sed "s|^|id.dana.$actual_module.|" | tr '\n' '+' | sed 's/+$//')
        local count=$(echo "$class_list" | wc -l | tr -d ' ')
        print_info "Running $count test class(es): $run_pattern"
    else
        test_arg="id.dana.$actual_module.*Test"
        local test_count=$(find_test_files "$actual_module" "" | wc -l | tr -d ' ')
        print_info "Found $test_count test file(s) in $module_display"
    fi
    echo
    
    # Run module tests
    print_info "Executing tests..."
    echo
    
    # Use Maven test pattern for the module (or filtered classes)
    if mvn test -Dtest="$test_arg" -q; then
        print_success "Module test execution completed successfully!"
    else
        print_warning "Module test execution completed with issues"
    fi
    
    cd - > /dev/null

    # Show detailed results
    show_test_results || exit 1
}

# Run all tests across all modules
run_all_tests() {
    print_header "Running All Tests Across All Modules"
    
    cd "$JAVA_TEST_DIR"
    
    # Count total tests for better feedback
    local business_modules=$(discover_business_modules)
    local total_tests=0
    print_info "Scanning modules for tests:"
    for module in $business_modules; do
        local count=$(find_test_files "$module" "" | wc -l | tr -d ' ')
        local module_display=$(get_module_display_name "$module")
        echo "   $module_display: $count test(s)"
        total_tests=$((total_tests + count))
    done
    
    echo
    print_info "Total tests to execute: $total_tests"
    echo
    
    print_info "Executing all tests..."
    echo
    
    # Run all tests
    if mvn test -q; then
        print_success "All tests execution completed successfully!"
    else
        print_warning "All tests execution completed with issues"
    fi
    
    cd - > /dev/null

    # Show detailed results
    show_test_results || exit 1
}

# ============================================================================
# RESULT ANALYSIS & REPORTING
# ============================================================================

# Show comprehensive test results with detailed analysis
show_test_results() {
    local surefire_reports="$JAVA_TEST_DIR/target/surefire-reports"
    local filter_class="$1"  # Optional parameter to filter specific test class
    
    if [ ! -d "$surefire_reports" ]; then
        print_warning "No test reports found"
        return 1
    fi
    
    print_header "Test Results Summary"
    echo
    
    local total_tests=0
    local passed_tests=0
    local failed_tests=0
    local skipped_tests=0
    local error_tests=0
    local found_results=0
    
    # Parse XML reports for detailed counts
    for xml_file in "$surefire_reports"/TEST-*.xml; do
        if [ -f "$xml_file" ]; then
            local class_name=$(basename "$xml_file" .xml | sed 's/TEST-//' | sed 's/.*\.//')
            
            # If we have a filter and this class doesn't match, skip it
            if [ -n "$filter_class" ] && [ "$class_name" != "$filter_class" ]; then
                continue
            fi
            
            # Extract test counts with better error handling
            local tests=$(grep -o 'tests="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)
            local failures=$(grep -o 'failures="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)
            local errors=$(grep -o 'errors="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)
            local skipped=$(grep -o 'skipped="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)
            
            # Handle missing attributes with safe defaults
            tests=${tests:-0}
            failures=${failures:-0}
            errors=${errors:-0}
            skipped=${skipped:-0}
            
            # Ensure all values are numeric using portable shell check
            case "$tests" in ''|*[!0-9]*) tests=0 ;; esac
            case "$failures" in ''|*[!0-9]*) failures=0 ;; esac
            case "$errors" in ''|*[!0-9]*) errors=0 ;; esac
            case "$skipped" in ''|*[!0-9]*) skipped=0 ;; esac
            
            local class_passed=$((tests - failures - errors - skipped))
            
            # Accumulate totals
            total_tests=$((total_tests + tests))
            passed_tests=$((passed_tests + class_passed))
            failed_tests=$((failed_tests + failures))
            error_tests=$((error_tests + errors))
            skipped_tests=$((skipped_tests + skipped))
            found_results=1
            
            # Show individual class results if they have tests
            if [ "$tests" -gt 0 ]; then
                echo "   $class_name:"
                echo "      Passed: $class_passed"
                [ "$failures" -gt 0 ] && echo "      Failed: $failures"
                [ "$errors" -gt 0 ] && echo "      Errors: $errors"
                [ "$skipped" -gt 0 ] && echo "      Skipped: $skipped"
            fi
        fi
    done
    
    # If no results found for the specific filter, show message
    if [ -n "$filter_class" ] && [ "$found_results" -eq 0 ]; then
        print_warning "No test results found for $filter_class"
        return 1
    fi
    
    echo
    printf "%s\n" "$(color_text "FINAL SUMMARY" $BOLD)"
    printf "%s\n" "$(color_text "═══════════════════════════════════════" $CYAN)"
    printf "Total Tests: %s\n" "$(color_text "$total_tests" $BOLD)"
    printf "Passed: %s\n" "$(color_text "$passed_tests" $GREEN)"
    printf "Failed: %s\n" "$(color_text "$failed_tests" $RED)"
    [ "$error_tests" -gt 0 ] && printf "Errors: %s\n" "$(color_text "$error_tests" $RED)"
    [ "$skipped_tests" -gt 0 ] && printf "Skipped: %s\n" "$(color_text "$skipped_tests" $YELLOW)"
    echo
    
    # Calculate and show success rate
    if [ "$total_tests" -gt 0 ]; then
        local success_rate=$(( (passed_tests * 100) / total_tests ))
        printf "Success Rate: %s%%\n" "$(color_text "$success_rate" $BLUE)"
        
        if [ "$success_rate" -eq 100 ]; then
            printf "%s\n" "$(color_text "Perfect! All tests passed!" $GREEN)"
        elif [ "$success_rate" -ge 80 ]; then
            printf "%s\n" "$(color_text "Good job! Most tests are passing." $YELLOW)"
        else
            printf "%s\n" "$(color_text "Attention needed - some tests are failing." $RED)"
        fi
    else
        printf "%s\n" "$(color_text "No tests were executed." $BLUE)"
    fi
    
    printf "%s\n" "$(color_text "═══════════════════════════════════════" $CYAN)"
    echo
    
    # Show failure details if there are any failures
    if [ "$failed_tests" -gt 0 ] || [ "$error_tests" -gt 0 ]; then
        show_failure_details "$filter_class"
    fi

    # Return non-zero so CI job fails when there are failures or errors
    [ "$failed_tests" -gt 0 ] || [ "$error_tests" -gt 0 ] && return 1
    return 0
}

# Show detailed failure information
show_failure_details() {
    local surefire_reports="$JAVA_TEST_DIR/target/surefire-reports"
    local filter_class="$1"  # Optional parameter to filter specific test class
    
    print_header "🔍 Failure Details"
    echo
    
    for xml_file in "$surefire_reports"/TEST-*.xml; do
        if [ -f "$xml_file" ]; then
            local class_name=$(basename "$xml_file" .xml | sed 's/TEST-//' | sed 's/.*\.//')
            
            # If we have a filter and this class doesn't match, skip it
            if [ -n "$filter_class" ] && [ "$class_name" != "$filter_class" ]; then
                continue
            fi
            
            # Check if this class has failures or errors
            local has_failures=$(grep -c '<failure\|<error' "$xml_file" 2>/dev/null || echo "0")
            
            if [ "$has_failures" -gt 0 ]; then
                printf "📝 %s\n" "$(color_text "$class_name" $BOLD)"
                printf "%s\n" "$(color_text "─────────────────────────────────────────" $RED)"
                
                # Extract and display failure messages safely
                grep -A 5 '<failure\|<error' "$xml_file" | while IFS= read -r line; do
                    case "$line" in
                        *message=\"*) 
                            # Extract message content safely  
                            failure_msg=$(echo "$line" | sed 's/.*message="\([^"]*\)".*/\1/')
                            printf "❌ %s\n" "$failure_msg"
                            ;;
                    esac
                done | head -3
                echo
            fi
        fi
    done
}

# ============================================================================
# MAIN EXECUTION LOGIC
# ============================================================================

# Main Java test runner function
run_java_runner() {
    local folder_name="$1"
    local case_name="$2"
    local run_pattern="$3"   # optional: single test method name (e.g. createOrderRedirectScenario)
    
    # Validate environment first
    validate_environment
    echo
    
    # Execute based on arguments with improved logic
    if [ -n "$folder_name" ] && [ -n "$case_name" ]; then
        # Run specific test case in a specific folder (optionally single method if run_pattern set)
        run_specific_test "$folder_name" "$case_name" "$run_pattern"
    elif [ -n "$folder_name" ]; then
        # Run tests in a specific folder (optionally only classes matching run_pattern, e.g. CreateOrderTest|CancelOrderTest)
        run_module_tests "$folder_name" "$run_pattern"
    else
        # Run all tests
        run_all_tests
    fi
}

# Main script entry point
main() {
    # Show usage if help is requested
    if [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
        show_usage
        exit 0
    fi
    
    # Execute runner with all arguments
    run_java_runner "$@" 
}

# Execute main function if script is run directly
case "${0##*/}" in
    "run-test-java.sh")
        main "$@"
        ;;
esac
