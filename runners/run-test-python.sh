#!/bin/sh

# Local Python test runner: mandatory-only.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PYTHON_RUNNERS_DIR="$SCRIPT_DIR"
PYTHON_MANDATORY_ONLY=true

. "$SCRIPT_DIR/python/common.sh"
. "$SCRIPT_DIR/python/retry.sh"

python_run_pytest() {
    k_pattern="$1"
    retry_on_failure="${2:-false}"
    shift 2

    if [ "$retry_on_failure" = "true" ]; then
        run_pytest_cmd "$k_pattern" "$@"
        return $?
    fi

    set +e
    if [ -n "$k_pattern" ]; then
        $PYTHON_CMD -m pytest -v -s "$@" -k "$k_pattern"
    else
        $PYTHON_CMD -m pytest -v -s "$@"
    fi
    exit_code=$?
    set -e

    if [ "$exit_code" -eq 5 ]; then
        echo "ERROR: No tests were collected" >&2
        return 1
    fi
    return "$exit_code"
}

. "$SCRIPT_DIR/python/runner.sh"

# run-test.sh calls: sh run-test-python.sh "$2" "$3" "$4"
run_python_runner_main "$@"
