#!/bin/sh

# Fail on error
set -e

run_go_runner(){
    # Adjust parameter mapping based on how run-test.sh calls this script
    # run-test.sh calls: sh run-test-go.sh "$2" "$3"
    # So: $1=module, $2=subCase
    module=$1       # e.g., "widget" or "payment_gateway"
    subCase=$2      # e.g., "cancel" (optional)
    
    if ! command -v go > /dev/null 2>&1; then
        echo "ERROR: Go not available in this system. Please install Go."
        exit 1
    fi
    
    echo "Running Go tests..."
    go version
    
    # Ensure environment variables are loaded from root .env file
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
    
    # Change to the test directory and run tests
    cd test/go
    
    # Run go mod tidy only if go.mod exists
    if [ -f "go.mod" ]; then
        echo "Updating Go dependencies..."
        go get -u github.com/dana-id/dana-go > /dev/null 2>&1 || true
        go get -u github.com/playwright-community/playwright-go > /dev/null 2>&1 || true
        go mod tidy > /dev/null 2>&1
        go clean -testcache > /dev/null 2>&1
        
        if [ -n "$module" ]; then
            echo "Running Go test file(s) for module: $module ${subCase:-}"  
            
            # Check if module is a directory that exists
            if [ -d "./$module" ] && find "./$module" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .; then
                test_dir="./$module"
                if [ -n "$subCase" ]; then
                    # Filter by subCase pattern in file names - handle both with and without _test suffix
                    if echo "$subCase" | grep -q "_test$"; then
                        # subCase already has _test suffix (e.g., "cancel_order_test")
                        found_files=$(find "$test_dir" -type f -name "${subCase}.go" 2>/dev/null || true)
                    else
                        # subCase doesn't have _test suffix (e.g., "cancel")
                        found_files=$(find "$test_dir" -type f -name "*${subCase}*_test.go" 2>/dev/null || true)
                    fi
                else
                    # Run all test files in the module
                    found_files=$(find "$test_dir" -type f -name "*_test.go" 2>/dev/null || true)
                fi
                
                if [ -z "$found_files" ]; then
                    echo "ERROR: No Go test files were found in directory: $module" >&2
                    exit 1
                fi
                
                echo "Test files to run:"
                for f in $found_files; do
                    echo "  - $f"
                done
                echo ""
                
                # Run tests individually to avoid hanging
                total_passed=0
                total_failed=0
                total_tests=0
                failed_files=""
                
                for test_file in $found_files; do
                    test_name=$(basename "$test_file" .go)
                    echo "=== Running $test_name ==="
                    
                    if go test -v -timeout=300s "$test_file" 2>&1; then
                        echo "✅ $test_name PASSED"
                        total_passed=$((total_passed + 1))
                    else
                        echo "❌ $test_name FAILED"
                        total_failed=$((total_failed + 1))
                        failed_files="$failed_files $test_name"
                    fi
                    total_tests=$((total_tests + 1))
                    echo ""
                done
                
                echo "=== Test Results Summary ==="
                echo "Total test files: $total_tests"
                echo "Passed: $total_passed"
                echo "Failed: $total_failed"
                
                if [ $total_failed -gt 0 ]; then
                    echo ""
                    echo "Failed test files:"
                    for file in $failed_files; do
                        echo "  - $file"
                    done
                    echo ""
                    echo "❌ Some tests failed"
                    exit 1
                else
                    echo ""
                    echo "✅ All tests passed!"
                fi
                
            else
                echo "ERROR: Directory '$module' not found or contains no test files" >&2
                exit 1
            fi
        else
            # Run all tests from all discovered business directories
            test_dirs=""
            for dir in */; do
                dir_name=$(basename "$dir")
                # Only include directories that contain test files
                if [ -d "$dir_name" ] && find "$dir_name" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .; then
                    test_dirs="$test_dirs ./$dir_name"
                fi
            done
            
            if [ -z "$test_dirs" ]; then
                echo "ERROR: No test directories found"
                exit 1
            fi
            
            echo "Running all Go tests from directories: $(echo $test_dirs | sed 's|./||g')..."
            echo ""
            
            total_passed=0
            total_failed=0
            total_dirs=0
            failed_dirs=""
            
            for test_dir in $test_dirs; do
                dir_name=$(basename "$test_dir")
                echo "=== Running tests in $dir_name ==="
                
                if go test -v -timeout=60s "$test_dir/..." 2>&1; then
                    echo "✅ $dir_name tests PASSED"
                    total_passed=$((total_passed + 1))
                else
                    echo "❌ $dir_name tests FAILED"
                    total_failed=$((total_failed + 1))
                    failed_dirs="$failed_dirs $dir_name"
                fi
                total_dirs=$((total_dirs + 1))
                echo ""
            done
            
            echo "=== Overall Test Results Summary ==="
            echo "Total directories: $total_dirs"
            echo "Passed: $total_passed"
            echo "Failed: $total_failed"
            
            if [ $total_failed -gt 0 ]; then
                echo ""
                echo "Failed directories:"
                for dir in $failed_dirs; do
                    echo "  - $dir"
                done
                echo ""
                echo "❌ Some tests failed"
                exit 1
            else
                echo ""
                echo "✅ All tests passed!"
            fi
        fi
    else
        echo "Error: go.mod file not found in test/go directory"
        exit 1
    fi
}

# Always execute the runner
run_go_runner "$@"