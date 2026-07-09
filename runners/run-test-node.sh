#!/bin/sh

# Local Node test runner: mandatory-only.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
NODE_RUNNERS_DIR="$SCRIPT_DIR"
NODE_MANDATORY_ONLY=true

. "$SCRIPT_DIR/node/common.sh"

node_run_jest() {
    title_pattern="$1"
    shift

    set +e
    if [ -n "$title_pattern" ]; then
        npx jest "$@" -t "$title_pattern"
    else
        npx jest "$@"
    fi
    exit_code=$?
    set -e
    return "$exit_code"
}

. "$SCRIPT_DIR/node/runner.sh"

# run-test.sh calls: sh run-test-node.sh "$2" "$3" "$4"
run_node_runner_main "$@"
