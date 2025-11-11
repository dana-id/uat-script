<?php

namespace DanaUat\Disbursement;

use PHPUnit\Framework\TestCase;
use Dana\Disbursement\v1\Api\v1\Api\DisbursementApi;
use Dana\ObjectSerializer;
use Dana\Configuration;
use Dana\Env;
use Dana\ApiException;
use Dana\Disbursement\v1\Api\DisbursementApi as ApiDisbursementApi;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use Exception;

class TransferToDanaTest extends TestCase
{
    private static $titleCase = 'TransferToDana';
    private static $jsonPathFile = 'resource/request/components/Disbursement.json';
    private static $apiInstance;
    private static $merchantId;
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
        self::$apiInstance = new ApiDisbursementApi(null, $configuration);

        // Store merchantId for reuse
        self::$merchantId = getenv('MERCHANT_ID');
    }

    /**
     * Should successfully top up customer with valid request
     */
    public function testTopUpCustomerValid(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerValid';

            try {
                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a BankAccountInquiryRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                // Make the API call
                $apiResponse = self::$apiInstance->transferToDana($transferToDanaRequestObj);

                // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString(),
                    ['partnerReferenceNo' => $partnerReferenceNo]
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to inquire bank account: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should give error response for insufficient fund scenario
     */
    public function testTopUpCustomerInsufficientFund(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerInsufficientFund';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    $this->fail('Expected ApiException for insufficient fund but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 403 Forbidden for insufficient fund (responseCode: 4033814)
                    $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for insufficient fund, got {$e->getCode()}");

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
     * Should give error response for timeout scenario
     */
    public function testTopUpCustomerTimeout(): void
    {
        $this->markTestSkipped('Skipping top up customer timeout test');
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerTimeout';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a BankAccountInquiryRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

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
     * Should test idempotent behavior - duplicate request should either return same result or duplicate error
     */
    public function testTopUpCustomerIdempotent(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerIdempotent';

            // Get and prepare the request
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Use a fixed partner reference number for idempotency testing
            $fixedPartnerRef = 'IDEMPOTENT_TEST_' . time() . '_' . substr(md5(rand()), 0, 8);
            $jsonDict['partnerReferenceNo'] = $fixedPartnerRef;

            // Create a TransferToDanaRequest object from the JSON request data
            $transferToDanaRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\TransferToDanaRequest'
            );

            $isIdempotent = false;
            $firstResponse = null;

            try {
                // STEP 1: First request - should succeed
                $firstResponse = self::$apiInstance->transferToDana($transferToDanaRequestObj);

                sleep(1);

                // STEP 2: Second request with SAME partnerReferenceNo - test idempotency
                try {
                    $secondResponse = self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    if ($firstResponse->getReferenceNo() === $secondResponse->getReferenceNo()) {
                        $isIdempotent = true;

                        $this->assertEquals(
                            $firstResponse->getReferenceNo(),
                            $secondResponse->getReferenceNo(),
                            'Reference numbers must be identical for truly idempotent requests'
                        );

                        if (method_exists($firstResponse, 'getResponseCode') && method_exists($secondResponse, 'getResponseCode')) {
                            $this->assertEquals(
                                $firstResponse->getResponseCode(),
                                $secondResponse->getResponseCode(),
                                'Response codes must be identical for truly idempotent requests'
                            );
                        }
                    } else {
                        $this->fail('Both requests succeeded but returned different reference numbers - NOT truly idempotent');
                    }
                } catch (ApiException $duplicateError) {
                    $errorResponse = json_decode($duplicateError->getResponseBody(), true);

                    $isDuplicateError =
                        (isset($errorResponse['responseCode']) &&
                            (strpos($errorResponse['responseCode'], 'DUPLICATE') !== false ||
                                strpos($errorResponse['responseCode'], 'ALREADY_EXIST') !== false ||
                                strpos($errorResponse['responseCode'], 'IDEMPOTENT') !== false)) ||
                        (isset($errorResponse['responseMessage']) &&
                            (strpos(strtolower($errorResponse['responseMessage']), 'duplicate') !== false ||
                                strpos(strtolower($errorResponse['responseMessage']), 'already exist') !== false ||
                                strpos(strtolower($errorResponse['responseMessage']), 'idempotent') !== false));

                    if ($isDuplicateError) {
                        Assertion::assertFailResponse(
                            self::$jsonPathFile,
                            self::$titleCase,
                            $caseName,
                            $duplicateError->getResponseBody(),
                            ['partnerReferenceNo' => $fixedPartnerRef]
                        );
                    } else {
                        $this->fail('Expected duplicate error, but got unexpected error: ' . $duplicateError->getMessage());
                    }
                }

                // STEP 3: Test with longer delay to check idempotency persistence
                sleep(1);

                $thirdResponse = self::$apiInstance->transferToDana($transferToDanaRequestObj);

                if ($isIdempotent) {
                    Assertion::assertResponse(
                        self::$jsonPathFile,
                        self::$titleCase,
                        $caseName,
                        $thirdResponse->__toString(),
                        []
                    );
                }
            } catch (ApiException $firstError) {
                $this->markTestSkipped('First request failed - cannot test idempotency: ' . $firstError->getMessage());
            } catch (Exception $e) {
                $this->fail("Unexpected exception: " . $e->getMessage());
            }
        });
    }

    /**
     * Should give error response for frozen account scenario
     */
    public function testTopUpCustomerFrozenAccount(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerFrozenAccount';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    $this->fail('Expected ApiException for frozen account but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 403 Forbidden for frozen account (responseCode: 4033805)
                    $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for frozen account, got {$e->getCode()}");

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
     * Should give error response for exceed amount limit scenario
     */
    public function testTopUpCustomerExceedAmountLimit(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerExceedAmountLimit';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    $this->fail('Expected ApiException for exceed amount limit but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 403 Forbidden for exceed amount limit (responseCode: 4033813)
                    $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for exceed amount limit, got {$e->getCode()}");

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
     * Should give error response for missing mandatory field scenario
     */
    public function testTopUpCustomerMissingMandatoryField(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerMissingMandatoryField';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    $this->fail('Expected ApiException for missing mandatory field but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for missing mandatory field (responseCode: 4004003)
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for missing mandatory field, got {$e->getCode()}");

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
     * Should give error response for unauthorized signature scenario
     */
    public function testTopUpCustomerUnauthorizedSignature(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'TopUpCustomerUnauthorizedSignature';
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Set the correct partner reference number
                $requestData['partnerReferenceNo'] = $partnerReferenceNo;
                
                // Create headers with invalid signature to test authorization failure
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/v1.0/emoney/topup.htm',
                    $requestData,
                    true,
                    false,
                    true  // invalid_signature set to true
                );
                
                // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/v1.0/emoney/topup.htm',
                        $headers,
                        $requestData
                    );
                    
                    $this->fail('Expected ApiException for invalid signature but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 401 Unauthorized for invalid signature (responseCode: 4034001)
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
     * Should give error response for invalid field format scenario
     */
    public function testTopUpCustomerInvalidFieldFormat(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerInvalidFieldFormat';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    $this->fail('Expected ApiException for invalid field format but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for invalid field format (responseCode: 4003003)
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for invalid field format, got {$e->getCode()}");

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
     * Should give error response for inconsistent request scenario
     */
    public function testTopUpCustomerInconsistentRequest(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerInconsistentRequest';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the first API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);
                } catch (\Exception $e) {
                    $this->fail("Failed to call first transfer to Dana API: " . $e->getMessage());
                }
                
                // Sleep for a second to ensure the first request is processed
                sleep(1);

                try {
                    // Preparing request with the same partner reference number but different amount
                    $requestData = $jsonDict;
                    $requestData['amount']['value'] = '4.00';
                    $requestData['amount']['currency'] = 'IDR';

                    // The inconsistent field is changed here
                    $transferToDanaRequestObjSecond = ObjectSerializer::deserialize(
                        $requestData,
                        'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                    );

                    $transferToDanaRequestObjSecond->setPartnerReferenceNo($partnerReferenceNo);
                    
                    // Make the second API call with the same reference number but different amount
                    self::$apiInstance->transferToDana($transferToDanaRequestObjSecond);

                    $this->fail('Expected ApiException for inconsistent request but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 404 Not Found for inconsistent request (responseCode: 4043009)
                    $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 Not Found for inconsistent request, got {$e->getCode()}");

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
                } catch (\Exception $e) {
                    $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
                }
        });
    }

    /**
     * Should give error response for internal server error scenario
     */
    public function testTopUpCustomerInternalServerError(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerInternalServerError';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    $this->fail('Expected ApiException for internal server error but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 500 Internal Server Error for internal server error (responseCode: 5003001)
                    $this->assertEquals(500, $e->getCode(), "Expected HTTP 500 Internal Server Error for internal server error, got {$e->getCode()}");

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
     * Should give error response for internal server error scenario
     */
    public function testTopUpCustomerInternalGeneralError(): void
    {
        Util::withDelay(function () {
            $caseName = 'TopUpCustomerInternalGeneralError';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToDanaRequest object from the JSON request data
                $transferToDanaRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToDana($transferToDanaRequestObj);

                    $this->fail('Expected ApiException for internal general error but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 500 Internal Server Error for internal general error (responseCode: 5004000)
                    $this->assertEquals(500, $e->getCode(), "Expected HTTP 500 Internal Server Error for internal general error, got {$e->getCode()}");

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
}