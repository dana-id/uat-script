#!/bin/sh

# Fail on error
set -e

run_java_runner(){
    folderName=$1
    caseName=$2
    
    # Check if Java is installed
    if ! command java -version &> /dev/null; then
        echo "Java not available in this system. Please install Java."
        exit 0 
    fi

    # Check if Maven is installed
    if ! command mvn -version &> /dev/null; then
        echo "Maven not available in this system. Please install Maven."
        exit 0
    fi

    echo "Running Java tests..."
    java -version
    mvn -version
    
    # Change to the Java test directory
    cd src/test/java
    
    # Install dependencies
    echo "Installing dependencies..."
    mvn clean install
    
    # Support running by folder and/or scenario name
    if [ -n "$folderName" ] && [ -n "$caseName" ]; then
        # Run specific test case in a specific folder
        echo "Running Java test '$caseName' in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi
        
        # Find all test classes in the folder that match the pattern
        TEST_FILES=$(find $folderName -type f -name "*$caseName*.java" 2>/dev/null)
        
        if [ -z "$TEST_FILES" ]; then
            echo "\033[31mERROR: No test classes found matching pattern '$caseName' in folder '$folderName'\033[0m" >&2
            exit 1
        fi
        
        echo "Running the following test files:"
        echo $TEST_FILES
        
        mvn test -Dtest="$folderName.$caseName"
    elif [ -n "$folderName" ]; then
        # Run all tests in a specific folder
        echo "Running all Java tests in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi
        mvn test -Dtest="$folderName.*"
    elif [ -n "$caseName" ]; then
        # Run a specific test case across all folders
        echo "Running Java test with pattern '$caseName' in all folders..."
        mvn test -Dtest="$caseName"
    else
        # Run all tests
        echo "Running all Java tests..."
        mvn test
    fi
    
    # Change back to project root
    cd ../..
}

# Always execute the runner
run_java_runner "$@"
