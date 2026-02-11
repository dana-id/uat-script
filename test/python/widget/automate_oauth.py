import asyncio
import os
import time
from playwright.async_api import async_playwright
from urllib.parse import urlparse, parse_qs, unquote
import json

# Configuration
PIN = '181818'  # Replace with your test PIN
oauth_url = 'https://m.sandbox.dana.id/n/ipg/oauth?partnerId=2025021714245533502768&scopes=CASHIER,AGREEMENT_PAY,QUERY_BALANCE,DEFAULT_BASIC_PROFILE,MINI_DANA&externalId=b3b7e164-a295-4461-8475-8db546f0d509&state=02c92610-aa7c-42b0-bf26-23bb06e4d475&channelId=2025021714245533502768&redirectUrl=https://google.com&timestamp=2025-05-20T22:27:01+00:00&seamlessData=%7B%22mobile%22%3A%22083811223355%22%7D&seamlessSign=IN8MCZZMge6C0SOQG4otmP9WE5yTll0%2F6OwgmUV0cfFi1e9Hj8PAWuD1ZanQ9MZcrKH1nwJEKnYTtqtQ3AdpLa24E%2B2W3%2BNJD8nh7FLicjPFQuDEIAdE%2ByEqTfpU5Z8%2B1tdB%2BW3HN4p6ko%2BiSXu28XHZOnxxXbfMZzQ0qwpwhTp76xSi2tH5eU7ksp37G9sjCB3eyFXBR8bWr7NCjDzFL5cxVlTuEZCmLieDLYh%2FiGClPfWj%2F7tnzzppyiPJsG7PjWkuM25%2B%2BwHBcb7DUA1yVllq30lxUpKeogZ3AuY%2Be9%2FeRHrhz6d%2BBFnzowI3Fk2ZA64BR9E8TSpNHyzWKCNc1A%3D%3D&isSnapBI=true'

def extract_auth_code_from_url(url):
    """Extract auth code from URL parameters"""
    try:
        parsed = urlparse(url)
        params = parse_qs(parsed.query)
        return params.get('auth_code', [None])[0] or params.get('authCode', [None])[0]
    except Exception:
        return None

def extract_mobile_from_url(url):
    """Extract mobile number from OAuth URL seamless data"""
    try:
        parsed = urlparse(url)
        params = parse_qs(parsed.query)
        seamless_data = params.get('seamlessData', [None])[0]
        if seamless_data:
            decoded = unquote(seamless_data)
            json_data = json.loads(decoded)
            return json_data.get('mobile', '083811223355')
    except Exception as e:
        print(f'Error extracting mobile number: {e}')
    return '083811223355'

