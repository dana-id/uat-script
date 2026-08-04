#!/bin/sh

# Local Go test runner: mandatory-only.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
GO_MANDATORY_ONLY=true

. "$SCRIPT_DIR/go/common.sh"
. "$SCRIPT_DIR/go/retry.sh"

go_run_tests() {
    package_path="$1"
    timeout="$2"
    run_pattern="$3"
    retry_on_failure="${4:-false}"

    if [ "$retry_on_failure" = "true" ]; then
        run_go_test_cmd "$package_path" "$timeout" "$run_pattern"
        return $?
    fi

    set +e
    if [ -n "$run_pattern" ]; then
        go test -v -timeout="$timeout" -run "$run_pattern" "$package_path" 2>&1
    else
        go test -v -timeout="$timeout" "$package_path" 2>&1
    fi
    exit_code=$?
    set -e
    return "$exit_code"
}

. "$SCRIPT_DIR/go/runner.sh"

# run-test.sh calls: sh run-test-go.sh "$2" "$3" "$4"
run_go_runner_main "$@"
