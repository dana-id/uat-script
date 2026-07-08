#!/bin/sh

# Fail on error
set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

resolve_mandatory_only() {
    if [ -n "${NODE_MANDATORY_ONLY:-}" ]; then
        echo "$NODE_MANDATORY_ONLY"
    elif [ -z "${CI:-}" ]; then
        echo "true"
    else
        echo "false"
    fi
}

resolve_node_retry_failed_only() {
    if [ "${NODE_RETRY_FAILED_ONLY:-}" = "false" ]; then
        echo "false"
    elif [ "${NODE_RETRY_FAILED_ONLY:-}" = "true" ]; then
        echo "true"
    elif [ -n "${CI:-}" ] || [ -n "${GITLAB_CI:-}" ]; then
        echo "true"
    else
        echo "false"
    fi
}

node_retry_sleep_seconds() {
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

resolve_retry_max_attempts() {
    max="${RETRY_MAX_ATTEMPTS:-5}"
    case "$max" in
        ''|*[!0-9]*) max=5 ;;
    esac
    if [ "$max" -lt 2 ]; then
        max=5
    fi
    echo "$max"
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

    if [ -f "$PROJECT_ROOT/.env" ]; then
        printf 'MERCHANT_ID=%s\n' "${DISBURSEMENT_MERCHANT_ID}" > "$PROJECT_ROOT/.env"
        printf 'X_PARTNER_ID=%s\n' "${DISBURSEMENT_X_PARTNER_ID}" >> "$PROJECT_ROOT/.env"
        printf 'CHANNEL_ID=%s\n' "${CHANNEL_ID}" >> "$PROJECT_ROOT/.env"
        printf "PRIVATE_KEY='%s'\n" "${DISBURSEMENT_PRIVATE_KEY}" >> "$PROJECT_ROOT/.env"
        printf 'ORIGIN=%s\n' "${ORIGIN}" >> "$PROJECT_ROOT/.env"
        printf "CLIENT_SECRET='%s'\n" "${DISBURSEMENT_CLIENT_SECRET}" >> "$PROJECT_ROOT/.env"
        printf "DISBURSEMENT_MERCHANT_ID=%s\n" "${DISBURSEMENT_MERCHANT_ID}" >> "$PROJECT_ROOT/.env"
        printf "DISBURSEMENT_X_PARTNER_ID=%s\n" "${DISBURSEMENT_X_PARTNER_ID}" >> "$PROJECT_ROOT/.env"
        printf "DISBURSEMENT_PRIVATE_KEY='%s'\n" "${DISBURSEMENT_PRIVATE_KEY}" >> "$PROJECT_ROOT/.env"
        printf "DISBURSEMENT_CLIENT_SECRET='%s'\n" "${DISBURSEMENT_CLIENT_SECRET}" >> "$PROJECT_ROOT/.env"
        printf "REDIRECT_URL_OAUTH='%s'\n" "${REDIRECT_URL_OAUTH}" >> "$PROJECT_ROOT/.env"
        printf "EXTERNAL_SHOP_ID='%s'\n" "${EXTERNAL_SHOP_ID}" >> "$PROJECT_ROOT/.env"
    fi
}

jest_regex_escape() {
    echo "$1" | sed 's/[][(){}.+*?^$|\\]/\\&/g'
}

