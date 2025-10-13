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

class ApplyTokenTest extends TestCase
{
    private static $titleCase = 'ApplyToken';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $phoneNumber = '0811742234';
    private static $userPin = '123321';
    private static $authCode;
    private static $partnerId;

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new WidgetApi(null, $configuration);

        self::$authCode = OauthUtil::getAuthCode(
            getenv('X_PARTNER_ID'),
            null,
            self::$phoneNumber,
            self::$userPin,
            getenv('REDIRECT_URL_OAUTH')
        );
        self::$partnerId = getenv('X_PARTNER_ID');
    }

    /**
     * Should give success response for apply token scenario
     */
    public function testApplyTokenSuccess(): void
    {   
        Util::withDelay(function () {
            // Continue with your API call
            $caseName = 'ApplyTokenSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyTokenRequest'
            );

            $requestObj->setAuthCode(self::$authCode);

            try {
                $apiResponse = self::$apiInstance->applyToken($requestObj);
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );
            } catch (ApiException $e) {
                $this->fail('Failed to cancel order in progress: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }
     public function testApplyTokenFailExpiredAuthcode(): void
    {
        Util::withDelay(function () {
            // Continue with your API call
            $caseName = 'ApplyTokenFailExpiredAuthcode';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyTokenRequest'
            );

            $requestObj->setAuthCode("GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800");

            try {
                $apiResponse = self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    public function testApplyTokenFailInvalidSignature(): void
    {   
        Util::withDelay(function () {
            // Continue with your API call
            $caseName = 'ApplyTokenFailInvalidSignature';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            $headers = Util::getHeadersWithSignature(
                'POST',
                "/v1.0/access-token/b2b2c.htm",
                $jsonDict,
                true,
                false,
                true
            );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\ApplyTokenRequest'
            );

            $headers['X-CLIENT-KEY'] = self::$partnerId;

            try {
                Util::executeApiRequest(
                    'POST',
                    'https://api.sandbox.dana.id/v1.0/access-token/b2b2c.htm',
                    $headers,
                    $requestObj
                );

                $this->fail('Expected ApiException for invalid signature but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 401 Unauthorized for invalid signature
                $this->assertEquals(401, $e->getCode(), "Expected HTTP 401 Unauthorized for invalid signature, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();

                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $responseContent
                );
            } catch (Exception $e) {
                throw $e;
            }
        });
    }
}
