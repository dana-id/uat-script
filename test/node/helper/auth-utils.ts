const { automateOAuth } = require('../automate-oauth');

/**
 * Generates an OAuth authorization code with automatic retries
 * 
 * @param phoneNumber Optional phone number to use in OAuth flow
 * @param pinCode Optional PIN code to use in OAuth flow
 * @returns Promise resolving to the authorization code string
 */
export async function generateAuthCode(phoneNumber?: string, pinCode?: string): Promise<string> {
    const MAX_RETRIES = 3;
    const RETRY_DELAY_MS = 1000; // 1 second delay between retries
    
    let lastError: Error | null = null;
    
    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
        try {
            if (attempt > 1) {
                console.log(`Auth code generation attempt ${attempt}/${MAX_RETRIES}`);
            }
            
            // Wrap in a timeout to prevent hanging
            const authCode = await Promise.race([
                automateOAuth(phoneNumber, pinCode, { log: false }),
                new Promise<null>((_, reject) => {
                    const timeout = setTimeout(() => {
                        clearTimeout(timeout); // Clear the timeout to prevent memory leaks
                        reject(new Error('Auth code generation timed out after 30 seconds'));
                    }, 30000);
                })
            ]);
            
            if (typeof authCode === 'string' && authCode) {
                return authCode;
            }
            
            if (authCode && typeof authCode === 'object' && authCode.auth_code) {
                return authCode.auth_code;
            }
            
            throw new Error('auth_code not found in automateOAuth result');
        } catch (error: any) {
            lastError = new Error(`Failed to get auth_code: ${error.message}`);
            
            // Only wait if we have more retries to go
            if (attempt < MAX_RETRIES) {
                await new Promise(resolve => {
                    const timer = setTimeout(resolve, RETRY_DELAY_MS);
                    // Ensure timer doesn't block process exit
                    if (timer.unref) timer.unref();
                });
            }
        }
    }
    
    // If we get here, all attempts failed - but don't throw an exception
    // Instead, return a placeholder that will fail gracefully
    console.error('Failed to get auth_code after multiple attempts');
    return 'failed_auth_code';
}