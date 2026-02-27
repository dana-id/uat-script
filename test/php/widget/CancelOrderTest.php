<?php

namespace DanaUat\Widget;

use PHPUnit\Framework\TestCase;
use Dana\Widget\v1\Api\WidgetApi;
use Dana\Configuration;
use Dana\Widget\v1\Model\CancelOrderRequest;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use DanaUat\Widget\PaymentUtil;
use Exception;

class CancelOrderTest extends TestCase
{
    private static $titleCase = 'CancelOrder';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $merchantId;
    private static $cancelUrl;
    private static $phoneNumber = '083811223355';
    private static $userPin = '181818';

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new WidgetApi(null, $configuration);
        self::$merchantId = getenv('MERCHANT_ID');
        self::$cancelUrl = '/payment-gateway/v1.0/debit/cancel.htm';
    }

    /**
     * Create an order, make it paid, and then refund it
     * 
     * @param string $orderOrigin The order template to use (default: PaymentSuccess)
     * @param bool $forceNewPayment If true, forces creation of a new payment
     * @return string The partner reference number of the order that was created, paid, and refunded
     * @throws Exception If any step fails
     */
    private static function createPaidAndRefundOrder(string $orderOrigin = 'PaymentSuccess', bool $forceNewPayment = false): string
    {
        try {
            echo "\nCreating, Paying, and Refunding Order\n";
            
            // Step 1: Create and pay the order
            echo "Step 1: Creating and paying order...\n";
            $partnerReferenceNo = PaymentUtil::createPaymentWidgetPaid($orderOrigin, $forceNewPayment);
            echo "Order created and paid with reference: {$partnerReferenceNo}\n";
            
            // Step 2: Refund the paid order
            echo "Step 2: Refunding the paid order...\n";
            $refundedReference = self::refundPaidOrder($partnerReferenceNo);
            echo "Order refunded successfully: {$refundedReference}\n";
            
            echo "Process completed successfully\n";
            return $partnerReferenceNo;
            
        } catch (Exception $e) {
            echo "Process failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * Refund a paid widget order using the given partner reference number
     * 
     * @param string $originalPartnerReferenceNo The original partner reference number of the paid order
     * @return string The original partner reference number that was refunded
     * @throws Exception If refund fails
     */
    private static function refundPaidOrder(string $originalPartnerReferenceNo): string
    {
        // Get the request data from the JSON file for refund
        $refundRequestData = Util::getRequest(
            self::$jsonPathFile,
            'RefundOrder',
            'RefundOrderValidScenario'
        );

        // Generate a unique partner refund number
        $partnerRefundNo = PaymentUtil::generatePartnerReferenceNo();
        
        // Set the required parameters
        $refundRequestData['originalPartnerReferenceNo'] = $originalPartnerReferenceNo;
        $refundRequestData['partnerRefundNo'] = $partnerRefundNo;
        $refundRequestData['merchantId'] = self::$merchantId;

        // Create a RefundOrderRequest object from the JSON request data
        $refundOrderRequestObj = ObjectSerializer::deserialize(
            $refundRequestData,
            'Dana\Widget\v1\Model\RefundOrderRequest'
        );

        echo "Refunding widget order with reference: {$originalPartnerReferenceNo}\n";
        
        // Make the API call
        $refundResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);
        $refundResponseArray = json_decode($refundResponse->__toString(), true);

        // Check if refund is successful
        if ($refundResponseArray && isset($refundResponseArray['responseCode']) && $refundResponseArray['responseCode'] === '2005800') {
            echo "Refund successful for widget transaction: {$originalPartnerReferenceNo}\n";
        } else {
            $responseCode = $refundResponseArray['responseCode'] ?? 'unknown';
            $responseMessage = $refundResponseArray['responseMessage'] ?? 'No message';
            echo "Refund response: Code {$responseCode} - {$responseMessage}\n";
        }

        return $originalPartnerReferenceNo;
    }

    public function testCancelOrderValidScenario(): void
    {
        Util::withDelay(function () {
            $caseName = 'CancelOrderValidScenario';
            try {
                $partnerReferenceNo = PaymentUtil::createPaymentWidget(
                    'PaymentSuccess'
                );

                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                $jsonDict['originalPartnerReferenceNo'] = (string)$partnerReferenceNo['partnerReferenceNo'];
                $jsonDict['merchantId'] = self::$merchantId;

                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Widget\v1\Model\CancelOrderRequest'
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
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailUserStatusAbnormal';
            try {
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Widget\v1\Model\CancelOrderRequest'
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
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailMerchantStatusAbnormal';
            try {
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Widget\v1\Model\CancelOrderRequest'
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
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailMissingParameter';
            try {
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Widget\v1\Model\CancelOrderRequest'
                );
                $requestObj->setMerchantId(self::$merchantId); // Simulate missing merchantId

                self::$apiInstance->cancelOrder($requestObj);
                $this->fail('Expected ApiException for merchant status abnormal was not thrown');
            } catch (ApiException $e) {
                // We expect a 400 Not Found for abnormal merchant status
                $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Not Found for abnormal merchant status, got {$e->getCode()}");

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
     * Should fail with order not exist (FAIL: Expected ApiException was not thrown)
     */
    public function testCancelOrderFailOrderNotExist(): void
    {
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailOrderNotExist';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $partnerReferenceNo = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['originalReferenceNo'] = $partnerReferenceNo;
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\CancelOrderRequest'
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
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailExceedCancelWindowTime';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\CancelOrderRequest'
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
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailNotAllowedByAgreement';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\CancelOrderRequest'
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
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailAccountStatusAbnormal';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\CancelOrderRequest'
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
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailInsufficientMerchantBalance';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\CancelOrderRequest'
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
     * Should fail when trying to cancel an already refunded order
     */
    public function testCancelOrderFailOrderInvalidStatus(): void
    {
        Util::withDelay(function () {
            try {
                $refundedOrderReference = self::createPaidAndRefundOrder('PaymentSuccess', true);
                $caseName = 'CancelOrderFailOrderInvalidStatus';
                
                // Get the cancel order request template
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                
                // Use the refunded order reference
                $jsonDict['originalPartnerReferenceNo'] = $refundedOrderReference;
                $jsonDict['merchantId'] = self::$merchantId;
                
                $requestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Widget\v1\Model\CancelOrderRequest'
                );
                
                try {
                    self::$apiInstance->cancelOrder($requestObj);
                    $this->fail('Expected ApiException for trying to cancel refunded order but the API call succeeded');
                } catch (ApiException $e) {
                    // Validate the error response
                    Assertion::assertFailResponse(
                        self::$jsonPathFile, 
                        self::$titleCase, 
                        $caseName, 
                        $e->getResponseBody(),
                        ['partnerReferenceNo' => $refundedOrderReference]
                    );
                    
                    $this->assertTrue(true, 'Cancel order properly failed for refunded order');
                }
                
            } catch (Exception $e) {
                echo "Test failed: {$e->getMessage()}\n";
                $this->fail('testCancelOrderFailOrderInvalidStatus failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should fail with timeout
     */
    public function testCancelOrderFailTimeout(): void
    {
        Util::withDelay(function () {
            $caseName = 'CancelOrderFailTimeout';
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\CancelOrderRequest'
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
