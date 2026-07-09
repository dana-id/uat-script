# Node test orchestration. Requires:
#   - runners/node/common.sh sourced first
#   - node_run_jest(title_pattern, ...jest args) defined by caller
#   - NODE_MANDATORY_ONLY=true|false

run_node_runner_main() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"

    setup_node_env "$folderName" "$caseName" "$runPattern"

    exit_code=0

    if [ -n "$runPattern" ]; then
        if [ -n "$folderName" ]; then
            echo "Running Node.js tests matching '$runPattern' in folder '$folderName'..."
            if [ ! -d "$folderName" ]; then
                echo "ERROR: Folder not found: $folderName" >&2
                exit 1
            fi
            if [ -n "$caseName" ]; then
                echo "Scoping to test file pattern '$caseName' before applying test title pattern..."
                testPathPattern="${folderName}/.*${caseName}"
                set +e
                node_run_jest "$runPattern" --testPathPattern="$testPathPattern"
                exit_code=$?
                set -e
            else
                set +e
                node_run_jest "$runPattern" "$folderName"
                exit_code=$?
                set -e
            fi
        else
            echo "Running Node.js tests matching '$runPattern' in all folders..."
            set +e
            node_run_jest "$runPattern"
            exit_code=$?
            set -e
        fi
    elif [ -n "$folderName" ] && [ -n "$caseName" ]; then
        echo "Running Node.js test '$caseName' in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "ERROR: Folder not found: $folderName" >&2
            exit 1
        fi

        TEST_FILES=$(find "$folderName" -type f \( -name "*${caseName}*.ts" -o -name "*${caseName}*.js" \) 2>/dev/null)

        if [ -z "$TEST_FILES" ]; then
            echo "ERROR: No test files found matching pattern '$caseName' in folder '$folderName'" >&2
            exit 1
        fi

        echo "Running the following test files:"
        echo "$TEST_FILES"

        set +e
        # shellcheck disable=SC2086
        node_run_jest "" $TEST_FILES
        exit_code=$?
        set -e
    elif [ -n "$folderName" ]; then
        echo "Running all Node.js tests in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "ERROR: Folder not found: $folderName" >&2
            exit 1
        fi
        mandatory_pattern=$(get_mandatory_pattern_for_folder "$folderName")
        if [ "$NODE_MANDATORY_ONLY" = "true" ] && [ -n "$mandatory_pattern" ]; then
            echo "Running mandatory $folderName tests only"
            set +e
            node_run_jest "$mandatory_pattern" "$folderName"
            exit_code=$?
            set -e
        else
            set +e
            node_run_jest "" "$folderName"
            exit_code=$?
            set -e
        fi
    elif [ -n "$caseName" ]; then
        echo "Running Node.js test with pattern '$caseName' in all folders..."
        set +e
        node_run_jest "$caseName"
        exit_code=$?
        set -e
    elif [ "$NODE_MANDATORY_ONLY" = "true" ]; then
        echo "Running mandatory tests per module only..."
        exit_code=0
        for folder in payment_gateway widget disbursement; do
            if [ -d "$folder" ]; then
                mandatory_pattern=$(get_mandatory_pattern_for_folder "$folder")
                set +e
                if [ -n "$mandatory_pattern" ]; then
                    echo "Running mandatory tests in $folder..."
                    node_run_jest "$mandatory_pattern" "$folder"
                else
                    node_run_jest "" "$folder"
                fi
                folder_code=$?
                set -e
                if [ "$folder_code" -ne 0 ]; then
                    exit_code=$folder_code
                fi
            fi
        done
    else
        echo "Running all Node.js tests..."
        set +e
        node_run_jest ""
        exit_code=$?
        set -e
    fi

    cd "$PROJECT_ROOT"
    exit "$exit_code"
}
