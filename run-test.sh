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
    python3 -m pip install -r dependency/python-requirements.txt
    
    export PYTHONPATH=$PYTHONPATH:$(pwd)/runner/python
    pytest -v -s
}

# Function to run the Javascript script
run_node_runner(){
    node $RUNNER_PATH"node/RunnerJavascript.js"
}

set -a
source .env
set +a
main
