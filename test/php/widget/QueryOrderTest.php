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

class QueryOrderTest extends TestCase
{
    private static $titleCase = 'QueryOrder';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $merchantId;
    private static $queryOrderUrl;
    private static $sandboxUrl;

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new IpgApi(null, $configuration);
        self::$merchantId = getenv('MERCHANT_ID');
        self::$queryOrderUrl = '/1.0/debit/status.htm';
        self::$sandboxUrl = 'https://api.sandbox.dana.id';
    }

    /**
     * @skip
     * Should give success response for query order scenario
     */
    public function testQueryOrderSuccess(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\QueryOrderRequest'
            );
            $apiResponse = self::$apiInstance->queryOrder($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should give success response for query order (paid)
     */
    public function testQueryOrderSuccessPaid(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderSuccessPaid';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            $apiResponse = self::$apiInstance->queryOrder($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should give success response for query order (initiated)
     */
    public function testQueryOrderSuccessInitiated(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderSuccessInitiated';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            $apiResponse = self::$apiInstance->queryOrder($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should give success response for query order (paying)
     */
    public function testQueryOrderSuccessPaying(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderSuccessPaying';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            $apiResponse = self::$apiInstance->queryOrder($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should give success response for query order (cancelled)
     */
    public function testQueryOrderSuccessCancelled(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderSuccessCancelled';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            $apiResponse = self::$apiInstance->queryOrder($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2000000', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * @skip
     * Should fail with not found
     */
    public function testQueryOrderNotFound(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderNotFound';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown. API response: ' . print_r($apiResponse, true));
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Should fail with invalid field
     */
    public function testQueryOrderFailInvalidField(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailInvalidField';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $headers = Util::getHeadersWithSignature(
                'POST', 
                self::$queryOrderUrl,
                $jsonDict,
                false
            );

            try {
                    Util::executeApiRequest(
                        'POST',
                        self::$sandboxUrl . self::$queryOrderUrl,
                        $headers,
                        $jsonDict
                    );
                    
                    $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for missing timestamp
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 BadRequest for missing X-TIMESTAMP, got {$e->getCode()}");

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

    /**
     * @skip
     * Should fail with missing or invalid mandatory field
     */
    public function testQueryOrderFailMissingOrInvalidMandatoryField(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailMissingOrInvalidMandatoryField';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
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
    public function testQueryOrderFailInvalidSignature(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailInvalidSignature';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with general error
     */
    public function testQueryOrderFailGeneralError(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailGeneralError';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with transaction not permitted
     */
    public function testQueryOrderFailTransactionNotPermitted(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailTransactionNotPermitted';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with merchant not exist or status abnormal
     */
    public function testQueryOrderFailMerchantNotExistOrStatusAbnormal(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailMerchantNotExistOrStatusAbnormal';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with inconsistent request
     */
    public function testQueryOrderFailInconsistentRequest(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailInconsistentRequest';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with internal server error
     */
    public function testQueryOrderFailInternalServerError(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailInternalServerError';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with timeout
     */
    public function testQueryOrderFailTimeout(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailTimeout';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with idempotent
     */
    public function testQueryOrderFailIdempotent(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'QueryOrderFailIdempotent';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\QueryOrderRequest');
            try {
                self::$apiInstance->queryOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
