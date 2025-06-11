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
        run_node_runner "$1" "$2" "$3"
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
    # Check for python3 or python command
    if command -v python3 &> /dev/null; then
        PYTHON_CMD="python3"
    elif command -v python &> /dev/null; then
        PYTHON_CMD="python"
    else
        echo "Python not available in this system. Please install Python 3."
        exit 1
    fi
    
    $PYTHON_CMD --version
    $PYTHON_CMD -m venv venv
    . venv/bin/activate

    # Install packages from requirements.txt
    $PYTHON_CMD -m pip install --upgrade pip
    
    $PYTHON_CMD -m pip install --upgrade -r test/python/requirements.txt
    
    # Install Playwright browsers
    $PYTHON_CMD -m playwright install --with-deps chromium
    
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
        $PYTHON_CMD -m pytest -v -s "$test_path" -k "$caseName"
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
        $PYTHON_CMD -m pytest -v -s -k "$caseName"
        exit_code=$?
        set -e
        if [ $exit_code -eq 5 ]; then
            echo "\033[31mERROR: No tests were collected for case name: $caseName\033[0m" >&2
            exit 1
        fi
        exit $exit_code
    else
        $PYTHON_CMD -m pytest -v -s
    fi
}

# Function to run the Javascript script
run_node_runner(){
    interpreter=$1
    folderName=$2
    caseName=$3
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
    npm install --save dana-node dotenv
    npm install --save-dev jest ts-jest
    
    # Warn if ts-jest is not installed
    if ! npm list --depth=0 | grep -q ts-jest; then
        echo "\033[33mWARNING: ts-jest is not installed. TypeScript tests may not run.\033[0m"
    fi
    
    # Support running by folder and/or scenario name
    if [ -n "$folderName" ] && [ -n "$caseName" ]; then
        # Run specific scenario in a specific folder
        test_path="$folderName"
        if [ ! -d "$test_path" ]; then
            echo "\033[31mERROR: Folder not found: $test_path\033[0m" >&2
            exit 1
        fi
        pattern="$folderName/.*$caseName.*\\.[tj]s$"
        echo "Pattern: $pattern"
        echo "Matching files:"
        matches=$(find "$folderName" -type f \( -name "*$caseName*.ts" -o -name "*$caseName*.js" \))
        if [ -z "$matches" ]; then
            echo "\033[31mERROR: No files matched pattern: $pattern\033[0m" >&2
            exit 1
        fi
        echo "$matches"
        set +e
        npx jest --color --testPathPattern="$pattern"
        exit_code=$?
        set -e
        if [ $exit_code -eq 1 ]; then
            echo "\033[31mERROR: No test files were collected for case name: $caseName in folder: $folderName\033[0m" >&2
            exit 1
        fi
        exit $exit_code
    elif [ -n "$folderName" ]; then
        # Run all tests in the specified folder
        test_path="$folderName"
        if [ ! -d "$test_path" ]; then
            echo "\033[31mERROR: Folder not found: $test_path\033[0m" >&2
            exit 1
        fi
        set +e
        npx jest --color --testPathPattern="$folderName/"
        exit_code=$?
        set -e
        if [ $exit_code -eq 1 ]; then
            echo "\033[31mERROR: No test files were collected in folder: $folderName\033[0m" >&2
            exit 1
        fi
        exit $exit_code
    elif [ -n "$caseName" ]; then
        # Fallback: run by scenario name in all tests
        set +e
        npx jest --color --testPathPattern="$caseName"
        exit_code=$?
        set -e
        if [ $exit_code -eq 1 ]; then
            echo "\033[31mERROR: No test files were collected for case name: $caseName\033[0m" >&2
            exit 1
        fi
        exit $exit_code
    else
        npm test
    fi
    
    cd ../..
}

# Function to run the Go tests
run_go_runner(){
    # Enable pipefail to make the pipeline exit with the first command that fails
    set -o pipefail
    
    caseName=$1
    if ! command go version &> /dev/null; then
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

set -a
. ./.env
set +a
main "$@"
