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
        sh "$RUNNERS_DIR/run-test-python.sh" "$2" "$3" "$4"
        ;;
    "go")
        sh "$RUNNERS_DIR/run-test-go.sh" "$2" "$3" "$4"
        ;;
    "node")
        sh "$RUNNERS_DIR/run-test-node.sh" "$2" "$3" "$4"
        ;;
    "php")
        sh "$RUNNERS_DIR/run-test-php.sh" "$2" "$3" "$4"
        ;;
    "java")
        sh "$RUNNERS_DIR/run-test-java.sh" "$2" "$3" "$4"
        ;;
    "help" | "-h" | "--help")
        display_help
        ;;
    "list" | "-ls" | "--list")
        display_list_solutions "$2" "$3"
        ;;
    *)
        echo "Invalid option. Please choose a valid interpreter."
        display_help
        exit 1
        ;;
    esac
}

display_help() {
    echo "Usage: ./run-test.sh <command> [options]"
    echo ""
    echo "Commands:"
    echo "  <language> [folder] [test_case] [run_pattern]   Run tests for specific language"
    echo "  list <language> [folder]          List available folders or APIs"
    echo "  help                              Show this help message"
    echo ""
    echo "Languages:"
    echo "  python    Run Python tests"
    echo "  node      Run Node.js tests"
    echo "  php       Run PHP tests"
    echo "  go        Run Go tests"
    echo "  java      Run Java tests"
    echo ""
    echo "List Command Usage:"
    echo "  list <language>                   List all folders for a language"
    echo "  list <language> <folder>          List specific APIs/tests in folder"
    echo "  --list <language> <folder>        Alternative syntax"
    echo "  -ls <language> <folder>           Short syntax"
    echo ""
    echo "Examples - Running Tests:"
    echo "  ./run-test.sh python                     # Run all Python tests"
    echo "  ./run-test.sh node payment_gateway       # Run Node.js tests in payment_gateway folder"
    echo "  ./run-test.sh php payment_gateway CancelOrderTest # Run specific PHP test"
    echo "  ./run-test.sh java paymentgateway CreateOrderTest # Run specific Java test"
    echo "  ./run-test.sh go payment_gateway '' 'TestCreateOrderRedirectScenario'                    # Run one Go test"
    echo "  ./run-test.sh go payment_gateway '' 'TestCreateOrderRedirectScenario|TestQueryPaymentCreatedOrder'  # Run multiple Go tests"
    echo "  ./run-test.sh python payment_gateway '' 'test_create_order_redirect'   # Run one Python test by -k pattern"
    echo "  ./run-test.sh python payment_gateway '' 'test_one|test_two'              # Run multiple Python tests (| → or)"
    echo "  ./run-test.sh node payment_gateway '' 'CreateOrderRedirect'            # Run Node tests matching name"
    echo "  ./run-test.sh node payment_gateway '' 'CreateOrderRedirect|query payment with status created'  # Multiple (use test titles, not Go names)"
    echo "  ./run-test.sh java paymentgateway CreateOrderTest createOrderRedirectScenario  # Run one Java test method"
    echo "  ./run-test.sh java paymentgateway CreateOrderTest 'method1|method2'    # Run multiple Java methods (| → +)"
    echo "  ./run-test.sh php payment_gateway '' 'testCreateOrderRedirect'         # Run PHP tests matching --filter"
    echo "  ./run-test.sh php payment_gateway '' 'testOne|testTwo'                 # Run multiple PHP tests (| = regex OR)"
    echo ""
    echo "Examples - Listing:"
    echo "  ./run-test.sh list python                # List Python test folders"
    echo "  ./run-test.sh list java                  # List Java test folders"
    echo "  ./run-test.sh list java paymentgateway   # List Java Payment Gateway APIs"
    echo "  ./run-test.sh list php payment_gateway   # List PHP Payment Gateway tests"
    echo "  ./run-test.sh --list node widget         # List Node.js Widget tests"
}

display_list_solutions() {
    language=$1
    solution=$2
    language_lower=$(echo "$language" | tr '[:upper:]' '[:lower:]')
    solution_lower=$(echo "$solution" | tr '[:upper:]' '[:lower:]')
    case $language_lower in
        "python"|"node"|"php"|"go")
            # Valid language, continue
            cd test/$language_lower || {
                echo "Invalid language specified solution.";
                echo "Example: ./run-test.sh list <language_lower>";
                exit 1; 
                }
            if [ -n "$solution_lower" ]; then
                if [ ! -d "$solution_lower" ]; then
                    echo "Test not found: $solution_lower"
                    exit 1
                fi
                cd "$solution_lower"
                echo "Available test files in $solution_lower $language_lower:"
                # List test files with multiple patterns safely
                (ls *test*.* 2>/dev/null || true)
                (ls *Test*.* 2>/dev/null || true)
                (ls test_*.* 2>/dev/null || true)
                (ls Test*.* 2>/dev/null || true)
            else
                ls -d */ | grep -v "^helper/" | grep -v "^node_modules/" | grep -v "^vendor/" | grep -v "^__pycache__/"
            fi
            exit 0
            ;;
        "java")
            cd test/java/src/test/java/id/dana || {
                echo "Java test directory not found"
                exit 1
            }
            
            if [ -n "$solution_lower" ]; then
                if [ ! -d "$solution_lower" ]; then
                    echo "Test not found: $solution_lower"
                    exit 1
                fi
                # List specific APIs in the Java solution folder
                cd "$solution_lower"
                echo "Available test files in $solution_lower $language_lower:"
                # List test files with multiple patterns safely
                (ls *test*.* 2>/dev/null || true)
                (ls *Test*.* 2>/dev/null || true)
                (ls test_*.* 2>/dev/null || true)
                (ls Test*.* 2>/dev/null || true)
            else
                echo "Available Java test folders:"
                ls -d */ 2>/dev/null | grep -v "^util/" | grep -v "^interceptor/" | sed 's|/$||' || {
                    echo "No Java test folders found"
                }
            fi
            exit 0
            ;;
        *)
            echo "Invalid language specified: $language"
            exit 1
            ;;
    esac
}

# Call main function with all arguments
main "$@"
