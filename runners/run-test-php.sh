#!/bin/sh

# Fail on error
set -e

run_php_runner(){
    # Check and install Selenium WebDriver dependencies if needed
    echo "Checking Selenium WebDriver dependencies..."
    
    # Check if running on macOS or Linux
    OS=$(uname)
    if [ "$OS" = "Darwin" ]; then
        # macOS-specific installations
        if ! command -v chromedriver &> /dev/null; then
            echo "ChromeDriver not found, installing..."
            if command -v brew &> /dev/null; then
                brew install --cask chromedriver
            else
                echo "Homebrew not found. Please install ChromeDriver manually."
                echo "You can use: brew install --cask chromedriver"
            fi
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
                sudo apt-get update && sudo apt-get install -y chromium chromium-driver
            elif command -v dnf &> /dev/null; then
                sudo dnf install -y chromium chromedriver
            else
                echo "Unsupported Linux distribution. Please install ChromeDriver manually."
            fi
        fi
        
        # Check for Java
        if ! command -v java &> /dev/null; then
            echo "Java not found, installing..."
            if command -v apt-get &> /dev/null; then
                sudo apt-get install -y default-jre
            elif command -v dnf &> /dev/null; then
                sudo dnf install -y java-latest-openjdk
            else
                echo "Unsupported Linux distribution. Please install Java manually."
            fi
        fi
        
        # PHP ZIP extension (for WebDriver)
        if ! php -m | grep -q "zip"; then
            echo "PHP ZIP extension not found, installing..."
            if command -v apt-get &> /dev/null; then
                sudo apt-get install -y libzip-dev
                sudo docker-php-ext-install zip 2>/dev/null || echo "Not in Docker environment, skipping PHP extension installation"
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
    
    # Check if Selenium is already running
    if ! pgrep -f "selenium-server" > /dev/null; then
        echo "Starting Selenium server..."
        java -jar "$SELENIUM_JAR" standalone > /dev/null 2>&1 &
        # Give Selenium time to start
        sleep 5
        echo "Selenium server started"
    else
        echo "Selenium server is already running"
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
        done
    fi
}

# Always execute the runner
run_php_runner "$@"
