#!/bin/sh

# Fail on error
set -e

run_go_runner(){
    # Note: pipefail is not available in sh, using simpler approach
    
    caseName=$1
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
        if [ -n "$caseName" ]; then
            echo "Running Go test file(s) containing: $caseName"
            found_files=$(find ./payment_gateway -type f -name "*${caseName}*.go")
            if [ -z "$found_files" ]; then
                echo "\033[31mERROR: No Go test files were found containing file pattern: $caseName\033[0m" >&2
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
            total=$(go test -list . $found_files | grep -c 'Test')
            echo "\n\033[1;36mTotal scenarios run: $total\033[0m"
            echo "\n\033[1;35m==== Go Test Results Summary Complete ====\033[0m"
            exit $test_exit_code
        else
            go test -v ./payment_gateway/... 2>&1 | awk '{
                if ($0 ~ /=== RUN/) {print "\n\033[36m" $0 "\033[0m"}
                else if ($0 ~ /--- PASS/) {print "\033[32m" $0 "\033[0m"}
                else if ($0 ~ /--- FAIL/) {print "\033[31m" $0 "\033[0m"}
                else if ($0 ~ /--- SKIP/) {print "\033[33m" $0 "\033[0m"}
                else if ($0 ~ /Assertion passed/) {print "\033[37m" $0 "\033[0m"}
                else if ($0 ~ /^[[:space:]]+[a-zA-Z0-9_\/-]+\.go:[0-9]+:/) {print "\033[1;31m" $0 "\033[0m"}
                else {print $0}
            }'
            test_exit_code=$?
            total=$(go test -list . ./payment_gateway/... | grep -c 'Test')
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
