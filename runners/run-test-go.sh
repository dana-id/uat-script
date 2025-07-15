#!/bin/sh

# Fail on error
set -e

run_go_runner(){
    # Note: pipefail is not available in sh, using simpler approach
    
    # Adjust parameter mapping based on how run-test.sh calls this script
    # run-test.sh calls: sh run-test-go.sh "$2" "$3"
    # So: $1=module, $2=subCase
    module=$1       # e.g., "widget" or "payment_gateway"
    subCase=$2      # e.g., "cancel" (optional)
    if ! command go version > /dev/null 2>&1; then
        echo "ERROR: Go not available in this system. Please install Go."
        exit 1
    fi
    
    echo "Running Go tests..."
    go version
    
    # Set up environment variables (ensure proper handling of multi-line PRIVATE_KEY)
    # Note: Go tests will use godotenv to load these from .env file
    
    # Change to the test directory and run tests
    cd test/go
    
    # Run go mod tidy only if go.mod exists
    if [ -f "go.mod" ]; then
        # Update dana Go client to the latest version
        go get -u github.com/dana-id/dana-go
        go mod tidy
        go clean -testcache
        if [ -n "$module" ]; then
            echo "Running Go test file(s) for module/pattern: $module ${subCase:-}"  
            # If module matches a known test directory
            if [ "$module" = "widget" ] || [ "$module" = "payment_gateway" ]; then
                test_dir="./$module"
                if [ -n "$subCase" ]; then
                    # Filter by subCase pattern in file names
                    found_files=$(find "$test_dir" -type f -name "*${subCase}*.go")
                else
                    # Run all test files in the module
                    found_files=$(find "$test_dir" -type f -name "*_test.go")
                fi
            else
                # Treat module as a file name pattern across both directories
                pg_files=$(find ./payment_gateway -type f -name "*${module}*.go" 2>/dev/null || true)
                widget_files=$(find ./widget -type f -name "*${module}*.go" 2>/dev/null || true)
                
                if [ -n "$pg_files" ] && [ -n "$widget_files" ]; then
                    # Found files in both directories - run them separately
                    echo "\033[36mFound matching files in both directories, running separately:\033[0m"
                    echo "\033[36mPayment Gateway files:\033[0m"
                    for f in $pg_files; do
                        echo "  \033[33m- $f\033[0m"
                    done
                    echo "\033[36mWidget files:\033[0m"
                    for f in $widget_files; do
                        echo "  \033[33m- $f\033[0m"
                    done
                    
                    echo "\n\033[36m=== Running Payment Gateway Tests ===\033[0m"
                    go test -v $pg_files 2>&1 | awk '{
                        if ($0 ~ /=== RUN/) {print "\n\033[36m" $0 "\033[0m"}
                        else if ($0 ~ /--- PASS/) {print "\033[32m" $0 "\033[0m"}
                        else if ($0 ~ /--- FAIL/) {print "\033[31m" $0 "\033[0m"}
                        else if ($0 ~ /--- SKIP/) {print "\033[33m" $0 "\033[0m"}
                        else if ($0 ~ /Assertion passed/) {print "\033[37m" $0 "\033[0m"}
                        else if ($0 ~ /^[[:space:]]+[a-zA-Z0-9_\/-]+\.go:[0-9]+:/) {print "\033[1;31m" $0 "\033[0m"}
                        else {print $0}
                    }'
                    pg_exit_code=$?
                    
                    echo "\n\033[36m=== Running Widget Tests ===\033[0m"
                    go test -v $widget_files 2>&1 | awk '{
                        if ($0 ~ /=== RUN/) {print "\n\033[36m" $0 "\033[0m"}
                        else if ($0 ~ /--- PASS/) {print "\033[32m" $0 "\033[0m"}
                        else if ($0 ~ /--- FAIL/) {print "\033[31m" $0 "\033[0m"}
                        else if ($0 ~ /--- SKIP/) {print "\033[33m" $0 "\033[0m"}
                        else if ($0 ~ /Assertion passed/) {print "\033[37m" $0 "\033[0m"}
                        else if ($0 ~ /^[[:space:]]+[a-zA-Z0-9_\/-]+\.go:[0-9]+:/) {print "\033[1;31m" $0 "\033[0m"}
                        else {print $0}
                    }'
                    widget_exit_code=$?
                    
                    # Combine found files for total count
                    found_files="$pg_files $widget_files"
                    
                    # Calculate total tests from both directories
                    pg_total=$(go test -list . $pg_files 2>/dev/null | grep -c 'Test' || echo "0")
                    widget_total=$(go test -list . $widget_files 2>/dev/null | grep -c 'Test' || echo "0")
                    total=$((pg_total + widget_total))
                    
                    echo "\n\033[1;36mTotal scenarios run: $total\033[0m"
                    echo "\n\033[1;35m==== Go Test Results Summary Complete ====\033[0m"
                    
                    # Exit with error if either test run failed
                    if [ $pg_exit_code -ne 0 ] || [ $widget_exit_code -ne 0 ]; then
                        exit 1
                    else
                        exit 0
                    fi
                elif [ -n "$pg_files" ]; then
                    # Only found files in payment_gateway
                    found_files="$pg_files"
                elif [ -n "$widget_files" ]; then
                    # Only found files in widget
                    found_files="$widget_files"
                else
                    # No files found
                    found_files=""
                fi
            fi
            
            # Only run normal single-directory test if we didn't handle multi-directory case above
            if [ -z "$pg_files" ] || [ -z "$widget_files" ]; then
            if [ -z "$found_files" ]; then
                echo "\033[31mERROR: No Go test files were found containing file pattern: $module\033[0m" >&2
                exit 1
            fi
            echo "\033[36mScenarios (test files) to run:\033[0m"
            for f in $found_files; do
                echo "  \033[33m- $f\033[0m"
            done
            go test -v $found_files 2>&1 | awk '{
                if ($0 ~ /=== RUN/) {print "\n\033[36m" $0 "\033[0m"}
                else if ($0 ~ /--- PASS/) {print "\033[32m" $0 "\033[0m"}
                else if ($0 ~ /--- FAIL/) {print "\033[31m" $0 "\033[0m"}
                else if ($0 ~ /--- SKIP/) {print "\033[33m" $0 "\033[0m"}
                else if ($0 ~ /Assertion passed/) {print "\033[37m" $0 "\033[0m"}
                else if ($0 ~ /^[[:space:]]+[a-zA-Z0-9_\/-]+\.go:[0-9]+:/) {print "\033[1;31m" $0 "\033[0m"}
                else {print $0}
            }'
            test_exit_code=$?
            fi
            total=$(go test -list . $found_files | grep -c 'Test')
            echo "\n\033[1;36mTotal scenarios run: $total\033[0m"
            echo "\n\033[1;35m==== Go Test Results Summary Complete ====\033[0m"
            exit $test_exit_code
        else
            # Run all tests from both payment_gateway and widget directories
            echo "Running all Go tests from payment_gateway and widget directories..."
            go test -v ./payment_gateway/... ./widget/... 2>&1 | awk '{
                if ($0 ~ /=== RUN/) {print "\n\033[36m" $0 "\033[0m"}
                else if ($0 ~ /--- PASS/) {print "\033[32m" $0 "\033[0m"}
                else if ($0 ~ /--- FAIL/) {print "\033[31m" $0 "\033[0m"}
                else if ($0 ~ /--- SKIP/) {print "\033[33m" $0 "\033[0m"}
                else if ($0 ~ /Assertion passed/) {print "\033[37m" $0 "\033[0m"}
                else if ($0 ~ /^[[:space:]]+[a-zA-Z0-9_\/-]+\.go:[0-9]+:/) {print "\033[1;31m" $0 "\033[0m"}
                else {print $0}
            }'
            test_exit_code=$?
            total=$(go test -list . ./payment_gateway/... ./widget/... | grep -c 'Test')
            echo "\n\033[1;36mTotal scenarios run: $total\033[0m"
            echo "\n\033[1;35m==== Go Test Results Summary Complete ====\033[0m"
            exit $test_exit_code
        fi
    else
        echo "Error: go.mod file not found in test/go directory"
        exit 1
    fi
    
    cd ../..
}

# Always execute the runner
run_go_runner "$@"
