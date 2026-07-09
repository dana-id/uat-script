#!/bin/bash

# Local Java test runner: mandatory-only.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
JAVA_RUNNERS_DIR="$SCRIPT_DIR"
JAVA_MANDATORY_ONLY=true

. "$SCRIPT_DIR/java/common.sh"

java_run_mvn_test_cmd() {
    local test_arg="${1:-}"
    clear_surefire_reports
    run_mvn_test_once "$test_arg"
}

. "$SCRIPT_DIR/java/runner.sh"

if [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
    show_usage
    exit 0
fi

# run-test.sh calls: sh run-test-java.sh "$2" "$3" "$4"
run_java_runner_main "$@"
