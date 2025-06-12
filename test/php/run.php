<?php
/**
 * PHP Test Runner for DANA Self Integration Tests
 */

// Load environment variables
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Handle quoted values
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            }
            
            putenv("$key=$value");
        }
    }
}

// Auto-detect Composer autoload file
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php'
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    echo "Error: Composer autoload file not found. Make sure to run 'composer install' first.\n";
    exit(1);
}

// Parse command line arguments
$folderName = $argv[1] ?? null;
$caseName = $argv[2] ?? null;

function runTest(string $testFile): void {
    echo "Running test file: $testFile\n";
    require_once $testFile;
    
    // Extract class name from file path
    $pathInfo = pathinfo($testFile);
    $className = $pathInfo['filename'];
    
    // Fully qualified class name
    $fullClassName = "Dana\\Test\\PaymentGateway\\$className";
    
    if (class_exists($fullClassName)) {
        // If the class has a runAllTests method, call it
        if (method_exists($fullClassName, 'runAllTests')) {
            $fullClassName::runAllTests();
        } else {
            echo "Warning: Class $fullClassName exists but does not have a runAllTests method.\n";
        }
    } else {
        echo "Error: Class $fullClassName not found in file $testFile\n";
    }
}

// Run the tests based on parameters
$exitCode = 0;
$baseDir = __DIR__ . '/payment_gateway';

if ($folderName !== null && $caseName !== null) {
    // Run a specific test case in a specific folder
    $testFolder = "$baseDir/$folderName";
    if (!is_dir($testFolder)) {
        echo "Error: Test folder '$folderName' not found.\n";
        exit(1);
    }
    
    $found = false;
    foreach (glob("$testFolder/*Test.php") as $testFile) {
        if (stripos(basename($testFile), $caseName) !== false) {
            runTest($testFile);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "Error: No test file found for case '$caseName' in folder '$folderName'.\n";
        exit(1);
    }
} elseif ($folderName !== null) {
    // Run all tests in a specific folder
    $testFolder = "$baseDir/$folderName";
    if (!is_dir($testFolder)) {
        echo "Error: Test folder '$folderName' not found.\n";
        exit(1);
    }
    
    $found = false;
    foreach (glob("$testFolder/*Test.php") as $testFile) {
        runTest($testFile);
        $found = true;
    }
    
    if (!$found) {
        echo "Error: No test files found in folder '$folderName'.\n";
        exit(1);
    }
} elseif ($caseName !== null) {
    // Run tests by case name across all folders
    $found = false;
    foreach (glob("$baseDir/*", GLOB_ONLYDIR) as $dir) {
        foreach (glob("$dir/*Test.php") as $testFile) {
            if (stripos(basename($testFile), $caseName) !== false) {
                runTest($testFile);
                $found = true;
                break 2;
            }
        }
    }
    
    if (!$found) {
        echo "Error: No test file found for case '$caseName'.\n";
        exit(1);
    }
} else {
    // Run all tests in all folders
    $testFiles = glob("$baseDir/*Test.php");
    if (empty($testFiles)) {
        // Try looking in subdirectories
        foreach (glob("$baseDir/*", GLOB_ONLYDIR) as $dir) {
            $dirTestFiles = glob("$dir/*Test.php");
            foreach ($dirTestFiles as $testFile) {
                runTest($testFile);
            }
        }
    } else {
        foreach ($testFiles as $testFile) {
            runTest($testFile);
        }
    }
}

exit($exitCode);
