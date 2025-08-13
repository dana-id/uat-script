<?php

namespace DanaUat\PaymentGateway;

use PHPUnit\Framework\TestCase;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use DanaUat\PaymentGateway\PaymentUtil;
use DanaUat\PaymentGateway\Scripts\WebAutomation;
use Exception;

class RefundOrderTest extends TestCase
{
    private static $titleCase = 'RefundOrder';
    private static $createOrderTitleCase = 'CreateOrder';
    private static $jsonPathFile = 'resource/request/components/PaymentGateway.json';
    private static $apiInstance;
    private static $partnerReferenceNoPaid;
    private static $partnerReferenceNoInit;

    /**
     * Generate a unique partner reference number using UUID v4
     * 
     * @return string
     */

    public static function setUpBeforeClass(): void
    {
        // Set up configuration with authentication settings
        $configuration = new Configuration();

        // The Configuration constructor automatically loads values from environment variables
        // But we can manually set them if needed
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);

        // Create PaymentGatewayApi instance directly with configuration
        self::$apiInstance = new PaymentGatewayApi(null, $configuration);

        self::$partnerReferenceNoInit = self::createTestOrder();

        self::$partnerReferenceNoPaid = self::createPaidOrder();
    }

    /**
     * Create a test order with paid status for refund tests
     */
    private static function createPaidOrder(): string
    {
        $partnerReferenceNo = PaymentUtil::getOrCreatePaidOrder(
                self::$apiInstance,
                self::$jsonPathFile,
                "CreateOrder",
                Util::generatePartnerReferenceNo()
            );

        return $partnerReferenceNo;
    }

    /**
     * Create a regular test order
     */
    private static function createTestOrder(): string
    {
        $caseName = 'CreateOrderRedirect';

        $partnerReferenceNo = Util::generatePartnerReferenceNo();
        // Get the request data from the JSON file
        $jsonDict = Util::getRequest(
            self::$jsonPathFile,
            self::$createOrderTitleCase,
            $caseName
        );

        // Set the partner reference number
        $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;
        $jsonDict['merchantId'] = getenv('MERCHANT_ID');

        // Create a CreateOrderByApiRequest object from the JSON request data
        $createOrderRequestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\PaymentGateway\v1\Model\CreateOrderByRedirectRequest',
        );

        $createOrderRequestObj->setPartnerReferenceNo($partnerReferenceNo);

        try {
            // Make the API call
            $response = self::$apiInstance->createOrder($createOrderRequestObj);
            error_log("CreateOrder API response: " . $response->__toString());
        } catch (Exception $e) {
            throw new Exception("Failed to create test order: " . $e->getMessage());
        }
        return $partnerReferenceNo;
    }

    /**
     * Test refund order valid scenario
     */
    public function testRefundOrderValidScenario(): void
    {
        Util::runWithRetry(function () {
            $caseName = 'RefundOrderValidScenario';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::createPaidOrder();

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to call refund order API: ' . $e->getResponseBody());
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        },
        3,  // 3 retries
        2000,  // 2 second initial delay
        [ApiException::class, Exception::class]);
    }

    /**
     * Test refund order that exceeds transaction amount limit
     */
    public function testRefundOrderExceedsTransactionAmountLimit(): void
    {
        $this->markTestSkipped('Skipping refund order exceeds transaction amount limit test temporarily.');
        Util::withDelay(function () {
            $caseName = 'RefundOrderExceedsTransactionAmountLimit';
            // Create test orders for refund scenarios
            $paidOrderReferenceNumber = self::$partnerReferenceNoPaid;

            $partnerReferenceNo = $paidOrderReferenceNumber;
            $partnerRefundNo = Util::generatePartnerReferenceNo();

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with exceeds amount limit
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order not allowed
     */
    public function testRefundOrderNotAllowed(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderNotAllowed';
            
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with not allowed
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order due to exceed refund window time
     */
    public function testRefundOrderDueToExceedRefundWindowTime(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderDueToExceedRefundWindowTime';

            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with window time exceeded
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order multiple refund
     */
    public function testRefundOrderMultipleRefund(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderMultipleRefund';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with multiple refund
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order not paid
     */
    public function testRefundOrderNotPaid(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderNotPaid';

            $partnerReferenceNo = self::$partnerReferenceNoInit;
            
            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with not paid
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order with illegal parameter
     */
    public function testRefundOrderIllegalParameter(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderIllegalParameter';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers (but keep illegal merchantId from JSON)
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with illegal parameter
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order with invalid mandatory parameter
     */
    public function testRefundOrderInvalidMandatoryParameter(): void
    {
        try {
            Util::withDelay(function () {
                $caseName = 'RefundOrderInvalidMandatoryParameter';
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                // Compose headers with missing X-TIMESTAMP (simulate invalid mandatory parameter)
                $headers = Util::getHeadersWithSignature(
                    'POST',
                    '/payment-gateway/v1.0/debit/refund.htm',
                    $requestData,
                    true,
                    false,
                    false
                );

                $headers['X-SIGNATURE'] = '';
                
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/refund.htm',
                        $headers,
                        $requestData
                    );
                    $this->fail('Expected ApiException for missing X-TIMESTAMP header but the API call succeeded');
                } catch (ApiException $e) {
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for missing X-TIMESTAMP header, got {$e->getCode()}");
                    $responseContent = (string)$e->getResponseBody();
                    Assertion::assertFailResponse(
                        self::$jsonPathFile,
                        self::$titleCase,
                        $caseName,
                        $responseContent
                    );
                } catch (Exception $e) {
                    $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
                }
            });
        } catch (Exception $e) {
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }

    /**
     * Test refund order with invalid bill
     */
    public function testRefundOrderInvalidBill(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderInvalidBill';
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the partner refund number and use invalid bill from JSON
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with invalid bill
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order with insufficient funds
     */
    public function testRefundOrderInsufficientFunds(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderInsufficientFunds';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with insufficient funds
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order with unauthorized access
     */
    public function testRefundOrderUnauthorized(): void
    {
        try {
            Util::withDelay(function () {
                $caseName = 'RefundOrderUnauthorized';
                // Create test orders for refund scenarios
                $partnerReferenceNo = self::$partnerReferenceNoPaid;

                $partnerRefundNo = $partnerReferenceNo;
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                // Set the correct partner reference numbers
                $requestData['originalPartnerReferenceNo'] = $partnerReferenceNo;
                $requestData['partnerRefundNo'] = $partnerRefundNo;
                $requestData['merchantId'] = getenv('MERCHANT_ID');
                // Create headers with invalid signature
                $headers = Util::getHeadersWithSignature(
                    'POST',
                    '/payment-gateway/v1.0/debit/refund.htm',
                    $requestData,
                    true,
                    false,
                    true
                );
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/refund.htm',
                        $headers,
                        $requestData
                    );
                    $this->fail('Expected ApiException for invalid signature but the API call succeeded');
                } catch (ApiException $e) {
                    $this->assertEquals(401, $e->getCode(), "Expected HTTP 401 Unauthorized for invalid signature, got {$e->getCode()}");
                    $responseContent = (string)$e->getResponseBody();
                    Assertion::assertFailResponse(
                        self::$jsonPathFile,
                        self::$titleCase,
                        $caseName,
                        $responseContent,
                        ['partnerReferenceNo' => $partnerReferenceNo]
                    );
                } catch (Exception $e) {
                    $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
                }
            });
        } catch (Exception $e) {
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }

    /**
     * Test refund order duplicate request
     */
    public function testRefundOrderDuplicateRequest(): void
    {
        Util::runWithRetry(function () {
            $caseName = 'RefundOrderDuplicateRequest';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::createPaidOrder();

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );
            try {
                // Make the API call
                self::$apiInstance->refundOrder($refundOrderRequestObj);

                     // Create and set the Money object for refund amount
                $refundAmount = new \Dana\PaymentGateway\v1\Model\Money();
                $refundAmount->setCurrency('IDR');  // Indonesian Rupiah
                $refundAmount->setValue('10000.00');  // Amount with 2 decimal places
                $refundOrderRequestObj->setRefundAmount($refundAmount);

                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);
                // Assert the API response
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        },
        3,  // 3 retries
        2000,  // 2 second initial delay
        [ApiException::class, Exception::class]);
    }

    /**
     * Test refund order timeout scenario
     */
    public function testRefundOrderTimeout(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderTimeout';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with timeout
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order merchant status abnormal scenario
     */
    public function testRefundOrderMerchantStatusAbnormal(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderMerchantStatusAbnormal';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should fail with merchant status abnormal
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order in progress scenario
     */
    public function testRefundOrderInProgress(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderInProgress';
            // Create test orders for refund scenarios
            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the API call
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should succeed with in progress status
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                // May succeed with in progress status or fail, check response
                $responseContent = (string)$e->getResponseBody();
                if (strpos($responseContent, '2025800') !== false) {
                    // Success case with in progress status
                    Assertion::assertResponse(
                        self::$jsonPathFile,
                        self::$titleCase,
                        $caseName,
                        $responseContent
                    );
                } else {
                    // Failure case
                    Assertion::assertFailResponse(
                        self::$jsonPathFile,
                        self::$titleCase,
                        $caseName,
                        $responseContent
                    );
                }
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }

    /**
     * Test refund order idempotent scenario
     */
    public function testRefundOrderIdempotent(): void
    {
        Util::withDelay(function () {
            $caseName = 'RefundOrderIdempotent';

            $partnerReferenceNo = self::$partnerReferenceNoPaid;

            $partnerRefundNo = $partnerReferenceNo;

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set the correct partner reference numbers (using specific values from JSON)
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['partnerRefundNo'] = $partnerRefundNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');

            // Create a RefundOrderRequest object from the JSON request data
            $refundOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\RefundOrderRequest',
            );

            try {
                // Make the first API call
                self::$apiInstance->refundOrder($refundOrderRequestObj);
                // Make the second API call (idempotent)
                $apiResponse = self::$apiInstance->refundOrder($refundOrderRequestObj);

                // Assert the API response - should succeed with idempotent response
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to call refund order API: ' . $e->getResponseBody());
            } catch (\Exception $e) {
                $this->fail('Failed to call refund order API: ' . $e->getMessage());
            }
        });
    }
}

