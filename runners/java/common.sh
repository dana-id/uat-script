# Shared Java test runner utilities (local + CI).

if [ -z "${JAVA_RUNNERS_DIR:-}" ]; then
    echo "JAVA_RUNNERS_DIR must be set before sourcing runners/java/common.sh" >&2
    exit 1
fi

PROJECT_ROOT="$(dirname "$JAVA_RUNNERS_DIR")"
JAVA_TEST_DIR="$PROJECT_ROOT/test/java"

readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly BLUE='\033[0;34m'
readonly YELLOW='\033[1;33m'
readonly CYAN='\033[0;36m'
readonly BOLD='\033[1m'
readonly RESET='\033[0m'

MAVEN_DISABLE_PLAYWRIGHT_PROFILE=""

MODULE_ALIAS_KEYS="pg payment_gateway paymentgateway payment gateway w wid d disb disburse"
MODULE_ALIAS_VALUES="paymentgateway paymentgateway paymentgateway paymentgateway paymentgateway widget widget disbursement disbursement disbursement"

color_text() {
    local text="$1"
    local color="${2:-$RESET}"
    printf "${color}%s${RESET}" "$text"
}

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

clear_surefire_reports() {
    local surefire_reports="$JAVA_TEST_DIR/target/surefire-reports"
    if [ -d "$surefire_reports" ]; then
        rm -f "$surefire_reports"/TEST-*.xml 2>/dev/null || true
    fi
}

run_mvn() {
    if [ -n "$MAVEN_DISABLE_PLAYWRIGHT_PROFILE" ]; then
        mvn -P "$MAVEN_DISABLE_PLAYWRIGHT_PROFILE" "$@"
    else
        mvn "$@"
    fi
}

run_mvn_test_once() {
    local test_arg="$1"
    if [ -n "$test_arg" ]; then
        run_mvn test -Dtest="$test_arg" -q
    else
        run_mvn test -q
    fi
}

compute_maven_playwright_profile_args() {
    MAVEN_DISABLE_PLAYWRIGHT_PROFILE=""
    if [ "$JAVA_MANDATORY_ONLY" != "true" ]; then
        return 0
    fi

    local effective_module=""
    if [ -n "$folder_name" ]; then
        effective_module=$(find_module "$folder_name" 2>/dev/null) || true
    fi
    local use_case
    use_case="$effective_module"
    if [ -z "$use_case" ]; then
        use_case="$folder_name"
    fi
    use_case=$(echo "$use_case" | tr '[:upper:]' '[:lower:]')

    local needs_playwright=false
    case "$use_case" in
        ""|widget)
            if [ -n "$case_name" ] || [ -n "$run_pattern" ]; then
                local caseNameLower runPatternLower
                caseNameLower=$(echo "$case_name" | tr '[:upper:]' '[:lower:]')
                runPatternLower=$(echo "$run_pattern" | tr '[:upper:]' '[:lower:]')
                if echo "$caseNameLower $runPatternLower" | grep -Eq "automation|oauth|browser|playwright"; then
                    needs_playwright=true
                fi
            fi
            ;;
        paymentgateway|disbursement)
            if [ -n "$case_name" ] || [ -n "$run_pattern" ]; then
                local caseNameLower runPatternLower
                caseNameLower=$(echo "$case_name" | tr '[:upper:]' '[:lower:]')
                runPatternLower=$(echo "$run_pattern" | tr '[:upper:]' '[:lower:]')
                if echo "$caseNameLower $runPatternLower" | grep -Eq "automation|oauth|browser|playwright"; then
                    needs_playwright=true
                fi
            fi
            ;;
    esac

    if [ "$needs_playwright" = "false" ]; then
        MAVEN_DISABLE_PLAYWRIGHT_PROFILE="!with-playwright"
        print_info "Local mandatory-only: omitting Playwright profile (-P '!with-playwright')"
    fi
}

