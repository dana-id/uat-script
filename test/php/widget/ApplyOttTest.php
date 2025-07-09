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

class ApplyOttTest extends TestCase
{
    private static $titleCase = 'ApplyOtt';
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
     * Should give success response for apply ott scenario
     */
    public function testApplyOttSuccess(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            $apiResponse = self::$apiInstance->applyOtt($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with invalid format
     */
    public function testApplyOttFailInvalidFormat(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailInvalidFormat';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with missing or invalid mandatory field
     */
    public function testApplyOttFailMissingOrInvalidMandatoryField(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailMissingOrInvalidMandatoryField';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with invalid signature
     */
    public function testApplyOttFailInvalidSignature(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailInvalidSignature';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with token expired
     */
    public function testApplyOttFailTokenExpired(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailTokenExpired';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with token not found
     */
    public function testApplyOttFailTokenNotFound(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailTokenNotFound';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with invalid user status
     */
    public function testApplyOttFailInvalidUserStatus(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailInvalidUserStatus';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with non-retryable error
     */
    public function testApplyOttFailNonRetryableError(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailNonRetryableError';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with internal server error
     */
    public function testApplyOttFailInternalServerError(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailInternalServerError';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail to apply OTT with unexpected response
     */
    public function testApplyOttFailUnexpectedResponse(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'ApplyOttFailUnexpectedResponse';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\ApplyOttRequest'
            );
            try {
                self::$apiInstance->applyOtt($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
