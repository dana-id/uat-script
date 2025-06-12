<?php

namespace DanaUat\PaymentGateway;

use Dana\Configuration;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\PaymentGateway\v1\Model\ConsultPayRequest;
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
        Util::withDelay(function() {
            $caseName = 'ConsultPayBalancedSuccess';
            
            // Create a ConsultPayRequest object from the JSON request data
            $consultPayRequestObj = Util::createModelFromJsonRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                ConsultPayRequest::class
            );
            
            // Make the API call
            $apiResponse = self::$apiInstance->consultPay($consultPayRequestObj);
            
            // Assert the API response
            Assertion::assertResponse(self::$jsonPathFile, self::$titleCase, $caseName, $apiResponse->__toString());
            
            $this->assertTrue(true);
        });
    }

    /**
     * Should give fail response code and message and correct mandatory fields
     */
    public function testConsultPayInvalidFieldFormat(): void
    {
        Util::withDelay(function() {
            $caseName = 'ConsultPayBalancedInvalidFieldFormat';
            
            // Create a ConsultPayRequest object from the JSON request data
            $consultPayRequestObj = Util::createModelFromJsonRequest(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                ConsultPayRequest::class
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
