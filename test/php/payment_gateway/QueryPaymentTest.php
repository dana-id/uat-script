<?php

namespace DanaUat\PaymentGateway;

use PHPUnit\Framework\TestCase;
use DanaUat\PaymentGateway\PaymentUtil;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use DanaUat\PaymentGateway\Scripts\WebAutomation;
use Exception;

class QueryPaymentTest extends TestCase
{
    private static $titleCase = 'QueryPayment';
    private static $createOrderTitleCase = 'CreateOrder';
    private static $jsonPathFile = 'resource/request/components/PaymentGateway.json';
    private static $apiInstance;
    private static $orderReferenceNumber;
    private static $orderPaidReferenceNumber;
    private static $orderCanceledReferenceNumber;
    private static $userPin = "123321";
    private static $userPhoneNumber = "0811742234";

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

        // Create test orders for different statuses
        // Order in created status (INIT)
        $dataOrder = self::createOrder();
        self::$orderReferenceNumber = $dataOrder['partnerReferenceNo'];

        // Order in paid status (PAID) - using OtherWallet payment method with specific amount
        self::$orderPaidReferenceNumber = self::createTestOrderPaid();

        // Order in canceled status (CANCELLED)
        self::$orderCanceledReferenceNumber = Util::generatePartnerReferenceNo();
        self::createTestOrderCanceled();
    }

    /**
     * Create a test order for query payment tests
     */
    private static function createTestOrder($partnerReferenceNo)
    {
        $caseName = 'CreateOrderRedirect';
        
        // Get the request data from the JSON file
        $jsonDict = Util::getRequest(
            self::$jsonPathFile,
            self::$titleCase,
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
            // Print the response
            error_log("CreateOrder API response: " . $response->__toString());
        } catch (Exception $e) {
            throw new Exception("Failed to create test order: " . $e->getMessage());
        }
    }

    /**
     * Create a test order with status PAID for query payment tests
     */
    private static function createTestOrderPaid(): string 
    {
        $partnerReferenceNo = Util::generatePartnerReferenceNo();
        
        // Use the shared utility with a cache key specific to query payment tests
        return PaymentUtil::getOrCreatePaidOrder(
            self::$apiInstance,
            self::$jsonPathFile,
            self::$createOrderTitleCase,
            'query_test',
            $partnerReferenceNo
        );
    }

    /**
     * Create a test order and then cancel it to get CANCELLED status for query payment tests
     */
    private static function createTestOrderCanceled():string
    {
        // First create a regular order
        $dataOrder = self::createOrder();
        
        // Get the request data for canceling the order
        $jsonDict = Util::getRequest(
            self::$jsonPathFile,
            "CancelOrder",
            "CancelOrderValidScenario"
        );
        
        // Set the original partner reference number
        $jsonDict['originalPartnerReferenceNo'] = $dataOrder['partnerReferenceNo'];
        $jsonDict['merchantId'] = getenv('MERCHANT_ID');
        
        // Create a CancelOrderRequest object from the JSON request data
        $cancelOrderRequestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\PaymentGateway\v1\Model\CancelOrderRequest',
        );
        
        try {
            // Make the API call
            self::$apiInstance->cancelOrder($cancelOrderRequestObj);
        } catch (Exception $e) {
            throw new Exception("Failed to cancel test order: " . $e->getMessage());
        }

        return (string)$dataOrder['partnerReferenceNo'];
    }

    /**
     * Should query the payment with status created but not paid (INIT)
     */
    public function testQueryPaymentCreatedOrder(): void
    {
        Util::withDelay(function() {
            $caseName = 'QueryPaymentCreatedOrder';
            $partnerReferenceNo = self::$orderReferenceNumber;
            error_log("Testing QueryPayment with partnerReferenceNo: " . $partnerReferenceNo);
            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            
            // Set the correct partner reference number
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');
            
            // Create a QueryPaymentRequest object from the JSON request data
            $queryPaymentRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\QueryPaymentRequest',
            );
            error_log("QueryPayment request: " . json_encode($jsonDict));
            try {
                // Make the API call
                $apiResponse = self::$apiInstance->queryPayment($queryPaymentRequestObj);
                
                // Assert the API response
                Assertion::assertResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                    ['partnerReferenceNo' => $partnerReferenceNo]
                );
                
                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to call query payment API: ' . $e->getResponseBody());
            } catch (Exception $e) {
                $this->fail('Unknown error occurred: ' . $e->getMessage());
            }
        });
    }

    // /**
    //  * Should query the payment with status paid (PAID)
    //  */
    public function testQueryPaymentPaidOrder(): void
    {
        Util::withDelay(function() {
            $caseName = 'QueryPaymentPaidOrder';
            $partnerReferenceNo = self::$orderPaidReferenceNumber;
            
            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            
            // Set the correct partner reference number
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');
            
            // Create a QueryPaymentRequest object from the JSON request data
            $queryPaymentRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\QueryPaymentRequest',
            );
            
            try {
                // Make the API call
                $apiResponse = self::$apiInstance->queryPayment($queryPaymentRequestObj);
                
                // Log the response (equivalent to Python's print)
                error_log("Query payment response: " . $apiResponse->__toString());
                
                // Assert the API response
                Assertion::assertResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                    ['partnerReferenceNo' => $partnerReferenceNo]
                );
                
                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to call query payment API: ' . $e->getResponseBody());
            } catch (Exception $e) {
                $this->fail('Unknown error occurred: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should query the payment with status canceled (CANCELLED)
     */
    public function testQueryPaymentCanceledOrder(): void
    {
        $this->markTestSkipped('Skipping this test temporarily.');
        Util::withDelay(function() {
            $caseName = 'QueryPaymentCanceledOrder';
            $partnerReferenceNo = self::$orderCanceledReferenceNumber;
            
            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            
            // Set the correct partner reference number
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            $jsonDict['merchantId'] = getenv('MERCHANT_ID');
            
            // Create a QueryPaymentRequest object from the JSON request data
            $queryPaymentRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\QueryPaymentRequest',
            );
            
            try {
                // Make the API call
                $apiResponse = self::$apiInstance->queryPayment($queryPaymentRequestObj);
                
                // Assert the API response
                Assertion::assertResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                    ['partnerReferenceNo' => $partnerReferenceNo]
                );
                
                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to call query payment API: ' . $e->getResponseBody());
            } catch (Exception $e) {
                $this->fail('Unknown error occurred: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should fail when query uses invalid format (ex: X-TIMESTAMP header format not correct)
     */
    public function testQueryPaymentInvalidFieldFormat(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'QueryPaymentInvalidFormat';
                $partnerReferenceNo = self::$orderReferenceNumber;
                
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set the correct partner reference number
                $requestData['originalPartnerReferenceNo'] = $partnerReferenceNo;
                
                // Create headers with invalid timestamp format using our helper
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/payment-gateway/v1.0/debit/status.htm',
                    $requestData,
                    true,
                    true,  // with_timestamp
                    false, // invalid_signature
                    true   // invalid_timestamp
                );
                
                // Make direct API call with the headers containing invalid timestamp format
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm',
                        $headers,
                        $requestData
                    );
                    
                    $this->fail('Expected ApiException for invalid timestamp format but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for invalid format
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for invalid timestamp format, got {$e->getCode()}");

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
                    $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
                }
            });
        } catch (Exception $e) {            
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }

    /**
     * Should fail when query is missing mandatory field (ex: request without X-TIMESTAMP header)
     */
    public function testQueryPaymentInvalidMandatoryField(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'QueryPaymentInvalidMandatoryField';
                $partnerReferenceNo = self::$orderReferenceNumber;
                
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set the correct partner reference number
                $requestData['originalPartnerReferenceNo'] = $partnerReferenceNo;
                
                // Create headers without X-TIMESTAMP to trigger mandatory field error
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/payment-gateway/v1.0/debit/status.htm',
                    $requestData,
                    false,  // with_timestamp set to false
                    true,   // with_partner_id
                    false,  // invalid_signature
                    false   // invalid_timestamp
                );
                
                // Make direct API call with the headers missing X-TIMESTAMP
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm',
                        $headers,
                        $requestData
                    );
                    
                    $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for missing mandatory field
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for missing X-TIMESTAMP, got {$e->getCode()}");

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
                    $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
                }
            });
        } catch (Exception $e) {            
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }

    /**
     * Should fail when unauthorized due to invalid signature
     */
    public function testQueryPaymentUnauthorized(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'QueryPaymentUnauthorized';
                $partnerReferenceNo = self::$orderReferenceNumber;
                
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    'QueryPaymentCreatedOrder'
                );
                
                // Set the correct partner reference number
                $requestData['originalPartnerReferenceNo'] = $partnerReferenceNo;
                
                // Create headers with invalid signature to test authorization failure
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/payment-gateway/v1.0/debit/status.htm',
                    $requestData,
                    true,
                    false,
                    true  // invalid_signature set to true
                );
                
                // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm',
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
                    $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
                }
            });
        } catch (Exception $e) {            
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }
    
    /**
     * Should fail when transaction is not found
     */
    public function testQueryPaymentTransactionNotFound(): void
    {
        Util::withDelay(function() {
            $caseName = 'QueryPaymentTransactionNotFound';
            $partnerReferenceNo = self::$orderReferenceNumber . "_NOT_FOUND";
            
            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            
            // Modify the reference number to ensure it's not found
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            
            // Create a QueryPaymentRequest object from the JSON request data
            $queryPaymentRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\QueryPaymentRequest',
            );
            
            try {
                // Make the API call
                self::$apiInstance->queryPayment($queryPaymentRequestObj);
                
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
                    ['partnerReferenceNo' => $partnerReferenceNo]
                );
            } catch (Exception $e) {
                $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
            }
        });
    }

    /**
     * Should handle general server error
     */
    public function testQueryPaymentGeneralError(): void
    {
        Util::withDelay(function() {
            $caseName = 'QueryPaymentGeneralError';
            $partnerReferenceNo = self::$orderReferenceNumber;
            
            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            
            // Set the correct partner reference number
            $jsonDict['originalPartnerReferenceNo'] = $partnerReferenceNo;
            
            // Create a QueryPaymentRequest object from the JSON request data
            $queryPaymentRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\QueryPaymentRequest',
            );
            
            try {
                // Make the API call
                self::$apiInstance->queryPayment($queryPaymentRequestObj);
                
                $this->fail('Expected ApiException for general server error but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 500 or similar server error status code
                $this->assertTrue($e->getCode() >= 500, "Expected HTTP 500+ server error, got {$e->getCode()}");

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
                $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
            }
        });
    }

    public static function createOrder(): array
    {
        // Get the request data from the JSON file
        $jsonDict = Util::getRequest(
            self::$jsonPathFile,
            "CreateOrder",
            "CreateOrderRedirect"
        );

        // Set a unique partner reference number
        $partnerReferenceNo = Util::generatePartnerReferenceNo();
        $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

        // Create a CreateOrderByRedirectRequest object from the JSON request data
        $createOrderRequestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\PaymentGateway\v1\Model\CreateOrderByRedirectRequest',
        );

        $createOrderRequestObj->setPartnerReferenceNo($partnerReferenceNo);
        $apiResponse = self::$apiInstance->createOrder($createOrderRequestObj);
        
        $responseArray = json_decode($apiResponse->__toString(), true);
        return [
            'partnerReferenceNo' => $responseArray['partnerReferenceNo'] ?? '',
            'webRedirectUrl' => $responseArray['webRedirectUrl'] ?? ''
        ];
    }
}
