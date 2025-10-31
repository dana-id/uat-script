#!/bin/sh

# Fail on error
set -e

run_python_runner(){
    folderName=$1
    caseName=$2
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

# Always execute the runner
run_python_runner "$@" 
