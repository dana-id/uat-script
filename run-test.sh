#!/bin/bash

# Fail on error
set -e

RUNNER_PATH=runner/

INTERPRETER=$1

main() {
    case $INTERPRETER in
    "python")
        run_python_runner
        ;;
    "go")
        run_go_runner
        ;;
    "java")
        run_node_runner
        ;;
    "javascript")
        run_node_runner
        ;;
    *)
        echo "Invalid option. Please choose a valid interpreter."
        ;;
esac
}

# Function to run the Python script
run_python_runner(){
    if ! command python3 --version &> /dev/null; then
        echo "Python not available in this system. Please install Python 3."
        exit 0 
    fi
    python3 --version
    python3 -m venv venv
    source venv/bin/activate

    # Install packages from requirements.txt
    python3 -m pip install --upgrade pip
    
    # Check if requirements.txt exists in test/python
    if [ -f "test/python/requirements.txt" ]; then
        python3 -m pip install -r test/python/requirements.txt
    else
        # Fallback to the old path for backward compatibility
        python3 -m pip install -r dependency/python-requirements.txt
    fi
    
    export PYTHONPATH=$PYTHONPATH:$(pwd)/runner/python
    pytest -v -s
}

# Function to run the Javascript script
run_node_runner(){
    echo "Javascript runner not implemented yet"
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
        go mod tidy
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
main
