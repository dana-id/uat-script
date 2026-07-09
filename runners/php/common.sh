# Shared PHP test runner utilities (local + CI).

get_mandatory_pattern_for_folder() {
    folder_name="$1"
    case "$folder_name" in
        "payment_gateway")
            echo "testCreateOrderRedirectScenario|testCreateOrderInvalidFieldFormat|testCreateOrderInconsistentRequest|testCreateOrderInvalidMandatoryField|testCreateOrderUnauthorized|testTransactionSuccessNotify|testInternalServerErrorNotify|testExpiredNotify"
            ;;
        "widget")
            echo "testPaymentSuccess|testPaymentFailInvalidFormat|testPaymentFailMissingOrInvalidMandatoryField|testPaymentFailInvalidSignature|testPaymentFailNotPermitted|testPaymentFailMerchantNotExistOrStatusAbnormal|testPaymentFailInconsistentRequest|testPaymentFailInternalServerError|testPaymentFailGeneralError|testPaymentFailExceedAmountLimit|testTransactionSuccessNotify|testInternalServerErrorNotify|testExpiredNotify"
            ;;
        "disbursement")
            echo "testTopUpCustomerValid|testTopUpCustomerInsufficientFund|testTopUpCustomerFrozenAccount|testTopUpCustomerMissingMandatoryField|testTopUpCustomerInconsistentRequest|testTopUpCustomerInternalServerError|testTopUpCustomerInternalGeneralError|testDisbursementBankValidAccount|testDisbursementBankValidAccountInProgress|testDisbursementBankInconsistentRequest|testDisbursementBankInsufficientFund|testDisbursementBankInactiveAccount|testDisbursementBankInvalidFieldFormat|testDisbursementBankMissingMandatoryField|testTransactionSuccessNotify|testInternalServerErrorNotify|testExpiredNotify"
            ;;
        *)
            echo ""
            ;;
    esac
}

case_needs_browser_automation() {
    scope=$(echo "$1 $2" | tr '[:upper:]' '[:lower:]')
    echo "$scope" | grep -Eq 'automation|oauth|browser|selenium|webdriver|payment|queryorder|refund|cancelorder|applytoken|applyott|accountunbinding|unbinding|createorder|redirect'
}

resolve_needs_selenium() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"
    needs_selenium=true

    case "$folderName" in
        ""|"widget")
            if [ "$PHP_MANDATORY_ONLY" = "true" ] && [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                needs_selenium=false
            elif [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                needs_selenium=true
            elif case_needs_browser_automation "$caseName" "$runPattern"; then
                needs_selenium=true
            else
                needs_selenium=false
            fi
            ;;
        "disbursement")
            needs_selenium=false
            ;;
        "payment_gateway")
            if [ "$PHP_MANDATORY_ONLY" = "true" ] && [ -z "$caseName" ] && [ -z "$runPattern" ]; then
                needs_selenium=false
            elif [ -n "$caseName" ] || [ -n "$runPattern" ]; then
                if case_needs_browser_automation "$caseName" "$runPattern"; then
                    needs_selenium=true
                else
                    needs_selenium=false
                fi
            fi
            ;;
    esac

    echo "$needs_selenium"
}

