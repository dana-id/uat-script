<?php

namespace DanaUat\Widget\Scripts;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class WebAutomation
{
    const DEFAULT_SELENIUM_URL = 'http://localhost:4444/wd/hub';
    const DEFAULT_PIN = '123321';
    
    /**
     * Attempts to start Selenium server if not running
     * This is a fallback mechanism if the runner script hasn't started it
     * 
     * @return bool True if Selenium was started or was already running, false if failed
     */
    private static function attemptToStartSelenium()
    {
        $seleniumJar = getenv('HOME') . '/.selenium/selenium-server.jar';
        
        // Check if jar exists
        if (!file_exists($seleniumJar)) {
            echo "Selenium JAR not found at {$seleniumJar}. Cannot auto-start." . PHP_EOL;
            echo "Run the test through the runner script to download and configure Selenium." . PHP_EOL;
            return false;
        }
        
        // Check if Java is available
        exec('which java', $output, $returnVar);
        if ($returnVar !== 0) {
            echo "Java not found. Cannot start Selenium server." . PHP_EOL;
            return false;
        }
        
        // Check if Selenium is already running
        exec('pgrep -f "selenium-server"', $output, $returnVar);
        if ($returnVar === 0) {
            // Process is running, no need to start
            echo "Selenium process already running." . PHP_EOL;
            return true;
        }
        
        echo "Attempting to start Selenium server..." . PHP_EOL;
        
        // Start Selenium in the background
        $cmd = "java -jar \"$seleniumJar\" standalone > /dev/null 2>&1 &";
        exec($cmd);
        
        // Give it time to start
        sleep(5);
        return true;
    }
    
    /**
     * Check if Selenium server is available and ready
     * 
     * @param string $seleniumUrl URL of the Selenium server
     * @return bool True if server is ready, false otherwise
     */
    public static function isSeleniumAvailable($seleniumUrl)
    {
        // First, let's try to auto-start Selenium if it's configured
        self::attemptToStartSelenium();
        
        // Check status endpoint to verify Selenium is actually ready
        $statusUrl = rtrim($seleniumUrl, '/') . '/status';
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300 && !empty($response)) {
            $json = json_decode($response, true);
            if (isset($json['value']['ready']) && $json['value']['ready'] === true) {
                echo "Selenium server is ready and accepting commands." . PHP_EOL;
                return true;
            }
            echo "Selenium server is running but not ready. Status response: " . $response . PHP_EOL;
        }
        
