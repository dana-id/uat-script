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

class GetAuth2Test extends TestCase
{
    private static $titleCase = 'GetAuth2';
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
     * Should give success response for get auth2 scenario
     */
    public function testGetAuth2Success(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'GetAuth2Success';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\GetAuth2Request'
            );
            $apiResponse = self::$apiInstance->getAuth2($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should fail with invalid format
     */
    public function testGetAuth2FailInvalidFormat(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'GetAuth2FailInvalidFormat';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\GetAuth2Request'
            );
            try {
                self::$apiInstance->getAuth2($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with missing or invalid mandatory field
     */
    public function testGetAuth2FailMissingOrInvalidMandatoryField(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'GetAuth2FailMissingOrInvalidMandatoryField';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\GetAuth2Request'
            );
            try {
                self::$apiInstance->getAuth2($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with invalid signature
     */
    public function testGetAuth2FailInvalidSignature(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'GetAuth2FailInvalidSignature';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\GetAuth2Request'
            );
            try {
                self::$apiInstance->getAuth2($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with token expired
     */
    public function testGetAuth2FailTokenExpired(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'GetAuth2FailTokenExpired';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\GetAuth2Request'
            );
            try {
                self::$apiInstance->getAuth2($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with token not found
     */
    public function testGetAuth2FailTokenNotFound(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'GetAuth2FailTokenNotFound';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\GetAuth2Request'
            );
            try {
                self::$apiInstance->getAuth2($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should give fail response for invalid get auth2 scenario
     */
    public function testGetAuth2InvalidFieldFormat(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'GetAuth2InvalidFieldFormat';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\GetAuth2Request'
            );
            try {
                self::$apiInstance->getAuth2($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