extract_failed_jest_tests_from_json() {
    json_file="$1"
    pattern=""
    title=""
    failed_titles=""

    if [ ! -f "$json_file" ]; then
        return 2
    fi

    if ! command -v jq >/dev/null 2>&1; then
        return 2
    fi

    failed_titles=$(jq -r '
        [.testResults[]?.assertionResults[]?
            | select(.status == "failed" or .status == "todo")
            | .title]
        | map(select(. != ""))
        | unique
        | .[]
    ' "$json_file" 2>/dev/null)

    if [ -n "$failed_titles" ]; then
        while IFS= read -r title; do
            if [ -z "$title" ]; then
                continue
            fi
            escaped=$(jest_regex_escape "$title")
            if [ -n "$pattern" ]; then
                pattern="$pattern|$escaped"
            else
                pattern="$escaped"
            fi
        done <<EOF
$failed_titles
EOF
        if [ -n "$pattern" ]; then
            printf '%s\n' "$pattern"
            return 0
        fi
    fi

    failed_suites=$(jq -r '
        [.testResults[]?
            | select(.status == "failed")
            | select((.assertionResults // []) | map(select(.status == "failed")) | length == 0)
            | .name
        ]
        | map(select(. != ""))
        | length
    ' "$json_file" 2>/dev/null)

    case "$failed_suites" in ''|*[!0-9]*) failed_suites=0 ;; esac
    if [ "$failed_suites" -gt 0 ]; then
        return 3
    fi

    num_failed=$(jq -r '.numFailedTests // 0' "$json_file" 2>/dev/null)
    num_failed_suites=$(jq -r '.numFailedTestSuites // 0' "$json_file" 2>/dev/null)
    num_runtime_errors=$(jq -r '.numRuntimeErrorTestSuites // 0' "$json_file" 2>/dev/null)
    case "$num_failed" in ''|*[!0-9]*) num_failed=0 ;; esac
    case "$num_failed_suites" in ''|*[!0-9]*) num_failed_suites=0 ;; esac
    case "$num_runtime_errors" in ''|*[!0-9]*) num_runtime_errors=0 ;; esac

    if [ "$num_failed" -gt 0 ] || [ "$num_failed_suites" -gt 0 ] || [ "$num_runtime_errors" -gt 0 ]; then
        return 3
    fi

    if [ "$(jq -r '.success // "true"' "$json_file" 2>/dev/null)" = "false" ]; then
        return 3
    fi

    return 2
}

print_jest_json_summary() {
    json_file="$1"
    if ! command -v jq >/dev/null 2>&1 || [ ! -f "$json_file" ]; then
        return 0
    fi

    jq -r '
        "Tests: \(.numPassedTests // 0) passed, \(.numFailedTests // 0) failed, \(.numPendingTests // 0) skipped",
        (.testResults[]? | select(.status == "failed") | "FAIL suite \(.name)"),
        (.testResults[]?.assertionResults[]? | select(.status == "failed") | "  ✕ \(.title)")
    ' "$json_file" 2>/dev/null || true
}

run_jest_once() {
    title_pattern="$1"
    json_file="$2"
    shift 2

    set +e
    if [ -n "$title_pattern" ]; then
        npx jest "$@" -t "$title_pattern" --json --outputFile="$json_file"
    else
        npx jest "$@" --json --outputFile="$json_file"
    fi
    exit_code=$?

    if [ "$exit_code" -eq 0 ] && command -v jq >/dev/null 2>&1 && [ -f "$json_file" ]; then
        if [ "$(jq -r '.success // "true"' "$json_file" 2>/dev/null)" = "false" ]; then
            exit_code=1
        fi
    fi

    print_jest_json_summary "$json_file"
    return "$exit_code"
}

# CI: attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_jest_cmd() {
    initial_title_pattern="${1:-}"
    shift

    if [ "$(resolve_node_retry_failed_only)" != "true" ]; then
        set +e
        if [ -n "$initial_title_pattern" ]; then
            npx jest "$@" -t "$initial_title_pattern"
        else
            npx jest "$@"
        fi
        exit_code=$?
        return "$exit_code"
    fi

    max_attempts=$(resolve_retry_max_attempts)
    attempt=1
    current_title_pattern="$initial_title_pattern"
    last_exit_code=1
    json_file=""

    set +e
    while [ "$attempt" -le "$max_attempts" ]; do
        switch_disbursement_credentials_if_needed "$attempt"

        json_file="$PWD/.jest-retry-results-${attempt}-$$.json"
        rm -f "$json_file"

        if [ "$attempt" -eq 1 ]; then
            echo "Jest attempt 1/$max_attempts (full suite)"
        else
            echo "Jest attempt $attempt/$max_attempts (failed tests only)"
        fi
        if [ -n "$current_title_pattern" ]; then
            echo "Filter: $current_title_pattern"
        fi

        run_jest_once "$current_title_pattern" "$json_file" "$@"
        last_exit_code=$?

        if [ "$last_exit_code" -eq 0 ]; then
            rm -f "$json_file"
            set -e
            return 0
        fi

        if [ "$attempt" -ge "$max_attempts" ]; then
            rm -f "$json_file"
            set -e
            return "$last_exit_code"
        fi

        failed_title_pattern=""
        extract_status=0
        failed_title_pattern=$(extract_failed_jest_tests_from_json "$json_file") || extract_status=$?

        if [ ! -s "$json_file" ]; then
            extract_status=3
            failed_title_pattern=""
        fi

        rm -f "$json_file"

        if [ "$extract_status" -eq 3 ]; then
            echo "Retrying same scoped suite (suite-level failure detected)"
            current_title_pattern="$initial_title_pattern"
        elif [ -z "$failed_title_pattern" ]; then
            echo "Could not determine failed tests for retry; stopping."
            set -e
            return "$last_exit_code"
        else
            echo "Retrying failed/error tests only: $failed_title_pattern"
            current_title_pattern="$failed_title_pattern"
        fi

        sleep_delay=$(node_retry_sleep_seconds "$attempt")
        echo "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    set -e
    return "$last_exit_code"
}

