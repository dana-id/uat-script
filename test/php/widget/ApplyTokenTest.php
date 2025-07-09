<?php

namespace DanaUat\Ipg\v1;

use PHPUnit\Framework\TestCase;
use Dana\IPG\v1\Api\IPGApi;
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

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new IpgApi(null, $configuration);
    }

    /**
     * @skip
     * Should give success response for apply token scenario
     */
    public function testApplyTokenSuccess(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            $apiResponse = self::$apiInstance->applyToken($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should fail to apply token with expired authcode
     */
    public function testApplyTokenFailExpiredAuthcode(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenFailExpiredAuthcode';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            try {
                self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply token with used authcode
     */
    public function testApplyTokenFailAuthcodeUsed(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenFailAuthcodeUsed';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            try {
                self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply token with invalid authcode
     */
    public function testApplyTokenFailAuthcodeInvalid(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenFailAuthcodeInvalid';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            try {
                self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply token with invalid params
     */
    public function testApplyTokenFailInvalidParams(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenFailInvalidParams';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            try {
                self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply token with invalid mandatory fields
     */
    public function testApplyTokenFailInvalidMandatoryFields(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenFailInvalidMandatoryFields';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            try {
                self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply token with invalid signature
     */
    public function testApplyTokenFailInvalidSignature(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenFailInvalidSignature';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            try {
                self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should give fail response for invalid apply token scenario
     */
    public function testApplyTokenInvalidFieldFormat(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyTokenInvalidFieldFormat';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyTokenRequest'
            );
            try {
                self::$apiInstance->applyToken($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
