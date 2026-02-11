<?php

namespace DanaUat\Widget\Scripts;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;

/**
 * WebAutomation class for DANA Widget E2E Testing
 * 
 * This class provides comprehensive browser automation capabilities for testing DANA widget
 * integration scenarios, including OAuth authentication and payment processing flows.
 * 
 * Features:
 * - Cross-browser compatibility (Chrome, Firefox)
 * - Headless and non-headless execution modes
 * - Vue.js and React component interaction support
 * - Mobile device emulation
 * - Enhanced PIN input handling for modern JavaScript frameworks
 * - Robust error handling and fallback mechanisms
 * 
 * @package DanaUat\Widget\Scripts
 * @author DANA Integration Team
 * @version 2.0.0
 */
class WebAutomation
{
    /** @var string Default Selenium server URL */
    const DEFAULT_SELENIUM_URL = 'http://localhost:4444/wd/hub';
    
    /** @var string Default phone number for testing */
    const DEFAULT_PHONE_NUMBER = '83811223355';
    
    /** @var string Default PIN for authentication */
    const DEFAULT_PIN = '181818';
    
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
     * Utility method to pause execution for a specified duration
     * 
     * This method provides precise timing control for automation scenarios where
     * elements need time to load, render, or process. Uses microsecond precision
     * for accurate timing.
     * 
     * @param float $seconds Number of seconds to wait (supports decimals)
     * 
     * @example
     * WebAutomation::wait(1.5);    // Wait 1.5 seconds
     * WebAutomation::wait(0.25);   // Wait 250 milliseconds
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
            
            // Configure capabilities
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
            
            // Create WebDriver
            echo "Connecting to WebDriver..." . PHP_EOL;
            $driver = RemoteWebDriver::create($seleniumUrl, $capabilities);
            $driver->manage()->window()->maximize();
            
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
                '  if (button.innerText.includes("Next") || ' .
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
            
            // Enter PIN using our enhanced JavaScript method
            echo "Looking for PIN input fields..." . PHP_EOL;
            $pinInputSuccess = self::handlePinWithJavaScript($driver, $pinToUse);
            