get_mandatory_pattern_for_module() {
    local module_name="$1"
    case "$module_name" in
        "paymentgateway")
            echo 'id.dana.paymentgateway.CreateOrderTest#testCreateOrderRedirect+testCreateOrderInvalidFieldFormat+testCreateOrderInconsistentRequest+testCreateOrderInvalidMandatoryField+testCreateOrderUnauthorized,id.dana.paymentgateway.FinishNotifyTest#testTransactionSuccessNotify+testInternalServerErrorNotify+testExpiredNotify'
            ;;
        "widget")
            echo 'id.dana.widget.PaymentTest#testPaymentOrderSuccess+testPaymentFailMissingOrInvalidMandatoryField+testPaymentOrderMerchantDoesNotExist+testPaymentOrderInconsistent+testPaymentFailInternalServerError+testPaymentFailInvalidFormat+testPaymentFailInvalidSignature+testPaymentFailNotPermitted+testPaymentFailGeneralError+testPaymentFailExceedAmountLimit,id.dana.widget.FinishNotifyTest#testTransactionSuccessNotify+testInternalServerErrorNotify+testExpiredNotify'
            ;;
        "disbursement")
            echo 'id.dana.disbursement.TransferToDanaTest#testTopUpCustomerValid+testTopUpCustomerInsufficientFund+testTopUpCustomerFrozenAccount+testTopUpCustomerMissingMandatoryField+testTopUpCustomerInconsistentRequest+testTopUpCustomerInternalServerError+testTopUpCustomerInternalGeneralError,id.dana.disbursement.TransferToBankTest#testDisbursementBankValidAccount+testDisbursementBankInsufficientFund+testDisbursementBankValidAccountInProgress+testDisbursementBankInactiveAccount+testDisbursementBankInvalidMandatoryFieldFormat+testDisbursementBankMissingMandatoryField+testDisbursementBankInvalidFieldFormat,id.dana.disbursement.FinishNotifyTest#testTransactionSuccessNotify+testInternalServerErrorNotify+testExpiredNotify'
            ;;
        *)
            echo ""
            ;;
    esac
}

discover_business_modules() {
    local dana_path="$JAVA_TEST_DIR/src/test/java/id/dana"

    if [ ! -d "$dana_path" ]; then
        echo ""
        return 1
    fi

    find "$dana_path" -maxdepth 1 -type d ! -name "dana" ! -name "util" ! -name "helper" ! -name "interceptor" ! -name "common" | \
        sed "s|$dana_path/||" | \
        grep -v '^$' | \
        sort
}

validate_environment() {
    print_info "Validating Java environment..."

    if [ ! -d "$JAVA_TEST_DIR" ]; then
        print_error "Java test directory not found: $JAVA_TEST_DIR"
        print_info "Please ensure you're running this script from the project root"
        exit 1
    fi

    if ! command -v java > /dev/null 2>&1; then
        print_error "Java is not installed or not in PATH"
        exit 1
    fi

    if ! command -v mvn > /dev/null 2>&1; then
        print_error "Maven is not installed or not in PATH"
        exit 1
    fi

    if [ ! -f "$JAVA_TEST_DIR/pom.xml" ]; then
        print_error "Maven pom.xml not found in $JAVA_TEST_DIR"
        exit 1
    fi

    local java_version
    java_version=$(java -version 2>&1 | head -n 1 | cut -d'"' -f2 | cut -d'.' -f1-2)
    local maven_version
    maven_version=$(mvn -version 2>&1 | head -n 1 | cut -d' ' -f3)

    print_success "Java version: $java_version"
    print_success "Maven version: $maven_version"
    print_success "Test directory: $JAVA_TEST_DIR"
}

find_module() {
    local input_module="$1"
    local normalized_input
    normalized_input=$(echo "$input_module" | tr '[:upper:]' '[:lower:]')
    local business_modules
    business_modules=$(discover_business_modules)

    for module in $business_modules; do
        if [ "$module" = "$normalized_input" ]; then
            echo "$module"
            return 0
        fi
    done

    local alias_key
    local alias_value
    local i=1
    for alias_key in $MODULE_ALIAS_KEYS; do
        alias_value=$(echo "$MODULE_ALIAS_VALUES" | cut -d' ' -f$i)
        if [ "$alias_key" = "$normalized_input" ]; then
            for module in $business_modules; do
                if [ "$module" = "$alias_value" ]; then
                    echo "$alias_value"
                    return 0
                fi
            done
        fi
        i=$((i + 1))
    done

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

get_module_display_name() {
    local module="$1"
    case "$module" in
        paymentgateway) echo "Payment Gateway" ;;
        disbursement) echo "Disbursement" ;;
        widget) echo "Widget" ;;
        *) echo "$module" ;;
    esac
}

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
    local business_modules
    business_modules=$(discover_business_modules)
    for module in $business_modules; do
        local display_name
        display_name=$(get_module_display_name "$module")
        echo "   - $display_name ($module)"
    done
    echo
    print_info "You can also use aliases like: pg, widget, disbursement"
}