async def automate_oauth_simple(phone_number=None, pin=None, max_retries=3, ci_mode=False):
    """
    Simple OAuth automation with retry logic.
    Opens full desktop Chrome and waits for auth code in URL.
    ci_mode: optimizes timeouts and browser args for CI environments
    """
    mobile_number = phone_number or extract_mobile_from_url(oauth_url)
    used_pin = pin or PIN
    # Adjust timeouts for CI
    if ci_mode:
        max_retries = 2  # Fewer retries in CI
        oauth_timeout = 20  # Shorter timeout in CI
        wait_timeout = 2000  # Shorter waits in CI
    else:
        oauth_timeout = 30
        wait_timeout = 3000
    
    for attempt in range(max_retries):
        print(f"\nOAuth Attempt {attempt + 1}/{max_retries}")
        print(f"Using mobile: {mobile_number}")
        print(f"Using PIN: {used_pin}")
        
        async with async_playwright() as p:
            # Launch full desktop Chrome with CI-optimized args
            browser_args = [
                '--disable-gpu',
                '--disable-web-security',
                '--disable-features=IsolateOrigins',
                '--disable-site-isolation-trials',
                '--disable-features=BlockInsecurePrivateNetworkRequests',
                '--disable-blink-features=AutomationControlled',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-extensions'
            ]
            
            if ci_mode:
                browser_args.extend([
                    '--disable-setuid-sandbox',
                    '--disable-background-timer-throttling',
                    '--disable-renderer-backgrounding',
                    '--disable-features=TranslateUI',
                    '--disable-ipc-flooding-protection',
                ])
            
            browser = await p.chromium.launch(
                headless=True,
                args=browser_args
            )
            
            context = await browser.new_context(
                viewport={'width': 1920, 'height': 1080},
                user_agent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
            )
            
            page = await context.new_page()
            
            try:
                # Navigate to OAuth URL
                print(f"Opening OAuth URL...")
                await page.goto(oauth_url, wait_until='domcontentloaded', timeout=30000)
                await page.wait_for_timeout(wait_timeout)
                
                # Step 1: Fill phone number
                print("Filling phone number...")
                await fill_phone_number(page, mobile_number)
                await page.wait_for_timeout(wait_timeout // 2)
                
                # Step 2: Click continue/submit
                print("Clicking continue...")
                await click_continue_button(page)
                await page.wait_for_timeout(wait_timeout)
                
                # Step 3: Fill PIN
                print("Filling PIN...")
                await fill_pin(page, used_pin)
                await page.wait_for_timeout(wait_timeout // 2)
                
                # Step 4: Click final submit
                print("Clicking final submit...")
                await click_continue_button(page)
                
                # Step 5: Wait for auth code with retry
                print("Waiting for auth code in URL...")
                auth_code = await wait_for_auth_code(page, timeout=oauth_timeout)
                
                if auth_code:
                    print(f"SUCCESS! Auth code: {auth_code}")
                    await browser.close()
                    return auth_code
                else:
                    print(f"Attempt {attempt + 1} failed - no auth code found")
                    
            except Exception as e:
                print(f"Attempt {attempt + 1} failed with error: {e}")
                
            finally:
                await browser.close()
                
        # Wait before retry
        if attempt < max_retries - 1:
            retry_wait = 2 if ci_mode else 3
            print(f"Waiting {retry_wait} seconds before retry...")
            await asyncio.sleep(retry_wait)
    
    print(f"All {max_retries} attempts failed!")
    return None

async def fill_phone_number(page, mobile_number):
    """Fill phone number using multiple strategies"""
    strategies = [
        "input[type='tel']",
        "input[placeholder*='phone']",
        "input[placeholder*='mobile']",
        "input[maxlength='13']",
        "input[class*='phone']",
        "input[class*='mobile']",
        "input:first-of-type"
    ]
    
    for strategy in strategies:
        try:
            element = await page.query_selector(strategy)
            if element and await element.is_visible():
                await element.fill(mobile_number)
                print(f"Phone filled using: {strategy}")
                return True
        except Exception:
            continue
    
    print("Could not find phone input field")
    return False

async def fill_pin(page, pin):
    """Fill PIN using multiple strategies"""
    strategies = [
        # Single PIN field
        "input[maxlength='6']",
        "input[type='password']",
        "input[class*='pin']",
        "input[name*='pin']",
        
        # Multiple single-digit fields
        "input[maxlength='1']"
    ]
    
    for strategy in strategies:
        try:
            elements = await page.query_selector_all(strategy)
            if elements:
                if len(elements) == 1:
                    # Single PIN field
                    element = elements[0]
                    if await element.is_visible():
                        await element.fill(pin)
                        print(f"PIN filled using single field: {strategy}")
                        return True
                elif len(elements) >= 6:
                    # Multiple single-digit fields
                    for i, digit in enumerate(pin[:len(elements)]):
                        if i < len(elements):
                            await elements[i].fill(digit)
                    print(f"PIN filled using multiple fields: {strategy}")
                    return True
        except Exception:
            continue
    
    print("Could not find PIN input field")
    return False

async def click_continue_button(page):
    """Click continue/submit button using multiple strategies"""
    strategies = [
        "button[type='submit']",
        "button:has-text('Continue')",
        "button:has-text('Next')",
        "button:has-text('Submit')",
        "button:has-text('Lanjutkan')",
        "button:has-text('Lanjut')",
        "button[class*='continue']",
        "button[class*='submit']",
        "button[class*='btn-primary']"
    ]
    
    for strategy in strategies:
        try:
            element = await page.query_selector(strategy)
            if element and await element.is_visible() and await element.is_enabled():
                await element.click()
                print(f"Button clicked using: {strategy}")
                return True
        except Exception:
            continue
    
    print("Could not find continue button")
    return False

async def wait_for_auth_code(page, timeout=30):
    """Wait for auth code to appear in URL with retry logic"""
    start_time = time.time()
    check_interval = 0.5  # Check every 500ms
    
    while time.time() - start_time < timeout:
        try:
            current_url = page.url
            auth_code = extract_auth_code_from_url(current_url)
            
            if auth_code:
                return auth_code
                
            # Check if we're on redirect URL (google.com)
            if 'google.com' in current_url:
                print(f"Checking redirect URL: {current_url}")
                auth_code = extract_auth_code_from_url(current_url)
                if auth_code:
                    return auth_code
                    
            await asyncio.sleep(check_interval)
            
        except Exception as e:
            print(f"Error checking URL: {e}")
            await asyncio.sleep(check_interval)
    
    print(f"Timeout after {timeout} seconds")
    return None

# Simple alias for backward compatibility
async def automate_oauth(phone_number=None, pin=None, show_log=True):
    """Backward compatible wrapper"""
    # Detect if running in CI by checking environment variables
    ci_mode = os.getenv('CI') is not None or os.getenv('GITLAB_CI') is not None
    return await automate_oauth_simple(phone_number, pin, max_retries=3, ci_mode=ci_mode)

if __name__ == '__main__':
    code = asyncio.run(automate_oauth_simple())
    print(f'Final result - Auth code: {code}')
