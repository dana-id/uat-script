# Shared Node failed-test retry (local mandatory + CI).

node_before_retry_attempt() {
    :
}

node_retry_sleep_seconds() {
    failed_attempt="$1"
    initial_delay="${RETRY_INITIAL_DELAY_SECONDS:-10}"
    delay_before_attempt_4="${RETRY_DELAY_BEFORE_ATTEMPT_4_SECONDS:-120}"
    delay_before_attempt_5="${RETRY_DELAY_BEFORE_ATTEMPT_5_SECONDS:-300}"

    case "$failed_attempt" in
        1) echo "$initial_delay" ;;
        2) echo "$((initial_delay * 2))" ;;
        3) echo "$delay_before_attempt_4" ;;
        4) echo "$delay_before_attempt_5" ;;
        *) echo "$delay_before_attempt_5" ;;
    esac
}

resolve_retry_max_attempts() {
    max="${RETRY_MAX_ATTEMPTS:-5}"
    case "$max" in
        ''|*[!0-9]*) max=5 ;;
    esac
    if [ "$max" -lt 2 ]; then
        max=5
    fi
    echo "$max"
}

jest_regex_escape() {
    echo "$1" | sed 's/[][(){}.+*?^$|\\]/\\&/g'
}

extract_failed_jest_tests_from_json() {
    json_file="$1"
    pattern=""
    title=""
    failed_titles=""

    if [ ! -f "$json_file" ]; then
        return 2
    fi

    if ! command -v jq >/dev/null 2>&1; then
        return 2
    fi

    failed_titles=$(jq -r '
        [.testResults[]?.assertionResults[]?
            | select(.status == "failed" or .status == "todo")
            | .title]
        | map(select(. != ""))
        | unique
        | .[]
    ' "$json_file" 2>/dev/null)

    if [ -n "$failed_titles" ]; then
        while IFS= read -r title; do
            if [ -z "$title" ]; then
                continue
            fi
            escaped=$(jest_regex_escape "$title")
            if [ -n "$pattern" ]; then
                pattern="$pattern|$escaped"
            else
                pattern="$escaped"
            fi
        done <<EOF
$failed_titles
EOF
        if [ -n "$pattern" ]; then
            printf '%s\n' "$pattern"
            return 0
        fi
    fi

    failed_suites=$(jq -r '
        [.testResults[]?
            | select(.status == "failed")
            | select((.assertionResults // []) | map(select(.status == "failed")) | length == 0)
            | .name
        ]
        | map(select(. != ""))
        | length
    ' "$json_file" 2>/dev/null)

    case "$failed_suites" in ''|*[!0-9]*) failed_suites=0 ;; esac
    if [ "$failed_suites" -gt 0 ]; then
        return 3
    fi

    num_failed=$(jq -r '.numFailedTests // 0' "$json_file" 2>/dev/null)
    num_failed_suites=$(jq -r '.numFailedTestSuites // 0' "$json_file" 2>/dev/null)
    num_runtime_errors=$(jq -r '.numRuntimeErrorTestSuites // 0' "$json_file" 2>/dev/null)
    case "$num_failed" in ''|*[!0-9]*) num_failed=0 ;; esac
    case "$num_failed_suites" in ''|*[!0-9]*) num_failed_suites=0 ;; esac
    case "$num_runtime_errors" in ''|*[!0-9]*) num_runtime_errors=0 ;; esac

    if [ "$num_failed" -gt 0 ] || [ "$num_failed_suites" -gt 0 ] || [ "$num_runtime_errors" -gt 0 ]; then
        return 3
    fi

    if [ "$(jq -r '.success // "true"' "$json_file" 2>/dev/null)" = "false" ]; then
        return 3
    fi

    return 2
}

print_jest_json_summary() {
    json_file="$1"
    if ! command -v jq >/dev/null 2>&1 || [ ! -f "$json_file" ]; then
        return 0
    fi

    jq -r '
        "Tests: \(.numPassedTests // 0) passed, \(.numFailedTests // 0) failed, \(.numPendingTests // 0) skipped",
        (.testResults[]? | select(.status == "failed") | "FAIL suite \(.name)"),
        (.testResults[]?.assertionResults[]? | select(.status == "failed") | "  ✕ \(.title)")
    ' "$json_file" 2>/dev/null || true
}

run_jest_once() {
    title_pattern="$1"
    json_file="$2"
    shift 2

    set +e
    if [ -n "$title_pattern" ]; then
        npx jest "$@" -t "$title_pattern" --json --outputFile="$json_file"
    else
        npx jest "$@" --json --outputFile="$json_file"
    fi
    exit_code=$?

    if [ "$exit_code" -eq 0 ] && command -v jq >/dev/null 2>&1 && [ -f "$json_file" ]; then
        if [ "$(jq -r '.success // "true"' "$json_file" 2>/dev/null)" = "false" ]; then
            exit_code=1
        fi
    fi

    print_jest_json_summary "$json_file"
    return "$exit_code"
}

# Attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_jest_cmd() {
    initial_title_pattern="${1:-}"
    shift

    max_attempts=$(resolve_retry_max_attempts)
    attempt=1
    current_title_pattern="$initial_title_pattern"
    last_exit_code=1
    json_file=""

    set +e
    while [ "$attempt" -le "$max_attempts" ]; do
        node_before_retry_attempt "$attempt"

        json_file="$PWD/.jest-retry-results-${attempt}-$$.json"
        rm -f "$json_file"

        if [ "$attempt" -eq 1 ]; then
            echo "Jest attempt 1/$max_attempts (full suite)"
        else
            echo "Jest attempt $attempt/$max_attempts (failed tests only)"
        fi
        if [ -n "$current_title_pattern" ]; then
            echo "Filter: $current_title_pattern"
        fi

        run_jest_once "$current_title_pattern" "$json_file" "$@"
        last_exit_code=$?

        if [ "$last_exit_code" -eq 0 ]; then
            rm -f "$json_file"
            set -e
            return 0
        fi

        if [ "$attempt" -ge "$max_attempts" ]; then
            rm -f "$json_file"
            set -e
            return "$last_exit_code"
        fi

        failed_title_pattern=""
        extract_status=0
        failed_title_pattern=$(extract_failed_jest_tests_from_json "$json_file") || extract_status=$?

        if [ ! -s "$json_file" ]; then
            extract_status=3
            failed_title_pattern=""
        fi

        rm -f "$json_file"

        if [ "$extract_status" -eq 3 ]; then
            echo "Retrying same scoped suite (suite-level failure detected)"
            current_title_pattern="$initial_title_pattern"
        elif [ -z "$failed_title_pattern" ]; then
            echo "Could not determine failed tests for retry; stopping."
            set -e
            return "$last_exit_code"
        else
            echo "Retrying failed/error tests only: $failed_title_pattern"
            current_title_pattern="$failed_title_pattern"
        fi

        sleep_delay=$(node_retry_sleep_seconds "$attempt")
        echo "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    set -e
    return "$last_exit_code"
}
