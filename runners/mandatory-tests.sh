# Mandatory smoke test patterns — sourced from resource/mandatory-tests.json (Go is canonical).

_mandatory_runners_dir() {
    if [ -n "${GO_RUNNERS_DIR:-}" ]; then
        echo "$GO_RUNNERS_DIR"
        return 0
    fi
    if [ -n "${NODE_RUNNERS_DIR:-}" ]; then
        echo "$NODE_RUNNERS_DIR"
        return 0
    fi
    if [ -n "${PYTHON_RUNNERS_DIR:-}" ]; then
        echo "$PYTHON_RUNNERS_DIR"
        return 0
    fi
    if [ -n "${PHP_RUNNERS_DIR:-}" ]; then
        echo "$PHP_RUNNERS_DIR"
        return 0
    fi
    if [ -n "${JAVA_RUNNERS_DIR:-}" ]; then
        echo "$JAVA_RUNNERS_DIR"
        return 0
    fi

    # Direct bash sourcing (e.g. CI parity check) when no runner dir is preset.
    if [ -n "${BASH_SOURCE[0]:-}" ]; then
        local script_dir
        script_dir=$(CDPATH= cd -- "$(dirname "${BASH_SOURCE[0]}")" && pwd)
        echo "$script_dir"
        return 0
    fi

    echo "ERROR: set GO_RUNNERS_DIR (or *._RUNNERS_DIR) before sourcing mandatory-tests.sh" >&2
    return 1
}

_mandatory_tests_json_path() {
    if [ -n "${MANDATORY_TESTS_JSON:-}" ] && [ -f "$MANDATORY_TESTS_JSON" ]; then
        echo "$MANDATORY_TESTS_JSON"
        return 0
    fi

    local runners_dir project_root candidate
    runners_dir=$(_mandatory_runners_dir) || return 1
    project_root=$(dirname "$runners_dir")
    candidate="$project_root/resource/mandatory-tests.json"
    if [ -f "$candidate" ]; then
        echo "$candidate"
        return 0
    fi

    echo "ERROR: mandatory-tests.json not found at $candidate (set MANDATORY_TESTS_JSON)" >&2
    return 1
}

_mandatory_jq() {
    local json
    json=$(_mandatory_tests_json_path) || return 1
    jq "$@" "$json"
}

# Go / Python / PHP / Node folder name: payment_gateway | widget | disbursement
_mandatory_product_key() {
    echo "$1"
}

# Java module dir name: paymentgateway | widget | disbursement
_mandatory_java_module_to_key() {
    case "$1" in
        paymentgateway) echo "payment_gateway" ;;
        widget) echo "widget" ;;
        disbursement) echo "disbursement" ;;
        *) echo "$1" ;;
    esac
}

_mandatory_uptime_schedule() {
    [ "${MANDATORY_UPTIME_SCHEDULE:-false}" = "true" ] \
        || [ "${PIPELINE_TRIGGER_SOURCE:-}" = "go-mandatory-schedule" ]
}

# Finish-notify (webhook / pay-order) — omitted from 15-min uptime schedule only.
_mandatory_go_uptime_jq_filter() {
    echo 'map(select(
        . != "TestTransactionSuccessNotify"
        and . != "TestInternalServerErrorNotify"
        and . != "TestExpiredNotify"
    ))'
}

mandatory_go_pattern() {
    local module filter
    module=$(_mandatory_product_key "$1")
    if _mandatory_uptime_schedule; then
        filter=$(_mandatory_go_uptime_jq_filter)
        _mandatory_jq -r --arg m "$module" ".products[\$m].go | $filter | join(\"|\")"
    else
        _mandatory_jq -r --arg m "$module" '.products[$m].go | join("|")'
    fi
}

mandatory_python_pattern() {
    local module
    module=$(_mandatory_product_key "$1")
    _mandatory_jq -r --arg m "$module" '.products[$m].python | join("|")'
}

mandatory_php_pattern() {
    local module
    module=$(_mandatory_product_key "$1")
    _mandatory_jq -r --arg m "$module" '.products[$m].php | join("|")'
}

mandatory_node_pattern() {
    local module
    module=$(_mandatory_product_key "$1")
    _mandatory_jq -r --arg m "$module" '.products[$m].node | join("|")'
}

mandatory_java_pattern() {
    local key
    key=$(_mandatory_java_module_to_key "$1")
    _mandatory_jq -r --arg m "$key" '
        .products[$m].java
        | map(.class + "#" + (.methods | join("+")))
        | join(",")
    '
}

mandatory_go_count() {
    local module filter
    module=$(_mandatory_product_key "$1")
    if _mandatory_uptime_schedule; then
        filter=$(_mandatory_go_uptime_jq_filter)
        _mandatory_jq -r --arg m "$module" ".products[\$m].go | $filter | length"
    else
        _mandatory_jq -r --arg m "$module" '.products[$m].go | length'
    fi
}

get_mandatory_pattern_for_module() {
    mandatory_go_pattern "$1"
}

get_mandatory_pattern_for_folder() {
    mandatory_go_pattern "$1"
}
