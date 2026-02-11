<?php

namespace DanaUat\Widget;

use PHPUnit\Framework\TestCase;
use Dana\Configuration;
use Dana\Env;
use Dana\Widget\v1\Api\WidgetApi;
use DanaUat\Helper\Util;
use Dana\ObjectSerializer;
use DanaUat\Widget\Scripts\WebAutomation;
use DanaUat\Widget\OauthUtil;
class PaymentUtil extends TestCase
{
    private static $apiInstanceWidget;
    private static $configuration;
    private static $merchantId;
    private static $sharedOriginalPartnerReference;
    private static $jsonPathFileWidget = 'resource/request/components/Widget.json';
    
    // Cache for paid payment reference number
    private static $cachedPaidPaymentReference = null;
    public static function payOrderWidget(
        string $phoneNumberUser,
        string $pinUser,
        string $redirectUrlWeb,
    ): void {
        $params = json_encode([
            'phoneNumber' => $phoneNumberUser,
            'pin' => $pinUser,
            'redirectUrl' => $redirectUrlWeb
        ]);

        Util::execFileAutomate(
            "/automate-payment.js",
            $params
        );
    }

    public static function createPaymentWidget(
        string $orderOrigin
    ): array {
        // Set up configuration with authentication settings
        self::$configuration = new Configuration();

        // The Configuration constructor automatically loads values from environment variables
        // But we can manually set them if needed
        self::$configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        self::$configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        self::$configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        self::$configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstanceWidget = new WidgetApi(null, self::$configuration);

        $jsonDict = Util::getRequest(
            self::$jsonPathFileWidget,
            'Payment',
            $orderOrigin
        );

        $jsonDict['merchantId'] = getenv('MERCHANT_ID');
        $jsonDict['partnerReferenceNo'] = self::generatePartnerReferenceNo();
        $jsonDict['validUpTo'] = Util::generateFormattedDate(30, 7);

        $requestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\Widget\v1\Model\WidgetPaymentRequest'
        );

        $apiResponse = self::$apiInstanceWidget->widgetPayment($requestObj);
        assert($apiResponse != null, "Order creation failed");

