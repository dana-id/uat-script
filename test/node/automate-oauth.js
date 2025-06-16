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
  // Disable all logging
  const log = () => {};
  const errorLog = () => {};

  let foundAuthCode = null;
  let browser = null;
  let context = null;
  let page = null;

  // Use provided phoneNumber or extract from URL
  const mobileNumber = phoneNumber || extractMobileFromUrl(oauthUrl);
  const pinToUse = pinCode || pin;

  try {
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
      launchOptions.executablePath = process.env.CHROMIUM_PATH;
    } else if (process.env.CI) {
      launchOptions.executablePath = '/usr/bin/chromium-browser';
    }
    
    browser = await chromium.launch(launchOptions);
    context = await browser.newContext({
      ...devices['iPhone 12'],
      locale: 'id-ID',
      geolocation: { longitude: 106.8456, latitude: -6.2088 }, // Jakarta coordinates
      permissions: ['geolocation']
    });

    // Open a new page
    page = await context.newPage();

    // Enable debug logging
    // Skip console logging
    page.on('pageerror', err => errorLog(`Browser page error: ${err.message}`));

    // Track navigation events
    page.on('framenavigated', async frame => {
      if (frame === page.mainFrame()) {
        const url = frame.url();
        // Navigation detected
        // Check if we've reached the PIN input page
        if (url.includes('/ipgLogin')) {
          // Detected login page
          await page.waitForTimeout(800); // Reduced timeout for faster tests
        }
      }
    });

    // Navigate to the OAuth URL
    await page.goto(oauthUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });

    // Wait a bit for any scripts to initialize (increased wait time)
    await page.waitForTimeout(2000);

    // Try different selectors for the phone input field
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
    // Phone input result processed

    // Find and click the next/submit button
    // Looking for submit button

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

    await page.waitForTimeout(2000);

    const needToContinue = await page.evaluate(() => {
      const continueBtn = document.querySelector('button.btn-continue.fs-unmask.btn.btn-primary');
      if (continueBtn) {
        continueBtn.click();
        return { clicked: true, message: 'Found another continue button - this might be needed to proceed to PIN input' };
      }
      return { clicked: false, message: 'No additional continue button found' };
    });

    if (needToContinue.clicked) {
      await page.waitForTimeout(1500);
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

    // Helper function to find and click a confirm/submit button after PIN entry
    async function tryToFindAndClickConfirmButton() {
      try {
        // Finding confirm button
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

        // Button click result processed
        if (buttonClicked.clicked) {
          await page.waitForTimeout(500); // Reduced timeout for faster tests
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
      // Confirm button clicked
      await page.waitForTimeout(200); // Further reduced wait time after confirm click
    }

    // Optimize: Use a single navigation event and race with a shorter timeout
    // Set up a cleanup function to handle any timeouts or interruptions
    let navigationListener = null;
    let timeoutId = null;
    
    // Create a cleanup function to ensure all resources are released
    const cleanup = () => {
      if (navigationListener && page) {
        try {
          page.removeListener('framenavigated', navigationListener);
        } catch (e) { /* Ignore errors during cleanup */ }
      }
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
      
    // Make sure cleanup happens when the script exits
    process.once('beforeExit', cleanup);
    
    try {
      foundAuthCode = await Promise.race([
        new Promise((resolve) => {
          let resolved = false;
          navigationListener = (frame) => {
            if (resolved) return;
            if (frame === page.mainFrame()) {
              const url = frame.url();
              if (url.includes('google.com')) {
                try {
                  const urlObj = new URL(url);
                  const params = urlObj.searchParams;
                  if (params.has('auth_code')) {
                    resolved = true;
                    cleanup();
                    resolve(params.get('auth_code'));
                  }
                } catch (e) { /* ignore */ }
              }
            }
          };
          if (page) {
            page.on('framenavigated', navigationListener);
          }
        }),
        new Promise(resolve => {
          timeoutId = setTimeout(() => {
            cleanup();
            resolve(null);
          }, 4000);
          if (timeoutId.unref) {
            timeoutId.unref();
          }
        })
      ]);
    } finally {
      cleanup();
      process.removeListener('beforeExit', cleanup);
    }
    await page.waitForTimeout(300);

  } catch (error) {
    errorLog('Error during automation:', error);
  } finally {
    try {
      if (page) {
        page.removeAllListeners();
      }
      
      if (context) {
        await context.close().catch(e => errorLog('Error closing context:', e));
      }
      
      if (browser) {
        await browser.close().catch(e => errorLog('Error closing browser:', e));
      }
    } catch (cleanupError) {
      errorLog('Error during cleanup:', cleanupError);
    }
  }

  return foundAuthCode;
}

module.exports = {
  automateOAuth,
  extractMobileFromUrl
};
