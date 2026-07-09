# Shared Java failed-test retry (local mandatory + CI).

java_before_retry_attempt() {
    :
}

java_retry_sleep_seconds() {
    local failed_attempt="$1"
    local initial_delay="${RETRY_INITIAL_DELAY_SECONDS:-10}"
    local delay_before_attempt_4="${RETRY_DELAY_BEFORE_ATTEMPT_4_SECONDS:-120}"
    local delay_before_attempt_5="${RETRY_DELAY_BEFORE_ATTEMPT_5_SECONDS:-300}"

    case "$failed_attempt" in
        1) echo "$initial_delay" ;;
        2) echo "$((initial_delay * 2))" ;;
        3) echo "$delay_before_attempt_4" ;;
        4) echo "$delay_before_attempt_5" ;;
        *) echo "$delay_before_attempt_5" ;;
    esac
}

resolve_retry_max_attempts() {
    local max="${RETRY_MAX_ATTEMPTS:-5}"
    case "$max" in
        ''|*[!0-9]*) max=5 ;;
    esac
    if [ "$max" -lt 2 ]; then
        max=5
    fi
    echo "$max"
}

extract_failed_maven_tests_from_surefire() {
    local reports_dir="$1"
    local result=""
    local report_args=""
    local xml_file

    if [ ! -d "$reports_dir" ]; then
        return 2
    fi

    for xml_file in "$reports_dir"/TEST-*.xml; do
        if [ -f "$xml_file" ]; then
            if [ -n "$report_args" ]; then
                report_args="$report_args $xml_file"
            else
                report_args="$xml_file"
            fi
        fi
    done

    if [ -z "$report_args" ]; then
        return 2
    fi

    # shellcheck disable=SC2086
    result=$(awk '
        function add_class_only(class) {
            if (class == "") {
                return
            }
            if (!(class in methods)) {
                methods[class] = ""
                order[++order_count] = class
            }
        }
        function add_method(class, method, parts, n, i, found) {
            if (class == "") {
                return
            }
            if (method == "" || method == class || method ~ /^\[engine:/) {
                add_class_only(class)
                return
            }
            if (!(class in methods)) {
                methods[class] = method
                order[++order_count] = class
                return
            }
            if (methods[class] == "") {
                return
            }
            n = split(methods[class], parts, "+")
            found = 0
            for (i = 1; i <= n; i++) {
                if (parts[i] == method) {
                    found = 1
                    break
                }
            }
            if (!found) {
                methods[class] = methods[class] "+" method
            }
        }
        function class_from_filename(path,    base) {
            base = path
            sub(/^.*\//, "", base)
            sub(/^TEST-/, "", base)
            sub(/\.xml$/, "", base)
            return base
        }
        function record_failed_testcase() {
            if (!failed) {
                return
            }
            if (classname == "") {
                classname = class_from_filename(FILENAME)
            }
            add_method(classname, name)
        }
        FNR == 1 {
            classname = ""
            name = ""
            failed = 0
            suite_failures = 0
            suite_errors = 0
            suite_class = class_from_filename(FILENAME)
        }
        /<testsuite / {
            if (match($0, / failures="[0-9]+"/)) {
                suite_failures = substr($0, RSTART + 11, RLENGTH - 12) + 0
            }
            if (match($0, / errors="[0-9]+"/)) {
                suite_errors = substr($0, RSTART + 9, RLENGTH - 10) + 0
            }
        }
        /<testcase / {
            classname = ""
            name = ""
            if (match($0, / classname="[^"]*"/)) {
                classname = substr($0, RSTART + 12, RLENGTH - 13)
            }
            if (match($0, / name="[^"]*"/)) {
                name = substr($0, RSTART + 7, RLENGTH - 8)
            }
            failed = ($0 ~ /<failure|<error/) ? 1 : 0
            if ($0 ~ /\/>[[:space:]]*$/) {
                record_failed_testcase()
                classname = ""
                name = ""
                failed = 0
            }
        }
        /<failure|<error/ {
            failed = 1
        }
        /<\/testcase>/ {
            record_failed_testcase()
            classname = ""
            name = ""
            failed = 0
        }
        ENDFILE {
            if (order_count == 0 && (suite_failures + suite_errors) > 0 && suite_class != "") {
                add_class_only(suite_class)
            }
        }
        END {
            if (order_count == 0) {
                exit 2
            }
            out = ""
            for (i = 1; i <= order_count; i++) {
                c = order[i]
                if (out != "") {
                    out = out ","
                }
                if (methods[c] == "") {
                    out = out c
                } else {
                    out = out c "#" methods[c]
                }
            }
            print out
        }
    ' $report_args 2>/dev/null) || result=""

    if [ -z "$result" ]; then
        for xml_file in $report_args; do
            if [ ! -f "$xml_file" ]; then
                continue
            fi
            local failures errors class_name
            failures=$(grep -o 'failures="[0-9]*"' "$xml_file" 2>/dev/null | head -1 | cut -d'"' -f2)
            errors=$(grep -o 'errors="[0-9]*"' "$xml_file" 2>/dev/null | head -1 | cut -d'"' -f2)
            failures=${failures:-0}
            errors=${errors:-0}
            case "$failures" in ''|*[!0-9]*) failures=0 ;; esac
            case "$errors" in ''|*[!0-9]*) errors=0 ;; esac
            if [ $((failures + errors)) -le 0 ]; then
                continue
            fi
            class_name=$(basename "$xml_file" .xml | sed 's/^TEST-//')
            if [ -n "$class_name" ]; then
                if [ -n "$result" ]; then
                    result="$result,$class_name"
                else
                    result="$class_name"
                fi
            fi
        done
    fi

    if [ -z "$result" ]; then
        return 2
    fi

    printf '%s\n' "$result"
}

# Attempt 1 runs the full scoped suite; attempts 2-5 retry only failed/error tests.
run_mvn_test_cmd() {
    local initial_test_arg="${1:-}"
    local surefire_reports="$JAVA_TEST_DIR/target/surefire-reports"
    local max_attempts
    max_attempts=$(resolve_retry_max_attempts)
    local attempt=1
    local current_test_arg="$initial_test_arg"
    local last_exit_code=1

    while [ "$attempt" -le "$max_attempts" ]; do
        java_before_retry_attempt "$attempt"
        clear_surefire_reports

        if [ "$attempt" -eq 1 ]; then
            print_info "Maven attempt 1/$max_attempts (full suite)"
        else
            print_info "Maven attempt $attempt/$max_attempts (failed tests only)"
        fi
        if [ -n "$current_test_arg" ]; then
            print_info "Test filter: $current_test_arg"
        fi

        set +e
        run_mvn_test_once "$current_test_arg"
        last_exit_code=$?
        set -e

        if [ "$last_exit_code" -eq 0 ]; then
            return 0
        fi

        if [ "$attempt" -ge "$max_attempts" ]; then
            return "$last_exit_code"
        fi

        local failed_test_arg=""
        failed_test_arg=$(extract_failed_maven_tests_from_surefire "$surefire_reports") || failed_test_arg=""
        if [ -z "$failed_test_arg" ]; then
            print_warning "Could not determine failed tests for retry; stopping."
            return "$last_exit_code"
        fi

        print_info "Retrying failed/error tests only: $failed_test_arg"
        current_test_arg="$failed_test_arg"

        local sleep_delay
        sleep_delay=$(java_retry_sleep_seconds "$attempt")
        print_info "Attempt $attempt failed; sleeping ${sleep_delay}s before retry..."
        sleep "$sleep_delay"
        attempt=$((attempt + 1))
    done

    return "$last_exit_code"
}
