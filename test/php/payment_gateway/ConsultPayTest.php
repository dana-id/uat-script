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

class ConsultPayTest extends TestCase
{
    private static $titleCase = 'ConsultPay';
    private static $jsonPathFile = 'resource/request/components/PaymentGateway.json';
    private static $apiInstance;

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
    }

    /**
     * Should give success response code and message and correct mandatory fields
     */
    public function testConsultPaySuccess(): void
    {
        $this->markTestSkipped('Skipping consult pay success test');
        Util::withDelay(function() {
            $caseName = 'ConsultPayBalancedSuccess';
            
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            
            $consultPayRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\ConsultPayRequest'
            );
            
            // Make the API call
            $apiResponse = self::$apiInstance->consultPay($consultPayRequestObj);
            
            // Parse the response JSON to check the paymentInfos array
            $responseJson = json_decode($apiResponse->__toString(), true);
            
            // Check if response code and message are successful
            $this->assertEquals('2005700', $responseJson['responseCode'], 'Expected success response code');
            $this->assertEquals('Successful', $responseJson['responseMessage'], 'Expected success response message');
            
            // Only check if paymentInfos array has at least one item
            $this->assertArrayHasKey('paymentInfos', $responseJson, 'Expected paymentInfos array in response');
            $this->assertIsArray($responseJson['paymentInfos'], 'Expected paymentInfos to be an array');
            $this->assertGreaterThan(0, count($responseJson['paymentInfos']), 'Expected at least one payment info item');
        });
    }

    /**
     * Should give fail response code and message and correct mandatory fields
     */
    public function testConsultPayInvalidFieldFormat(): void
    {
        $this->markTestSkipped('Skipping consult pay success test');
        Util::withDelay(function() {
            $caseName = 'ConsultPayBalancedInvalidFieldFormat';

            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName
            );
            
            // Create a ConsultPayRequest object from the JSON request data
            $consultPayRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\ConsultPayRequest'
            );
            
            try {
                // Make the API call
                self::$apiInstance->consultPay($consultPayRequestObj);
                $this->fail('Expected BadRequestException was not thrown');
            } catch (ApiException $e) {
                // Assert the API response
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                
                $this->assertTrue(true);
            }
        });
    }
}
