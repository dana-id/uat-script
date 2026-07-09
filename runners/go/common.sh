# Shared Go test runner utilities (local + CI).

has_test_files() {
    dir="$1"
    [ -d "$dir" ] && find "$dir" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .
}

get_mandatory_pattern_for_module() {
    module_name="$1"
    case "$module_name" in
        "payment_gateway")
            echo 'TestCreateOrderRedirectScenario|TestCreateOrderInvalidFieldFormat|TestCreateOrderInconsistentRequest|TestCreateOrderInvalidMandatoryField|TestCreateOrderUnauthorized|TestTransactionSuccessNotify|TestInternalServerErrorNotify|TestExpiredNotify'
            ;;
        "widget")
            echo 'TestPaymentSuccess|TestPaymentFailMissingOrInvalidMandatoryField|TestPaymentFailGeneralError|TestPaymentFailTransactionNotPermitted|TestPaymentFailMerchantNotExistOrStatusAbnormal|TestPaymentFailInconsistentRequest|TestPaymentFailInternalServerError|TestPaymentFailInvalidFormat|TestPaymentFailInvalidSignature|TestPaymentFailExceedsTransactionAmountLimit|TestTransactionSuccessNotify|TestInternalServerErrorNotify|TestExpiredNotify'
            ;;
        "disbursement")
            echo 'TestTopUpCustomerValid|TestTopUpCustomerInsufficientFund|TestTopUpCustomerFrozenAccount|TestTopUpCustomerMissingMandatoryField|TestTopUpCustomerInconsistentRequest|TestTopUpCustomerInternalServerError|TestTopUpCustomerInternalGeneralError|TestDisbursementBankValidAccount|TestDisbursementBankValidAccountInProgress|TestDisbursementBankInconsistentRequest|TestDisbursementBankInsufficientFund|TestDisbursementBankInactiveAccount|TestDisbursementBankInvalidFieldFormat|TestDisbursementBankMissingMandatoryField|TestTransactionSuccessNotify|TestInternalServerErrorNotify|TestExpiredNotify'
            ;;
        *)
            echo ""
            ;;
    esac
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
    go get github.com/mxschmitt/playwright-go@v0.6100.0 > /dev/null 2>&1 || true
    go mod tidy > /dev/null 2>&1
    go clean -testcache > /dev/null 2>&1
}

setup_go_runner() {
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
}
