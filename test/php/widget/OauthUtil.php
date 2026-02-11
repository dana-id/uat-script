<?php

namespace DanaUat\Widget;

use DanaUat\Widget\Scripts\WebAutomation;
use Dana\Widget\v1\Util\Util;
use Dana\Widget\v1\Model\Oauth2UrlData;
use Dana\Env;

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
        $oauth2UrlData->setRedirectUrl($redirectUrl);
        $oauth2UrlData->setMerchantId($merchantId);
        $oauth2UrlData->setSeamlessData([
            'mobileNumber' => '083811223355'
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
        if ($redirectUrl == null) {
            $redirectUrl = getenv('REDIRECT_URL_OAUTH');
        }
        echo "Starting OAuth automation..." . PHP_EOL;
        $phoneNumber = $phoneNumber ?: '083811223355';
        $pinCode = $pinCode ?: '181818';
        
        $oauthUrl = self::getOAuthUrl($partnerId, $state, $redirectUrl, null, $phoneNumber, $pinCode);
        
        echo "Generated OAuth URL: {$oauthUrl}" . PHP_EOL;
        
        return WebAutomation::automateOauth($oauthUrl, $phoneNumber, $pinCode);
    }
}