find_test_files() {
    local module="$1"
    local test_pattern="$2"
    local dana_path="$JAVA_TEST_DIR/src/test/java/id/dana"

    if [ -z "$test_pattern" ]; then
        find "$dana_path/$module" -name "*.java" -type f 2>/dev/null | head -20
    else
        find "$dana_path/$module" -name "*$test_pattern*.java" -type f 2>/dev/null || \
        find "$dana_path/$module" -name "*${test_pattern}Test*.java" -type f 2>/dev/null || \
        find "$dana_path/$module" -name "*Test${test_pattern}*.java" -type f 2>/dev/null
    fi
}

show_test_results() {
    local surefire_reports="$JAVA_TEST_DIR/target/surefire-reports"
    local filter_class="$1"

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

    for xml_file in "$surefire_reports"/TEST-*.xml; do
        if [ -f "$xml_file" ]; then
            local class_name
            class_name=$(basename "$xml_file" .xml | sed 's/TEST-//' | sed 's/.*\.//')

            if [ -n "$filter_class" ] && [ "$class_name" != "$filter_class" ]; then
                continue
            fi

            local tests failures errors skipped
            tests=$(grep -o 'tests="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)
            failures=$(grep -o 'failures="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)
            errors=$(grep -o 'errors="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)
            skipped=$(grep -o 'skipped="[0-9]*"' "$xml_file" | cut -d'"' -f2 | head -1)

            tests=${tests:-0}
            failures=${failures:-0}
            errors=${errors:-0}
            skipped=${skipped:-0}

            case "$tests" in ''|*[!0-9]*) tests=0 ;; esac
            case "$failures" in ''|*[!0-9]*) failures=0 ;; esac
            case "$errors" in ''|*[!0-9]*) errors=0 ;; esac
            case "$skipped" in ''|*[!0-9]*) skipped=0 ;; esac

            local class_passed=$((tests - failures - errors - skipped))

            total_tests=$((total_tests + tests))
            passed_tests=$((passed_tests + class_passed))
            failed_tests=$((failed_tests + failures))
            error_tests=$((error_tests + errors))
            skipped_tests=$((skipped_tests + skipped))
            found_results=1

            if [ "$tests" -gt 0 ]; then
                echo "   $class_name:"
                echo "      Passed: $class_passed"
                [ "$failures" -gt 0 ] && echo "      Failed: $failures"
                [ "$errors" -gt 0 ] && echo "      Errors: $errors"
                [ "$skipped" -gt 0 ] && echo "      Skipped: $skipped"
            fi
        fi
    done

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

    if [ "$failed_tests" -gt 0 ] || [ "$error_tests" -gt 0 ]; then
        show_failure_details "$filter_class"
    fi

    [ "$failed_tests" -gt 0 ] || [ "$error_tests" -gt 0 ] && return 1
    return 0
}

show_failure_details() {
    local surefire_reports="$JAVA_TEST_DIR/target/surefire-reports"
    local filter_class="$1"

    print_header "Failure Details"
    echo

    for xml_file in "$surefire_reports"/TEST-*.xml; do
        if [ -f "$xml_file" ]; then
            local class_name
            class_name=$(basename "$xml_file" .xml | sed 's/TEST-//' | sed 's/.*\.//')

            if [ -n "$filter_class" ] && [ "$class_name" != "$filter_class" ]; then
                continue
            fi

            local has_failures
            has_failures=$(grep -E -c '<failure|<error' "$xml_file" 2>/dev/null) || true
            case "$has_failures" in ''|*[!0-9]*) has_failures=0 ;; esac

            if [ "$has_failures" -gt 0 ]; then
                printf "%s\n" "$(color_text "$class_name" $BOLD)"
                printf "%s\n" "$(color_text "─────────────────────────────────────────" $RED)"

                grep -A 5 '<failure\|<error' "$xml_file" | while IFS= read -r line; do
                    case "$line" in
                        *message=\"*)
                            local failure_msg
                            failure_msg=$(echo "$line" | sed 's/.*message="\([^"]*\)".*/\1/')
                            printf "FAIL %s\n" "$failure_msg"
                            ;;
                    esac
                done | head -3
                echo
            fi
        fi
    done
}
