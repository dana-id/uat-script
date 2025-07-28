<?php

namespace DanaUat\Widget\v1;

use PHPUnit\Framework\TestCase;
use Dana\Widget\v1\Api\WidgetApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use DanaUat\Widget\PaymentUtil;
use Exception;

class PaymentTest extends TestCase
{
    private static $titleCase = 'Payment';
    private static $jsonPathFileWidget = 'resource/request/components/Widget.json';
    private static $apiInstanceWidget;
    private static $configuration;
    private static $merchantId;
    private static $sharedOriginalPartnerReference;

    public static function setUpBeforeClass(): void
    {
        // Set up configuration with authentication settings
        self::$configuration = new Configuration();
        
        // The Configuration constructor automatically loads values from environment variables
        // But we can manually set them if needed
        self::$configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        self::$configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        self::$configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        self::$configuration->setApiKey('ENV', Env::SANDBOX);

        self::$merchantId = getenv('MERCHANT_ID');
        self::$apiInstanceWidget = new WidgetApi(null, self::$configuration);

        $dataOrder = PaymentUtil::createPaymentWidget("PaymentSuccess");
        self::$sharedOriginalPartnerReference = (string)$dataOrder["partnerReferenceNo"];
    }

    /**
     * @skip
     * @testdox Should give success response for payment scenario
     */
    public function testPaymentSuccess(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            $apiResponse = self::$apiInstanceWidget->widgetPayment($requestObj);
            // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                    []
                );
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for invalid payment scenario
     */
    public function testPaymentInvalidFieldFormat(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailInvalidFormat';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            // Assert the response matches the expected data
            try {
                self::$apiInstanceWidget->widgetPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    []
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for missing or invalid mandatory field
     */
    public function testPaymentFailMissingOrInvalidMandatoryField(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailMissingOrInvalidMandatoryField';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();

            // Create headers without timestamp to test validation
            $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/rest/redirection/v1.0/debit/payment-host-to-host.htm',
                    $jsonDict,
                    false
                );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                Util::executeApiRequest(
                    'POST',
                    'https://api.sandbox.dana.id/rest/redirection/v1.0/debit/payment-host-to-host',
                    $headers,
                    $jsonDict
                );

                $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    []
                );
            }  
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for invalid signature
     */
    public function testPaymentInvalidSignature(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailInvalidSignature';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();

            // Create headers without timestamp to test validation
            $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/rest/redirection/v1.0/debit/payment-host-to-host.htm',
                    $jsonDict,
                    true,
                    false,
                    true
                );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                Util::executeApiRequest(
                    'POST',
                    'https://api.sandbox.dana.id/rest/redirection/v1.0/debit/payment-host-to-host',
                    $headers,
                    $jsonDict
                );

                $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
            } catch (Exception $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    []
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for timeout
     */
    public function testPaymentTimeout(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailTimeout';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                self::$apiInstanceWidget->widgetPayment($requestObj);
            } catch (ApiException $e) {
                $this->assertEquals(504, $e->getCode(), "Gateway Time-out");
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for idempotent
     */
    public function testPaymentIdempotent(): void
    {
        $this->markTestSkipped('Return error 500.');
        Util::withDelay(function() {
            $caseName = 'PaymentFailIdempotent';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";
            self::$apiInstanceWidget->widgetPayment($requestObj);

            try {
                self::$apiInstanceWidget->widgetPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    []
                );
            }
        });
    }
}
