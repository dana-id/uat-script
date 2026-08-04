# Mandatory smoke test patterns — sourced from resource/mandatory-tests.json (Go is canonical).

_mandatory_tests_json_path() {
    if [ -n "${MANDATORY_TESTS_JSON:-}" ] && [ -f "$MANDATORY_TESTS_JSON" ]; then
        echo "$MANDATORY_TESTS_JSON"
        return 0
    fi

    local script_dir
    script_dir=$(CDPATH= cd -- "$(dirname "${BASH_SOURCE[0]}")" && pwd)
    local candidate="$script_dir/../resource/mandatory-tests.json"
    if [ -f "$candidate" ]; then
        echo "$candidate"
        return 0
    fi

    echo "ERROR: mandatory-tests.json not found (set MANDATORY_TESTS_JSON)" >&2
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

mandatory_go_pattern() {
    local module
    module=$(_mandatory_product_key "$1")
    _mandatory_jq -r --arg m "$module" '.products[$m].go | join("|")'
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
    local module
    module=$(_mandatory_product_key "$1")
    _mandatory_jq -r --arg m "$module" '.products[$m].go | length'
}

get_mandatory_pattern_for_module() {
    mandatory_go_pattern "$1"
}

get_mandatory_pattern_for_folder() {
    mandatory_go_pattern "$1"
}
