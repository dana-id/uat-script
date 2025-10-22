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
    
    # Ensure environment variables are loaded from root .env file
    if [ -f "../.env" ]; then
        set -a
        . ../.env
        set +a
    fi
    
    # Change to the test directory and run tests
    cd test/go
    
    # Run go mod tidy only if go.mod exists
    if [ -f "go.mod" ]; then
        # Update dana Go client to the latest version
        go get -u github.com/dana-id/dana-go
        go get -u github.com/playwright-community/playwright-go
        go mod tidy
        go clean -testcache
        if [ -n "$module" ]; then
            echo "Running Go test file(s) for module/pattern: $module ${subCase:-}"  
            
            # First check if module matches any existing directory
            if [ -d "./$module" ] && find "./$module" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .; then
                test_dir="./$module"
                if [ -n "$subCase" ]; then
                    # Filter by subCase pattern in file names
                    found_files=$(find "$test_dir" -type f -name "*${subCase}*.go")
                else
                    # Run all test files in the module
                    found_files=$(find "$test_dir" -type f -name "*_test.go")
                fi
            else
                # Treat module as a file name pattern across directories
                # Automatically discover available business directories
                business_types=""
                for dir in */; do
                    dir_name=$(basename "$dir")
                    # Only include directories that contain test files
                    if [ -d "$dir_name" ] && find "$dir_name" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .; then
                        business_types="$business_types $dir_name"
                    fi
                done
                business_types=$(echo $business_types | xargs)  # trim whitespace
                
                found_in_all=true
                all_files=""
                
                # Check each business type and collect files
                for business in $business_types; do
                    files=$(find ./$business -type f -name "*${module}*.go" 2>/dev/null || true)
                    if [ -z "$files" ]; then
                        found_in_all=false
                    else
                        eval "${business}_files=\"$files\""
                        all_files="$all_files $files"
                    fi
                done

                if [ "$found_in_all" = true ]; then
                    # Found files in all directories - run them separately
                    echo "Found matching files in all directories, running separately:"
                    
                    # Display files for each business type
                    for business in $business_types; do
                        eval "files=\$${business}_files"
                        if [ -n "$files" ]; then
                            echo "$(echo $business | tr '_' ' ' | sed 's/\b\w/\U&/g') files:"
                            for f in $files; do
                                echo "  - $f"
                            done
                        fi
                    done
                    
                    # Run tests for each business type
                    exit_codes=""
                    temp_files=""
                    for business in $business_types; do
                        eval "files=\$${business}_files"
                        if [ -n "$files" ]; then
                            business_name=$(echo $business | tr '_' ' ' | sed 's/\b\w/\U&/g')
                            temp_file="/tmp/${business}_test_output_$$.txt"
                            temp_files="$temp_files $temp_file"
                            
                            echo "\n=== Running $business_name Tests ==="
                            go test -v $files > $temp_file 2>&1
                            test_exit_code=$?
                            cat $temp_file | awk '{
                                if ($0 ~ /=== RUN/) {print "\n" $0 ""}
                                else if ($0 ~ /--- PASS/) {print "" $0 ""}
                                else if ($0 ~ /--- FAIL/) {print "" $0 ""}
                                else if ($0 ~ /--- SKIP/) {print "" $0 ""}
                                else if ($0 ~ /Assertion passed/) {print "\033[37m" $0 ""}
                                else if ($0 ~ /^[[:space:]]+[a-zA-Z0-9_\/-]+\.go:[0-9]+:/) {print "\033[1;31m" $0 ""}
                                else {print $0}
                            }'
                            exit_codes="$exit_codes $test_exit_code"
                        fi
                    done
                    
                    # Count and display combined results
                    total_passed=0
                    total_failed=0
                    total_skipped=0
                    
                    for temp_file in $temp_files; do
                        if [ -f "$temp_file" ]; then
                            passed=$(grep "^--- PASS" $temp_file 2>/dev/null | wc -l | tr -d ' ')
                            failed=$(grep "^--- FAIL" $temp_file 2>/dev/null | wc -l | tr -d ' ')
                            skipped=$(grep "^--- SKIP" $temp_file 2>/dev/null | wc -l | tr -d ' ')
                            
                            total_passed=$((total_passed + passed))
                            total_failed=$((total_failed + failed))
                            total_skipped=$((total_skipped + skipped))
                        fi
                    done
                    
                    total_tests=$((total_passed + total_failed + total_skipped))
                    
                    echo "=== Combined Test Results Summary ==="
                    echo "Passed: $total_passed"
                    echo "Failed: $total_failed"
                    echo "Skipped: $total_skipped"
                    echo "Total: $total_tests"
                    
                    # Cleanup
                    rm -f $temp_files

                    # Combine found files for total count
                    found_files="$all_files"

                    # Calculate total tests from all directories
                    total=0
                    for business in $business_types; do
                        eval "files=\$${business}_files"
                        if [ -n "$files" ]; then
                            business_total=$(go test -list . $files 2>/dev/null | grep -c 'Test' || echo "0")
                            total=$((total + business_total))
                        fi
                    done

                    echo "Total scenarios run: $total"
                    echo "==== Go Test Results Summary Complete ===="
                    
                    # Exit with error only if there are actual failures (not skips)
                    if [ $total_failed -gt 0 ]; then
                        exit 1
                    fi
                    exit 0
                else
                    # Only found files in some directories, set found_files to the combined result
                    found_files="$all_files"
                fi
            fi
            
            # Only run normal single-directory test if we didn't handle multi-directory case above
            if [ "$found_in_all" != true ]; then
            if [ -z "$found_files" ]; then
                echo "ERROR: No Go test files were found containing file pattern: $module" >&2
                exit 1
            fi
            echo "Scenarios (test files) to run:"
            for f in $found_files; do
                echo "  - $f"
            done
            
            # Run tests and show output
            echo "Running tests..."
            echo ""
            
            # Run tests directly to show real-time output
            go test -v $found_files
            test_exit_code=$?
            
            echo ""
            echo "=== Test Execution Complete ==="
            
            # Run again to capture results for summary (quick second run for counting)
            temp_output="/tmp/test_output_$$.txt"
            go test -v $found_files > "$temp_output" 2>&1
            
            # Count results from output
            if [ -f "$temp_output" ]; then
                passed=$(grep "^--- PASS" "$temp_output" | wc -l | tr -d ' ')
                failed=$(grep "^--- FAIL" "$temp_output" | wc -l | tr -d ' ')
                skipped=$(grep "^--- SKIP" "$temp_output" | wc -l | tr -d ' ')
                total=$((passed + failed + skipped))
                
                echo "=== Test Results Summary ==="
                echo "Passed: $passed"
                echo "Failed: $failed"
                echo "Skipped: $skipped"
                echo "Total: $total"
                
                rm -f "$temp_output"
                
                # Only exit with error if there are actual failures
                if [ $failed -gt 0 ]; then
                    exit 1
                fi
            else
                # Use the original exit code if we can't parse results
                exit $test_exit_code
            fi
            total=$(go test -list . $found_files | grep -c 'Test')
            echo "Total scenarios run: $total"
            echo "==== Go Test Results Summary Complete ===="
            exit 0
            fi
        else
            # Run all tests from all discovered business directories
            # Automatically discover available business directories
            test_dirs=""
            for dir in */; do
                dir_name=$(basename "$dir")
                # Only include directories that contain test files
                if [ -d "$dir_name" ] && find "$dir_name" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .; then
                    test_dirs="$test_dirs ./$dir_name/..."
                fi
            done
            test_dirs=$(echo $test_dirs | xargs)  # trim whitespace
            
            echo "Running all Go tests from discovered directories: $(echo $test_dirs | sed 's|./||g' | sed 's|/\.\.\.||g')..."
            go test -v $test_dirs > /tmp/all_test_output_$$.txt 2>&1
            test_exit_code=$?
            cat /tmp/all_test_output_$$.txt | awk '{
                if ($0 ~ /=== RUN/) {print "\n" $0 ""}
                else if ($0 ~ /--- PASS/) {print "" $0 ""}
                else if ($0 ~ /--- FAIL/) {print "" $0 ""}
                else if ($0 ~ /--- SKIP/) {print "" $0 ""}
                else if ($0 ~ /Assertion passed/) {print "\033[37m" $0 ""}
                else if ($0 ~ /^[[:space:]]+[a-zA-Z0-9_\/-]+\.go:[0-9]+:/) {print "\033[1;31m" $0 ""}
                else {print $0}
            }'
            
            # Count and display results
            if [ -f "/tmp/all_test_output_$$.txt" ]; then
                passed=$(grep "^--- PASS" /tmp/all_test_output_$$.txt | wc -l | tr -d ' ')
                failed=$(grep "^--- FAIL" /tmp/all_test_output_$$.txt | wc -l | tr -d ' ')
                skipped=$(grep "^--- SKIP" /tmp/all_test_output_$$.txt | wc -l | tr -d ' ')
                total=$((passed + failed + skipped))
                
                echo "=== Test Results Summary ==="
                echo "Passed: $passed"
                echo "Failed: $failed"
                echo "Skipped: $skipped"
                echo "Total: $total"
                
                rm -f /tmp/all_test_output_$$.txt
                
                # Only exit with error if there are actual failures
                if [ $failed -gt 0 ]; then
                    exit 1
                fi
            fi
            total=$(go test -list . $test_dirs | grep -c 'Test')
            echo "Total scenarios run: $total"
            echo "==== Go Test Results Summary Complete ===="
            exit 0
        fi
    else
        echo "Error: go.mod file not found in test/go directory"
        exit 1
    fi
    
    cd ../..
}

# Always execute the runner
run_go_runner "$@"