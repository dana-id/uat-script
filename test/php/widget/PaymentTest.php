<?php

namespace DanaUat\IPG\v1;

use PHPUnit\Framework\TestCase;
use Dana\IPG\v1\Api\IPGApi;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use Exception;

class PaymentTest extends TestCase
{
    private static $titleCase = 'Payment';
    private static $jsonPathFilePaymentGateway = 'resource/request/components/PaymentGateway.json';
    private static $jsonPathFileWidget = 'resource/request/components/Widget.json';
    private static $apiInstanceWidget;
    private static $configuration;
    private static $merchantId;
    private static $apiInstancePaymentGatewayAPI;
    private static $sharedOriginalPartnerReference;


    private static function generatePartnerReferenceNo(): string
    {
        // Generate a UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

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

        self::$apiInstancePaymentGatewayAPI = new PaymentGatewayApi(null, self::$configuration);
        self::$apiInstanceWidget = new IPGApi(null, self::$configuration);
    }

    protected function setUp(): void
    {
        // Store merchantId for reuse
        self::$merchantId = getenv('MERCHANT_ID');
        echo "Using Merchant ID: " . self::$merchantId . "\n";
        
        // Create shared order for testing
        try {
            // Get the request data from the JSON file
            $jsonDict = Util::getRequest(
                self::$jsonPathFilePaymentGateway,
                'CreateOrder',
                'CreateOrderApi'
            );
            
            // Set a unique partner reference number
            self::$sharedOriginalPartnerReference = self::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;
            $jsonDict['merchantId'] = self::$merchantId;
            
            // Create a CreateOrderByApiRequest object from the JSON request data
            $createOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\CreateOrderByApiRequest'
            );

            $createOrderRequestObj->setPartnerReferenceNo(self::$sharedOriginalPartnerReference);
            
            // Make the API call
            $createOrderResponse = self::$apiInstancePaymentGatewayAPI->createOrder($createOrderRequestObj);
            
            // Add a delay to ensure order is processed in the system
            sleep(2);
            
            echo "Created test order with reference: " . self::$sharedOriginalPartnerReference . "\n";
        } catch (Exception $e) {
            echo "Failed to create shared order - tests cannot continue: " . $e->getMessage() . "\n";
        }
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
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            $apiResponse = self::$apiInstanceWidget->ipgPayment($requestObj);
            // Assert the response matches the expected data
                Assertion::assertResponse(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $apiResponse->__toString(),
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
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
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            // Assert the response matches the expected data
            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
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
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            // Create headers without timestamp to test validation
            $headers = Util::getHeadersWithSignature(
                    'POST', 
                    '/rest/redirection/v1.0/debit/payment-host-to-host.htm',
                    $jsonDict,
                    false
                );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
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
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
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
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

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
                'Dana\IPG\v1\Model\IPGPaymentRequest'
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
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for general error
     */
    public function testPaymentGeneralError(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailGeneralError';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            // Assert the response matches the expected data
            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for transaction not permitted
     */
    public function testPaymentTransactionNotPermitted(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailTransactionNotPermitted';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for merchant not exist or status abnormal
     */
    public function testPaymentMerchantNotExistOrStatusAbnormal(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailMerchantNotExistOrStatusAbnormal';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for inconsistent request
     */
    public function testPaymentInconsistentRequest(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailInconsistentRequest';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            // Assert the response matches the expected data
            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for internal server error
     */
    public function testPaymentInternalServerError(): void
    {
        Util::withDelay(function() {
            $caseName = 'PaymentFailInternalServerError';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            }
        });
    }

    /**
     * @skip
     * @testdox Should give fail response for exceeds transaction amount limit
     */
    public function testPaymentExceedsTransactionAmountLimit(): void
    {
        $this->markTestSkipped('Return error 500.');
        Util::withDelay(function() {
            $caseName = 'PaymentFailExceedsTransactionAmountLimit';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            
            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (Exception $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
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
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";

            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
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
            $jsonDict['partnerReferenceNo'] = self::$sharedOriginalPartnerReference;

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\IPG\v1\Model\IPGPaymentRequest'
            );

            echo "Request: " . $requestObj->__toString() . "\n";
            self::$apiInstanceWidget->ipgPayment($requestObj);

            try {
                self::$apiInstanceWidget->ipgPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget, 
                    self::$titleCase, 
                    $caseName, 
                    $e,
                    ['partnerReferenceNo' => self::$sharedOriginalPartnerReference]
                );
            }
        });
    }
}
