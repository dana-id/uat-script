#!/bin/sh

# Fail on error
set -e

run_php_runner(){
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
