<?php
/**
 * Web Automation Helper for Payment Tests
 *
 * PHP version 7.4
 *
 * @category Test
 * @package  Dana\PaymentGateway\Scripts
 * @author   DANA Indonesia
 * @link     https://dashboard.dana.id/
 */

namespace DanaUat\PaymentGateway\Scripts;

// Import Facebook WebDriver classes if available
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;
use Exception;

/**
 * WebAutomation Class
 * 
 * Provides helper functions for web automation in tests
 */
class WebAutomation
{
    // Default test credentials
    const DEFAULT_PHONE_NUMBER = '811742234';
    const DEFAULT_PIN = '123321';
    
    // Default Selenium settings
    const DEFAULT_SELENIUM_URL = 'http://localhost:4444/wd/hub';
    
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
    public static function automatePayment($paymentUrl, $headless = true, $outputFile = null)
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
        if (!self::isSeleniumAvailable()) {
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
                // Selector
                $inputPinSelector = ".txt-input-pin-field";
                $buttonPaySelector = ".btn.btn-primary.btn-pay";
                $submitPhoneNumberSelector = ".agreement__button>.btn-continue";
                $phoneSelectors = [
                    '.desktop-input>.txt-input-phone-number-field', // Primary selector for desktop layout
                    '.desktop-input>input'                          // Selector for input inside desktop-input container
                ];
                $buttonDanaPaymentSelector = "//*[contains(@class,'dana')]/*[contains(@class,'bank-title')]";

                self::wait(5.0);
                
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

                if (!$phoneSelectorElement) {
                    throw new \Exception("Could not find phone number input field with any selector");
                }

                // Process phone number entry and PIN input
                echo "Filling phone number and PIN..." . PHP_EOL;
                if ($phoneSelectorElement) {
                    // Enter phone number and proceed to PIN authentication
                    $phoneSelectorElement->sendKeys(self::DEFAULT_PHONE_NUMBER);
                    $driver->findElement(WebDriverBy::cssSelector($submitPhoneNumberSelector))->click();
                    
                    self::wait(5.0);
                    
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
                    self::wait(5.0);
                } else {
                    echo "Could not find phone number input field with any selector" . PHP_EOL;
                    echo "This might indicate the page hasn't loaded completely or has a different structure" . PHP_EOL;
                }

                // Wait for payment button to become visible after authentication
                $driver->wait(5, 250)->until(
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
            self::wait(5.0);
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
                self::wait(5.0); // Wait for payment to complete
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
    
    /**
     * Handle OAuth flow with phone number and PIN entry
     *
     * @param RemoteWebDriver $driver WebDriver instance
     * @return void
     */
    public static function handleOAuthFlow($driver)
    {
        echo "Handling OAuth flow..." . PHP_EOL;
        
        // Check if a new window/tab has been opened
        $windowHandles = $driver->getWindowHandles();
        if (count($windowHandles) > 1) {
            // Switch to the latest window/tab (similar to Node.js context.pages()[last])
            $newWindowHandle = end($windowHandles);
            echo "Switching to new window/tab for OAuth flow" . PHP_EOL;
            $driver->switchTo()->window($newWindowHandle);
        }
        
        self::wait(1.5); // 1500ms wait to match TypeScript implementation
        
        // Try to enter phone number - directly match TypeScript implementation's approach
        $phoneEntered = false;
        
        try {
            $phoneSelector = 'label.new-clearable-input.form-ipg-phonenumber';
            $phoneElements = $driver->findElements(WebDriverBy::cssSelector($phoneSelector));
            
            if (count($phoneElements) > 0 && !$phoneEntered) {
                echo "Label with class \"new-clearable-input form-ipg-phonenumber\" found: yes" . PHP_EOL;
                $phoneElements[0]->click();
                $driver->getKeyboard()->sendKeys(self::DEFAULT_PHONE_NUMBER);
                echo "Phone number entered via label.new-clearable-input.form-ipg-phonenumber click" . PHP_EOL;
                $phoneEntered = true;
            } else {
                echo "Phone number label not found" . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo "Error during phone input handling: {$e->getMessage()}" . PHP_EOL;
            return;
        }
        
        self::wait(1);
        
        // Try to click next/continue button
        $nextButtonSelectors = [
            // Standard CSS selectors
            'button.btn-primary',
            'button.btn-next',
            'button.next-btn',
            'button.btn-continue',
            'button.dana-btn-primary',
            'button.submit-button',
            '.btn.btn-primary',
            '.button-submit',
            'button[type="submit"]',
            'form .button',
            '.form-footer button',
            '.footer-button button',
            // XPath selectors for text matching (instead of :has-text)
            '//button[contains(text(),"Next")]',
            '//button[text()="Next"]',
            '//button[contains(text(),"Continue")]',
            '//button[text()="Continue"]',
            '//button[contains(text(),"Lanjut")]',
            '//button[contains(text(),"Lanjutkan")]',
            '//button[contains(text(),"Selanjutnya")]',
            // Additional selectors for common next/continue button patterns
            'button.ant-btn',
            'button.ant-btn-primary'
        ];
        
        $nextButtonClicked = false;
        foreach ($nextButtonSelectors as $selector) {
            try {
                // Use appropriate WebDriverBy method based on selector type (XPath or CSS)
                if (strpos($selector, '//') === 0) {
                    $elements = $driver->findElements(WebDriverBy::xpath($selector));
                    echo "Looking for button using XPath: {$selector}" . PHP_EOL;
                } else {
                    $elements = $driver->findElements(WebDriverBy::cssSelector($selector));
                    echo "Looking for button using CSS: {$selector}" . PHP_EOL;
                }
                
                if (count($elements) > 0) {
                    $isVisible = $elements[0]->isDisplayed();
                    if ($isVisible) {
                        echo "Found visible next button with selector: {$selector}" . PHP_EOL;
                        
                        // Scroll to button if needed (equivalent to scrollIntoViewIfNeeded in Playwright)
                        $driver->executeScript('arguments[0].scrollIntoView(true);', [$elements[0]]);
                        self::wait(0.5); // Matching the 500ms wait in TypeScript
                        
                        $elements[0]->click();
                        $nextButtonClicked = true;
                        break;
                    } else {
                        echo "Button {$selector} found but not visible, trying next selector..." . PHP_EOL;
                    }
                }
            } catch (\Exception $e) {
                echo "Error clicking continue button with selector: {$selector}, {$e->getMessage()}" . PHP_EOL;
                return; // Match TypeScript behavior of returning on error
            }
        }
        
        if (!$nextButtonClicked) {
            echo "Failed to find or click next button!" . PHP_EOL;
            return;
        }
        
        self::wait(1);
        
        // Enter PIN
        self::enterPin($driver);
        
        self::wait(1);
        
        // Click Pay button
        self::clickPayButton($driver);
        
        self::wait(1);
    }
    
    /**
     * Enter PIN for authentication
     *
     * @param RemoteWebDriver $driver WebDriver instance
     * @return void
     */
    public static function enterPin($driver)
    {
        echo "Looking for PIN input fields..." . PHP_EOL;
        
        $pinEntered = false;
        
        // METHOD 1: Try JavaScript direct input method - bypass the click interception
        try {
            echo "Trying JavaScript method for PIN entry" . PHP_EOL;
            // Look for PIN input container first
            $pinContainers = $driver->findElements(WebDriverBy::cssSelector(
                '.password-wrapper, .pin-input-wrapper, .pin-container, .password-item, [class*="pin-input"]'
            ));
            
            if (count($pinContainers) > 0) {
                echo "Found PIN container, using JavaScript to input PIN" . PHP_EOL;
                
                // Find all input fields within containers
                $inputs = $driver->findElements(WebDriverBy::cssSelector(
                    'input[type="password"], input[type="tel"], input[pattern="[0-9]*"], input.password-focus'
                ));
                
                if (count($inputs) >= 1) {
                    // If it's a single input for all digits
                    if (count($inputs) === 1) {
                        echo "Found single PIN input, entering PIN directly" . PHP_EOL;
                        // Use JavaScript to set value directly (bypassing possible interceptions)
                        $driver->executeScript(
                            'arguments[0].value = arguments[1]; arguments[0].dispatchEvent(new Event("input")); arguments[0].dispatchEvent(new Event("change"));', 
                            [$inputs[0], self::DEFAULT_PIN]
                        );
                        $pinEntered = true;
                    } 
                    // If we have individual inputs for each digit (typical pattern)
                    else if (count($inputs) >= 6) {
                        echo "Found " . count($inputs) . " PIN input fields, entering digits individually" . PHP_EOL;
                        
                        // Use JavaScript to populate each input without clicking
                        for ($i = 0; $i < 6 && $i < count($inputs); $i++) {
                            $digit = substr(self::DEFAULT_PIN, $i, 1);
                            $driver->executeScript(
                                'arguments[0].value = arguments[1]; arguments[0].dispatchEvent(new Event("input")); arguments[0].dispatchEvent(new Event("change"));',
                                [$inputs[$i], $digit]
                            );
                            self::wait(0.2);
                        }
                        $pinEntered = true;
                    }
                    
                    if ($pinEntered) {
                        echo "PIN entered successfully via JavaScript" . PHP_EOL;
                    }
                }
            }
        } catch (\Exception $e) {
            echo "Error using JavaScript method for PIN: {$e->getMessage()}" . PHP_EOL;
        }
        
        self::wait(2);
    }
    
    /**
     * Click the Pay button to complete transaction
     *
     * @param RemoteWebDriver $driver WebDriver instance
     */
    public static function clickPayButton($driver)
    {
        $payButtonSelectors = [
            // CSS Selectors
            'button.btn.btn-primary.btn-pay',
            'button.btn-pay',
            'button.payment-button',
            'button.btn-primary',
            'button.dana-button',
            'button.ant-btn',
            'button[type="submit"]',
            // XPath Selectors for text matching (replacing invalid :contains pseudo-selector)
            '//button[contains(text(),"PAY Rp")]',
            '//button[contains(text(),"Bayar Rp")]',
            '//button[contains(text(),"PAY")]',
            '//button[contains(text(),"BAYAR")]',
            '//button[contains(text(),"Pay")]',
            '//button[contains(text(),"Bayar")]',
            '//button[contains(text(),"Confirm")]',
            '//button[contains(text(),"Continue")]'
        ];
        
        // Try to click pay button using various selectors
        foreach ($payButtonSelectors as $selector) {
            echo "Looking for pay button with selector: {$selector}" . PHP_EOL;
            
            try {
                // Determine if using XPath or CSS selector
                if (strpos($selector, '//') === 0) {
                    $elements = $driver->findElements(WebDriverBy::xpath($selector));
                } else {
                    $elements = $driver->findElements(WebDriverBy::cssSelector($selector));
                }
                
                if (count($elements) > 0) {
                    echo "Found pay button with selector: {$selector}, attempting to click..." . PHP_EOL;
                    // Try JavaScript click first to avoid potential interception
                    try {
                        $driver->executeScript('arguments[0].click();', [$elements[0]]);
                    } catch (\Exception $jsClickException) {
                        // Fall back to regular click if JavaScript click fails
                        $elements[0]->click();
                    }
                    $payButtonClicked = true;
                    self::wait(1);
                    echo "Pay button clicked successfully" . PHP_EOL;
                    break;
                } else {
                    echo "No pay button found with selector: {$selector}" . PHP_EOL;
                }
            } catch (\Exception $e) {
                echo "Error clicking pay button {$selector}: {$e->getMessage()}" . PHP_EOL;
            }
        }
        
        self::wait(3); // Short wait to let UI update
    }
    
    /**
     * Wait for specified number of seconds
     *
     * @param float $seconds Number of seconds to wait
     * @return void
     */
    public static function wait($seconds)
    {
        usleep((int)($seconds * 1000000));
    }
    
    /**
     * Check if Selenium WebDriver is available
     *
     * @return bool True if Selenium is available, false otherwise
     */
    public static function isSeleniumAvailable()
    {
        // Try to open a socket connection to Selenium server
        $fp = @fsockopen('localhost', 4444, $errno, $errstr, 1);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
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
                self::wait(2.0);
                
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
