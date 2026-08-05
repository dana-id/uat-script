# Shared Go test runner utilities (local + CI).

if [ -z "${GO_RUNNERS_DIR:-}" ]; then
    echo "GO_RUNNERS_DIR must be set before sourcing runners/go/common.sh" >&2
    exit 1
fi

PROJECT_ROOT="$(dirname "$GO_RUNNERS_DIR")"

_mandatory_tests_json() {
    if [ -n "${MANDATORY_TESTS_JSON:-}" ] && [ -f "$MANDATORY_TESTS_JSON" ]; then
        echo "$MANDATORY_TESTS_JSON"
    else
        echo "$PROJECT_ROOT/resource/mandatory-tests.json"
    fi
}

has_test_files() {
    dir="$1"
    [ -d "$dir" ] && find "$dir" -name "*_test.go" -type f 2>/dev/null | head -1 | grep -q .
}

get_mandatory_pattern_for_module() {
    module="$1"
    json=$(_mandatory_tests_json)
    if ! command -v jq > /dev/null 2>&1; then
        echo "ERROR: jq is required to read mandatory-tests.json" >&2
        exit 1
    fi
    jq -r --arg m "$module" '.products[$m].go | join("|")' "$json"
}

load_env_if_exists() {
    if [ -f "../.env" ]; then
        set -a
        . ../.env
        set +a
        echo "Environment variables loaded from .env file"
    elif [ -f "../../.env" ]; then
        set -a
        . ../../.env
        set +a
        echo "Environment variables loaded from .env file"
    fi
}

prepare_go_deps() {
    echo "Updating Go dependencies..."
    go get github.com/dana-id/dana-go/v2@v2.2.3 > /dev/null 2>&1 || true
    go get github.com/mxschmitt/playwright-go@v0.6100.0 > /dev/null 2>&1 || true
    go mod tidy > /dev/null 2>&1
    go clean -testcache > /dev/null 2>&1
}

setup_go_runner() {
    if ! command -v go > /dev/null 2>&1; then
        echo "ERROR: Go not available in this system. Please install Go."
        exit 1
    fi

    echo "Running Go tests..."
    go version

    load_env_if_exists
    cd test/go

    if [ ! -f "go.mod" ]; then
        echo "Error: go.mod file not found in test/go directory"
        exit 1
    fi

    prepare_go_deps
}
