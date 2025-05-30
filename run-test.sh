#!/bin/bash

# Fail on error
set -e

RUNNER_PATH=runner/

INTERPRETER=$1

main() {
    case $INTERPRETER in
    "python")
        run_python_runner "$1"
        ;;
    "go")
        run_go_runner
        ;;
    "node")
        run_node_runner
        ;;
    *)
        echo "Invalid option. Please choose a valid interpreter."
        ;;
    esac
}

# Function to run the Python script
run_python_runner(){
    caseName=$1
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
    
    export PYTHONPATH=$PYTHONPATH:$(pwd)/runner/python
    
    # Support running a specific scenario if provided as caseName
    if [ -n "$caseName" ]; then
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
    
    # Run the tests
    echo "Running tests..."
    npm test
    
    cd ../..
}

# Function to run the Go tests
run_go_runner(){
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
        go test -v ./payment_gateway/...
    else
        echo "Error: go.mod file not found in test/go directory"
        exit 1
    fi
    
    cd ../..
}

set -a
source .env
set +a
main "$2"