get_mandatory_pattern_for_folder() {
    folder_name="$1"
    case "$folder_name" in
        "payment_gateway")
            echo "CreateOrderRedirect|CreateOrderInvalidFieldFormat|CreateOrderInconsistentRequest|CreateOrderInvalidMandatoryField|CreateOrderUnauthorized|TransactionSuccessNotify|InternalServerErrorNotify|ExpiredNotify"
            ;;
        "widget")
            echo "PaymentSuccess|PaymentFailInvalidFormat|PaymentFailMissingOrInvalidMandatoryField|PaymentFailInvalidSignature|PaymentFailNotPermitted|PaymentFailMerchantNotExistOrStatusAbnormal|PaymentFailInconsistentRequest|PaymentFailInternalServerError|PaymentFailGeneralError|PaymentFailExceedAmountLimit|TransactionSuccessNotify|InternalServerErrorNotify|ExpiredNotify"
            ;;
        "disbursement")
            echo "TopUpCustomerValid|TopUpCustomerInsufficientFund|TopUpCustomerFrozenAccount|TopUpCustomerMissingMandatoryField|TopUpCustomerInconsistentRequest|TopUpCustomerInternalServerError|TopUpCustomerInternalGeneralError|DisbursementBankValidAccount|DisbursementBankValidAccountInProgress|DisbursementBankInconsistentRequest|DisbursementBankInsufficientFund|DisbursementBankInactiveAccount|DisbursementBankInvalidFieldFormat|DisbursementBankMissingMandatoryField|TransactionSuccessNotify|InternalServerErrorNotify|ExpiredNotify"
            ;;
        *)
            echo ""
            ;;
    esac
}

