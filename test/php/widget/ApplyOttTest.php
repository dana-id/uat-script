<?php

namespace DanaUat\Widget;

use PHPUnit\Framework\TestCase;
use Dana\Widget\v1\Api\WidgetApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use DanaUat\Widget\OauthUtil;
use Exception;
use GuzzleHttp\Psr7\Response;

class ApplyOttTest extends TestCase
{
    private static $titleCase = 'ApplyOtt';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $phoneNumber = '0811742234';
    private static $userPin = '123321';
    private static $authCode;
    private static $accessToken;

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new WidgetApi(null, $configuration);
        
        // Get OAuth authorization code
        self::$authCode = OauthUtil::getAuthCode(
            getenv('X_PARTNER_ID'),
            null,
            self::$phoneNumber,
            self::$userPin,
            null
        );
        
        echo "\nObtained auth code: " . self::$authCode . "\n";
        
        // Get access token using the auth code
        $tokenCaseName = 'ApplyTokenSuccess';
        $tokenJsonDict = Util::getRequest(
            'resource/request/components/Widget.json',
            'ApplyToken',
            $tokenCaseName
        );
        
        $tokenRequestObj = ObjectSerializer::deserialize(
            $tokenJsonDict,
            'Dana\Widget\v1\Model\ApplyTokenRequest'
        );
        
        $tokenRequestObj->setAuthCode(self::$authCode);
        
        try {
            $apiResponse = self::$apiInstance->applyToken($tokenRequestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            self::$accessToken = $responseJson['accessToken'];
            echo "\nObtained access token: " . self::$accessToken . "\n";
        } catch (Exception $e) {
            echo "\nFailed to obtain access token: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Should give success response for apply ott scenario
     */
    public function testApplyOttSuccess(): void
    {

        Util::withDelay(function () {
            $caseName = 'ApplyOttSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $jsonDict['additionalInfo']['accessToken'] = self::$accessToken;
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyOTTRequest'
            );

            $apiResponse = self::$apiInstance->applyOTT($requestObj);
            
            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                $apiResponse->__toString()
            );
        });
    }

    /**
     * Should fail to apply OTT with token not found
     */
    public function testApplyOttFailTokenNotFound(): void
    {

        Util::withDelay(function () {
            $caseName = 'ApplyOttCustomerTokenNotFound';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyOTTRequest'
            );
            // Set an invalid access token for testing token not found error
            $jsonDict['additionalInfo']['accessToken'] = 'invalid_access_token_for_testing';
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyOTTRequest'
            );
            try {
                self::$apiInstance->applyOTT($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Should fail to apply OTT with invalid user status
     */
    public function testApplyOttFailInvalidUserStatus(): void
    {

        $this->markTestSkipped('Scenario skipped because the result the same with testApplyOttFailTokenNotFound');
        
        Util::withDelay(function () {
            $caseName = 'ApplyOttFailInvalidUserStatus';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyOTTRequest'
            );
            // Set a modified access token that will trigger invalid user status error
            $jsonDict['additionalInfo']['accessToken'] = self::$accessToken !== null ? self::$accessToken.'_modified' : 'test_access_token';
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyOTTRequest'
            );
            try {
                self::$apiInstance->applyOTT($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
