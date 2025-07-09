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

class TransactionListTest extends TestCase
{
    private static $titleCase = 'TransactionList';
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
     * Should give success response for transaction list scenario
     */
    public function testTransactionListSuccess(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'TransactionListSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\TransactionListRequest'
            );
            $apiResponse = self::$apiInstance->transactionList($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should fail with invalid param
     */
    public function testTransactionListFailInvalidParam(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'TransactionListFailInvalidParam';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\TransactionListRequest');
            try {
                self::$apiInstance->transactionList($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with data not available
     */
    public function testTransactionListFailDataNotAvailable(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'TransactionListFailDataNotAvailable';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\TransactionListRequest');
            try {
                self::$apiInstance->transactionList($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with system error
     */
    public function testTransactionListFailSystemError(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'TransactionListFailSystemError';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\TransactionListRequest');
            try {
                self::$apiInstance->transactionList($requestObj);
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
    public function testTransactionListFailInvalidSignature(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'TransactionListFailInvalidSignature';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\TransactionListRequest');
            try {
                self::$apiInstance->transactionList($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with invalid token
     */
    public function testTransactionListFailInvalidToken(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'TransactionListFailInvalidToken';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\TransactionListRequest');
            try {
                self::$apiInstance->transactionList($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with invalid mandatory parameter
     */
    public function testTransactionListFailInvalidMandatoryParameter(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'TransactionListFailInvalidMandatoryParameter';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\TransactionListRequest');
            try {
                self::$apiInstance->transactionList($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