download_chromedriver() {
    CHROME_MAJOR_VERSION="$1"
    CHROMEDRIVER_DIR="$HOME/.chromedriver"

    if [ "$(uname -m)" = "arm64" ]; then
        PLATFORM="mac_arm64"
        JSON_PLATFORM="mac-arm64"
        DOWNLOAD_PLATFORM="mac-arm64"
        LEGACY_PLATFORM="mac64_m1"
    else
        PLATFORM="mac64"
        JSON_PLATFORM="mac-x64"
        DOWNLOAD_PLATFORM="mac-x64"
        LEGACY_PLATFORM="mac64"
    fi

    echo "Detected platform: $PLATFORM"
    echo "Finding ChromeDriver for Chrome version $CHROME_MAJOR_VERSION"

    CHROMEDRIVER_VERSION=$(curl -s "https://googlechromelabs.github.io/chrome-for-testing/LATEST_RELEASE_$CHROME_MAJOR_VERSION")

    if [ -n "$CHROMEDRIVER_VERSION" ] && [ "$CHROMEDRIVER_VERSION" != "404: Not Found"* ] && [ "$CHROMEDRIVER_VERSION" != *"<"*">"* ]; then
        echo "Found version: $CHROMEDRIVER_VERSION"
        DOWNLOAD_URL="https://storage.googleapis.com/chrome-for-testing-public/$CHROMEDRIVER_VERSION/$DOWNLOAD_PLATFORM/chromedriver-$DOWNLOAD_PLATFORM.zip"
    else
        echo "No direct version found, trying JSON API..."
        VERSIONS_JSON=$(curl -s "https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json")
        MATCHING_VERSION=$(echo "$VERSIONS_JSON" | grep -o '"version":"'"$CHROME_MAJOR_VERSION"'[^"]*"' | head -1 | sed 's/"version":"\(.*\)"/\1/')

        if [ -n "$MATCHING_VERSION" ]; then
            echo "Found matching version: $MATCHING_VERSION"
            DOWNLOAD_URL="https://storage.googleapis.com/chrome-for-testing-public/$MATCHING_VERSION/$DOWNLOAD_PLATFORM/chromedriver-$DOWNLOAD_PLATFORM.zip"
        else
            echo "No matching version found in JSON API, trying legacy endpoint..."
            CHROMEDRIVER_VERSION=$(curl -s "https://chromedriver.storage.googleapis.com/LATEST_RELEASE_$CHROME_MAJOR_VERSION")

            if [ -n "$CHROMEDRIVER_VERSION" ] && [ "$CHROMEDRIVER_VERSION" != "404: Not Found"* ] && [ "$CHROMEDRIVER_VERSION" != *"<"*">"* ]; then
                echo "Found version via legacy endpoint: $CHROMEDRIVER_VERSION"
                DOWNLOAD_URL="https://chromedriver.storage.googleapis.com/$CHROMEDRIVER_VERSION/chromedriver_$LEGACY_PLATFORM.zip"
            else
                echo "No version-specific driver found, using latest stable ChromeDriver"
                CHROMEDRIVER_VERSION=$(curl -s "https://chromedriver.storage.googleapis.com/LATEST_RELEASE")
                DOWNLOAD_URL="https://chromedriver.storage.googleapis.com/$CHROMEDRIVER_VERSION/chromedriver_$LEGACY_PLATFORM.zip"
            fi
        fi
    fi

    if [ -z "$DOWNLOAD_URL" ]; then
        echo "Error: Could not find a suitable ChromeDriver download URL for Chrome $CHROME_MAJOR_VERSION"
        exit 1
    fi

    echo "Download URL: $DOWNLOAD_URL"
    TEMP_ZIP="$CHROMEDRIVER_DIR/chromedriver.zip"
    curl -L -o "$TEMP_ZIP" "$DOWNLOAD_URL"

    if [ ! -s "$TEMP_ZIP" ]; then
        echo "Error: Failed to download ChromeDriver or file is empty"
        exit 1
    fi

    EXTRACT_DIR="$CHROMEDRIVER_DIR/extract"
    rm -rf "$EXTRACT_DIR"
    mkdir -p "$EXTRACT_DIR"

    if ! unzip -o "$TEMP_ZIP" -d "$EXTRACT_DIR" 2>/dev/null; then
        if ! ditto -xk "$TEMP_ZIP" "$EXTRACT_DIR" 2>/dev/null; then
            if ! jar xf "$TEMP_ZIP" -C "$EXTRACT_DIR" 2>/dev/null; then
                echo "Error: Could not extract ChromeDriver zip file"
                exit 1
            fi
        fi
    fi

    DRIVER_PATH=$(find "$EXTRACT_DIR" -type f -name "chromedriver" | head -1)
    if [ -z "$DRIVER_PATH" ]; then
        echo "Error: ChromeDriver binary not found in extracted files"
        exit 1
    fi

    cp "$DRIVER_PATH" "$CHROMEDRIVER_DIR/chromedriver"
    rm -rf "$EXTRACT_DIR"
    chmod +x "$CHROMEDRIVER_DIR/chromedriver"
    rm -f "$TEMP_ZIP"
    export PATH="$CHROMEDRIVER_DIR:$PATH"
    xattr -d com.apple.quarantine "$CHROMEDRIVER_DIR/chromedriver" 2>/dev/null || true
    export SELENIUM_SERVER_URL="http://localhost:4444/wd/hub"
}

