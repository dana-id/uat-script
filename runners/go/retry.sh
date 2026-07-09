# Shared Go failed-test retry (local mandatory + CI).

go_before_retry_attempt() {
    :
}

go_retry_sleep_seconds() {
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

extract_failed_go_tests_from_json() {
    json_log="$1"
    failed=""
    result=""

    if [ ! -f "$json_log" ]; then
        return 2
    fi

    if ! command -v jq >/dev/null 2>&1; then
        return 2
    fi

    failed=$(jq -r 'select(.Action=="fail") | .Test' "$json_log" 2>/dev/null | sort -u)
    if [ -z "$failed" ]; then
        return 2
    fi

    result=$(printf '%s\n' "$failed" | paste -sd'|' -)
    if [ -z "$result" ]; then
        return 2
    fi

    printf '%s\n' "$result"
}

run_go_test_once_json() {
    package_path="$1"
    timeout="$2"
    run_pattern="$3"
    json_log="$4"

    if [ -n "$run_pattern" ]; then
        go test -json -v -timeout="$timeout" -run "$run_pattern" "$package_path" > "$json_log" 2>&1
    else
        go test -json -v -timeout="$timeout" "$package_path" > "$json_log" 2>&1
    fi
}

# Attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_go_test_cmd() {
    package_path="$1"
    timeout="$2"
    initial_run_pattern="${3:-}"

    max_attempts=$(resolve_retry_max_attempts)
    attempt=1
    current_run_pattern="$initial_run_pattern"
    last_exit_code=1
    json_log=""

    set +e
    while [ "$attempt" -le "$max_attempts" ]; do
        go_before_retry_attempt "$attempt"

        json_log=$(mktemp "${TMPDIR:-/tmp}/go-test-results.XXXXXX.jsonl")
        if [ "$attempt" -eq 1 ]; then
            echo "Go test attempt 1/$max_attempts (full suite) for $package_path"
        else
            echo "Go test attempt $attempt/$max_attempts (failed tests only) for $package_path"
        fi
        if [ -n "$current_run_pattern" ]; then
            echo "Filter: $current_run_pattern"
        fi

        run_go_test_once_json "$package_path" "$timeout" "$current_run_pattern" "$json_log"
        last_exit_code=$?
        cat "$json_log"

        if [ "$last_exit_code" -eq 0 ]; then
            rm -f "$json_log"
            set -e
            return 0
        fi

        if [ "$attempt" -ge "$max_attempts" ]; then
            rm -f "$json_log"
            set -e
            return "$last_exit_code"
        fi

        failed_run_pattern=""
        extract_status=0
        failed_run_pattern=$(extract_failed_go_tests_from_json "$json_log") || extract_status=$?
        rm -f "$json_log"

        if [ -z "$failed_run_pattern" ]; then
            echo "Could not determine failed tests for retry; stopping."
            set -e
            return "$last_exit_code"
        fi

        echo "Retrying failed/error tests only: $failed_run_pattern"
        current_run_pattern="$failed_run_pattern"

        sleep_delay=$(go_retry_sleep_seconds "$attempt")
        echo "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    set -e
    return "$last_exit_code"
}
