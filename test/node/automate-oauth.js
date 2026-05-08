const { chromium, devices } = require('playwright');
const fs = require('fs');

const defaultPin = '181818';
const defaultPhoneNumber = '083811223355';

function extractMobileFromUrl(url) {
  try {
    const urlObj = new URL(url);
    const seamlessData = urlObj.searchParams.get('seamlessData');
    if (seamlessData) {
      const jsonData = JSON.parse(decodeURIComponent(seamlessData));
      if (jsonData && jsonData.mobile) {
        return jsonData.mobile;
      }
    }
  } catch (error) {
    console.error('Error extracting mobile number from URL:', error);
  }
  return defaultPhoneNumber;
}

function normalizeMobileNumber(mobile) {
  const digits = String(mobile).replace(/\D/g, '');
  if (!digits) return defaultPhoneNumber;
  if (digits.startsWith('62')) {
    return '0' + digits.slice(2);
  }
  return digits;
}

/**
 * Automates the OAuth flow (aligned with dana-node-api-client automate_oauth.ts).
 * @param {string} oauthUrl
 * @param {{ phoneNumber?: string; pinCode?: string; outputFile?: string }} [options]
 * @returns {Promise<string|null>}
 */
async function automateOAuth(oauthUrl, options = {}) {
  const rawPhone = options.phoneNumber || extractMobileFromUrl(oauthUrl);
  const phoneNumber = normalizeMobileNumber(rawPhone);
  const pinCode = options.pinCode || defaultPin;
  const outputFile = options.outputFile;

  console.log(`Starting OAuth automation with phone: ${phoneNumber}`);

  let browser = null;
  let context = null;

  try {
    const launchArgs = [
      '--disable-web-security',
      '--disable-features=IsolateOrigins',
      '--disable-site-isolation-trials',
      '--disable-blink-features=AutomationControlled',
      '--no-sandbox',
      '--disable-dev-shm-usage',
    ];

    const launchOptions = { headless: true, args: launchArgs };
    if (process.env.CHROMIUM_PATH) {
      launchOptions.executablePath = process.env.CHROMIUM_PATH;
    } else if (process.env.CI) {
      launchOptions.executablePath = '/usr/bin/chromium-browser';
    }

    browser = await chromium.launch(launchOptions);

    const device = devices['iPhone 13'];
    context = await browser.newContext({
      ...device,
      locale: 'id-ID',
      geolocation: { longitude: 106.8456, latitude: -6.2088 },
      permissions: ['geolocation'],
    });
    context.setDefaultTimeout(60000);

    const page = await context.newPage();

    page.on('framenavigated', async (frame) => {
      if (frame === page.mainFrame() && frame.url().startsWith('chrome-error://')) {
        console.log('Detected chrome-error (link.dana.id deep-link failure) — going back to restore DANA page');
        await new Promise((r) => setTimeout(r, 300));
        try {
          await page.goBack({ waitUntil: 'domcontentloaded', timeout: 8000 });
          console.log('Successfully restored page after chrome-error');
        } catch (e) {
          console.error('GoBack from chrome-error failed:', e);
        }
      }
    });

    await page.goto(oauthUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });

    await page.waitForTimeout(2000);

    const pinSelector =
      '.txt-input-pin-field, input[maxlength="6"][inputmode="numeric"], input[type="password"]';

    const isPinVisible = await page.locator(pinSelector).first().isVisible();

    if (!isPinVisible) {
      const phoneSelectors = [
        'input.txt-input-phone-number-field',
        "input[type='tel']",
        "input[placeholder='12312345678']",
        "input[maxlength='13']",
        "input[maxlength='15']",
      ];

      let phoneFilled = false;
      for (const sel of phoneSelectors) {
        const loc = page.locator(sel).first();
        if (await loc.isVisible()) {
          const currentVal = await loc.inputValue();
          if (currentVal !== '') {
            console.log(`Phone field already pre-filled by DANA: ${currentVal} (skipping fill)`);
            phoneFilled = true;
          } else {
            try {
              await loc.fill(phoneNumber);
              console.log(`Phone filled using selector: ${sel} with value: ${phoneNumber}`);
              phoneFilled = true;
            } catch (e) {
              /* try next */
            }
          }
          break;
        }
      }

      if (!phoneFilled) {
        const label = page.locator('label.new-clearable-input.form-ipg-phonenumber').first();
        if (await label.isVisible()) {
          try {
            await label.click();
            await page.keyboard.type(phoneNumber);
            console.log('Phone filled via label click + keyboard type');
            phoneFilled = true;
          } catch (e) {
            /* ignore */
          }
        }
      }

      if (!phoneFilled) {
        console.warn('Warning: could not determine phone field state');
      }

      await page.waitForTimeout(1000);

      let submitted = false;
      for (const text of ['LANJUTKAN', 'Lanjutkan', 'Next', 'Continue']) {
        const loc = page.getByRole('button', { name: text, exact: true }).first();
        if (await loc.isVisible()) {
          try {
            await loc.click();
            console.log(`Submit clicked via role+name: ${text}`);
            submitted = true;
            break;
          } catch (e) {
            /* try next */
          }
        }
      }

      if (!submitted) {
        for (const sel of [
          "button[type='submit']",
          'button.btn-primary',
          'button.next-button',
          '.btn-continue',
          '.btn-submit',
        ]) {
          const loc = page.locator(sel).first();
          if (await loc.isVisible()) {
            try {
              await loc.click();
              console.log(`Submit clicked via selector: ${sel}`);
              submitted = true;
              break;
            } catch (e) {
              /* try next */
            }
          }
        }
      }

      if (!submitted) {
        console.warn('Warning: could not click LANJUTKAN button');
      }

      await page.waitForTimeout(3000);

      const continueLoc = page.locator('button.btn-continue.fs-unmask.btn.btn-primary').first();
      if (await continueLoc.isVisible()) {
        await continueLoc.click();
        await page.waitForTimeout(1000);
      }

      try {
        await page.waitForSelector(pinSelector, { state: 'attached', timeout: 15000 });
      } catch (e) {
        console.warn('Timeout waiting for PIN field after phone submit');
      }
    }

    let pinFilled = false;
    const pinLoc = page.locator(pinSelector).first();
    if (await pinLoc.isVisible()) {
      try {
        await pinLoc.click();
        await page.keyboard.type(pinCode);
        console.log('PIN entered via keyboard type');
        pinFilled = true;
      } catch (e) {
        /* fall through */
      }
    }

    if (!pinFilled) {
      try {
        const el = await page.waitForSelector(pinSelector, { state: 'visible', timeout: 10000 });
        await el.click();
        await page.keyboard.type(pinCode);
        console.log('PIN entered via waitForSelector + keyboard type');
        pinFilled = true;
      } catch (e) {
        /* ignore */
      }
    }

    if (!pinFilled) {
      throw new Error('could not enter PIN: PIN field not visible');
    }

    let authCode = null;
    await new Promise((resolve) => {
      const timer = setTimeout(resolve, 30000);

      page.on('framenavigated', (frame) => {
        if (frame !== page.mainFrame()) return;
        try {
          const u = new URL(frame.url());
          if (u.host === 'google.com' || u.searchParams.has('authCode')) {
            const code = u.searchParams.get('authCode');
            if (code) {
              authCode = code;
              clearTimeout(timer);
              resolve();
            }
          }
        } catch (e) {
          /* ignore */
        }
      });
    });

    if (!authCode) {
      try {
        const u = new URL(page.url());
        authCode = u.searchParams.get('authCode');
      } catch (e) {
        /* ignore */
      }
    }

    if (!authCode) {
      throw new Error('could not capture authorization code');
    }

    console.log(`OAuth flow completed successfully, auth code: ${authCode}`);

    if (outputFile && authCode) {
      fs.writeFileSync(outputFile, authCode);
    }

    return authCode;
  } catch (error) {
    console.error('Error during automation:', error);
    return null;
  } finally {
    try {
      if (context) await context.close();
      if (browser) await browser.close();
    } catch (e) {
      console.error('Error during cleanup:', e);
    }
  }
}

if (require.main === module) {
  (async () => {
    const args = process.argv.slice(2);
    if (args.length < 1) {
      console.error('Usage: node automate-oauth.js <oauth_url> [--output=output_file_path]');
      process.exit(1);
    }

    const oauthUrl = args[0];
    const outputArg = args.find((arg) => arg.startsWith('--output='));
    const outputFile = outputArg ? outputArg.split('=')[1] : undefined;

    try {
      const authCode = await automateOAuth(oauthUrl, { outputFile });
      console.log('Auth code obtained:', authCode);
      process.exit(authCode ? 0 : 1);
    } catch (error) {
      console.error('OAuth automation failed:', error);
      process.exit(1);
    }
  })();
}

module.exports = {
  automateOAuth,
  extractMobileFromUrl,
};
