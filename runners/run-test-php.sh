#!/bin/sh

# Local PHP test runner: mandatory-only.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_RUNNERS_DIR="$SCRIPT_DIR"
PHP_MANDATORY_ONLY=true

. "$SCRIPT_DIR/php/common.sh"

php_run_phpunit() {
    test_path="$1"
    filter="$2"
    phpunit_bin="$3"
    phpunit_config="$4"

    set +e
    if [ -n "$filter" ]; then
        "$phpunit_bin" --configuration="$phpunit_config" --testdox --debug --colors=always --filter="$filter" "$test_path"
    else
        "$phpunit_bin" --configuration="$phpunit_config" --testdox --debug --colors=always "$test_path"
    fi
    exit_code=$?
    set -e
    return "$exit_code"
}

. "$SCRIPT_DIR/php/runner.sh"

# run-test.sh calls: sh run-test-php.sh "$2" "$3" "$4"
run_php_runner_main "$@"
