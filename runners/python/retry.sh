# Shared Python failed-test retry (local mandatory + CI).

python_before_retry_attempt() {
    :
}

python_retry_sleep_seconds() {
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

extract_failed_pytest_tests_from_junit() {
    junit_file="$1"
    "$PYTHON_CMD" - "$junit_file" <<'PY'
import sys
import xml.etree.ElementTree as ET

path = sys.argv[1]
try:
    root = ET.parse(path).getroot()
except ET.ParseError:
    sys.exit(2)

names = []
for testcase in root.iter("testcase"):
    if testcase.find("failure") is not None or testcase.find("error") is not None:
        name = (testcase.get("name") or "").strip()
        if name:
            names.append(name)

if not names:
    sys.exit(2)

print(" or ".join(names))
PY
}

run_pytest_once() {
    k_pattern="$1"
    shift
    junit_file="$1"
    shift

    if [ -n "$k_pattern" ]; then
        $PYTHON_CMD -m pytest -v -s --junitxml="$junit_file" "$@" -k "$k_pattern"
    else
        $PYTHON_CMD -m pytest -v -s --junitxml="$junit_file" "$@"
    fi
}

# Attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_pytest_cmd() {
    k_pattern="$1"
    shift

    max_attempts=$(resolve_retry_max_attempts)
    attempt=1
    current_k_pattern="$k_pattern"
    last_exit_code=1
    junit_file=""

    set +e
    while [ "$attempt" -le "$max_attempts" ]; do
        python_before_retry_attempt "$attempt"

        junit_file=$(mktemp "${TMPDIR:-/tmp}/pytest-results.XXXXXX.xml")
        if [ "$attempt" -eq 1 ]; then
            echo "Pytest attempt 1/$max_attempts (full suite) for $*"
        else
            echo "Pytest attempt $attempt/$max_attempts (failed tests only) for $*"
        fi
        if [ -n "$current_k_pattern" ]; then
            echo "Filter: $current_k_pattern"
        fi

        run_pytest_once "$current_k_pattern" "$junit_file" "$@"
        last_exit_code=$?

        if [ "$last_exit_code" -eq 0 ]; then
            rm -f "$junit_file"
            set -e
            return 0
        fi
        if [ "$last_exit_code" -eq 5 ]; then
            rm -f "$junit_file"
            set -e
            echo "ERROR: No tests were collected" >&2
            return 1
        fi

        if [ "$attempt" -ge "$max_attempts" ]; then
            rm -f "$junit_file"
            set -e
            return "$last_exit_code"
        fi

        failed_k_pattern=""
        failed_k_pattern=$(extract_failed_pytest_tests_from_junit "$junit_file") || failed_k_pattern=""
        rm -f "$junit_file"

        if [ -z "$failed_k_pattern" ]; then
            echo "Could not determine failed tests for retry; stopping."
            set -e
            return "$last_exit_code"
        fi

        echo "Retrying failed/error tests only: $failed_k_pattern"
        current_k_pattern="$failed_k_pattern"

        sleep_delay=$(python_retry_sleep_seconds "$attempt")
        echo "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    set -e
    return "$last_exit_code"
}