setup_selenium_if_needed() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"

    if [ "$(resolve_needs_selenium "$folderName" "$caseName" "$runPattern")" != "true" ]; then
        echo "Skipping Selenium / ChromeDriver setup (mandatory-only or non-browser-scoped run)."
        return 0
    fi

    echo "Checking Selenium WebDriver dependencies..."
    OS=$(uname)

    if [ "$OS" = "Darwin" ]; then
        if command -v /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome > /dev/null 2>&1; then
            CHROME_VERSION=$(/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --version | awk '{print $3}' | cut -d. -f1)
            CHROME_MAJOR_VERSION=$(echo "$CHROME_VERSION" | cut -d. -f1)
            CHROMEDRIVER_DIR="$HOME/.chromedriver"
            mkdir -p "$CHROMEDRIVER_DIR"

            if [ -f "$CHROMEDRIVER_DIR/chromedriver" ] && [ -x "$CHROMEDRIVER_DIR/chromedriver" ]; then
                INSTALLED_VERSION=$($CHROMEDRIVER_DIR/chromedriver --version | awk '{print $2}' | cut -d. -f1)
                if [ "$INSTALLED_VERSION" = "$CHROME_MAJOR_VERSION" ]; then
                    export PATH="$CHROMEDRIVER_DIR:$PATH"
                    export SELENIUM_SERVER_URL="http://localhost:4444/wd/hub"
                    xattr -d com.apple.quarantine "$CHROMEDRIVER_DIR/chromedriver" 2>/dev/null || true
                else
                    download_chromedriver "$CHROME_MAJOR_VERSION"
                fi
            else
                download_chromedriver "$CHROME_MAJOR_VERSION"
            fi
        else
            echo "Google Chrome not found in standard location. Please install Chrome or verify its path."
            exit 1
        fi

        if ! command -v java > /dev/null 2>&1; then
            if command -v brew > /dev/null 2>&1; then
                brew install --cask temurin
            else
                echo "Java not found. Please install Java manually."
                exit 1
            fi
        fi
    elif [ "$OS" = "Linux" ]; then
        if ! command -v chromedriver > /dev/null 2>&1; then
            if command -v apt-get > /dev/null 2>&1; then
                apt-get update && apt-get install -y chromium chromium-driver
            elif command -v dnf > /dev/null 2>&1; then
                dnf install -y chromium chromedriver
            fi
        fi

        if ! command -v java > /dev/null 2>&1; then
            if command -v apt-get > /dev/null 2>&1; then
                apt-get install -y default-jre
            elif command -v dnf > /dev/null 2>&1; then
                dnf install -y java-latest-openjdk
            fi
        fi

        if ! php -m | grep -q "zip"; then
            if command -v apt-get > /dev/null 2>&1; then
                apt-get install -y libzip-dev
                docker-php-ext-install zip 2>/dev/null || true
            fi
        fi
    fi

    if [ -f "/opt/selenium/selenium-server.jar" ]; then
        SELENIUM_JAR="/opt/selenium/selenium-server.jar"
    else
        SELENIUM_JAR="$HOME/.selenium/selenium-server.jar"
    fi
    SELENIUM_DIR=$(dirname "$SELENIUM_JAR")

    if [ ! -f "$SELENIUM_JAR" ]; then
        mkdir -p "$SELENIUM_DIR"
        wget -q -O "$SELENIUM_JAR" https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.10.0/selenium-server-4.10.0.jar || \
        curl -L -o "$SELENIUM_JAR" https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.10.0/selenium-server-4.10.0.jar
    fi

    check_selenium_ready() {
        for i in 1 2 3 4 5 6; do
            RESPONSE=$(curl -s http://localhost:4444/status 2>/dev/null)
            if echo "$RESPONSE" | grep -q "\"ready\":\s*true"; then
                echo "Selenium server is ready"
                return 0
            fi
            echo "Waiting for Selenium server to be ready..."
            sleep 5
        done
        return 1
    }

    start_selenium() {
        if pgrep -f "selenium-server" > /dev/null; then
            pkill -f "selenium-server"
            sleep 2
        fi
        java -jar "$SELENIUM_JAR" standalone > /dev/null 2>&1 &
        sleep 5
    }

    if pgrep -f "selenium-server" > /dev/null; then
        if ! check_selenium_ready; then
            start_selenium
            check_selenium_ready
        fi
    else
        start_selenium
        check_selenium_ready
    fi
}

