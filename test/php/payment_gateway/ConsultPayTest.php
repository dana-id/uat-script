<?php

namespace DanaUat\PaymentGateway;

use Dana\Configuration;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\PaymentGateway\v1\Model\ConsultPayRequest;
use Dana\ObjectSerializer;
use Dana\Env;
use DanaUat\Helper\Util;
use DanaUat\Helper\Assertion;
use Dana\ApiException;
use PHPUnit\Framework\TestCase;
use Exception;

class ConsultPayTest extends TestCase
{
    private static $titleCase = 'ConsultPay';
    private static $jsonPathFile = 'resource/request/components/PaymentGateway.json';
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
        self::$apiInstance = new PaymentGatewayApi(null, $configuration);
        
        // Store merchantId for reuse
        self::$merchantId = getenv('MERCHANT_ID');
    }

    /**
     * Should give success response code and message and correct mandatory fields
     */
    public function testConsultPaySuccess(): void
    {
        Util::withDelay(function() {
            $caseName = 'ConsultPayBalancedSuccess';
            
            try {
                // Get the request data from the JSON file
                $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                
                // Set merchant ID
                $jsonDict['merchantId'] = self::$merchantId;
                
                // Create a ConsultPayRequest object from the JSON request data
                $consultPayRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\PaymentGateway\v1\Model\ConsultPayRequest'
                );
                
                // Make the API call
                $apiResponse = self::$apiInstance->consultPay($consultPayRequestObj);
                
                // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $apiResponse->__toString()
                );
                
                $this->assertTrue(true);
            } catch (ApiException $e) {
                $this->fail('Failed to consult pay: ' . $e->getMessage());
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }

    /**
     * Should give fail response code and message for invalid field format
     */
    public function testConsultPayInvalidFieldFormat(): void
    {
        Util::withDelay(function() {
            $caseName = 'ConsultPayBalancedInvalidFieldFormat';

            $jsonDict = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );

            // Set merchant ID
                $jsonDict['merchantId'] = '';
                
                // Create a ConsultPayRequest object from the JSON request data
                $consultPayRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\PaymentGateway\v1\Model\ConsultPayRequest'
                );

            try {                
                // Make the API call - this should fail due to invalid field format
                self::$apiInstance->consultPay($consultPayRequestObj);

            } catch (ApiException $e) {

                // Expected to fail, check if error matches expected response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $e->getResponseBody()
                );
            }
        });
    }

    /**
     * Should give fail response code and message for missing mandatory field
     */
    public function testConsultPayInvalidMandatoryField(): void
    {
        try {
            Util::withDelay(function() {
                $caseName = 'ConsultPayBalancedInvalidMandatoryField';

                // Get the request data from the JSON file
                $requestData = Util::getRequest(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName
                );
                
                // Set merchant ID
                $requestData['merchantId'] = self::$merchantId;
                
                // Create headers without timestamp to test validation
                $headers = Util::getHeadersWithSignature(
                    'POST',
                    '/v1.0/payment-gateway/consult-pay.htm',
                    $requestData,
                    false,  // withTimestamp = false to trigger mandatory field error
                    false   // invalidSignature = false
                );

                // Use the executeApiRequest helper method to make manual API call
                try {
                    Util::executeApiRequest(
                        'POST',
                        'https://api.sandbox.dana.id/v1.0/payment-gateway/consult-pay.htm',
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
                        $responseContent
                    );
                } catch (Exception $e) {
                    throw $e;
                }
            });
        } catch (Exception $e) {
            $this->fail("Unexpected exception: " . $e->getMessage());
        }
    }
}
