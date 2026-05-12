#!/bin/sh

# Fail on error
set -e

resolve_mandatory_only() {
    if [ -n "${NODE_MANDATORY_ONLY:-}" ]; then
        echo "$NODE_MANDATORY_ONLY"
    elif [ -z "${CI:-}" ]; then
        echo "true"
    else
        echo "false"
    fi
}

get_mandatory_pattern_for_folder() {
    folder_name="$1"
    case "$folder_name" in
        "payment_gateway")
            echo "CreateOrderRedirect|CreateOrderInvalidFieldFormat|CreateOrderInconsistentRequest|CreateOrderInvalidMandatoryField|CreateOrderUnauthorized"
            ;;
        "widget")
            echo "PaymentSuccess|PaymentFailInvalidFormat|PaymentFailMissingOrInvalidMandatoryField|PaymentFailInvalidSignature|PaymentFailNotPermitted|PaymentFailMerchantNotExistOrStatusAbnormal|PaymentFailInconsistentRequest|PaymentFailInternalServerError|PaymentFailGeneralError|PaymentFailExceedAmountLimit"
            ;;
        "disbursement")
            echo "TopUpCustomerValid|TopUpCustomerInsufficientFund|TopUpCustomerFrozenAccount|TopUpCustomerMissingMandatoryField|TopUpCustomerInconsistentRequest|TopUpCustomerInternalServerError|TopUpCustomerInternalGeneralError|DisbursementBankValidAccount|DisbursementBankValidAccountInProgress|DisbursementBankInconsistentRequest|DisbursementBankInsufficientFund|DisbursementBankInactiveAccount|DisbursementBankInvalidFieldFormat|DisbursementBankMissingMandatoryField"
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

    if [ -f .env ]; then set -a; . ./.env; set +a; fi
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
    cd test/node
    
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
    # Local mandatory-only runs for widget (or the per-folder mandatory sweep) use API-only
    # payment-host-to-host tests; skip browser deps unless the user scoped to automation-related names.
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
        
        # Check if we're running in CI
        if [ -n "${CI}" ]; then
            echo "Detected CI environment, using system Chrome if available"
            # Set environment variables to use system Chrome if available
            export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
            
            # Set path to system Chrome binary
            export PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=$(which chromium-browser 2>/dev/null || which chromium 2>/dev/null || which chrome 2>/dev/null || which google-chrome-stable 2>/dev/null || echo "")
            
            if [ -z "$PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH" ]; then
                echo "WARNING: Could not find system Chrome/Chromium. Will try to use downloaded browser."
                npx playwright install chromium
            else
                echo "Using system Chrome/Chromium at: $PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH"
            fi
        else
            # On non-Alpine environments, just install Playwright browsers normally
            npx playwright install chromium
        fi
    else
        echo "Skipping Playwright install for targeted non-automation run."
    fi
    
    # Install TypeScript
    npm install --save-dev typescript @types/node
    
    # Set up TypeScript configuration if it doesn't exist
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
    
    # Set up Jest configuration if it doesn't exist
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
    
    # Support running by folder, scenario name, and/or specific test run pattern
    # Multiple tests: use | in pattern (e.g. CreateOrderRedirect|query payment) – Jest -t accepts regex
    # Note: Use substrings of the actual test() titles
    if [ -n "$runPattern" ]; then
        # Run specific test(s) by name (jest -t pattern, | = regex OR)
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
                runPatternOut=$(npx jest --testPathPattern="$testPathPattern" -t "$runPattern" 2>&1)
                jestCode=$?
                set -e
            else
                set +e
                runPatternOut=$(npx jest "$folderName" -t "$runPattern" 2>&1)
                jestCode=$?
                set -e
            fi
            echo "$runPatternOut"
            # Jest reports "Test Suites: 5 skipped, 0 of 5 total" when -t matches nothing
            if echo "$runPatternOut" | grep -q "0 of [0-9]* total"; then
                echo "" >&2
                echo "\033[31mERROR: Pattern '$runPattern' did not match any test.\033[0m" >&2
                echo "Node test names come from test('...') titles, not Go function names." >&2
                echo "Examples: use 'CreateOrderRedirect' or 'query payment with status created' or 'INIT'." >&2
                exit 1
            fi
            exit $jestCode
        else
            echo "Running Node.js tests matching '$runPattern' in all folders..."
            runPatternOut=$(npx jest -t "$runPattern" 2>&1); jestCode=$?
            echo "$runPatternOut"
            if echo "$runPatternOut" | grep -q "0 of [0-9]* total"; then
                echo "" >&2
                echo "\033[31mERROR: Pattern '$runPattern' did not match any test.\033[0m" >&2
                echo "Node test names come from test('...') titles. Examples: 'CreateOrderRedirect', 'query payment with status created'." >&2
                exit 1
            fi
            exit $jestCode
        fi
    elif [ -n "$folderName" ] && [ -n "$caseName" ]; then
        # Run specific test in a specific folder
        echo "Running Node.js test '$caseName' in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi
        
        # Find all test files in the folder that match the pattern
        TEST_FILES=$(find $folderName -type f -name "*$caseName*.ts" -o -name "*$caseName*.js" 2>/dev/null)
        
        if [ -z "$TEST_FILES" ]; then
            echo "\033[31mERROR: No test files found matching pattern '$caseName' in folder '$folderName'\033[0m" >&2
            exit 1
        fi
        
        echo "Running the following test files:"
        echo $TEST_FILES
        
        npx jest $TEST_FILES
    elif [ -n "$folderName" ]; then
        # Run all tests in a specific folder
        echo "Running all Node.js tests in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi
        mandatory_pattern=$(get_mandatory_pattern_for_folder "$folderName")
        if [ "$mandatory_only" = "true" ] && [ -n "$mandatory_pattern" ]; then
            echo "Non-CI mode: running mandatory $folderName tests only"
            npx jest "$folderName" -t "$mandatory_pattern"
        else
            npx jest "$folderName"
        fi
    elif [ -n "$caseName" ]; then
        # Run a specific test case across all folders
        echo "Running Node.js test with pattern '$caseName' in all folders..."
        npx jest -t "$caseName"
    else
        # Run all tests
        if [ "$mandatory_only" = "true" ]; then
            echo "Non-CI mode: running mandatory tests per module only..."
            for folder in payment_gateway widget disbursement; do
                if [ -d "$folder" ]; then
                    mandatory_pattern=$(get_mandatory_pattern_for_folder "$folder")
                    if [ -n "$mandatory_pattern" ]; then
                        echo "Running mandatory tests in $folder..."
                        npx jest "$folder" -t "$mandatory_pattern"
                    else
                        npx jest "$folder"
                    fi
                fi
            done
        else
            echo "Running all Node.js tests..."
            npx jest
        fi
    fi
    
    # Change back to project root
    cd ../..
}

# Always execute the runner
run_node_runner "$@"