setup_php_runner() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"

    ROOT_DIR="$(cd "$PHP_RUNNERS_DIR/.." && pwd)"

    setup_selenium_if_needed "$folderName" "$caseName" "$runPattern"

    echo "Running PHP tests with PHPUnit..."
    php --version

    COMPOSER_BIN=""
    if command -v composer > /dev/null 2>&1; then
        COMPOSER_BIN="composer"
    elif [ -x "/usr/local/bin/composer" ]; then
        COMPOSER_BIN="/usr/local/bin/composer"
    fi

    if [ -z "$COMPOSER_BIN" ]; then
        echo "Composer not available, installing locally..."
        TEMP_DIR=$(mktemp -d)
        COMPOSER_PHAR="$TEMP_DIR/composer.phar"
        COMPOSER_VERSION="2.6.6"
        GITHUB_URL="https://github.com/composer/composer/releases/download/$COMPOSER_VERSION/composer.phar"

        if ! curl -sS --insecure --connect-timeout 30 "$GITHUB_URL" -o "$COMPOSER_PHAR"; then
            wget --timeout=30 --no-check-certificate -q "$GITHUB_URL" -O "$COMPOSER_PHAR" || true
        fi

        if [ -f "$COMPOSER_PHAR" ]; then
            cp "$COMPOSER_PHAR" "$ROOT_DIR/composer.phar"
            chmod +x "$ROOT_DIR/composer.phar"
        fi
        rm -rf "$TEMP_DIR"
    fi

    PHP_COMPOSER_DIR="$ROOT_DIR/test/php"
    if [ ! -d "$PHP_COMPOSER_DIR" ] || [ ! -f "$PHP_COMPOSER_DIR/composer.json" ]; then
        echo "ERROR: PHP test directory not found at $PHP_COMPOSER_DIR" >&2
        exit 1
    fi

    if [ -n "$COMPOSER_BIN" ]; then
        COMPOSER_CMD="$COMPOSER_BIN"
    elif [ -f "$ROOT_DIR/composer.phar" ]; then
        COMPOSER_CMD="php $ROOT_DIR/composer.phar"
    else
        echo "ERROR: No Composer available" >&2
        exit 1
    fi

    if [ -n "${COMPOSER_AUTH:-}" ]; then
        if ! php -r 'json_decode(getenv("COMPOSER_AUTH")); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);' 2>/dev/null; then
            COMPOSER_AUTH=$(php -r '
                $t = getenv("COMPOSER_AUTH");
                echo json_encode(["http-basic" => ["gitlab.dana.id" => ["username" => "oauth2", "password" => $t]]]);
            ')
            export COMPOSER_AUTH
        fi
    fi

    echo "Clearing Composer cache..."
    COMPOSER_PROCESS_TIMEOUT=600 $COMPOSER_CMD clearcache 2>/dev/null || true

    echo "Installing PHP dependencies in $PHP_COMPOSER_DIR..."
    cd "$PHP_COMPOSER_DIR"
    COMPOSER_OUTPUT=$(COMPOSER_PROCESS_TIMEOUT=600 $COMPOSER_CMD install --no-interaction 2>&1) || true
    COMPOSER_EXIT=$?
    echo "$COMPOSER_OUTPUT"
    if [ "$COMPOSER_EXIT" -ne 0 ]; then
        echo "ERROR: composer install failed in test/php" >&2
        cd "$ROOT_DIR"
        exit 1
    fi
    cd "$ROOT_DIR"

    PHPUNIT_BIN="$ROOT_DIR/test/php/vendor/bin/phpunit"
    PHPUNIT_CONFIG="$ROOT_DIR/test/php/phpunit.xml"
    PHP_TEST_DIR="$ROOT_DIR/test/php"

    if [ ! -x "$PHPUNIT_BIN" ]; then
        echo "ERROR: PHPUnit not found at $PHPUNIT_BIN" >&2
        exit 1
    fi
}