        echo "Selenium server not available or not responding correctly. HTTP code: {$httpCode}" . PHP_EOL;
        return false;
    }
    
    /**
     * Extract mobile number from OAuth URL
     * 
     * @param string $url OAuth URL
     * @return string|null Extracted mobile number or null if not found
     */
    public static function extractMobileFromUrl($url)
    {
        if (preg_match('/mobile=(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Helper method to wait for a specific number of seconds
     * 
     * @param float $seconds Seconds to wait
     */
    public static function wait($seconds)
    {
        usleep((int)($seconds * 1000000));
    }
    
    /**
     * Automate OAuth flow to obtain auth code
     * 
     * @param string $oauthUrl OAuth URL
     * @param string|null $phoneNumber Optional phone number to use
     * @param string|null $pinCode Optional PIN code to use
     * @param string|null $outputFile Optional file to write auth code to
     * @return string|null Auth code or null if not found
     */
    public static function automateOauth($oauthUrl, $phoneNumber = null, $pinCode = null, $outputFile = null)
    {
        if (empty($oauthUrl)) {
            echo "Error: OAuth URL is empty" . PHP_EOL;
            return null;
        }
        
        echo "Starting OAuth automation for URL: {$oauthUrl}" . PHP_EOL;
        
        // Override selenium server URL if provided in environment
        $seleniumUrl = getenv('SELENIUM_SERVER_URL') ?: self::DEFAULT_SELENIUM_URL;
        
        // Check if Selenium server is available
        if (!self::isSeleniumAvailable($seleniumUrl)) {
            echo "Selenium server not available at {$seleniumUrl}" . PHP_EOL;
            echo "Please run the test through the runner script: ./runners/run-test-php.sh" . PHP_EOL;
            return null;
        }
        
        echo "Selenium server available at {$seleniumUrl}" . PHP_EOL;
        
        // Use provided phoneNumber or extract from URL
        $mobileNumber = $phoneNumber ?: self::extractMobileFromUrl($oauthUrl);
        $pinToUse = $pinCode ?: self::DEFAULT_PIN;
        
        echo "Using mobile number: {$mobileNumber}" . PHP_EOL;
        echo "Using PIN: {$pinToUse}" . PHP_EOL;
        
        $foundAuthCode = null;
        $driver = null;
        
        try {
            // Set up Chrome options
            $chromeOptions = new ChromeOptions();
            
            // Common Chrome options for stability and mobile emulation
            $chromeOptions->addArguments([
                '--headless',
                '--disable-gpu',
                '--disable-web-security',
                '--disable-features=IsolateOrigins',
                '--disable-site-isolation-trials',
                '--disable-features=BlockInsecurePrivateNetworkRequests',
                '--disable-blink-features=AutomationControlled',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-extensions'
            ]);
            
            // Set up mobile emulation for better compatibility
            $mobileEmulation = [
                'deviceName' => 'iPhone X'
            ];
            $chromeOptions->setExperimentalOption('mobileEmulation', $mobileEmulation);
            
            // Configure capabilities
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
            
            // Create WebDriver
            echo "Connecting to WebDriver..." . PHP_EOL;
            $driver = RemoteWebDriver::create($seleniumUrl, $capabilities);
            $driver->manage()->window()->setSize(new WebDriverDimension(375, 812));
            
            // Navigate to the OAuth URL
            echo "Navigating to OAuth URL..." . PHP_EOL;
            $driver->get($oauthUrl);
            
            // Wait for page to load
            self::wait(2);
            
            // Fill phone input field using JavaScript
            echo "Looking for phone input field..." . PHP_EOL;
            $phoneInputFilled = $driver->executeScript(
                'const inputs = document.querySelectorAll("input");' .
                'for (const input of Array.from(inputs)) {' .
                '  if (input.type === "tel" || ' .
                '      input.placeholder === "12312345678" || ' .
                '      input.maxLength === 13 || ' .
                '      input.className.includes("phone-number")) {' .
                '    input.value = arguments[0];' .
                '    input.dispatchEvent(new Event("input", { bubbles: true }));' .
                '    return { filled: true, message: "Found and filled mobile number input" };' .
                '  }' .
                '}' .
                'return { filled: false, message: "No suitable mobile number input found" };',
                [$mobileNumber]
            );
            
            if (isset($phoneInputFilled['filled']) && $phoneInputFilled['filled']) {
                echo "Phone input filled: {$phoneInputFilled['message']}" . PHP_EOL;
            } else {
                echo "Warning: {$phoneInputFilled['message']}" . PHP_EOL;
            }
            
            // Find and click the next/submit button
            echo "Looking for submit button..." . PHP_EOL;
            $submitButtonClicked = $driver->executeScript(
                'const buttons = document.querySelectorAll("button");' .
                'for (const button of Array.from(buttons)) {' .
                '  if (button.type === "submit" || ' .
                '      button.innerText.includes("Next") || ' .
                '      button.innerText.includes("Continue") || ' .
                '      button.innerText.includes("Submit") || ' .
                '      button.innerText.includes("Lanjutkan")) {' .
                '    button.click();' .
                '    return { clicked: true, message: "Found and clicked button via JS evaluation" };' .
                '  }' .
                '}' .
                'return { clicked: false, message: "No suitable submit button found" };'
            );
            
            if (isset($submitButtonClicked['clicked']) && $submitButtonClicked['clicked']) {
                echo "Submit button clicked: {$submitButtonClicked['message']}" . PHP_EOL;
            } else {
                echo "Warning: {$submitButtonClicked['message']}" . PHP_EOL;
            }
            
            // Wait for the next screen
            self::wait(2);
            
            // Check if there's a continue button to proceed to PIN input
            $needToContinue = $driver->executeScript(
                'const continueBtn = document.querySelector("button.btn-continue.fs-unmask.btn.btn-primary");' .
                'if (continueBtn) {' .
                '  continueBtn.click();' .
                '  return { clicked: true, message: "Found another continue button - this might be needed to proceed to PIN input" };' .
                '}' .
                'return { clicked: false, message: "No additional continue button found" };'
            );
            
            if (isset($needToContinue['clicked']) && $needToContinue['clicked']) {
                echo "Continue button clicked: {$needToContinue['message']}" . PHP_EOL;
                self::wait(1.5);
            }
            
            // Enter PIN using JavaScript
            echo "Looking for PIN input fields..." . PHP_EOL;
            $pinInputResult = $driver->executeScript(
                'const specificPinInput = document.querySelector(".txt-input-pin-field");' .
                'if (specificPinInput) {' .
                '  specificPinInput.value = arguments[0];' .
                '  specificPinInput.dispatchEvent(new Event("input", { bubbles: true }));' .
                '  specificPinInput.dispatchEvent(new Event("change", { bubbles: true }));' .
                '  return { success: true, method: "specific", message: "Found specific PIN input field: .txt-input-pin-field" };' .
                '}' .
                'const inputs = document.querySelectorAll("input");' .
                'const singlePinInput = Array.from(inputs).find(input => ' .
                '  input.maxLength === 6 && ' .
                '  (input.type === "text" || input.type === "tel" || input.type === "number" || input.inputMode === "numeric")' .
                ');' .
                'if (singlePinInput) {' .
                '  singlePinInput.value = arguments[0];' .
                '  singlePinInput.dispatchEvent(new Event("input", { bubbles: true }));' .
                '  singlePinInput.dispatchEvent(new Event("change", { bubbles: true }));' .
                '  return { success: true, method: "single", message: "Found single PIN input field with maxLength=6" };' .
                '}' .
                'const pinInputs = Array.from(inputs).filter(input => ' .
                '  input.maxLength === 1 || ' .
                '  input.type === "password" || ' .
                '  input.className.includes("pin")' .
                ');' .
                'if (pinInputs.length >= arguments[0].length) {' .
                '  for (let i = 0; i < arguments[0].length; i++) {' .
                '    pinInputs[i].value = arguments[0].charAt(i);' .
                '    pinInputs[i].dispatchEvent(new Event("input", { bubbles: true }));' .
                '    pinInputs[i].dispatchEvent(new Event("change", { bubbles: true }));' .
                '  }' .
                '  return { success: true, method: "multi", message: `Found ${pinInputs.length} PIN inputs via JS` };' .
                '}' .
                'return { success: false, method: "none", message: "Could not find any suitable PIN input field" };',
                [$pinToUse]
            );
            
            if (isset($pinInputResult['success']) && $pinInputResult['success']) {
                echo "PIN input successful: {$pinInputResult['message']} (method: {$pinInputResult['method']})" . PHP_EOL;
            } else {
                echo "Warning: {$pinInputResult['message']}" . PHP_EOL;
            }
            
            // Try to find and click a confirm button after PIN entry
            echo "Looking for confirm button after PIN entry..." . PHP_EOL;
            $buttonClicked = $driver->executeScript(
                'const allButtons = document.querySelectorAll("button");' .
                'let continueButton, backButton;' .
                'allButtons.forEach((button) => {' .
                '  const buttonText = button.innerText.trim().toLowerCase();' .
                '  if (buttonText.includes("lanjut") || ' .
                '      buttonText.includes("continue") || ' .
                '      buttonText.includes("submit") || ' .
                '      buttonText.includes("confirm") || ' .
                '      buttonText.includes("next") || ' .
                '      button.className.includes("btn-continue") || ' .
                '      button.className.includes("btn-submit") || ' .
                '      button.className.includes("btn-confirm")) {' .
                '    continueButton = button;' .
                '  }' .
                '});' .
                'if (continueButton) {' .
                '  continueButton.click();' .
                '  return { clicked: true, message: "Found continue button, clicked it: " + continueButton.innerText };' .
                '}' .
                'return { clicked: false, message: "No confirm/continue button found" };'
            );
            
            if (isset($buttonClicked['clicked']) && $buttonClicked['clicked']) {
                echo "Confirm button clicked: {$buttonClicked['message']}" . PHP_EOL;
            }
            
            // Wait for potential redirects
            echo "Waiting for redirects to capture auth code..." . PHP_EOL;
            $startTime = time();
            $timeout = 5; // seconds
            
            // Keep checking current URL for auth_code parameter
            while (time() - $startTime < $timeout) {
                $currentUrl = $driver->getCurrentURL();
                
                if (strpos($currentUrl, 'google.com') !== false) {
                    // Parse URL to extract auth_code parameter
                    $urlParts = parse_url($currentUrl);
                    if (isset($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                        
                        if (isset($queryParams['auth_code'])) {
                            $foundAuthCode = $queryParams['auth_code'];
                            echo "Auth code found: {$foundAuthCode}" . PHP_EOL;
                            break;
                        }
                    }
                }
                
                // Short wait before checking again
                self::wait(0.5);
            }
            
            // If auth code was not found, log the issue
            if (empty($foundAuthCode)) {
                echo "Auth code not found within timeout period" . PHP_EOL;
                echo "Final URL: " . $driver->getCurrentURL() . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo "Error during OAuth automation: {$e->getMessage()}" . PHP_EOL;
            // Additional debug info may be helpful
            if ($driver !== null) {
                echo "Current URL: " . $driver->getCurrentURL() . PHP_EOL;
                echo "Page source length: " . strlen($driver->getPageSource()) . " bytes" . PHP_EOL;
            }
        } finally {
            // Always close the browser
            if ($driver !== null) {
                try {
                    $driver->quit();
                    echo "Browser closed successfully" . PHP_EOL;
                } catch (\Exception $e) {
                    echo "Error closing browser: {$e->getMessage()}" . PHP_EOL;
                }
            }
        }
        
        // Write auth code to output file if specified
        if ($outputFile && $foundAuthCode) {
            file_put_contents($outputFile, $foundAuthCode);
            echo "Auth code written to: {$outputFile}" . PHP_EOL;
        }
        
        return $foundAuthCode;
    }

    /**
     * Automate the payment process on the DANA widget payment page
     *
     * @param string $paymentUrl The payment redirect URL to open
     * @param bool $headless Whether to run browser in headless mode
     * @param string $outputFile Optional path to write success status
     * @return bool True if payment was successful, false otherwise
     */
    public static function automatePaymentWidget($paymentUrl, $headless = true, $outputFile = null)
    {
        if (empty($paymentUrl)) {
            echo "Error: Payment URL is empty" . PHP_EOL;
            return false;
        }
        
        echo "Starting payment automation" . PHP_EOL;
        echo "Payment URL: {$paymentUrl}" . PHP_EOL;
        
        // Override selenium server URL if provided in environment
        $seleniumUrl = getenv('SELENIUM_SERVER_URL') ?: self::DEFAULT_SELENIUM_URL;
        
        // Check if Selenium server is available
        if (!self::isSeleniumAvailable($seleniumUrl)) {
            echo "Selenium server not available at {$seleniumUrl}" . PHP_EOL;
            return false;
        }
        
        $driver = null;
        $success = false;
        
        try {
            // Set up Chrome options
            echo "Launching browser..." . PHP_EOL;
            $chromeOptions = new ChromeOptions();
            
            // Common Chrome options for stability and mobile emulation
            $chromeOptions->addArguments([
                '--headless',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-web-security',
                '--disable-features=IsolateOrigins',
                '--disable-site-isolation-trials',
                '--disable-features=BlockInsecurePrivateNetworkRequests',
                '--disable-blink-features=AutomationControlled',
                '--disable-dev-shm-usage'
            ]);
            
            // Set up mobile emulation for better compatibility with payment widget
            $mobileEmulation = [
                'deviceName' => 'iPhone X'
            ];
            $chromeOptions->setExperimentalOption('mobileEmulation', $mobileEmulation);
            
            // Configure capabilities
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
            
            // Create WebDriver
            echo "Connecting to WebDriver..." . PHP_EOL;
            $driver = RemoteWebDriver::create($seleniumUrl, $capabilities);
            $driver->manage()->window()->setSize(new WebDriverDimension(390, 844));
            
            // Navigate to the payment URL
            echo "Navigating to payment URL..." . PHP_EOL;
            $driver->get($paymentUrl);
            
            // Wait for page to load and payment button to be visible
            echo "Waiting for payment button..." . PHP_EOL;
            try {
                $driver->wait(60, 1000)->until(
                    WebDriverExpectedCondition::visibilityOfElementLocated(
                        WebDriverBy::cssSelector('.btn.btn-primary.btn-pay')
                    )
                );
            } catch (\Exception $e) {
                echo "Payment button not found after waiting: {$e->getMessage()}" . PHP_EOL;
                
                // Try other common selectors as fallback
                $payButtonSelectors = [
                    '.btn-pay',
                    '.payment-button',
                    '.btn-primary',
                    '.dana-button',
                    'button[type="submit"]'
                ];
                
                $buttonFound = false;
                foreach ($payButtonSelectors as $selector) {
                    try {
                        $elements = $driver->findElements(WebDriverBy::cssSelector($selector));
                        if (count($elements) > 0) {
                            echo "Found alternative payment button with selector: {$selector}" . PHP_EOL;
                            $buttonFound = true;
                            break;
                        }
                    } catch (\Exception $e) {
                        // Continue trying other selectors
                    }
                }
                
                if (!$buttonFound) {
                    throw new \Exception("Could not find any payment button after trying multiple selectors");
                }
            }
            
            // Click the payment button
            sleep(2);
            echo "Clicking payment button..." . PHP_EOL;
            try {
                $payButton = $driver->findElement(WebDriverBy::cssSelector('.btn.btn-primary.btn-pay'));
                $payButton->click();
            } catch (\Exception $e) {
                echo "Error clicking primary payment button: {$e->getMessage()}, trying JavaScript click..." . PHP_EOL;
                
                // Try JavaScript click as fallback
                $driver->executeScript(
                    'const buttons = document.querySelectorAll("button");' .
                    'for (const button of Array.from(buttons)) {' .
                    '  if (button.classList.contains("btn-pay") || ' .
                    '      button.innerText.includes("Pay") || ' .
                    '      button.innerText.includes("BAYAR")) {' .
                    '    button.click();' .
                    '    return true;' .
                    '  }' .
                    '}' .
                    'return false;'
                );
            }
            
            // Wait for payment success message
            echo "Waiting for payment success message..." . PHP_EOL;
            try {
                $driver->wait(120, 1000)->until(function ($driver) {
                    $pageSource = $driver->getPageSource();
                    return (strpos($pageSource, 'Payment Success') !== false) ||
                           (strpos($pageSource, 'Pembayaran Berhasil') !== false);
                });
                
                echo "Payment completed successfully!" . PHP_EOL;
                $success = true;
                sleep(5); // Wait for payment to complete
            } catch (\Exception $e) {
                echo "Timeout waiting for payment success: {$e->getMessage()}" . PHP_EOL;
                echo "Final page source snippet: " . substr($driver->getPageSource(), 0, 500) . "..." . PHP_EOL;
            }
            
            // Output file handling is optional
            if ($outputFile && $success) {
                $outputDir = dirname($outputFile);
                if (!file_exists($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }
                
                file_put_contents($outputFile, 'SUCCESS', LOCK_EX);
                echo "Wrote success indicator to {$outputFile}" . PHP_EOL;
            }
            
        } catch (\Exception $e) {
            echo "Error during payment automation: {$e->getMessage()}" . PHP_EOL;
            if ($driver !== null) {
                echo "Current URL: " . $driver->getCurrentURL() . PHP_EOL;
            }
        } finally {
            // Always close the browser
            if ($driver !== null) {
                try {
                    $driver->quit();
                    echo "Browser closed successfully" . PHP_EOL;
                } catch (\Exception $e) {
                    echo "Error closing browser: {$e->getMessage()}" . PHP_EOL;
                }
            }
        }
        
        return $success;
    }
}