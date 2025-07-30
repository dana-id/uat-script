#!/bin/sh

# Fail on error
set -e

run_php_runner(){
    # Check and install Selenium WebDriver dependencies if needed
    echo "Checking Selenium WebDriver dependencies..."
    
    # Check if running on macOS or Linux
    OS=$(uname)
    if [ "$OS" = "Darwin" ]; then
        # Check Chrome and ChromeDriver versions
        if command -v /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome &> /dev/null; then
            CHROME_VERSION=$(/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --version | awk '{print $3}' | cut -d. -f1)
            echo "Detected Chrome version: $CHROME_VERSION"
            
            # Get the major version of Chrome
            CHROME_MAJOR_VERSION=$(echo $CHROME_VERSION | cut -d. -f1)
            echo "Chrome major version: $CHROME_MAJOR_VERSION"
            
            # Set ChromeDriver directory
            CHROMEDRIVER_DIR="$HOME/.chromedriver"
            mkdir -p "$CHROMEDRIVER_DIR"
            
            # Check if ChromeDriver is already installed and matches the required version
            if [ -f "$CHROMEDRIVER_DIR/chromedriver" ] && [ -x "$CHROMEDRIVER_DIR/chromedriver" ]; then
                INSTALLED_VERSION=$($CHROMEDRIVER_DIR/chromedriver --version | awk '{print $2}' | cut -d. -f1)
                if [ "$INSTALLED_VERSION" = "$CHROME_MAJOR_VERSION" ]; then
                    echo "ChromeDriver version $INSTALLED_VERSION already installed, matching Chrome version $CHROME_MAJOR_VERSION"
                    export PATH="$CHROMEDRIVER_DIR:$PATH"
                    echo "ChromeDriver path: $CHROMEDRIVER_DIR/chromedriver"
                    echo "ChromeDriver version: $($CHROMEDRIVER_DIR/chromedriver --version)"
                    
                    # On macOS, remove quarantine attribute
                    if [ "$OS" = "Darwin" ]; then
                        xattr -d com.apple.quarantine "$CHROMEDRIVER_DIR/chromedriver" 2>/dev/null || true
                    fi
                    
                    # Set the Selenium Server URL
                    export SELENIUM_SERVER_URL="http://localhost:4444/wd/hub"
                    
                    # Skip download but continue with the test
                    echo "Using existing ChromeDriver"
                else
                    echo "Installed ChromeDriver version ($INSTALLED_VERSION) doesn't match Chrome version ($CHROME_MAJOR_VERSION). Downloading correct version..."
                    download_chromedriver "$CHROME_MAJOR_VERSION"
                fi
            else
                echo "ChromeDriver not found or not executable. Downloading..."
                download_chromedriver "$CHROME_MAJOR_VERSION"
            fi
        else
            echo "Google Chrome not found in standard location. Please install Chrome or verify its path."
            exit 1
        fi
        
        # Check for Java (needed for Selenium server)
        if ! command -v java &> /dev/null; then
            echo "Java not found, installing..."
            if command -v brew &> /dev/null; then
                brew install --cask temurin
            else
                echo "Homebrew not found. Please install Java manually."
                echo "You can use: brew install --cask temurin"
            fi
        fi
    elif [ "$OS" = "Linux" ]; then
        # Linux-specific installations (similar to CI script)
        if ! command -v chromedriver &> /dev/null; then
            echo "Installing Chrome and ChromeDriver..."
            if command -v apt-get &> /dev/null; then
                apt-get update && apt-get install -y chromium chromium-driver
            elif command -v dnf &> /dev/null; then
                dnf install -y chromium chromedriver
            else
                echo "Unsupported Linux distribution. Please install ChromeDriver manually."
            fi
        fi
        
        # Check for Java
        if ! command -v java &> /dev/null; then
            echo "Java not found, installing..."
            if command -v apt-get &> /dev/null; then
                apt-get install -y default-jre
            elif command -v dnf &> /dev/null; then
                dnf install -y java-latest-openjdk
            else
                echo "Unsupported Linux distribution. Please install Java manually."
            fi
        fi
        
        # PHP ZIP extension (for WebDriver)
        if ! php -m | grep -q "zip"; then
            echo "PHP ZIP extension not found, installing..."
            if command -v apt-get &> /dev/null; then
                apt-get install -y libzip-dev
                docker-php-ext-install zip 2>/dev/null || echo "Not in Docker environment, skipping PHP extension installation"
            else
                echo "Please install PHP ZIP extension manually for your distribution."
            fi
        fi
    fi
    
    # Download Selenium server if not present
    SELENIUM_JAR="$HOME/.selenium/selenium-server.jar"
    SELENIUM_DIR=$(dirname "$SELENIUM_JAR")
    
    if [ ! -f "$SELENIUM_JAR" ]; then
        echo "Selenium server not found, downloading..."
        mkdir -p "$SELENIUM_DIR"
        wget -q -O "$SELENIUM_JAR" https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.10.0/selenium-server-4.10.0.jar || \
        curl -L -o "$SELENIUM_JAR" https://github.com/SeleniumHQ/selenium/releases/download/selenium-4.10.0/selenium-server-4.10.0.jar
    fi
    
    # Function to check if Selenium is ready
    check_selenium_ready() {
        for i in {1..3}; do
            RESPONSE=$(curl -s http://localhost:4444/wd/hub/status 2>/dev/null)
            if echo "$RESPONSE" | grep -q "\"ready\":\s*true"; then
                echo "Selenium server is ready"
                return 0
            fi
            echo "Waiting for Selenium server to be ready (attempt $i/3)..."
            sleep 5
        done
        echo "Selenium server is not ready after multiple attempts"
        return 1
    }
    
    # Function to start or restart Selenium server
    start_selenium() {
        # Kill existing Selenium if running
        if pgrep -f "selenium-server" > /dev/null; then
            echo "Stopping existing Selenium server..."
            pkill -f "selenium-server"
            sleep 2
        fi
        
        echo "Starting Selenium server..."
        # Run Selenium with increased shared memory
        java -jar "$SELENIUM_JAR" standalone > /dev/null 2>&1 &
        
        # Give Selenium time to start
        sleep 5
    }
    
    # Check if Selenium is already running and ready
    if pgrep -f "selenium-server" > /dev/null; then
        echo "Selenium server is already running, checking if it's ready..."
        if ! check_selenium_ready; then
            echo "Selenium server is not responding correctly. Restarting..."
            start_selenium
            check_selenium_ready
        fi
    else
        echo "Selenium server is not running. Starting..."
        start_selenium
        check_selenium_ready
    fi
    folderName=$1
    caseName=$2
    ROOT_DIR=$(pwd)
    
    echo "Running PHP tests with PHPUnit..."
    
    # Check PHP version
    php --version
    
    # Check and install Composer if not available
    if ! command -v composer &> /dev/null; then
        echo "Composer not available, installing locally..."
        curl -sS https://getcomposer.org/installer | php
        # composer.phar will be in the current directory (project root)
    fi

    # Change to the PHP test directory where composer.json is located
    echo "Changing to test/php directory..."
    cd test/php

    # Clear composer cache first
    echo "Clearing Composer cache..."
    if [ -f ../../composer.phar ]; then
        COMPOSER_PROCESS_TIMEOUT=600 php ../../composer.phar clearcache
    else
        COMPOSER_PROCESS_TIMEOUT=600 composer clearcache
    fi

    # Install dependencies
    echo "Installing PHP dependencies..."
    rm composer.lock || true
    
    # Force update to latest stable danaid/dana-php package
    echo "Updating danaid/dana-php to latest stable version..."
    if [ -f ../../composer.phar ]; then
        COMPOSER_PROCESS_TIMEOUT=600 php ../../composer.phar require danaid/dana-php:"^0.1" --update-with-dependencies --no-interaction
    else
        COMPOSER_PROCESS_TIMEOUT=600 composer require danaid/dana-php:"^0.1" --update-with-dependencies --no-interaction
    fi
    
    # Install remaining dependencies
    echo "Installing remaining dependencies..."
    if [ -f ../../composer.phar ]; then
        COMPOSER_PROCESS_TIMEOUT=600 php ../../composer.phar install --no-interaction
    else
        COMPOSER_PROCESS_TIMEOUT=600 composer install --no-interaction
    fi
    
    # Change back to the project root for running PHPUnit
    echo "Changing back to project root..."
    cd "$ROOT_DIR"
    
    echo "Running PHP tests with PHPUnit..."
    
    # Support running by folder and/or scenario name
    if [ -n "$folderName" ] && [ -n "$caseName" ]; then
        # Run specific test in a specific folder
        echo "Running test '$caseName' in folder 'test/php/$folderName'..."
        test/php/vendor/bin/phpunit --configuration=phpunit.xml --testdox --debug --colors=always --filter="^.*\\\\$caseName.*$" "test/php/$folderName"
    elif [ -n "$folderName" ]; then
        # Run all tests in a specific folder
        echo "Running all tests in folder 'test/php/$folderName'..."
        test/php/vendor/bin/phpunit --configuration=phpunit.xml --testdox --debug --colors=always "test/php/$folderName"
    elif [ -n "$caseName" ]; then
        # Find all test directories (excluding helper)
        TEST_DIRS=$(find test/php -type d -mindepth 1 -maxdepth 1 -not -path "*/helper" -not -path "*/vendor")
        
        if [ -z "$TEST_DIRS" ]; then
            echo "\033[31mERROR: No test directories found under test/php\033[0m" >&2
            exit 1
        fi
        
        # Run test in all directories
        for dir in $TEST_DIRS; do
            echo "\033[36mRunning test '$caseName' in $dir...\033[0m"
            test/php/vendor/bin/phpunit --configuration=phpunit.xml --testdox --debug --colors=always --filter="^.*\\\\$caseName.*$" "$dir"
            # Add a 3-second gap between test runs
            sleep 3
        done
    else
        # Run all PHP tests
        echo "Running all PHP tests..."
        
        # Find all test directories (excluding helper)
        TEST_DIRS=$(find test/php -type d -mindepth 1 -maxdepth 1 -not -path "*/helper" -not -path "*/vendor")
        
        if [ -z "$TEST_DIRS" ]; then
            echo "\033[31mERROR: No test directories found under test/php\033[0m" >&2
            exit 1
        fi
        
        # Run tests in all directories
        for dir in $TEST_DIRS; do
            echo "\033[36mRunning tests in $dir...\033[0m"
            test/php/vendor/bin/phpunit --configuration=phpunit.xml --testdox --debug --colors=always "$dir"
            # Add a 3-second gap between test runs
            sleep 3
        done
    fi
}

download_chromedriver() {
    # Get the Chrome major version from the parameter
    local CHROME_MAJOR_VERSION="$1"
    
    # Determine the platform - check if on Apple Silicon
    if [[ $(uname -m) == "arm64" ]]; then
        PLATFORM="mac_arm64"
    else
        PLATFORM="mac64"
    fi
    
    echo "Detected platform: $PLATFORM"
                
    echo "Finding ChromeDriver for Chrome version $CHROME_MAJOR_VERSION"
    
    # Determine platform for the API
    if [[ $(uname -m) == "arm64" ]]; then
        JSON_PLATFORM="mac-arm64"
        DOWNLOAD_PLATFORM="mac-arm64"
        LEGACY_PLATFORM="mac64_m1"
    else
        JSON_PLATFORM="mac-x64"
        DOWNLOAD_PLATFORM="mac-x64"
        LEGACY_PLATFORM="mac64"
    fi
    echo "Detected platform: $JSON_PLATFORM"
    
    # Try the direct Chrome-for-Testing endpoint for this version
    CHROMEDRIVER_VERSION=$(curl -s "https://googlechromelabs.github.io/chrome-for-testing/LATEST_RELEASE_$CHROME_MAJOR_VERSION")
    
    if [[ -n "$CHROMEDRIVER_VERSION" && "$CHROMEDRIVER_VERSION" != "404: Not Found"* && "$CHROMEDRIVER_VERSION" != *"<"*">"* ]]; then
        echo "Found version: $CHROMEDRIVER_VERSION"
        DOWNLOAD_URL="https://storage.googleapis.com/chrome-for-testing-public/$CHROMEDRIVER_VERSION/$DOWNLOAD_PLATFORM/chromedriver-$DOWNLOAD_PLATFORM.zip"
    else
        # Try to get version from the JSON API (known-good-versions)
        echo "No direct version found, trying JSON API..."
        
        # Get the JSON and find matching versions with grep and sed
        echo "Getting versions from https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json"
        VERSIONS_JSON=$(curl -s "https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json")
        
        # Extract a version that starts with our Chrome major version
        MATCHING_VERSION=$(echo "$VERSIONS_JSON" | grep -o '"version":"'"$CHROME_MAJOR_VERSION"'[^"]*"' | head -1 | sed 's/"version":"\(.*\)"/\1/')
        
        if [[ -n "$MATCHING_VERSION" ]]; then
            echo "Found matching version: $MATCHING_VERSION"
            # Construct the download URL
            DOWNLOAD_URL="https://storage.googleapis.com/chrome-for-testing-public/$MATCHING_VERSION/$DOWNLOAD_PLATFORM/chromedriver-$DOWNLOAD_PLATFORM.zip"
        else
            # Final fallback - try the legacy endpoint
            echo "No matching version found in JSON API, trying legacy endpoint..."
            CHROMEDRIVER_VERSION=$(curl -s "https://chromedriver.storage.googleapis.com/LATEST_RELEASE_$CHROME_MAJOR_VERSION")
            
            if [[ -n "$CHROMEDRIVER_VERSION" && "$CHROMEDRIVER_VERSION" != "404: Not Found"* && "$CHROMEDRIVER_VERSION" != *"<"*">"* ]]; then
                echo "Found version via legacy endpoint: $CHROMEDRIVER_VERSION"
                DOWNLOAD_URL="https://chromedriver.storage.googleapis.com/$CHROMEDRIVER_VERSION/chromedriver_$LEGACY_PLATFORM.zip"
            else
                # Absolute final fallback - try the latest stable
                echo "No version-specific driver found, using latest stable ChromeDriver"
                CHROMEDRIVER_VERSION=$(curl -s "https://chromedriver.storage.googleapis.com/LATEST_RELEASE")
                DOWNLOAD_URL="https://chromedriver.storage.googleapis.com/$CHROMEDRIVER_VERSION/chromedriver_$LEGACY_PLATFORM.zip"
            fi
        fi
    fi
    # Output the final download URL
    if [[ -z "$DOWNLOAD_URL" ]]; then
        echo "Error: Could not find a suitable ChromeDriver download URL for Chrome $CHROME_VERSION"
        exit 1
    fi
    # Now proceed with download
    echo "Download URL: $DOWNLOAD_URL"
    
    # Download ChromeDriver
    TEMP_ZIP="$CHROMEDRIVER_DIR/chromedriver.zip"
    echo "Downloading to $TEMP_ZIP"
    curl -L -o "$TEMP_ZIP" "$DOWNLOAD_URL"
    
    # Verify download was successful
    if [ ! -s "$TEMP_ZIP" ]; then
        echo "Error: Failed to download ChromeDriver or file is empty"
        exit 1
    fi
    
    echo "Successfully downloaded $(du -h "$TEMP_ZIP" | cut -f1) to $TEMP_ZIP"
    
    # Create a clean extraction directory
    EXTRACT_DIR="$CHROMEDRIVER_DIR/extract"
    rm -rf "$EXTRACT_DIR"
    mkdir -p "$EXTRACT_DIR"
    
    echo "Extracting ChromeDriver..."
    
    # Try unzip first
    if ! unzip -o "$TEMP_ZIP" -d "$EXTRACT_DIR" 2>/dev/null; then
        # If that fails, try ditto (macOS specific)
        echo "Unzip failed, trying ditto..."
        if ! ditto -xk "$TEMP_ZIP" "$EXTRACT_DIR" 2>/dev/null; then
            # Last resort - try jar xf (Java's jar tool can extract zip files)
            echo "Ditto failed, trying jar..."
            if ! jar xf "$TEMP_ZIP" -C "$EXTRACT_DIR" 2>/dev/null; then
                echo "Error: Could not extract ChromeDriver zip file"
                echo "Contents of zip file:"
                zipinfo -1 "$TEMP_ZIP" || echo "(zipinfo not available)"
                exit 1
            fi
        fi
    fi
    
    # Find the chromedriver binary (recursively search all subdirectories)
    echo "Searching for chromedriver binary..."
    DRIVER_PATH=$(find "$EXTRACT_DIR" -type f -name "chromedriver" | head -1)
    
    if [ -z "$DRIVER_PATH" ]; then
        echo "Error: ChromeDriver binary not found in extracted files"
        echo "Contents of extraction directory:"
        find "$EXTRACT_DIR" -type f | xargs ls -la
        exit 1
    fi
    
    echo "Found driver at: $DRIVER_PATH"
    
    # Copy to final location
    cp "$DRIVER_PATH" "$CHROMEDRIVER_DIR/chromedriver"
    echo "Copied to: $CHROMEDRIVER_DIR/chromedriver"
    
    # Clean up extraction directory
    rm -rf "$EXTRACT_DIR"
    
    # Make sure the driver exists and is executable
    if [ ! -f "$CHROMEDRIVER_DIR/chromedriver" ]; then
        echo "Error: ChromeDriver binary not found after extraction"
        echo "Contents of $CHROMEDRIVER_DIR:"
        ls -la "$CHROMEDRIVER_DIR"
        exit 1
    fi
    
    # Make executable and clean up
    chmod +x "$CHROMEDRIVER_DIR/chromedriver"
    rm -f "$TEMP_ZIP"
    
    # Add to PATH
    export PATH="$CHROMEDRIVER_DIR:$PATH"
    echo "ChromeDriver installed at: $CHROMEDRIVER_DIR/chromedriver"
    echo "ChromeDriver version: $($CHROMEDRIVER_DIR/chromedriver --version)"
    
    # Remove Apple quarantine attribute to prevent security warnings
    xattr -d com.apple.quarantine "$CHROMEDRIVER_DIR/chromedriver" 2>/dev/null || true
    
    # Update SELENIUM_SERVER_URL environment variable to use the correct ChromeDriver
    export SELENIUM_SERVER_URL="http://localhost:4444/wd/hub"
}
    


# Always execute the runner
run_php_runner "$@"
