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
use Exception;

class AccountUnbindingTest extends TestCase
{
    private static $titleCase = 'AccountUnbinding';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $phoneNumber = '083811223355';
    private static $userPin = '181818';
    private static $apiInstance;
    private static $authCode;
    private static $accessToken;
    private static $merchantId;

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

        // Store merchantId for reuse
        self::$merchantId = getenv('MERCHANT_ID');
    }

    /**
     * @skip
     * Should give success response for account unbinding scenario
     */
    public function testAccountUnbindSuccess(): void
    {
        Util::withDelay(function() {
            $caseName = 'AccountUnbindSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            $jsonDict['additionalInfo']['deviceId'] = "deviceid123";
            $jsonDict['additionalInfo']['accessToken'] = self::$accessToken;
            $jsonDict['merchantId'] = self::$merchantId;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\AccountUnbindingRequest'
            );

            $apiResponse = self::$apiInstance->accountUnbinding($requestObj);

            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                $apiResponse->__toString()
            );
        });
    }

    /**
     * @skip
     * Should fail to unbind account when access token does not exist
     */
    public function testAccountUnbindingFailAccessTokenNotExist(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'AccountUnbindFailAccessTokenNotExist';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\AccountUnbindingRequest'
            );
            try {
                self::$apiInstance->accountUnbinding($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to unbind account with invalid user status
     */
    public function testAccountUnbindingFailInvalidUserStatus(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'AccountUnbindFailInvalidUserStatus';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\AccountUnbindingRequest'
            );
            try {
                self::$apiInstance->accountUnbinding($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to unbind account with invalid params
     */
    public function testAccountUnbindingFailInvalidParams(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'AccountUnbindFailInvalidParams';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\AccountUnbindingRequest'
            );
            try {
                self::$apiInstance->accountUnbinding($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should give fail response for invalid account unbinding scenario
     */
    public function testAccountUnbindingInvalidFieldFormat(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'AccountUnbindingInvalidFieldFormat';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\AccountUnbindingRequest'
            );
            try {
                self::$apiInstance->accountUnbinding($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
