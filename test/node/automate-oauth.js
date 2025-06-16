const { chromium, devices } = require('playwright');

// Configuration
const pin = '123321'; // Replace with your test PIN
const oauthUrl = 'https://m.sandbox.dana.id/n/ipg/oauth?partnerId=2025021714245533502768&scopes=CASHIER,AGREEMENT_PAY,QUERY_BALANCE,DEFAULT_BASIC_PROFILE,MINI_DANA&externalId=b3b7e164-a295-4461-8475-8db546f0d509&state=02c92610-aa7c-42b0-bf26-23bb06e4d475&channelId=2025021714245533502768&redirectUrl=https://google.com&timestamp=2025-05-20T22:27:01+00:00&seamlessData=%7B%22mobile%22%3A%220811742234%22%7D&seamlessSign=IN8MCZZMge6C0SOQG4otmP9WE5yTll0%2F6OwgmUV0cfFi1e9Hj8PAWuD1ZanQ9MZcrKH1nwJEKnYTtqtQ3AdpLa24E%2B2W3%2BNJD8nh7FLicjPFQuDEIAdE%2ByEqTfpU5Z8%2B1tdB%2BW3HN4p6ko%2BiSXu28XHZOnxxXbfMZzQ0qwpwhTp76xSi2tH5eU7ksp37G9sjCB3eyFXBR8bWr7NCjDzFL5cxVlTuEZCmLieDLYh%2FiGClPfWj%2F7tnzzppyiPJsG7PjWkuM25%2B%2BwHBcb7DUA1yVllq30lxUpKeogZ3AuY%2Be9%2FeRHrhz6d%2BBFnzowI3Fk2ZA64BR9E8TSpNHyzWKCNc1A%3D%3D&isSnapBI=true';

// Extract mobile number from the URL
function extractMobileFromUrl(url) {
  try {
    const urlObj = new URL(url);
    const seamlessData = urlObj.searchParams.get('seamlessData');

    if (seamlessData) {
      const decodedData = decodeURIComponent(seamlessData);
      const jsonData = JSON.parse(decodedData);

      if (jsonData && jsonData.mobile) {
        return jsonData.mobile;
      }
    }
  } catch (error) {
    console.error('Error extracting mobile number from URL:', error);
  }

  // Fallback to default number if extraction fails
  return '0811742234';
}

