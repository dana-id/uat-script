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

class TransferToDanaInquiryStatusTest extends TestCase
{
    private static $titleCase = 'TransferToDanaInquiryStatus';
    private static $jsonPathFile = 'resource/request/components/Disbursement.json';
    private static $apiInstance;
    private static $merchantId;
    private static $originalPartnerReferencePaid;
    private static $originalPartnerReferenceFailed;

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

        // Create prerequisite disbursement transactions for status inquiry tests
        self::createDisbursementPaid();
        self::createDisbursementFailed();
    }

    /**
     * Helper function to create a successful disbursement for status testing
     */
    private static function createDisbursementPaid(): void
    {
        try {
            // Get the request data for a valid transfer
            $jsonDict = Util::getRequest(
                'resource/request/components/Disbursement.json',
                'TransferToDana',
                'TopUpCustomerValid'
            );

            // Generate unique partner reference number
            self::$originalPartnerReferencePaid = Util::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = self::$originalPartnerReferencePaid;

            // Create request object
            $transferToDanaRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\TransferToDanaRequest'
            );

            // Make the API call to create a successful transaction
            self::$apiInstance->transferToDana($transferToDanaRequestObj);
            
            echo "Shared paid order created with reference: " . self::$originalPartnerReferencePaid . "\n";
        } catch (Exception $e) {
            echo "Failed to create shared paid order: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Helper function to create a failed disbursement for status testing
     */
    private static function createDisbursementFailed(): void
    {
        try {
            // Get the request data for an amount limit exceeded transfer
            $jsonDict = Util::getRequest(
                'resource/request/components/Disbursement.json',
                'TransferToDana',
                'TopUpCustomerExceedAmountLimit'
            );

            // Generate unique partner reference number
            self::$originalPartnerReferenceFailed = Util::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = self::$originalPartnerReferenceFailed;

            // Create request object
            $transferToDanaRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\TransferToDanaRequest'
            );

            // Make the API call to create a failed transaction
            try {
                self::$apiInstance->transferToDana($transferToDanaRequestObj);
            } catch (ApiException $e) {
                // Expected to fail - this creates a failed transaction
                echo "Shared failed order created with reference: " . self::$originalPartnerReferenceFailed . "\n";
            }
        } catch (Exception $e) {
            echo "Failed to create shared failed order: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Should successfully inquire transfer to DANA status PAID
     */
    public function testInquiryTopUpStatusValidPaid(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryTopUpStatusValidPaid';

            try {
                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Use the original partner reference from the successful transaction
                $jsonDict['originalPartnerReferenceNo'] = self::$originalPartnerReferencePaid;

                // Create a TransferToDanaInquiryStatusRequest object from the JSON request data
                $inquiryStatusRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaInquiryStatusRequest'
                );

                // Make the API call
                $apiResponse = self::$apiInstance->transferToDanaInquiryStatus($inquiryStatusRequestObj);

                // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString(),
                    [
                        'originalPartnerReferenceNo' => self::$originalPartnerReferencePaid,
                        'originalReferenceNo' => $apiResponse->getOriginalReferenceNo()
                    ]
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to inquire transfer to DANA status: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should successfully inquire transfer to DANA status FAILED
     */
    public function testInquiryTopUpStatusValidFailed(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryTopUpStatusValidFail';

            try {
                // Get and prepare the request
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

                // Use the original partner reference from the failed transaction
                $jsonDict['originalPartnerReferenceNo'] = self::$originalPartnerReferenceFailed;

                // Create a TransferToDanaInquiryStatusRequest object from the JSON request data
                $inquiryStatusRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\TransferToDanaInquiryStatusRequest'
                );

                // Make the API call
                $apiResponse = self::$apiInstance->transferToDanaInquiryStatus($inquiryStatusRequestObj);

                // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString(),
                    [
                        'originalPartnerReferenceNo' => self::$originalPartnerReferenceFailed,
                        'originalReferenceNo' => $apiResponse->getOriginalReferenceNo()
                    ]
                );

                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to inquire transfer to DANA status: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should fail inquiry when input is invalid
     */
    public function testInquiryTopUpStatusInvalidFieldFormat(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryTopUpStatusInvalidFieldFormat';

            // Get and prepare the request
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Use the original partner reference from the successful transaction
            $jsonDict['originalPartnerReferenceNo'] = self::$originalPartnerReferencePaid;

            // Create a TransferToDanaInquiryStatusRequest object from the JSON request data
            $inquiryStatusRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\TransferToDanaInquiryStatusRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->transferToDanaInquiryStatus($inquiryStatusRequestObj);

                $this->fail('Expected ApiException for invalid field format but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 400 Bad Request for invalid field format
                $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for invalid field format, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();
                
                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName, 
                    $responseContent,
                    ['partnerReferenceNo' => self::$originalPartnerReferencePaid]
                );
            } catch (Exception $e) {
                $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
            }
        });
    }

    /**
     * Should fail inquiry when transaction not found
     */
    public function testInquiryTopUpStatusNotFoundTransaction(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryTopUpStatusNotFoundTransaction';

            // Get and prepare the request
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Use a non-existent partner reference
            $nonExistentRef = 'test123123';
            $jsonDict['originalPartnerReferenceNo'] = $nonExistentRef;

            // Create a TransferToDanaInquiryStatusRequest object from the JSON request data
            $inquiryStatusRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\TransferToDanaInquiryStatusRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->transferToDanaInquiryStatus($inquiryStatusRequestObj);

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
                    ['originalPartnerReferenceNo' => $nonExistentRef]
                );
            } catch (Exception $e) {
                $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
            }
        });
    }

    /**
     * Should fail inquiry when transaction is missing mandatory field
     */
    public function testInquiryTopUpStatusMissingMandatoryField(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'InquiryTopUpStatusMissingMandatoryField';
                
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Use the original partner reference from the successful transaction
                $requestData['originalPartnerReferenceNo'] = self::$originalPartnerReferencePaid;
                
                // Create headers without timestamp to test validation
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/rest/v1.0/emoney/topup-status',
                    $requestData,
                    false  // withTimestamp = false to test missing X-TIMESTAMP
                );
                
                // Make direct API call with missing timestamp
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/rest/v1.0/emoney/topup-status',
                        $headers,
                        $requestData
                    );
                    
                    $this->fail('Expected ApiException for missing mandatory field but the API call succeeded');
                } catch (ApiException $e) {
                    // We expect a 400 Bad Request for missing mandatory field
                    $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for missing mandatory field, got {$e->getCode()}");

                    // Get the response body from the exception
                    $responseContent = (string)$e->getResponseBody();
                    
                    // Use assertFailResponse to validate the error response
                    Assertion::assertFailResponse(
                        self::$jsonPathFile, 
                        self::$titleCase, 
                        $caseName, 
                        $responseContent,
                        ['partnerReferenceNo' => self::$originalPartnerReferencePaid]
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
     * Should fail inquiry when signature is unauthorized
     */
    public function testInquiryTopUpStatusUnauthorizedSignature(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'InquiryTopUpStatusUnauthorizedSignature';
                
                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile, 
                    self::$titleCase, 
                    $caseName
                );
                
                // Use the original partner reference from the successful transaction
                $requestData['originalPartnerReferenceNo'] = self::$originalPartnerReferencePaid;
                
                // Create headers with invalid signature to test authorization failure
                $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/rest/v1.0/emoney/topup-status',
                    $requestData,
                    true,
                    false,
                    true  // invalid_signature set to true
                );
                
                // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/rest/v1.0/emoney/topup-status',
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
                        ['partnerReferenceNo' => self::$originalPartnerReferencePaid]
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