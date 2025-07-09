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

class RefundOrderTest extends TestCase
{
    private static $titleCase = 'RefundOrder';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $sharedOriginalPartnerReference;
    private static $merchantId;
    private static $refundUrl;
    private static $sandboxUrl;

    /**
     * Generate a unique partner reference number using UUID v4
     * 
     * @return string
     */
    private static function generatePartnerReferenceNo(): string
    {
        // Generate a UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new IpgApi(null, $configuration);
        self::$merchantId = getenv('MERCHANT_ID');
        self::$refundUrl = '/v1.0/debit/refund.htm';
        self::$sandboxUrl = 'https://api.sandbox.dana.id';
    }

    /**
     * Test a valid refund order scenario.
     *
     * This test verifies that a valid refund order request returns a successful response.
     * @skip
     */
    public function testRefundOrderValidScenario(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'RefundOrderValidScenario';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
            $apiResponse = self::$apiInstance->refundOrder($requestObj);
            $responseJson = json_decode($apiResponse->__toString(), true);
            $this->assertEquals('2005800', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
        });
    }

    /**
     * Test refund order in process scenario.
     *
     * This test verifies that a refund order in process returns the expected response.
     */
    public function testRefundInProcess(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundInProcess';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                $apiResponse = self::$apiInstance->refundOrder($requestObj);
                $responseJson = json_decode($apiResponse->__toString(), true);
                Assertion::assertResponse(self::$jsonPathFile, self::$titleCase, $caseName, $apiResponse->__toString(), ['partnerReferenceNo' => '2025700']);
                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to refund order in progress: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to exceeding payment amount.
     *
     * This test verifies that a refund request exceeding the payment amount fails as expected.
     * @skip
     */
    public function testRefundFailExceedPaymentAmount(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'RefundFailExceedPaymentAmount';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
            try {
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Test refund failure when not allowed by agreement.
     *
     * This test verifies that a refund request not allowed by agreement fails with 403 Forbidden.
     */
    public function testRefundFailNotAllowedByAgreement(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailNotAllowedByAgreement';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for abnormal user status
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for abnormal user status, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to exceeding refund window time.
     *
     * This test verifies that a refund request outside the allowed window fails with 403 Forbidden.
     */
    public function testRefundFailExceedRefundWindowTime(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailExceedRefundWindowTime';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for abnormal user status, got {$e->getCode()}");
                $responseContent = (string)$e->getResponseBody();
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to multiple refunds not allowed.
     *
     * This test verifies that a multiple refund request fails with 403 Forbidden.
     */
    public function testRefundFailMultipleRefundNotAllowed(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailMultipleRefundNotAllowed';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for abnormal user status, got {$e->getCode()}");
                $responseContent = (string)$e->getResponseBody();
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to duplicate request.
     *
     * This test verifies that a duplicate refund request fails as expected.
     * @skip
     */
    public function testRefundFailDuplicateRequest(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'RefundFailDuplicateRequest';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
            try {
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Test refund failure when order is not paid.
     *
     * This test verifies that a refund request for an unpaid order fails as expected.
     * @skip
     */
    public function testRefundFailOrderNotPaid(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'RefundFailOrderNotPaid';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
            try {
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Test refund failure due to illegal parameter.
     *
     * This test verifies that a refund request with illegal parameters fails with 400 Bad Request.
     */
    public function testRefundFailParameterIllegal(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailParameterIllegal';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                // We expect a 400 Bad Request for invalid parameter
                $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for invalid parameter, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to missing mandatory parameter.
     *
     * This test verifies that a refund request missing a mandatory parameter fails with 400 Bad Request.
     * @skip
     */
    public function testRefundFailMandatoryParameterInvalid(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailMandatoryParameterInvalid';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $headers = Util::getHeadersWithSignature(
                'POST', 
                self::$refundUrl,
                $jsonDict,
                false
            );
            try {
                Util::executeApiRequest('POST', self::$sandboxUrl . self::$refundUrl, $headers, $jsonDict);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                    // We expect a 400 Bad Request for missing timestamp
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 BadRequest for missing X-TIMESTAMP, got {$e->getCode()}");

                    // Get the response body from the exception
                    $responseContent = (string)$e->getResponseBody();
                    
                    // Use assertFailResponse to validate the error response
                    Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
                } catch (Exception $e) {
                    throw $e;
                }
        });
    }

    /**
     * Test refund failure when order does not exist.
     *
     * This test verifies that a refund request for a non-existent order fails with 404 Not Found.
     */
    public function testRefundFailOrderNotExist(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailOrderNotExist';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                // We expect a 404 Not Found for order not exist
                $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 Not Found for order not exist, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to insufficient merchant balance.
     *
     * This test verifies that a refund request fails with 403 Forbidden when merchant balance is insufficient.
     */
    public function testRefundFailInsufficientMerchantBalance(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailInsufficientMerchantBalance';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for insufficient merchant balance
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for insufficient merchant balance, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to invalid signature.
     *
     * This test verifies that a refund request with an invalid signature fails with 401 Unauthorized.
     */
    public function testRefundFailInvalidSignature(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailInvalidSignature';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
            $headers = Util::getHeadersWithSignature('POST', self::$refundUrl, $jsonDict, true, false, true);
            try {
                Util::executeApiRequest('POST', self::$sandboxUrl . self::$refundUrl, $headers, $jsonDict);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                    // We expect a 401 Unauthorized for invalid signature
                    $this->assertEquals(401, $e->getCode(), "Expected HTTP 401 Unauthorized for invalid signature, got {$e->getCode()}");

                    // Get the response body from the exception
                    $responseContent = (string)$e->getResponseBody();
                    
                    // Use assertFailResponse to validate the error response
                    Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
                } catch (Exception $e) {
                    throw $e;
                }
        });
    }

    /**
     * Test refund failure due to timeout.
     *
     * This test verifies that a refund request that times out fails with 500 Internal Server Error.
     */
    public function testRefundFailTimeout(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailTimeout';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                $this->assertEquals(500, $e->getCode(), "Expected HTTP 500 Internal Server Error for timeout, got {$e->getCode()}");
                $responseContent = (string)$e->getResponseBody();
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund failure due to idempotency violation.
     *
     * This test verifies that a repeated refund request fails as expected due to idempotency.
     * @skip
     */
    public function testRefundFailIdempotent(): void
    {
        $this->markTestSkipped('Widget scenario skipped by automation.');
        Util::withDelay(function() {
            $caseName = 'RefundFailIdempotent';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
            try {
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Test refund failure due to merchant status abnormal.
     *
     * This test verifies that a refund request fails with 404 Not Found when merchant status is abnormal.
     */
    public function testRefundFailMerchantStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'RefundFailMerchantStatusAbnormal';
            try {
                $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
                $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\IPG\v1\Model\RefundOrderRequest');
                self::$apiInstance->refundOrder($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                // We expect a 404 Not found for merchant status abnormal
                $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 Not Found for merchant status abnormal, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $responseContent);
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }
}