            if ($pinInputSuccess) {
                echo "PIN input successful using enhanced JavaScript method!" . PHP_EOL;
            } else {
                echo "Warning: Enhanced PIN input method failed, trying fallback..." . PHP_EOL;
                
                // Fallback to the original method
                $pinInputResult = $driver->executeScript(
                    'const specificPinInput = document.querySelector(".txt-input-pin-field");' .
                    'if (specificPinInput) {' .
                    '  specificPinInput.value = arguments[0];' .
                    '  specificPinInput.dispatchEvent(new Event("input", { bubbles: true }));' .
                    '  specificPinInput.dispatchEvent(new Event("change", { bubbles: true }));' .
                    '  return { success: true, method: "specific", message: "Found specific PIN input field: .txt-input-pin-field" };' .
                    '}' .
                    'return { success: false, method: "none", message: "Could not find PIN input field" };',
                    [$pinToUse]
                );
                
                if (isset($pinInputResult['success']) && $pinInputResult['success']) {
                    echo "Fallback PIN input successful: {$pinInputResult['message']}" . PHP_EOL;
                } else {
                    echo "Warning: Both PIN input methods failed!" . PHP_EOL;
                }
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
                echo "Current URL: {$currentUrl}" . PHP_EOL;
                
                // Check if URL contains auth_code parameter (regardless of domain)
                if (strpos($currentUrl, 'auth_code=') !== false || strpos($currentUrl, 'authCode=') !== false) {
                    echo "Auth code parameter detected in URL: {$currentUrl}" . PHP_EOL;
                    
                    // Parse URL to extract auth_code parameter
                    $urlParts = parse_url($currentUrl);
                    if (isset($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                        
                        if (isset($queryParams['auth_code']) || isset($queryParams['authCode'])) {
                            $foundAuthCode = $queryParams['auth_code'] ?? $queryParams['authCode'];
                            echo "Auth code found: {$foundAuthCode}" . PHP_EOL;
                            break;
                        }
                    }
                    
                    // Alternative regex method as fallback
                    if (empty($foundAuthCode) && preg_match('/auth_code=([^&]+)/', $currentUrl, $matches) && preg_match('/authCode=([^&]+)/', $currentUrl, $matches)) {
                        $foundAuthCode = $matches[1];
                        echo "Auth code found via regex: {$foundAuthCode}" . PHP_EOL;
                        break;
                    }
                }
                
                // Also check for redirect URL match as secondary condition
                $redirectUrl = getenv('REDIRECT_URL_OAUTH');
                if (!empty($redirectUrl) && strpos($currentUrl, $redirectUrl) !== false) {
                    echo "Redirect URL reached: {$currentUrl}" . PHP_EOL;
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
     * Automate the complete payment process on the DANA widget payment page
     *
     * This method handles the full payment flow including:
     * - Phone number entry with multiple selector fallbacks
     * - PIN authentication using Vue.js-compatible JavaScript injection
     * - Payment button interaction with error handling
     * - Success verification and status reporting
     *
     * @param string $paymentUrl The payment redirect URL to open and process
     * @param bool $headless Whether to run browser in headless mode (default: true)
     * @param string|null $outputFile Optional path to write success status file
     * @return bool True if payment was successfully completed, false otherwise
     * 
     * @throws \Exception If Selenium server is unavailable or browser fails to launch
     * 
     * @example
     * $success = WebAutomation::automatePaymentWidget(
     *     'https://m.sandbox.dana.id/n/cashier/new/checkout?bizNo=...',
     *     true,
     *     '/tmp/payment_success.txt'
     * );
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
            
            // Configure capabilities
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
            
            // Create WebDriver
            echo "Connecting to WebDriver..." . PHP_EOL;
            $driver = RemoteWebDriver::create($seleniumUrl, $capabilities);
            $driver->manage()->window()->maximize();

            // Navigate to the payment URL
            echo "Navigating to payment URL..." . PHP_EOL;
            $driver->get($paymentUrl);
            
            try {
                // Selector definitions
                $inputPinSelector = ".txt-input-pin-field";
                $buttonPaySelector = ".btn.btn-primary.btn-pay";
                $submitPhoneNumberSelector = ".agreement__button>.btn-continue";
                $labelFailedPayment = ".lbl-failed-payment";
                $phoneSelectors = [
                    '.desktop-input>.txt-input-phone-number-field',  // Primary selector for desktop layout
                    '.txt-input-phone-number-field',                 // Fallback without parent container
                    'input[type="tel"]',                             // Standard telephone input
                    'input[name="phone"]',                           // By name attribute
                    'input[placeholder*="phone"]',                   // By placeholder containing "phone"
                    'input[placeholder*="nomor"]',                   // Indonesian placeholder text
                    'input[inputmode="tel"]',                        // By input mode for mobile
                    'input[type="text"]',                            // Generic text input fallback
                    'input:not([type="hidden"]):not([type="submit"]):not([type="button"])' // Any visible input
                ];
                $buttonDanaPaymentSelector = "//*[contains(@class,'dana')]/*[contains(@class,'bank-title')]";

                $buttonElements = $driver->findElements(WebDriverBy::xpath($buttonDanaPaymentSelector));
                if (count($buttonElements) > 0) {
                    echo "DANA payment option found, clicking it..." . PHP_EOL;
                    $buttonElements[0]->click();
                    self::wait(1.0); // Wait for the page to update
                } else {
                    echo "DANA payment option not found, proceeding..." . PHP_EOL;
                }

                // Attempt to locate phone number input field
                $phoneSelectorElement = null;
                
                // Try each selector until we find a phone input field
                foreach ($phoneSelectors as $selector) {
                    try {
                        $elements = $driver->findElements(WebDriverBy::cssSelector($selector));
                        if (count($elements) > 0) {
                            $phoneSelectorElement = $elements[0];
                            echo "Found phone input with selector: {$selector}" . PHP_EOL;
                            break;
                        }
                    } catch (\Exception $e) {
                        // Continue to next selector if current one fails
                    }
                }

                // Process phone number entry and PIN input
                echo "Filling phone number and PIN..." . PHP_EOL;
                if ($phoneSelectorElement) {
                    // Enter phone number and proceed to PIN authentication
                    $phoneSelectorElement->sendKeys(self::DEFAULT_PHONE_NUMBER);
                    $driver->findElement(WebDriverBy::cssSelector($submitPhoneNumberSelector))->click();
                    
                    // Wait for PIN fields to appear after phone number submission
                    echo "Waiting for PIN fields to appear..." . PHP_EOL;
                    self::wait(3.0);
                    
                    // Use enhanced JavaScript PIN handling method for Vue.js compatibility
                    echo "Entering PIN using JavaScript method..." . PHP_EOL;
                    $pinSuccess = self::handlePinWithJavaScript($driver, self::DEFAULT_PIN);
                    
                    // Fallback to traditional WebDriver method if JavaScript approach fails
                    // Fallback to traditional WebDriver method if JavaScript approach fails
                    if (!$pinSuccess) {
                        echo "JavaScript PIN method failed, trying fallback WebDriver method..." . PHP_EOL;
                        try {
                            $driver->findElement(WebDriverBy::cssSelector($inputPinSelector))->sendKeys(self::DEFAULT_PIN);
                            echo "Fallback PIN entry completed" . PHP_EOL;
                        } catch (\Exception $pinError) {
                            echo "Both PIN methods failed: {$pinError->getMessage()}" . PHP_EOL;
                        }
                    }

                    // Wait for PIN processing and potential page transition
                    echo "Waiting for PIN processing..." . PHP_EOL;
                    self::wait(1.0);
                } else {
                    echo "Could not find phone number input field with any selector" . PHP_EOL;
                    echo "This might indicate the page hasn't loaded completely or has a different structure" . PHP_EOL;
                }

                // Wait for payment button to become visible after authentication
                $driver->wait(60, 1000)->until(
                    WebDriverExpectedCondition::visibilityOfElementLocated(
                        WebDriverBy::cssSelector($buttonPaySelector)
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
            echo "Clicking payment button..." . PHP_EOL;
            try {
                $payButton = $driver->findElement(WebDriverBy::cssSelector($buttonPaySelector));
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

            // Make sure payment success
            $driver->get($paymentUrl);
            $driver->wait(60, 1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(
                    WebDriverBy::cssSelector($buttonPaySelector)
                )
            );
        
            $payButton = $driver->findElement(WebDriverBy::cssSelector($buttonPaySelector));
            $payButton->click();

            $driver->wait(60, 1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(
                    WebDriverBy::cssSelector($labelFailedPayment)
                )
            );

            // Wait for payment success message
            echo "Payment success" . PHP_EOL;
            
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
    
    /**
     * Handle PIN input using advanced JavaScript for Vue.js and React compatibility
     * 
     * This method provides robust PIN entry capabilities that work with modern JavaScript
     * frameworks by using native DOM manipulation and comprehensive event simulation.
     * 
     * Key features:
     * - Multiple selector strategies for different PIN field implementations
     * - Vue.js reactive system compatibility using native setters
     * - Comprehensive event simulation (input, change, blur, keydown, keyup)
     * - Automatic form submission with Enter key simulation
     * - Fallback button clicking for various UI patterns
     * 
     * Supported PIN field types:
     * - Single input fields (maxlength=6)
     * - Vue.js component fields with data attributes
     * - Password type inputs
     * - Numeric input mode fields
     * - Pattern-based validation fields
     * 
     * @param RemoteWebDriver $driver Active WebDriver instance
     * @param string|null $pin PIN code to enter (uses DEFAULT_PIN if null)
     * @return bool True if PIN was successfully entered and submitted, false otherwise
     * 
     * @example
     * $success = WebAutomation::handlePinWithJavaScript($driver, '123456');
     * if (!$success) {
     *     echo "PIN entry failed, check if element exists and is visible";
     * }
     */
    public static function handlePinWithJavaScript($driver, $pin = null)
    {
        $pinToUse = $pin ?: self::DEFAULT_PIN;
        
        echo "Attempting to enter PIN using JavaScript method..." . PHP_EOL;
        
        // JavaScript to find and fill the PIN input element
        $jsScript = "
            // Function to find PIN input element with comprehensive selector coverage
            function findPinInput() {
                // Try multiple selectors for PIN input fields in order of specificity
                const selectors = [
                    'input.txt-input-pin-field',                    // Primary DANA PIN field
                    'input[class*=\"txt-input-pin-field\"]',        // Class contains PIN field
                    'input[type=\"text\"][inputmode=\"numeric\"]',  // Numeric text input
                    'input[type=\"text\"][pattern=\"[0-9]*\"]',     // Text input with numeric pattern
                    'input[maxlength=\"6\"][pattern=\"[0-9]*\"]',   // 6-digit numeric input
                    'input[data-v-3a4f5050]',                       // Vue.js component instance
                    'input[autofocus=\"true\"][maxlength=\"6\"]',   // Auto-focused 6-digit input
                    'input[type=\"password\"]',                     // Password type input
                    'input[name=\"pin\"]',                          // By name attribute
                    'input[name=\"password\"]',                     // By password name
                    'input[placeholder*=\"PIN\"]',                  // Placeholder contains PIN
                    'input[placeholder*=\"pin\"]',                  // Lowercase PIN in placeholder
                    'input[class*=\"pin\"]',                        // Class contains pin
                    'input[id*=\"pin\"]',                           // ID contains pin
                    'input[inputmode=\"numeric\"]',                 // Any numeric input mode
                    'input[pattern*=\"[0-9]\"]'                     // Any numeric pattern
                ];
                
                // Try each selector and return the first match
                for (let selector of selectors) {
                    const element = document.querySelector(selector);
                    if (element && element.offsetParent !== null) {
                        console.log('Found PIN input with selector:', selector);
                        return element;
                    }
                }
                
                console.log('PIN input not found with any selector');
                return null;
            }
            
            // Function to set PIN value and trigger appropriate events for Vue.js compatibility
            function setPinValue(element, value) {
                // Clear any existing value
                element.value = '';
                
                // Set the new value using native setter to bypass Vue.js reactivity system
                const nativeSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
                nativeSetter.call(element, value);
                
                // Trigger essential input events for Vue.js component reactivity
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
                element.dispatchEvent(new Event('blur', { bubbles: true }));
                
                // Focus the element and simulate individual key presses
                element.focus();
                
                // Simulate keydown and keyup events for each digit to trigger validation
                for (let i = 0; i < value.length; i++) {
                    const char = value[i];
                    element.dispatchEvent(new KeyboardEvent('keydown', { 
                        key: char, 
                        code: 'Digit' + char,
                        bubbles: true 
                    }));
                    element.dispatchEvent(new KeyboardEvent('keyup', { 
                        key: char, 
                        code: 'Digit' + char,
                        bubbles: true 
                    }));
                }
                
                // Trigger Enter key to submit the PIN
                element.dispatchEvent(new KeyboardEvent('keydown', { 
                    key: 'Enter', 
                    code: 'Enter',
                    bubbles: true 
                }));
                element.dispatchEvent(new KeyboardEvent('keyup', { 
                    key: 'Enter', 
                    code: 'Enter',
                    bubbles: true 
                }));
                
                console.log('PIN value set to:', element.value);
                return true;
            }
            
            // Main execution
            const pinInput = findPinInput();
            if (pinInput) {
                return setPinValue(pinInput, arguments[0]);
            }
            
            return false;
        ";
        
        try {
            $result = $driver->executeScript($jsScript, [$pinToUse]);
            
            if ($result) {
                echo "PIN entered successfully using JavaScript!" . PHP_EOL;
                
                // Wait a moment for the events to process
                sleep(2);
                
                // Try to find and click submit button after PIN entry
                $submitScript = "
                    const submitSelectors = [
                        'button[type=\"submit\"]',
                        '.btn-submit',
                        '.btn-primary',
                        '.dana-button',
                        'button:contains(\"Submit\")',
                        'button:contains(\"Confirm\")',
                        'button:contains(\"Continue\")'
                    ];
                    
                    for (let selector of submitSelectors) {
                        const button = document.querySelector(selector);
                        if (button && button.offsetParent !== null) {
                            button.click();
                            console.log('Clicked submit button with selector:', selector);
                            return true;
                        }
                    }
                    
                    // Try clicking any visible button as fallback
                    const allButtons = document.querySelectorAll('button');
                    for (let button of allButtons) {
                        if (button.offsetParent !== null && button.textContent.trim()) {
                            button.click();
                            console.log('Clicked fallback button:', button.textContent);
                            return true;
                        }
                    }
                    
                    return false;
                ";
                
                $submitResult = $driver->executeScript($submitScript);
                if ($submitResult) {
                    echo "Submit button clicked successfully!" . PHP_EOL;
                }
                
                return true;
            } else {
                echo "Failed to find PIN input element" . PHP_EOL;
                return false;
            }
            
        } catch (\Exception $e) {
            echo "Error executing PIN JavaScript: {$e->getMessage()}" . PHP_EOL;
            return false;
        }
    }
}