run_node_runner(){
    folderName=$1
    caseName=$2
    runPattern=$3   # optional: jest -t pattern for specific test name(s)

    if [ -f "$PROJECT_ROOT/.env" ]; then
        set -a
        . "$PROJECT_ROOT/.env"
        set +a
    fi
    mandatory_only=$(resolve_mandatory_only)

    if ! command -v node >/dev/null 2>&1; then
        echo "Node.js not available in this system. Please install Node.js."
        exit 0
    fi

    echo "Running Node.js tests..."
    node --version
    npm --version

    export NODE_OPTIONS="${NODE_OPTIONS:-} --experimental-vm-modules"

    # Change to the Node.js test directory
    cd "$PROJECT_ROOT/test/node"

    # Create package.json if it doesn't exist
    if [ ! -f "package.json" ]; then
        echo "Initializing Node.js project..."
        npm init -y
    fi

    # Install dependencies
    echo "Installing dependencies..."
    npm install --save dana-node dotenv
    npm install --save-dev jest ts-jest

    # Install Playwright only when running tests that likely need browser automation.
    needsPlaywrightInstall=false
    case "$folderName" in
        ""|"widget")
            if [ "$mandatory_only" = "true" ] && [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                :
            elif [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                needsPlaywrightInstall=true
            else
                caseNameLower=$(echo "$caseName" | tr '[:upper:]' '[:lower:]')
                runPatternLower=$(echo "$runPattern" | tr '[:upper:]' '[:lower:]')
                if echo "$caseNameLower $runPatternLower" | grep -Eq "automation|oauth|browser|playwright"; then
                    needsPlaywrightInstall=true
                fi
            fi
            ;;
    esac

    if [ "$needsPlaywrightInstall" = true ]; then
        echo "Widget tests detected or all tests running. Installing Playwright..."
        npm install --save-dev @playwright/test playwright

        if [ -n "${CI}" ]; then
            echo "Detected CI environment, using system Chrome if available"
            export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
            export PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=$(which chromium-browser 2>/dev/null || which chromium 2>/dev/null || which chrome 2>/dev/null || which google-chrome-stable 2>/dev/null || echo "")

            if [ -z "$PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH" ]; then
                echo "WARNING: Could not find system Chrome/Chromium. Will try to use downloaded browser."
                npx playwright install chromium
            else
                echo "Using system Chrome/Chromium at: $PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH"
            fi
        else
            npx playwright install chromium
        fi
    else
        echo "Skipping Playwright install for targeted non-automation run."
    fi

    npm install --save-dev typescript @types/node

    if [ ! -f "tsconfig.json" ]; then
        echo "Setting up TypeScript configuration..."
        cat > tsconfig.json << 'EOF'
{
  "compilerOptions": {
    "target": "es2020",
    "module": "commonjs",
    "esModuleInterop": true,
    "strict": true,
    "outDir": "dist"
  },
  "include": ["**/*.ts"]
}
EOF
    fi

    if [ ! -f "jest.config.js" ]; then
        echo "Setting up Jest configuration..."
        cat > jest.config.js << 'EOF'
module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  testTimeout: 60000
};
EOF
    fi

    exit_code=0

    if [ -n "$runPattern" ]; then
        if [ -n "$folderName" ]; then
            echo "Running Node.js tests matching '$runPattern' in folder '$folderName'..."
            if [ ! -d "$folderName" ]; then
                echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
                exit 1
            fi
            if [ -n "$caseName" ]; then
                echo "Scoping to test file pattern '$caseName' before applying test title pattern..."
                testPathPattern="${folderName}/.*${caseName}"
                set +e
                run_jest_cmd "$runPattern" --testPathPattern="$testPathPattern"
                exit_code=$?
                set -e
            else
                set +e
                run_jest_cmd "$runPattern" "$folderName"
                exit_code=$?
                set -e
            fi
        else
            echo "Running Node.js tests matching '$runPattern' in all folders..."
            set +e
            run_jest_cmd "$runPattern"
            exit_code=$?
            set -e
        fi
    elif [ -n "$folderName" ] && [ -n "$caseName" ]; then
        echo "Running Node.js test '$caseName' in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi

        TEST_FILES=$(find "$folderName" -type f \( -name "*${caseName}*.ts" -o -name "*${caseName}*.js" \) 2>/dev/null)

        if [ -z "$TEST_FILES" ]; then
            echo "\033[31mERROR: No test files found matching pattern '$caseName' in folder '$folderName'\033[0m" >&2
            exit 1
        fi

        echo "Running the following test files:"
        echo "$TEST_FILES"

        set +e
        # shellcheck disable=SC2086
        run_jest_cmd "" $TEST_FILES
        exit_code=$?
        set -e
    elif [ -n "$folderName" ]; then
        echo "Running all Node.js tests in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi
        mandatory_pattern=$(get_mandatory_pattern_for_folder "$folderName")
        if [ "$mandatory_only" = "true" ] && [ -n "$mandatory_pattern" ]; then
            echo "Non-CI mode: running mandatory $folderName tests only"
            set +e
            run_jest_cmd "$mandatory_pattern" "$folderName"
            exit_code=$?
            set -e
        else
            set +e
            run_jest_cmd "" "$folderName"
            exit_code=$?
            set -e
        fi
    elif [ -n "$caseName" ]; then
        echo "Running Node.js test with pattern '$caseName' in all folders..."
        set +e
        run_jest_cmd "$caseName"
        exit_code=$?
        set -e
    else
        if [ "$mandatory_only" = "true" ]; then
            echo "Non-CI mode: running mandatory tests per module only..."
            exit_code=0
            for folder in payment_gateway widget disbursement; do
                if [ -d "$folder" ]; then
                    mandatory_pattern=$(get_mandatory_pattern_for_folder "$folder")
                    set +e
                    if [ -n "$mandatory_pattern" ]; then
                        echo "Running mandatory tests in $folder..."
                        run_jest_cmd "$mandatory_pattern" "$folder"
                    else
                        run_jest_cmd "" "$folder"
                    fi
                    folder_code=$?
                    set -e
                    if [ "$folder_code" -ne 0 ]; then
                        exit_code=$folder_code
                    fi
                fi
            done
        else
            echo "Running all Node.js tests..."
            set +e
            run_jest_cmd ""
            exit_code=$?
            set -e
        fi
    fi

    cd "$PROJECT_ROOT"
    exit "$exit_code"
}

# Always execute the runner
run_node_runner "$@"