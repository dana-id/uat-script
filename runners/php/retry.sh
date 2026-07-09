# Shared PHP failed-test retry (local mandatory + CI).

php_before_retry_attempt() {
    :
}

php_retry_sleep_seconds() {
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

extract_failed_phpunit_tests_from_junit() {
    junit_file="$1"
    php -r '
$path = $argv[1] ?? "";
if ($path === "" || !is_readable($path)) {
    exit(2);
}
$xml = @simplexml_load_file($path);
if ($xml === false) {
    exit(2);
}
$names = [];
foreach ($xml->xpath("//testcase[failure or error]") as $testcase) {
    $name = trim((string) $testcase["name"]);
    if ($name !== "") {
        $names[$name] = true;
    }
}
if ($names === []) {
    exit(2);
}
echo implode("|", array_keys($names));
' "$junit_file" 2>/dev/null
}

run_phpunit_once() {
    phpunit_bin="$1"
    phpunit_config="$2"
    test_path="$3"
    filter="$4"
    junit_file="$5"

    if [ -n "$filter" ]; then
        "$phpunit_bin" --configuration="$phpunit_config" --testdox --debug --colors=always --log-junit="$junit_file" --filter="$filter" "$test_path"
    else
        "$phpunit_bin" --configuration="$phpunit_config" --testdox --debug --colors=always --log-junit="$junit_file" "$test_path"
    fi
}

# Attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_phpunit_cmd() {
    test_path="$1"
    initial_filter="${2:-}"
    phpunit_bin="$3"
    phpunit_config="$4"

    max_attempts=$(resolve_retry_max_attempts)
    attempt=1
    current_filter="$initial_filter"
    last_exit_code=1
    junit_file=""

    set +e
    while [ "$attempt" -le "$max_attempts" ]; do
        php_before_retry_attempt "$attempt"

        junit_file=$(mktemp "${TMPDIR:-/tmp}/phpunit-results.XXXXXX.xml")
        if [ "$attempt" -eq 1 ]; then
            echo "PHPUnit attempt 1/$max_attempts (full suite) for $test_path"
        else
            echo "PHPUnit attempt $attempt/$max_attempts (failed tests only) for $test_path"
        fi
        if [ -n "$current_filter" ]; then
            echo "Filter: $current_filter"
        fi

        run_phpunit_once "$phpunit_bin" "$phpunit_config" "$test_path" "$current_filter" "$junit_file"
        last_exit_code=$?

        if [ "$last_exit_code" -eq 0 ]; then
            rm -f "$junit_file"
            set -e
            return 0
        fi

        if [ "$attempt" -ge "$max_attempts" ]; then
            rm -f "$junit_file"
            set -e
            return "$last_exit_code"
        fi

        failed_filter=""
        failed_filter=$(extract_failed_phpunit_tests_from_junit "$junit_file") || failed_filter=""
        rm -f "$junit_file"

        if [ -z "$failed_filter" ]; then
            echo "Could not determine failed tests for retry; stopping."
            set -e
            return "$last_exit_code"
        fi

        echo "Retrying failed/error tests only: $failed_filter"
        current_filter="$failed_filter"

        sleep_delay=$(php_retry_sleep_seconds "$attempt")
        echo "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    set -e
    return "$last_exit_code"
}
