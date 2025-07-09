<?php

namespace DanaUat\Ipg\v1;

use PHPUnit\Framework\TestCase;
use Dana\IPG\v1\Api\IPGApi;
use Dana\Configuration;
use Dana\IPG\v1\Model\CancelOrderRequest;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use Exception;

class CancelOrderTest extends TestCase
{
    private static $titleCase = 'CancelOrder';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $merchantId;
    private static $cancelUrl;

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new IpgApi(null, $configuration);
        self::$merchantId = getenv('MERCHANT_ID');
        self::$cancelUrl = '/payment-gateway/v1.0/debit/cancel.htm';
    }

    /**
     * Should give success response for CancelOrderSuccessInProcess
     */
    public function testCancelOrderSuccessInProcess(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderSuccessInProcess';
            try {
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\IPG\v1\Model\CancelOrderRequest'
                );
                $apiResponse = self::$apiInstance->cancelOrder($requestObj);
                Assertion::assertResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                );
                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to cancel order in progress: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should fail with user status abnormal
     */
    public function testCancelOrderFailUserStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailUserStatusAbnormal';
            try {
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\IPG\v1\Model\CancelOrderRequest'
                );
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for abnormal user status
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for abnormal user status, got {$e->getCode()}");

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
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should fail with merchant status abnormal
     */
    public function testCancelOrderFailMerchantStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailMerchantStatusAbnormal';
            try {
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\IPG\v1\Model\CancelOrderRequest'
                );
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException for merchant status abnormal was not thrown');
            } catch (ApiException $e) {
                // We expect a 404 Not Found for abnormal merchant status
                $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 Not Found for abnormal merchant status, got {$e->getCode()}");

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
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * @skip
     * Should fail with missing parameter (FAIL: Value mismatch for 'responseMessage')
     */
    public function testCancelOrderFailMissingParameter(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailMissingParameter';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            // Create headers without timestamp to test validation
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    self::$cancelUrl,
                    $jsonDict,
                    false
                );
            try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm',
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
     * Should fail with order not exist (FAIL: Expected ApiException was not thrown)
     */
    public function testCancelOrderFailOrderNotExist(): void
    {
        $this->markTestSkipped('Skipping testCancelOrderFailOrderNotExist due to API changes that prevent testing with non-existent orders.');
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailOrderNotExist';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $jsonDict['originalPartnerReferenceId'] = 'nonexistent-order-id'; // Simulate non-existent order]
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            try {
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Should fail with exceed cancel window time
     */
    public function testCancelOrderFailExceedCancelWindowTime(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailExceedCancelWindowTime';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            try {
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Should fail not allowed by agreement
     */
    public function testCancelOrderFailNotAllowedByAgreement(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailNotAllowedByAgreement';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            try {
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Should fail with account status abnormal
     */
    public function testCancelOrderFailAccountStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailAccountStatusAbnormal';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            try {
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Should fail with insufficient merchant balance
     */
    public function testCancelOrderFailInsufficientMerchantBalance(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailInsufficientMerchantBalance';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            try {
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with order refunded (ERROR: Request data not found)
     */
    public function testCancelOrderFailOrderRefunded(): void
    {
        $this->markTestSkipped('Skipping testCancelOrderFailOrderRefunded due to waiting for functionality to be implemented.');
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailOrderRefunded';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            try {
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * @skip
     * Should fail with invalid signature (FAIL: Expected ApiException was not thrown)
     */
    public function testCancelOrderFailInvalidSignature(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailInvalidSignature';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            $headers = Util::getHeadersWithSignature(
                'POST',
                self::$cancelUrl,
                $jsonDict,
                true,
                false,
                true
            );
            // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm',
                        $headers,
                        $jsonDict
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

    /**
     * Should fail with timeout
     */
    public function testCancelOrderFailTimeout(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderFailTimeout';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\CancelOrderRequest'
            );
            try {
                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }
}
