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

async function automateOAuth(phoneNumber, pinCode) {
  console.log('Starting OAuth automation...');
  let foundAuthCode = null;

  // Use provided phoneNumber or extract from URL
  const mobileNumber = phoneNumber || extractMobileFromUrl(oauthUrl);
  const pinToUse = pinCode || pin;
  console.log(`Extracted mobile number from parameter or URL: ${mobileNumber}`);

  // Launch the browser (headful mode so you can see what's happening)
  const browser = await chromium.launch({
    headless: true, // Set to true for production
    slowMo: 300, // Slow down operations for better visibility during testing
    args: [
      '--disable-web-security',
      '--disable-features=IsolateOrigins',
      '--disable-site-isolation-trials',
      '--disable-features=BlockInsecurePrivateNetworkRequests',
      '--disable-blink-features=AutomationControlled',
      '--user-agent="Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1"'
    ]
  });

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
    page.on('console', msg => console.log(`Browser console: ${msg.text()}`));
    page.on('pageerror', err => console.error(`Browser page error: ${err.message}`));

    // Track navigation events
    page.on('framenavigated', async frame => {
      if (frame === page.mainFrame()) {
        const url = frame.url();
        console.log('Navigation detected to:', url);
        // Check if we've reached the PIN input page
        if (url.includes('/ipgLogin')) {
          console.log('Detected navigation to login page - waiting for PIN input to appear');
          await page.waitForTimeout(1500);
        }
      }
    });

    // Navigate to the OAuth URL
    console.log('Opening OAuth URL...');
    await page.goto(oauthUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    console.log('Page loaded, waiting for scripts to initialize...');

    // Wait a bit for any scripts to initialize (increased wait time)
    await page.waitForTimeout(4000);
    console.log('Done waiting for initialization');

    // Try different selectors for the phone input field
    console.log('Trying to find the phone input field...');

    await page.evaluate((mobile) => {
      const inputs = document.querySelectorAll('input');
      for (const input of inputs) {
        if (input.type === 'tel' ||
          input.placeholder === '12312345678' ||
          input.maxLength === 13 ||
          input.className.includes('phone-number')) {
          input.value = mobile;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          console.log('Found and filled mobile number input');
          return true;
        }
      }
      return false;
    }, mobileNumber);

    // Find and click the next/submit button
    console.log('Looking for the submit button...');

    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button');
      for (const button of buttons) {
        if (button.type === 'submit' ||
          button.innerText.includes('Next') ||
          button.innerText.includes('Continue') ||
          button.innerText.includes('Submit') ||
          button.innerText.includes('Lanjutkan')) {
          button.click();
          console.log('Found and clicked button via JS evaluation');
          return true;
        }
      }
      return false;
    });


    // Wait for PIN input screen with longer timeout
    console.log('Waiting for PIN input screen...');
    await page.waitForTimeout(4000); // Increased wait time for page transition

    // Directly check if we need to click the "Continue" button again
    const needToContinue = await page.evaluate(() => {
      // Look for continue button
      const continueBtn = document.querySelector('button.btn-continue.fs-unmask.btn.btn-primary');
      if (continueBtn) {
        console.log('Found another continue button - this might be needed to proceed to PIN input');
        continueBtn.click();
        return true;
      }
      return false;
    });

    if (needToContinue) {
      console.log('Clicked another continue button - waiting for PIN input to appear');
      await page.waitForTimeout(1500);
    }

    const success = await page.evaluate((pinCode) => {
      const specificPinInput = document.querySelector('.txt-input-pin-field');
      if (specificPinInput) {
        console.log('Found specific PIN input field: .txt-input-pin-field');
        specificPinInput.value = pinCode;
        specificPinInput.dispatchEvent(new Event('input', { bubbles: true }));
        specificPinInput.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
      }

      const inputs = document.querySelectorAll('input');

      // Approach 1: Look for an input with maxlength=6 (full PIN)
      const singlePinInput = Array.from(inputs).find(input =>
        input.maxLength === 6 &&
        (input.type === 'text' || input.type === 'tel' || input.type === 'number' || input.inputMode === 'numeric')
      );

      if (singlePinInput) {
        console.log('Found single PIN input field with maxLength=6');
        singlePinInput.value = pinCode;
        singlePinInput.dispatchEvent(new Event('input', { bubbles: true }));
        singlePinInput.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
      }

      // Approach 2: Look for multiple inputs with common PIN input characteristics
      const pinInputs = Array.from(inputs).filter(input =>
        input.maxLength === 1 ||
        input.type === 'password' ||
        input.className.includes('pin')
      );

      if (pinInputs.length >= pinCode.length) {
        console.log(`Found ${pinInputs.length} PIN inputs via JS`);
        for (let i = 0; i < pinCode.length; i++) {
          pinInputs[i].value = pinCode.charAt(i);
          pinInputs[i].dispatchEvent(new Event('input', { bubbles: true }));
          pinInputs[i].dispatchEvent(new Event('change', { bubbles: true }));
        }
        return true;
      }

      console.log('Could not find any suitable PIN input field');
      return false;
    }, pinToUse);

    if (success) {
      console.log('Successfully entered PIN via JavaScript');
    } else {
      console.log('Failed to enter PIN, no suitable input field found');
    }

    // Helper function to find and click a confirm/submit button after PIN entry
    async function tryToFindAndClickConfirmButton() {
      // Try JavaScript approach if no button found
      try {
        console.log('Trying JavaScript to find confirm button...');
        const buttonClicked = await page.evaluate(() => {
          const allButtons = document.querySelectorAll('button');
          console.log(`Found ${allButtons.length} buttons on page`);

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
            console.log('Found continue button, clicking it: ' + continueButton.innerText);
            continueButton.click();
            return true;
          }

          return false;
        });

        if (buttonClicked) {
          console.log('Found and clicked confirm button via JS evaluation');
          await page.waitForTimeout(1000); // Wait after clicking
          return true;
        }
      } catch (e) {
        console.error('Error with PIN confirm button:', e);
      }

      return false;
    }

    // Try to click a confirm button if one exists
    const buttonClicked = await tryToFindAndClickConfirmButton();

    if (buttonClicked) {
      console.log('Confirm button was clicked, waiting for action to complete...');
      await page.waitForTimeout(1000); // Give time for the button click to take effect
    }

    // Watching for redirects and URL changes...
    const authCodePromise = new Promise((resolve) => {
      let resolved = false;
      const handleNavigation = async (frame) => {
        if (resolved) return;
        if (frame === page.mainFrame()) {
          const url = frame.url();
          if (url.includes('google.com')) {
            try {
              const urlObj = new URL(url);
              const params = urlObj.searchParams;
              if (params.has('auth_code')) {
                const authCode = params.get('auth_code');
                resolved = true;
                resolve(authCode);
                return;
              }
            } catch (e) {
              // ignore
            }
          }
        }
      };
      page.on('framenavigated', handleNavigation);
    });
    // timeout promise
    const timeoutPromise = new Promise((resolve) => {
      setTimeout(() => {
        resolve(null);
      }, 30000);
    });
    // Wait for either auth code or timeout
    foundAuthCode = await Promise.race([authCodePromise, timeoutPromise]);
    // Allow some time to see the final state
    await new Promise(resolve => setTimeout(resolve, 5000));

  } catch (error) {
    console.error('Error during automation:', error);
  } finally {
    // Close the browser
    await browser.close();
    console.log('Browser closed');
  }

  return foundAuthCode;
}

// Run the automation
automateOAuth().then(authCode => {
  if (authCode) {
    console.log(`\n✅ SUCCESS! Auth code found: ${authCode}`);
    console.log('To use this auth code with your applyToken API:');
    console.log(`const authCode = '${authCode}';`);
    console.log('Example API call:');
    console.log(`client.applyToken({\n  grantType: 'authorization_code',\n  authCode: '${authCode}'\n});\n`);
  } else {
    console.log('No auth_code returned.');
  }
}).catch(console.error);
