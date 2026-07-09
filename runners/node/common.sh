# Shared Node test runner utilities (local + CI).

if [ -z "${NODE_RUNNERS_DIR:-}" ]; then
    echo "NODE_RUNNERS_DIR must be set before sourcing runners/node/common.sh" >&2
    exit 1
fi

PROJECT_ROOT="$(dirname "$NODE_RUNNERS_DIR")"
NODE_TEST_DIR="$PROJECT_ROOT/test/node"

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

resolve_needs_playwright() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"
    needs_playwright=false

    if [ "$NODE_MANDATORY_ONLY" != "true" ]; then
        echo "true"
        return 0
    fi

    case "$folderName" in
        ""|"widget")
            if [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                needs_playwright=false
            elif [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                needs_playwright=true
            else
                caseNameLower=$(echo "$caseName" | tr '[:upper:]' '[:lower:]')
                runPatternLower=$(echo "$runPattern" | tr '[:upper:]' '[:lower:]')
                if echo "$caseNameLower $runPatternLower" | grep -Eq "automation|oauth|browser|playwright"; then
                    needs_playwright=true
                fi
            fi
            ;;
    esac

    echo "$needs_playwright"
}

setup_node_env() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"

    if [ -f "$PROJECT_ROOT/.env" ]; then
        set -a
        . "$PROJECT_ROOT/.env"
        set +a
    fi

    if ! command -v node >/dev/null 2>&1; then
        echo "Node.js not available in this system. Please install Node.js."
        exit 0
    fi

    echo "Running Node.js tests..."
    node --version
    npm --version

    export NODE_OPTIONS="${NODE_OPTIONS:-} --experimental-vm-modules"
    cd "$NODE_TEST_DIR"

    if [ ! -f "package.json" ]; then
        echo "Initializing Node.js project..."
        npm init -y
    fi

    echo "Installing dependencies..."
    npm install --save dana-node dotenv
    npm install --save-dev jest ts-jest

    needs_playwright=$(resolve_needs_playwright "$folderName" "$caseName" "$runPattern")
    if [ "$needs_playwright" = "true" ]; then
        echo "Widget tests detected or all tests running. Installing Playwright..."
        npm install --save-dev @playwright/test playwright

        if [ -n "${CI:-}" ]; then
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
}
