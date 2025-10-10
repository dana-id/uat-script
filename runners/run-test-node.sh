#!/bin/sh

# Fail on error
set -e

run_node_runner(){
    folderName=$1
    caseName=$2
    
    if ! command -v node &> /dev/null; then
        echo "Node.js not available in this system. Please install Node.js."
        exit 0 
    fi
    
    echo "Running Node.js tests..."
    node --version
    npm --version
    
    # Change to the Node.js test directory
    cd test/node
    
    # Create package.json if it doesn't exist
    if [ ! -f "package.json" ]; then
        echo "Initializing Node.js project..."
        npm init -y
    fi
    
    # Install dependencies
    echo "Installing dependencies..."
    npm install --save dana-node dotenv
    npm install --save-dev jest ts-jest

    # Install Playwright only when running Widget tests which require browser automation
    if [ "$folderName" = "widget" ] || [ -z "$folderName" ]; then
        echo "Widget tests detected or all tests running. Installing Playwright..."
        npm install --save-dev @playwright/test playwright
        
        # Check if we're running in CI (Alpine container)
        if [ -n "${CI}" ] || command -v apk &> /dev/null; then
            echo "Detected CI or Alpine environment, using system Chrome if available"
            # Set environment variables to use system Chrome if available
            export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
            
            # If we're in Alpine, install chromium (this won't hurt if it's already installed)
            if command -v apk &> /dev/null; then
                echo "Installing Chromium on Alpine..."
                apk add --no-cache chromium
            fi
            
            # Set path to system Chrome binary
            export PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=$(which chromium-browser 2>/dev/null || which chromium 2>/dev/null || which chrome 2>/dev/null || which google-chrome-stable 2>/dev/null || echo "")
            
            if [ -z "$PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH" ]; then
                echo "WARNING: Could not find system Chrome/Chromium. Will try to use downloaded browser."
                npx playwright install chromium
            else
                echo "Using system Chrome/Chromium at: $PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH"
            fi
        else
            # On non-Alpine environments, just install Playwright browsers normally
            npx playwright install chromium
        fi
    fi
    
    # Install TypeScript
    npm install --save-dev typescript @types/node
    
    # Set up TypeScript configuration if it doesn't exist
    if [ ! -f "tsconfig.json" ]; then
        echo "Setting up TypeScript configuration..."
        cat > tsconfig.json << 'EOF'
{
  "compilerOptions": {
    "target": "es2020",
    "module": "commonjs",
    "esModuleInterop": true,
    "strict": true,
    "outDir": "dist"
  },
  "include": ["**/*.ts"]
}
EOF
    fi
    
    # Set up Jest configuration if it doesn't exist
    if [ ! -f "jest.config.js" ]; then
        echo "Setting up Jest configuration..."
        cat > jest.config.js << 'EOF'
module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  testTimeout: 60000
};
EOF
    fi
    
    # Support running by folder and/or scenario name
    if [ -n "$folderName" ] && [ -n "$caseName" ]; then
        # Run specific test in a specific folder
        echo "Running Node.js test '$caseName' in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi
        
        # Find all test files in the folder that match the pattern
        TEST_FILES=$(find $folderName -type f -name "*$caseName*.ts" -o -name "*$caseName*.js" 2>/dev/null)
        
        if [ -z "$TEST_FILES" ]; then
            echo "\033[31mERROR: No test files found matching pattern '$caseName' in folder '$folderName'\033[0m" >&2
            exit 1
        fi
        
        echo "Running the following test files:"
        echo $TEST_FILES
        
        npx jest $TEST_FILES
    elif [ -n "$folderName" ]; then
        # Run all tests in a specific folder
        echo "Running all Node.js tests in folder '$folderName'..."
        if [ ! -d "$folderName" ]; then
            echo "\033[31mERROR: Folder not found: $folderName\033[0m" >&2
            exit 1
        fi
        npx jest "$folderName"
    elif [ -n "$caseName" ]; then
        # Run a specific test case across all folders
        echo "Running Node.js test with pattern '$caseName' in all folders..."
        npx jest -t "$caseName"
    else
        # Run all tests
        echo "Running all Node.js tests..."
        npx jest
    fi
    
    # Change back to project root
    cd ../..
}

# Always execute the runner
run_node_runner "$@"