        $responseArray = json_decode($apiResponse->__toString(), true);
        return [
            'partnerReferenceNo' => $responseArray['partnerReferenceNo'] ?? '',
            'webRedirectUrl' => $responseArray['webRedirectUrl'] ?? ''
        ];
    }
    
    /**
     * Creates a payment and completes the full payment flow including automated payment
     * 
     * This method can be shared between test classes to avoid duplicate payment creation
     * The method uses caching to ensure only one payment is created across all test runs
     * 
     * @param string $userPhoneNumber Phone number for authentication
     * @param string $userPin PIN for authentication
     * @param string $orderOrigin The order template to use (default: PaymentSuccess)
     * @param bool $forceNewPayment If true, forces creation of a new payment ignoring cache
     * @return string The partnerReferenceNo of the created and paid order
     * @throws \Exception If any step of the payment flow fails
     */
    public static function createPaidPaymentWidget(
        string $userPhoneNumber = "083811223355",
        string $userPin = "181818",
        string $orderOrigin = "PaymentSuccess",
        bool $forceNewPayment = false
    ): string {
        // Check if we already have a cached payment reference
        if (!$forceNewPayment && self::$cachedPaidPaymentReference !== null) {
            echo "\nReusing cached paid payment reference: " . self::$cachedPaidPaymentReference . "\n";
            return self::$cachedPaidPaymentReference;
        }
        
        echo "\nCreating new paid payment...\n";
        
        // Set up configuration with authentication settings if not already done
        if (!isset(self::$configuration) || !isset(self::$apiInstanceWidget)) {
            self::$configuration = new Configuration();
            self::$configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
            self::$configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
            self::$configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
            self::$configuration->setApiKey('ENV', Env::SANDBOX);
            self::$apiInstanceWidget = new WidgetApi(null, self::$configuration);
        }
        
        // 1. Create payment order
        $dataOrder = self::createPaymentWidget($orderOrigin);
        $partnerId = getenv('X_PARTNER_ID');
        
        // 2. Get OAuth authorization code
        $authCode = OauthUtil::getAuthCode(
            $partnerId,
            null,
            $userPhoneNumber,
            $userPin,
            null
        );
        if (!$authCode) {
            throw new \Exception("Failed to obtain OAuth authorization code");
        }
        
        // 3. ApplyToken: Exchange auth code for access token
        $tokenCaseName = 'ApplyTokenSuccess';
        $tokenJsonDict = \DanaUat\Helper\Util::getRequest(
            'resource/request/components/Widget.json',
            'ApplyToken',
            $tokenCaseName
        );
        $tokenRequestObj = ObjectSerializer::deserialize(
            $tokenJsonDict,
            'Dana\\Widget\\v1\\Model\\ApplyTokenRequest'
        );
        $tokenRequestObj->setAuthCode($authCode);
        try {
            $apiResponse = self::$apiInstanceWidget->applyToken($tokenRequestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $accessToken = $responseJson['accessToken'] ?? null;
        } catch (\Exception $e) {
            throw new \Exception("Failed to obtain access token: " . $e->getMessage());
        }
        if (!$accessToken) {
            throw new \Exception("Access token not found in ApplyToken response");
        }
        
        // 4. ApplyOtt: Use access token to get OTT
        $ottCaseName = 'ApplyOttSuccess';
        $ottJsonDict = \DanaUat\Helper\Util::getRequest(
            'resource/request/components/Widget.json',
            'ApplyOtt',
            $ottCaseName
        );
        // Set the access token in the request
        if (!isset($ottJsonDict['additionalInfo'])) {
            $ottJsonDict['additionalInfo'] = [];
        }
        $ottJsonDict['additionalInfo']['accessToken'] = $accessToken;
        $ottRequestObj = ObjectSerializer::deserialize(
            $ottJsonDict,
            'Dana\\Widget\\v1\\Model\\ApplyOTTRequest'
        );
        try {
            $apiResponse = self::$apiInstanceWidget->applyOTT($ottRequestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $ott = $responseJson['userResources'][0]['value'] ?? null;
        } catch (\Exception $e) {
            throw new \Exception("Failed to obtain OTT: " . $e->getMessage());
        }
        if (!$ott) {
            throw new \Exception("OTT not found in ApplyOtt response");
        }
        
        // 5. Append OTT to payment widget URL and automate payment
        $webRedirectUrl = $dataOrder['webRedirectUrl'] . "&ott=" . $ott;
        WebAutomation::automatePaymentWidget(
            $webRedirectUrl
        );
        
        // 6. Query the payment status to verify success
        $queryOrderJsonDict = \DanaUat\Helper\Util::getRequest(
            'resource/request/components/Widget.json',
            "QueryOrder",
            "QueryOrderSuccessPaid"
        );
        $queryOrderJsonDict['merchantId'] = getenv('MERCHANT_ID');
        $queryOrderJsonDict['originalPartnerReferenceNo'] = $dataOrder['partnerReferenceNo'];
        $queryOrderJsonDict['transactionDate'] = (new \DateTime('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d\\TH:i:s+07:00');
        $queryRequestObj = ObjectSerializer::deserialize(
            $queryOrderJsonDict,
            'Dana\\Widget\\v1\\Model\\QueryPaymentRequest'
        );
        $queryApiResponse = self::$apiInstanceWidget->queryPayment($queryRequestObj);
        $queryResponseArray = json_decode($queryApiResponse->__toString(), true);
        $status = $queryResponseArray['transactionStatusDesc'] ?? null;
        if ($status !== 'SUCCESS') {
            throw new \Exception("Payment status is not SUCCESS. Actual status: " . print_r($queryResponseArray, true));
        }
        
        // Cache the successful payment reference for future use
        self::$cachedPaidPaymentReference = (string)$dataOrder['partnerReferenceNo'];
        echo "\nCached new paid payment reference: " . self::$cachedPaidPaymentReference . "\n";
        
        return self::$cachedPaidPaymentReference;
    }

    public static function createPaymentWidgetPaid(
        string $orderOrigin = "PaymentSuccess",
        bool $forceNewPayment = false
    ): string {
        // Check if we already have a cached payment reference
        if (!$forceNewPayment && self::$cachedPaidPaymentReference !== null) {
            echo "\nReusing cached paid payment reference: " . self::$cachedPaidPaymentReference . "\n";
            return self::$cachedPaidPaymentReference;
        }
        
        echo "\nCreating new paid payment...\n";
        
        // Set up configuration with authentication settings if not already done
        if (!isset(self::$configuration) || !isset(self::$apiInstanceWidget)) {
            self::$configuration = new Configuration();
            self::$configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
            self::$configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
            self::$configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
            self::$configuration->setApiKey('ENV', Env::SANDBOX);
            self::$apiInstanceWidget = new WidgetApi(null, self::$configuration);
        }
        
        // 1. Create payment order
        $dataOrder = self::createPaymentWidget($orderOrigin);
        $webRedirectUrl = $dataOrder['webRedirectUrl'];

        WebAutomation::automatePaymentWidget(
            $webRedirectUrl
        );
        
        // 6. Query the payment status to verify success
        $queryOrderJsonDict = \DanaUat\Helper\Util::getRequest(
            'resource/request/components/Widget.json',
            "QueryOrder",
            "QueryOrderSuccessPaid"
        );
        $queryOrderJsonDict['merchantId'] = getenv('MERCHANT_ID');
        $queryOrderJsonDict['originalPartnerReferenceNo'] = $dataOrder['partnerReferenceNo'];
        $queryOrderJsonDict['transactionDate'] = (new \DateTime('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d\\TH:i:s+07:00');
        $queryRequestObj = ObjectSerializer::deserialize(
            $queryOrderJsonDict,
            'Dana\\Widget\\v1\\Model\\QueryPaymentRequest'
        );
        $queryApiResponse = self::$apiInstanceWidget->queryPayment($queryRequestObj);
        $queryResponseArray = json_decode($queryApiResponse->__toString(), true);
        $status = $queryResponseArray['transactionStatusDesc'] ?? null;
        if ($status !== 'SUCCESS') {
            throw new \Exception("Payment status is not SUCCESS. Actual status: " . print_r($queryResponseArray, true));
        }
        
        // Cache the successful payment reference for future use
        self::$cachedPaidPaymentReference = (string)$dataOrder['partnerReferenceNo'];
        echo "\nCached new paid payment reference: " . self::$cachedPaidPaymentReference . "\n";
        
        return self::$cachedPaidPaymentReference;
    }

    public static function generatePartnerReferenceNo(): string
    {
        // Generate a UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
