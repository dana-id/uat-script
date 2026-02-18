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

class DanaAccountInquiryTest extends TestCase
{
    private static $titleCase = 'DanaAccountInquiry';
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
     * Should successfully inquire DANA account with valid data
     */
    public function testInquiryCustomerValidData(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryCustomerValidData';

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

                // Create a DanaAccountInquiryRequest object from the JSON request data
                $danaAccountInquiryRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Disbursement\v1\Model\DanaAccountInquiryRequest'
                );

                // Make the API call
                $apiResponse = self::$apiInstance->danaAccountInquiry($danaAccountInquiryRequestObj);

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
                $this->fail('Failed to inquire DANA account: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should fail inquiry due to unauthorized signature
     */
    public function testInquiryCustomerUnauthorizedSignature(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'InquiryCustomerUnauthorizedSignature';
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
                    '/rest/v1.0/emoney/account-inquiry',
                    $requestData,
                    true,
                    false,
                    true  // invalid_signature set to true
                );
                
                // Make direct API call with invalid signature
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/rest/v1.0/emoney/account-inquiry',
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
     * Should fail inquiry due to frozen account
     */
    public function testInquiryCustomerFrozenAccount(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryCustomerFrozenAccount';

            // Get and prepare the request
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set a unique partner reference number
            $partnerReferenceNo = Util::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

            // Create a DanaAccountInquiryRequest object from the JSON request data
            $danaAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\DanaAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->danaAccountInquiry($danaAccountInquiryRequestObj);

                $this->fail('Expected ApiException for frozen account but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for frozen account
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
     * Should fail inquiry due to unregistered account
     */
    public function testInquiryCustomerUnregisteredAccount(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryCustomerUnregisteredAccount';

            // Get and prepare the request
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set a unique partner reference number
            $partnerReferenceNo = Util::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

            // Create a DanaAccountInquiryRequest object from the JSON request data
            $danaAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\DanaAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->danaAccountInquiry($danaAccountInquiryRequestObj);

                $this->fail('Expected ApiException for unregistered account but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for unregistered account
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for unregistered account, got {$e->getCode()}");

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
     * Should fail inquiry due to exceeded limit
     */
    public function testInquiryCustomerExceededLimit(): void
    {
        Util::withDelay(function () {
            $caseName = 'InquiryCustomerExceededLimit';

            // Get and prepare the request
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );

            // Set a unique partner reference number
            $partnerReferenceNo = Util::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;

            // Create a DanaAccountInquiryRequest object from the JSON request data
            $danaAccountInquiryRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Disbursement\v1\Model\DanaAccountInquiryRequest'
            );

            try {
                // Make the API call
                self::$apiInstance->danaAccountInquiry($danaAccountInquiryRequestObj);

                $this->fail('Expected ApiException for exceeded limit but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 403 Forbidden for exceeded limit
                $this->assertEquals(403, $e->getCode(), "Expected HTTP 403 Forbidden for exceeded limit, got {$e->getCode()}");

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