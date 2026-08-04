# Shared Python test runner utilities (local + CI).

if [ -z "${PYTHON_RUNNERS_DIR:-}" ]; then
    echo "PYTHON_RUNNERS_DIR must be set before sourcing runners/python/common.sh" >&2
    exit 1
fi

PROJECT_ROOT="$(dirname "$PYTHON_RUNNERS_DIR")"

. "$PYTHON_RUNNERS_DIR/mandatory-tests.sh"

resolve_python_cmd() {
    if command -v python3 > /dev/null 2>&1; then
        echo "python3"
    elif command -v python > /dev/null 2>&1; then
        echo "python"
    else
        echo ""
    fi
}

pattern_for_pytest_k() {
    echo "$1" | sed 's/|/ or /g'
}

get_mandatory_pattern_for_folder() {
    mandatory_python_pattern "$1"
}

resolve_needs_playwright() {
    folderName="$1"
    caseName="$2"
    runPattern="$3"
    needs_playwright=false

    caseNameLower=$(echo "$caseName" | tr '[:upper:]' '[:lower:]')
    runPatternLower=$(echo "$runPattern" | tr '[:upper:]' '[:lower:]')
    scope="$caseNameLower $runPatternLower $folderName"

    # Browser/OAuth UI automation (widget + PG cancel/query/refund flows).
    if echo "$scope" | grep -Eq \
        'automation|oauth|browser|playwright|apply_token|apply_ott|get_auth|unbinding|balance_inquiry|query_order|query_payment|cancel_order|refund_order|payment_widget|payment_pg'; then
        needs_playwright=true
    fi

    echo "$needs_playwright"
}

setup_python_env() {
    needs_playwright="$1"

    cd "$PROJECT_ROOT"

    $PYTHON_CMD --version
    $PYTHON_CMD -m venv venv
    . venv/bin/activate

    $PYTHON_CMD -m pip install --upgrade pip
    if [ "$needs_playwright" = "true" ]; then
        $PYTHON_CMD -m pip install --upgrade -r test/python/requirements.txt
    else
        echo "Using test/python/requirements-core.txt (no Playwright)."
        $PYTHON_CMD -m pip install --upgrade -r test/python/requirements-core.txt
    fi
    $PYTHON_CMD -m pip install --upgrade "dana-python>=2.2.1"

    if [ "$needs_playwright" = "true" ]; then
        $PYTHON_CMD -m playwright install --with-deps chromium
    else
        echo "Skipping Playwright browser install (not required for this run)."
    fi

    export PYTHONPATH="${PYTHONPATH:+$PYTHONPATH:}$PROJECT_ROOT/test/python:$PROJECT_ROOT/runner/python"
}
