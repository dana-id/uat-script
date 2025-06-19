<?php

namespace DanaUat\PaymentGateway;

use PHPUnit\Framework\TestCase;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\Configuration;
use Dana\PaymentGateway\v1\Model\CreateOrderByApiRequest;
use Dana\PaymentGateway\v1\Model\CancelOrderRequest;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use Exception;

class CancelOrderTest extends TestCase
{
    private static $titleCase = 'CancelOrder';
    private static $jsonPathFile = 'resource/request/components/PaymentGateway.json';
    private static $apiInstance;
    private static $sharedOriginalPartnerReference;
    private static $merchantId;

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
        
        // Store merchantId for reuse
        self::$merchantId = getenv('MERCHANT_ID');
        
        // Create shared order for testing
        try {
            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                'CreateOrder',
                'CreateOrderApi'
            );
            
            // Set a unique partner reference number
            self::$sharedOriginalPartnerReference = self::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;
            $jsonDict['merchantId'] = self::$merchantId;
            
            // Create a CreateOrderByApiRequest object from the JSON request data
            $createOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\CreateOrderByApiRequest'
            );

            $createOrderRequestObj->setPartnerReferenceNo(self::$sharedOriginalPartnerReference);
            
            // Make the API call
            $createOrderResponse = self::$apiInstance->createOrder($createOrderRequestObj);

            echo "Create Order Response: " . $createOrderResponse->__toString() . "\n";
            
            // Add a delay to ensure order is processed in the system
            sleep(2);
            
            echo "Created test order with reference: " . self::$sharedOriginalPartnerReference . "\n";
        } catch (Exception $e) {
            echo "Failed to create shared order - tests cannot continue: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Should successfully cancel an order
     */
    public function testCancelOrderValidScenario(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderValidScenario';
            
            try {
                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                $jsonDict['originalPartnerReferenceNo'] = self::$sharedOriginalPartnerReference;
                
                // Create a CancelOrderRequest object from the JSON request data
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                $apiResponse = self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
                
                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to cancel order: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should cancel an order in progress
     */
    public function testCancelOrderInProgress(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderInProgress';
            
            try {
                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Create a CancelOrderRequest object from the JSON request data
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                $apiResponse = self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                    ['partnerReferenceNo' => '2025700']
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
     * Should fail when user status is abnormal
     */
    public function testCancelOrderUserStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderUserStatusAbnormal';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Create a CancelOrderRequest object from the JSON request data
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for abnormal user status but the API call succeeded');
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
     * Should fail with not found when merchant status is abnormal
     */
    public function testCancelOrderMerchantStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderMerchantStatusAbnormal';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Create a CancelOrderRequest object from the JSON request data
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for abnormal merchant status but the API call succeeded');
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
     * Should fail when mandatory field is missing (ex: X-TIMESTAMP in header)
     */
    public function testCancelOrderInvalidMandatoryField(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'CancelOrderInvalidMandatoryField';
                
                // Generate a unique partner reference number
                $partnerReferenceNo = $this->generatePartnerReferenceNo();
                
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                $requestData['originalPartnerReferenceNo'] = $partnerReferenceNo;
                
                // Create headers without timestamp to test validation
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/payment-gateway/v1.0/debit/cancel.htm',
                    $requestData,
                    false
                );
                
                // Use the executeApiRequest helper method with throwOnError=true
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm',
                        $headers,
                        $requestData
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
                        $responseContent,
                        ['partnerReferenceNo' => $partnerReferenceNo]
                    );
                } catch (Exception $e) {
                    throw $e;
                }
            });
        } catch (Exception $e) {            
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }

    /**
     * Should fail when transaction not found
     */
    public function testCancelOrderTransactionNotFound(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderTransactionNotFound';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Append _NOT_FOUND to ensure it doesn't exist, same pattern as in TS test
                $requestData['originalPartnerReferenceNo'] = self::$sharedOriginalPartnerReference . "_NOT_FOUND";
                
                // Create a CancelOrderRequest object from the JSON request data
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );

                $cancelOrderRequestObj['originalPartnerReferenceNo'] = self::$sharedOriginalPartnerReference . "_NOT_FOUND";
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for transaction not found but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 404 Not Found for transaction not found
                $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 Not Found for transaction not found, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $responseContent,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

        /**
     * Should fail to cancel the order when transaction is expired
     */
    public function testCancelOrderWithExpiredTransaction(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderTransactionExpired';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set a known expired transaction partner reference number
                $requestData['partnerReferenceNo'] = '4035700';
                
                // Create the request object
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for expired transaction but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for expired transaction
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for expired transaction, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $responseContent,
                    ['partnerReferenceNo' => '4035700']
                );
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

        /**
     * Should fail to cancel the order when agreement is not allowed
     */
    public function testCancelOrderWithAgreementNotAllowed(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderNotAllowed';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set a known reference number for agreement not allowed
                $requestData['partnerReferenceNo'] = '4035715';
                
                // Create the request object
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for agreement not allowed but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for agreement not allowed
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for agreement not allowed, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $responseContent,
                    ['partnerReferenceNo' => '4035715']
                );
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

        /**
     * Should fail to cancel the order when account status is abnormal
     */
    public function testCancelOrderWithAccountStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderAccountStatusAbnormal';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set a known reference number for abnormal account status
                $requestData['partnerReferenceNo'] = '4035705';
                
                // Create the request object
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for abnormal account status but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for abnormal account status
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for abnormal account status, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $responseContent,
                    ['partnerReferenceNo' => '4035705']
                );
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

        /**
     * Should fail to cancel the order when funds are insufficient
     */
    public function testCancelOrderWithInsufficientFunds(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderInsufficientFunds';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set a known reference number for insufficient funds
                $requestData['partnerReferenceNo'] = '4035714';
                
                // Create the request object
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for insufficient funds but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for insufficient funds
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for insufficient funds, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $responseContent,
                    ['partnerReferenceNo' => '4035714']
                );
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should fail when authorization fails (ex: wrong X-SIGNATURE)
     */
    public function testCancelOrderUnauthorized(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'CancelOrderUnauthorized';
                
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Generate a unique partner reference number
                $partnerReferenceNo = $this->generatePartnerReferenceNo();
                $requestData['originalPartnerReferenceNo'] = $partnerReferenceNo;
                
                // Create headers with invalid signature to test authorization failure
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/payment-gateway/v1.0/debit/cancel.htm',
                    $requestData,
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
                        $requestData
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
                        $responseContent,
                        ['partnerReferenceNo' => $partnerReferenceNo]
                    );
                } catch (Exception $e) {
                    throw $e;
                }
            });
        } catch (Exception $e) {            
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }
    
    /**
     * Should fail to cancel the order due to request timeout
     */
    public function testCancelOrderTimeout(): void
    {
        Util::withDelay(function() {
            $caseName = 'CancelOrderRequestTimeout';
            
            try {
                // Get and prepare the request
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set a known reference number for request timeout
                $requestData['partnerReferenceNo'] = '5005701';
                
                // Create the request object
                $cancelOrderRequestObj = ObjectSerializer::deserialize(
                    $requestData,
                    'Dana\PaymentGateway\v1\Model\CancelOrderRequest'
                );
                
                // Make the API call
                self::$apiInstance->cancelOrder($cancelOrderRequestObj);
                
                $this->fail('Expected ApiException for request timeout but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 500 Service Exception for request timeout
                $this->assertEquals(500, $e->getCode(), "Expected HTTP 500 Service Exception for request timeout, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $responseContent,
                    ['partnerReferenceNo' => '5005701']
                );
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }
}
