<?php

namespace DanaUat\Widget;

use DanaUat\Widget\Scripts\WebAutomation;
use Dana\Widget\v1\Util\Util;
use Dana\Widget\v1\Model\Oauth2UrlData;

class OauthUtil
{
    
    /**
     * Get OAuth URL for DANA authorization with comprehensive scopes and parameters
     * 
     * @param string $partnerId Partner ID for OAuth
     * @param string|null $state Optional state parameter for the OAuth flow
     * @param string|null $redirectUrl Optional redirect URL, defaults to Google
     * @param array|null $scopes Optional array of scopes
     * @param string|null $phoneNumber Optional phone number for seamless flow
     * @return string The complete OAuth URL
     */
    public static function getOAuthUrl($partnerId, $state = null, $redirectUrl = null, $scopes = null, $phoneNumber = null, $pinCode = null)
    {
        $merchantId = getenv('MERCHANT_ID');
        
        $oauth2UrlData = new Oauth2UrlData();
        $oauth2UrlData->setRedirectUrl('https://google.com');
        $oauth2UrlData->setMerchantId($merchantId);
        $oauth2UrlData->setSeamlessData([
            'mobileNumber' => '0811742234'
        ]);
        
        $privateKey = getenv('PRIVATE_KEY');
        
        return Util::generateOauthUrl($oauth2UrlData, $privateKey, null);
    }
    
    /**
     * Get authorization code by automating the OAuth flow
     * 
     * @param string $partnerId Partner ID for OAuth
     * @param string $state Optional state parameter for the OAuth flow
     * @param string $phoneNumber Phone number to use for authentication
     * @param string $pinCode PIN code to use for authentication
     * @param string $redirectUrl Optional redirect URL, defaults to Google
     * @return string|null Authorization code or null if not obtained
     */
    public static function getAuthCode($partnerId, $state = null, $phoneNumber = null, $pinCode = null, $redirectUrl = null)
    {
        $phoneNumber = $phoneNumber ?: '0811742234';
        $pinCode = $pinCode ?: '123321';
        
        $oauthUrl = self::getOAuthUrl($partnerId, $state, $redirectUrl, null, $phoneNumber, $pinCode);
        
        echo "Generated OAuth URL: {$oauthUrl}" . PHP_EOL;
        
        return WebAutomation::automateOauth($oauthUrl, $phoneNumber, $pinCode);
    }
}
