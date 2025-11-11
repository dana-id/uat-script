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

class BankAccountInquiryTest extends TestCase
{
    private static $titleCase = 'BankAccountInquiry';
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

        // Create DisbursementApi instance directly with configuration
        self::$apiInstance = new ApiDisbursementApi(null, $configuration);

        // Store merchantId for reuse
        self::$merchantId = getenv('MERCHANT_ID');
    }

    /**
     * Should successfully inquire bank account with valid data and amount
     */
    public function testInquiryBankAccountValidDataAmount(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryBankAccountValidDataAmount';

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
                $bankAccountInquiryRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\BankAccountInquiryRequest'
                );

                // Make the API call
                $apiResponse = self::$apiInstance->bankAccountInquiry($bankAccountInquiryRequestObj);

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
     * Should fail to inquire bank account with insufficient fund
     */
    public function testInquiryBankAccountInsufficientFund(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryBankAccountInsufficientFund';

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
            $bankAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\BankAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->bankAccountInquiry($bankAccountInquiryRequestObj);

                $this->fail('Expected ApiException for insufficient fund but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for insufficient fund (responseCode: 4034214)
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
     * Should fail to inquire bank account with unauthorized signature
     */
    public function testInquiryBankAccountUnauthorizedSignature(): void
    {
       try {
            Util::withDelay(function() {
                $caseName = 'InquiryBankAccountUnauthorizedSignature';
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
                    '/v1.0/emoney/bank-account-inquiry.htm',
                    $requestData,
                    true,
                    false,
                    true  // invalid_signature set to true
                );
                
                // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/v1.0/emoney/bank-account-inquiry.htm',
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
     * Should fail to inquire bank account with inactive account
     */
    public function testInquiryBankAccountInactiveAccount(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryBankAccountInactiveAccount';

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
            $bankAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\BankAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->bankAccountInquiry($bankAccountInquiryRequestObj);

                $this->fail('Expected ApiException for inactive account but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for inactive account (responseCode: 4034218)
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for inactive account, got {$e->getCode()}");

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
     * Should fail to inquire bank account with invalid merchant
     */
    public function testInquiryBankAccountInvalidMerchant(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryBankAccountInvalidMerchant';

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
            $bankAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\BankAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->bankAccountInquiry($bankAccountInquiryRequestObj);

                $this->fail('Expected ApiException for invalid merchant but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 404 Not Found for invalid merchant (responseCode: 4044208)
                $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 Not Found for invalid merchant, got {$e->getCode()}");

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
     * Should fail to inquire bank account with invalid card
     */
    public function testInquiryBankAccountInvalidCard(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryBankAccountInvalidCard';

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
            $bankAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\BankAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->bankAccountInquiry($bankAccountInquiryRequestObj);

                $this->fail('Expected ApiException for invalid card but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 404 Not Found for invalid card (responseCode: 4044211)
                $this->assertEquals(404, $e->getCode(), "Expected HTTP 404 Not Found for invalid card, got {$e->getCode()}");

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
     * Should fail to inquire bank account with invalid field format
     */
    public function testInquiryBankAccountInvalidFieldFormat(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryBankAccountInvalidFieldFormat';

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
            $bankAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\BankAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->bankAccountInquiry($bankAccountInquiryRequestObj);

                $this->fail('Expected ApiException for invalid field format but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 400 Bad Request for invalid field format (responseCode: 4004201)
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
     * Should fail to inquire bank account with missing mandatory field
     */
    public function testInquiryBankAccountMissingMandatoryField(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryBankAccountMissingMandatoryField';

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
            $bankAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\BankAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->bankAccountInquiry($bankAccountInquiryRequestObj);

                $this->fail('Expected ApiException for missing mandatory field but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 400 Bad Request for missing mandatory field (responseCode: 4004202)
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
}