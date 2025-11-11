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

class TransferToBankTest extends TestCase
{
    private static $titleCase = 'TransferToBank';
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
    public function testDisbursementBankValidAccount(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankValidAccount';

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
                $transferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                // Make the API call
                $apiResponse = self::$apiInstance->transferToBank($transferToBankRequestObj);

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
    public function testDisbursementBankInsufficientFund(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankInsufficientFund';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToBankRequest object from the JSON request data
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    $this->fail('Expected ApiException for insufficient fund but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 403 Forbidden for insufficient fund (responseCode: 4034314)
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
     * Should give error response for missing mandatory field scenario
     */
    public function testDisbursementBankMissingMandatoryField(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankMissingMandatoryField';

                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Set a unique partner reference number
                $partnerReferenceNo = Util::generatePartnerReferenceNo();
                $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

                // Create a TransferToBankRequest object from the JSON request data
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    $this->fail('Expected ApiException for missing mandatory field but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for missing mandatory field (responseCode: 4004302)
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
    public function testDisbursementBankUnauthorizedSignature(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'DisbursementBankUnauthorizedSignature';
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
                    '/v1.0/emoney/transfer-bank.htm',
                    $requestData,
                    true,
                    false,
                    true  // invalid_signature set to true
                );
                
                // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/v1.0/emoney/transfer-bank.htm',
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
     * Should give error response for invalid field format scenario
     */
    public function testDisbursementBankInvalidFieldFormat(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankInvalidFieldFormat';

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
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    $this->fail('Expected ApiException for transaction not found but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for transaction not found
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for transaction not found, got {$e->getCode()}");

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
    public function testDisbursementBankInconsistentRequest(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankInconsistentRequest';

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
                $transferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the first API call
                    self::$apiInstance->transferToBank($transferToBankRequestObj);
                } catch (\Exception $e) {
                    $this->fail("Failed to call first transfer to Bank API: " . $e->getMessage());
                }

                // Sleep for a second to ensure the first request is processed
                sleep(1);

                try {
                    // Preparing request with the same partner reference number but different amount
                    $requestData = $jsonDict;
                    $requestData['amount']['value'] = '2.00';
                    $requestData['amount']['currency'] = 'IDR';

                    // The inconsistent field is changed here
                    $transferToBankRequestObjSecond = ObjectSerializer::deserialize(
                        $requestData,
                        'Dana\Disbursement\v1\Model\TransferToBankRequest',
                    );

                    $transferToBankRequestObjSecond->setPartnerReferenceNo($partnerReferenceNo);

                    // Make the second API call with the same reference number but different amount
                    self::$apiInstance->transferToBank($transferToBankRequestObjSecond);

                    $this->fail('Expected ApiException for inconsistent request but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 404 Not Found for inconsistent request
                    $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 NotFoundException for inconsistent request, got {$e->getCode()}");

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
     * Should give error response for unknown error scenario
     */
    public function testDisbursementBankUnknownError(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankUnknownError';

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
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    $this->fail('Expected ApiException for transaction not found but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 500 Internal Server Error for transaction not found
                    $this->assertEquals(500, $e->getCode(), "Expected HTTP 500 Internal Server Error for transaction not found, got {$e->getCode()}");

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
     * Should give error response for general error scenario
     */
    public function testDisbursementBankGeneralError(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankGeneralError';

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
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    $this->fail('Expected ApiException for transaction not found but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 500 Internal Server Error for transaction not found
                    $this->assertEquals(500, $e->getCode(), "Expected HTTP 500 Internal Server Error for transaction not found, got {$e->getCode()}");

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
     * Should give error response for inactive account scenario
     */
    public function testDisbursementBankInactiveAccount(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankInactiveAccount';

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
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    $this->fail('Expected ApiException for transaction not found but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 403 Forbidden for transaction not found
                    $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for transaction not found, got {$e->getCode()}");

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
     * Should give error response for suspected fraud scenario
     */
    public function testDisbursementBankSuspectedFraud(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankSuspectedFraud';

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
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    // Make the API call
                    self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    $this->fail('Expected ApiException for transaction not found but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 403 Forbidden for transaction not found
                    $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for transaction not found, got {$e->getCode()}");

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
     * Should give error response for in progress transaction scenario
     */
    public function testDisbursementBankValidAccountInProgress(): void
    {
        Util::withDelay(function () {
            $caseName = 'DisbursementBankValidAccountInProgress';

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
                $TransferToBankRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToBankRequest'
                );

                try {
                    $apiResponse = self::$apiInstance->transferToBank($TransferToBankRequestObj);

                    // Assert the response matches the expected data
                    Assertion::assertResponse(
                        self::$jsonPathFile,
                        self::$titleCase,
                        $caseName,
                        $apiResponse->__toString(),
                        ['partnerReferenceNo' => $partnerReferenceNo]
                    );
                } catch (ApiException $e) {
                $this->fail('Failed to inquire bank account: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should give error response for invalid mandatory field format scenario
     */
    public function testDisbursementBankInvalidMandatoryFieldFormat(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'DisbursementBankInvalidMandatoryFieldFormat';
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
                    '/v1.0/emoney/transfer-bank.htm',
                    $requestData,
                    true,
                    false,
                    true  // invalid_signature set to true
                );

                $headers['X-SIGNATURE'] = '';

                // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/v1.0/emoney/transfer-bank.htm',
                        $headers,
                        $requestData
                    );
                    
                    $this->fail('Expected ApiException for invalid signature but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for invalid signature
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for invalid signature, got {$e->getCode()}");

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
}