#!/bin/bash

# Fail on error

set -e

INTERPRETER=$1

# Get script directory for absolute path resolution
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RUNNERS_DIR="$SCRIPT_DIR/runners"

# Ensure script files are executable
chmod +x "./runners"/*.sh 2>/dev/null || true

main() {
    # Make sure env vars are available to scripts
    set -a
    . ./.env 2>/dev/null || true
    set +a
    
    case $INTERPRETER in
    "python")
        sh "$RUNNERS_DIR/run-test-python.sh" "$2" "$3"
        ;;
    "go")
        sh "$RUNNERS_DIR/run-test-go.sh" "$2" "$3"
        ;;
    "node")
        sh "$RUNNERS_DIR/run-test-node.sh" "$2" "$3"
        ;;
    "php")
        sh "$RUNNERS_DIR/run-test-php.sh" "$2" "$3"
        ;;
    "help" | "-h" | "--help")
        display_help
        ;;
    "list" | "-ls" | "--list")
        display_list_solutions "$2"
        ;;
    *)
        echo "Invalid option. Please choose a valid interpreter."
        display_help
        exit 1
        ;;
    esac
}

display_help() {
    echo "Usage: ./run-test.sh <language> [folder] [test_case]"
    echo ""
    echo "Languages:"
    echo "  python    Run Python tests"
    echo "  node      Run Node.js tests"
    echo "  php       Run PHP tests"
    echo "  go        Run Go tests"
    echo ""
    echo "Parameters:"
    echo "  folder     Optional: Specific folder to run tests from"
    echo "  test_case  Optional: Specific test case to run"
    echo ""
    echo "Examples:"
    echo "  ./run-test.sh python                 # Run all Python tests"
    echo "  ./run-test.sh node payment_gateway   # Run Node.js tests in payment_gateway folder"
    echo "  ./run-test.sh php payment_gateway CancelOrderTest # Run specific PHP test"
}

display_list_solutions() {
    language=$1
    language_lower=$(echo "$language" | tr '[:upper:]' '[:lower:]')
    case $language_lower in
        "python"|"node"|"php"|"go")
            # Valid language, continue
            cd test/$language_lower || {
                echo "Invalid language specified solution.";
                echo "Example: ./run-test.sh list <language_lower>";
                exit 1; 
                }
            ls -d */ | grep -v "^helper/" | grep -v "^node_modules/" | grep -v "^vendor/" | grep -v "^__pycache__/"
            ;;
        "java")
            cd test/java/src/test/java/id/dana
            ls -d */ | grep -v "^util/" | grep -v "^interceptor/"
            exit 1
            ;;
        *)
            echo "Invalid language specified: $language"
            exit 1
            ;;
    esac
}

# Call main function with all arguments
main "$@"
