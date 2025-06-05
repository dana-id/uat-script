#!/bin/bash

# Fail on error
set -e

RUNNER_PATH=runner/

INTERPRETER=$1

main() {
    case $INTERPRETER in
    "python")
        run_python_runner "$@"
        ;;
    "go")
        run_go_runner "$2"
        ;;
    "node")
        run_node_runner "$1" "$2"
        ;;
    *)
        echo "Invalid option. Please choose a valid interpreter."
        ;;
    esac
}

# Function to run the Python script
run_python_runner(){
    interpreter=$1
    folderName=$2
    caseName=$3
    if ! command python3 --version &> /dev/null; then
        echo "Python not available in this system. Please install Python 3."
        exit 0 
    fi
    python3 --version
    python3 -m venv venv
    source venv/bin/activate

    # Install packages from requirements.txt
    python3 -m pip install --upgrade pip
    
    python3 -m pip install --upgrade -r test/python/requirements.txt
    
    export PYTHONPATH=$PYTHONPATH:$(pwd)/test/python:$(pwd)/runner/python
    
    # Support running by folder and/or scenario name
    if [ -n "$folderName" ] && [ -n "$caseName" ]; then
        # Run specific scenario in a specific folder
        test_path="test/python/$folderName"
        if [ ! -d "$test_path" ]; then
            echo "\033[31mERROR: Folder not found: $test_path\033[0m" >&2
            exit 1
        fi
        set +e
        pytest -v -s "$test_path" -k "$caseName"
        exit_code=$?
        set -e
        if [ $exit_code -eq 5 ]; then
            echo "\033[31mERROR: No tests were collected for case name: $caseName in folder: $folderName\033[0m" >&2
            exit 1
        fi
        exit $exit_code
    elif [ -n "$folderName" ]; then
        # Run all tests in the specified folder
        test_path="test/python/$folderName"
        if [ ! -d "$test_path" ]; then
            echo "\033[31mERROR: Folder not found: $test_path\033[0m" >&2
            exit 1
        fi
        pytest -v -s "$test_path"
    elif [ -n "$caseName" ]; then
        # Fallback: run by scenario name in all tests
        set +e
        pytest -v -s -k "$caseName"
        exit_code=$?
        set -e
        if [ $exit_code -eq 5 ]; then
            echo "\033[31mERROR: No tests were collected for case name: $caseName\033[0m" >&2
            exit 1
        fi
        exit $exit_code
    else
        pytest -v -s
    fi
}

# Function to run the Javascript script
run_node_runner(){
    interpreter=$1
    caseName=$2
    if ! command node --version &> /dev/null; then
        echo "Node.js not available in this system. Please install Node.js."
        exit 0 
    fi
    
    echo "Running Node.js tests..."
    node --version
    npm --version
    
    # Change to the Node.js test directory
    cd test/node
    
    # Create package.json if it doesn't exist
    if [ ! -f "package.json" ]; then
        echo "Initializing Node.js project..."
        npm init -y
    fi
    
    # Install dependencies
    echo "Installing dependencies..."
    npm install --save dana-node-api-client dotenv
    npm install --save-dev jest
    
    # Run the tests, support running a specific scenario if provided as caseName (by file name)
    if [ -n "$caseName" ]; then
        echo "Running Node.js test file(s) matching: $caseName"
        npx jest --color --testPathPattern="$caseName"
        exit_code=$?
        if [ $exit_code -eq 0 ]; then
            : # success
        elif [ $exit_code -eq 1 ]; then
            echo "\033[31mERROR: No test files were collected containing file pattern: $caseName\033[0m" >&2
            exit 1
        else
            exit $exit_code
        fi
    else
        npm test
    fi
    
    cd ../..
}

# Function to run the Go tests
run_go_runner(){
    caseName=$1
    if ! command go version &> /dev/null; then
        echo "Go not available in this system. Please install Go."
        exit 0 
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
        go get -u github.com/dana-id/go_client
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

set -a
source .env
set +a
main "$@"