async function automateOAuth(phoneNumber, pinCode, options = {}) {
  const logEnabled = options.log === false; // Default to off, only log if explicitly true
  const log = (...args) => { if (logEnabled) console.log(...args); };
  const errorLog = (...args) => { if (logEnabled) console.error(...args); };

  log('Starting OAuth automation...');
  let foundAuthCode = null;

  // Use provided phoneNumber or extract from URL
  const mobileNumber = phoneNumber || extractMobileFromUrl(oauthUrl);
  const pinToUse = pinCode || pin;
  log(`Extracted mobile number from parameter or URL: ${mobileNumber}`);

  // Launch the browser with flexible executable path options
  const launchOptions = {
    headless: true, // Set to true for production
    args: [
      '--disable-web-security',
      '--disable-features=IsolateOrigins',
      '--disable-site-isolation-trials',
      '--disable-features=BlockInsecurePrivateNetworkRequests',
      '--disable-blink-features=AutomationControlled',
      '--user-agent="Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1"'
    ]
  };
  
  // Try different executable paths based on environment
  if (process.env.CHROMIUM_PATH) {
    // Use explicit path from environment variable if set
    launchOptions.executablePath = process.env.CHROMIUM_PATH;
    log(`Using custom Chrome path: ${process.env.CHROMIUM_PATH}`);
  } else if (process.env.CI) {
    // Try common Alpine Linux Chromium path
    launchOptions.executablePath = '/usr/bin/chromium-browser';
    log('Using Alpine Linux default Chromium path');
  }
  
  const browser = await chromium.launch(launchOptions);

  try {
    // Use a preset device profile instead of manual settings
    const context = await browser.newContext({
      ...devices['iPhone 12'],
      locale: 'id-ID',
      geolocation: { longitude: 106.8456, latitude: -6.2088 }, // Jakarta coordinates
      permissions: ['geolocation']
    });

    // Open a new page
    const page = await context.newPage();

    // Enable debug logging
    page.on('console', msg => log(`Browser console: ${msg.text()}`));
    page.on('pageerror', err => errorLog(`Browser page error: ${err.message}`));

    // Track navigation events
    page.on('framenavigated', async frame => {
      if (frame === page.mainFrame()) {
        const url = frame.url();
        log('Navigation detected to:', url);
        // Check if we've reached the PIN input page
        if (url.includes('/ipgLogin')) {
          log('Detected navigation to login page - waiting for PIN input to appear');
          await page.waitForTimeout(1500);
        }
      }
    });

    // Navigate to the OAuth URL
    log('Opening OAuth URL...');
    await page.goto(oauthUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    log('Page loaded, waiting for scripts to initialize...');

    // Wait a bit for any scripts to initialize (increased wait time)
    await page.waitForTimeout(4000);
    log('Done waiting for initialization');

    // Try different selectors for the phone input field
    log('Trying to find the phone input field...');

    const phoneInputFilled = await page.evaluate((mobile) => {
      const inputs = document.querySelectorAll('input');
      for (const input of inputs) {
        if (input.type === 'tel' ||
          input.placeholder === '12312345678' ||
          input.maxLength === 13 ||
          input.className.includes('phone-number')) {
          input.value = mobile;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          // Logging moved out of browser context
          return { filled: true, message: 'Found and filled mobile number input' };
        }
      }
      return { filled: false, message: 'No suitable mobile number input found' };
    }, mobileNumber);
    log(phoneInputFilled.message);

    // Find and click the next/submit button
    log('Looking for the submit button...');

    const submitButtonClicked = await page.evaluate(() => {
      const buttons = document.querySelectorAll('button');
      for (const button of buttons) {
        if (button.type === 'submit' ||
          button.innerText.includes('Next') ||
          button.innerText.includes('Continue') ||
          button.innerText.includes('Submit') ||
          button.innerText.includes('Lanjutkan')) {
          button.click();
          // Logging moved out of browser context
          return { clicked: true, message: 'Found and clicked button via JS evaluation' };
        }
      }
      return { clicked: false, message: 'No suitable submit button found' };
    });
    log(submitButtonClicked.message);

    // Wait for PIN input screen with longer timeout
    log('Waiting for PIN input screen...');
    await page.waitForTimeout(4000); // Increased wait time for page transition

    // Directly check if we need to click the "Continue" button again
    const needToContinue = await page.evaluate(() => {
      // Look for continue button
      const continueBtn = document.querySelector('button.btn-continue.fs-unmask.btn.btn-primary');
      if (continueBtn) {
        continueBtn.click();
        // Logging moved out of browser context
        return { clicked: true, message: 'Found another continue button - this might be needed to proceed to PIN input' };
      }
      return { clicked: false, message: 'No additional continue button found' };
    });

    if (needToContinue.clicked) {
      log(needToContinue.message);
      log('Clicked another continue button - waiting for PIN input to appear');
      await page.waitForTimeout(1500);
    } else {
      log(needToContinue.message);
    }

    const pinInputResult = await page.evaluate((pinCode) => {
      const specificPinInput = document.querySelector('.txt-input-pin-field');
      if (specificPinInput) {
        specificPinInput.value = pinCode;
        specificPinInput.dispatchEvent(new Event('input', { bubbles: true }));
        specificPinInput.dispatchEvent(new Event('change', { bubbles: true }));
        return { success: true, method: 'specific', message: 'Found specific PIN input field: .txt-input-pin-field' };
      }

      const inputs = document.querySelectorAll('input');

      // Approach 1: Look for an input with maxlength=6 (full PIN)
      const singlePinInput = Array.from(inputs).find(input =>
        input.maxLength === 6 &&
        (input.type === 'text' || input.type === 'tel' || input.type === 'number' || input.inputMode === 'numeric')
      );

      if (singlePinInput) {
        singlePinInput.value = pinCode;
        singlePinInput.dispatchEvent(new Event('input', { bubbles: true }));
        singlePinInput.dispatchEvent(new Event('change', { bubbles: true }));
        return { success: true, method: 'single', message: 'Found single PIN input field with maxLength=6' };
      }

      // Approach 2: Look for multiple inputs with common PIN input characteristics
      const pinInputs = Array.from(inputs).filter(input =>
        input.maxLength === 1 ||
        input.type === 'password' ||
        input.className.includes('pin')
      );

      if (pinInputs.length >= pinCode.length) {
        for (let i = 0; i < pinCode.length; i++) {
          pinInputs[i].value = pinCode.charAt(i);
          pinInputs[i].dispatchEvent(new Event('input', { bubbles: true }));
          pinInputs[i].dispatchEvent(new Event('change', { bubbles: true }));
        }
        return { success: true, method: 'multi', message: `Found ${pinInputs.length} PIN inputs via JS` };
      }

      return { success: false, method: 'none', message: 'Could not find any suitable PIN input field' };
    }, pinToUse);

    log(pinInputResult.message);
    if (pinInputResult.success) {
      log('Successfully entered PIN via JavaScript');
    } else {
      log('Failed to enter PIN, no suitable input field found');
    }

    // Helper function to find and click a confirm/submit button after PIN entry
    async function tryToFindAndClickConfirmButton() {
      try {
        log('Trying JavaScript to find confirm button...');
        const buttonClicked = await page.evaluate(() => {
          const allButtons = document.querySelectorAll('button');
          let continueButton = null;
          let backButton = null;

          allButtons.forEach((button) => {
            const buttonText = button.innerText.trim().toLowerCase();
            // Identify buttons by text or class
            if (buttonText.includes('lanjut') ||
              buttonText.includes('continue') ||
              buttonText.includes('submit') ||
              buttonText.includes('confirm') ||
              buttonText.includes('next') ||
              button.className.includes('btn-continue') ||
              button.className.includes('btn-submit') ||
              button.className.includes('btn-confirm')) {
              continueButton = button;
            }

            // Avoid buttons with text 'kembali'
            if (buttonText.includes('kembali') ||
              buttonText.includes('back') ||
              button.className.includes('btn-back')) {
              backButton = button;
            }
          });

          // Prioritize continue button if found
          if (continueButton) {
            continueButton.click();
            return { clicked: true, message: 'Found continue button, clicked it: ' + continueButton.innerText };
          }

          return { clicked: false, message: 'No confirm/continue button found' };
        });

        log(buttonClicked.message);
        if (buttonClicked.clicked) {
          await page.waitForTimeout(1000); // Wait after clicking
          return true;
        }
      } catch (e) {
        errorLog('Error with PIN confirm button:', e);
      }

      return false;
    }

    // Try to click a confirm button if one exists
    const buttonClicked = await tryToFindAndClickConfirmButton();

    if (buttonClicked) {
      log('Confirm button was clicked, waiting for action to complete...');
      await page.waitForTimeout(200); // Further reduced wait time after confirm click
    }

    // Optimize: Use a single navigation event and race with a shorter timeout
    foundAuthCode = await Promise.race([
      new Promise((resolve) => {
        let resolved = false;
        const handleNavigation = (frame) => {
          if (resolved) return;
          if (frame === page.mainFrame()) {
            const url = frame.url();
            if (url.includes('google.com')) {
              try {
                const urlObj = new URL(url);
                const params = urlObj.searchParams;
                if (params.has('auth_code')) {
                  resolved = true;
                  resolve(params.get('auth_code'));
                }
              } catch (e) { /* ignore */ }
            }
          }
        };
        page.on('framenavigated', handleNavigation);
      }),
      new Promise(resolve => setTimeout(() => resolve(null), 5000)) // Shorter timeout for failover
    ]);
    // Minimal wait to ensure redirect is processed
    await page.waitForTimeout(300);

  } catch (error) {
    errorLog('Error during automation:', error);
  } finally {
    await browser.close();
    log('Browser closed');
  }

  return foundAuthCode;
}

// Run the automation
// automateOAuth(undefined, undefined, { log: true }).then(authCode => {
//   if (authCode) {
//     console.log(`\n✅ SUCCESS! Auth code found: ${authCode}`);
//     console.log('To use this auth code with your applyToken API:');
//     console.log(`const authCode = '${authCode}';`);
//     console.log('Example API call:');
//     console.log(`client.applyToken({\n  grantType: 'authorization_code',\n  authCode: '${authCode}'\n});\n`);
//   } else {
//     console.log('No auth_code returned.');
//   }
// }).catch(console.error);

module.exports = {
  automateOAuth,
  extractMobileFromUrl
};
