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

class QueryOrderTest extends TestCase
{
    private static $titleCase = 'QueryOrder';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $merchantId;
    private static $queryOrderUrl;
    private static $userPin = "123321";
    private static $userPhoneNumber = "0811742234";
    private static $sandboxUrl;
    private static $originalPartnerReferenceCancel, $originalPartnerReferencePaid, $originalPartnerReferenceInit, $originalPartnerReferencePaying;

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new WidgetApi(null, $configuration);
        self::$merchantId = getenv('MERCHANT_ID');
        self::$queryOrderUrl = '/1.0/debit/status.htm';
        self::$sandboxUrl = 'https://api.sandbox.dana.id';

        self::$originalPartnerReferenceCancel = self::createCancelPayment();
        self::$originalPartnerReferencePaid = self::createPaymentPaid();
        $dataOrder = self::createPaymentOrder();
        self::$originalPartnerReferenceInit = $dataOrder['partnerReferenceNo'];
        $dataOrderPaying = self::createPaymentOrder("PaymentPaying");
        self::$originalPartnerReferencePaying = $dataOrderPaying['partnerReferenceNo'];

        echo "Original Partner Reference Cancel: " . self::$originalPartnerReferenceCancel . PHP_EOL;
        echo "Original Partner Reference Paid: " . self::$originalPartnerReferencePaid . PHP_EOL;
        echo "Original Partner Reference Init: " . self::$originalPartnerReferenceInit . PHP_EOL;
        echo "Original Partner Reference Paying: " . self::$originalPartnerReferencePaying . PHP_EOL;
    }

    /**
     * @skip
     * Should give success response for query order (paid)
     */
    public function testQueryOrderSuccessPaid(): void
    {
        Util::withDelay(function () {
            $caseName = 'QueryOrderSuccessPaid';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['transactionDate'] = self::generateDate();
            $jsonDict['originalPartnerReferenceNo'] = self::$originalPartnerReferencePaid;

            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\Widget\v1\Model\QueryPaymentRequest');
            $apiResponse = self::$apiInstance->queryPayment($requestObj);
            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                $apiResponse->__toString(),
            );
        });
    }

    /**
     * @skip
     * Should give success response for query order (initiated)
     */
    public function testQueryOrderSuccessInitiated(): void
    {
        Util::withDelay(function () {
            $caseName = 'QueryOrderSuccessInitiated';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['transactionDate'] = self::generateDate();
            $jsonDict['originalPartnerReferenceNo'] = self::$originalPartnerReferenceInit;

            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\Widget\v1\Model\QueryPaymentRequest');
            $apiResponse = self::$apiInstance->queryPayment($requestObj);

            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                $apiResponse->__toString(),
            );
        });
    }

    /**
     * @skip
     * Should give success response for query order (paying)
     */
    public function testQueryOrderSuccessPaying(): void
    {
        Util::withDelay(function () {
            $caseName = 'QueryOrderSuccessPaying';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['transactionDate'] = self::generateDate();
            $jsonDict['originalPartnerReferenceNo'] = self::$originalPartnerReferencePaying;

            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\Widget\v1\Model\QueryPaymentRequest');
            $apiResponse = self::$apiInstance->queryPayment($requestObj);

            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                $apiResponse->__toString(),
            );
        });
    }

    /**
     * Should give success response for query order (cancelled)
     */
    public function testQueryOrderSuccessCancelled(): void
    {
        Util::withDelay(function () {
            $caseName = 'QueryOrderSuccessCancelled';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);

            $jsonDict['transactionDate'] = self::generateDate();
            $jsonDict['originalPartnerReferenceNo'] = self::$originalPartnerReferenceCancel;
            $jsonDict['merchantId'] = self::$merchantId;

            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\Widget\v1\Model\QueryPaymentRequest');

            $apiResponse = self::$apiInstance->queryPayment($requestObj);

            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                $caseName,
                $apiResponse->__toString(),
            );
        });
    }

    /**
     * @skip
     * Should fail with not found
     */
    public function testQueryOrderNotFound(): void
    {
        Util::withDelay(function () {
            $caseName = 'QueryOrderNotFound';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $jsonDict['transactionDate'] = self::generateDate();
            $jsonDict['originalPartnerReferenceNo'] = "tesr12124";
            $jsonDict['merchantId'] = self::$merchantId;

            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\Widget\v1\Model\QueryPaymentRequest');
            try {
                $apiResponse = self::$apiInstance->queryPayment($requestObj);
                $this->fail('Expected ApiException was not thrown. API response: ' . print_r($apiResponse, true));
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    /**
     * Should fail with invalid field
     */
    public function testQueryOrderFailInvalidField(): void
    {
        $this->markTestSkipped('Skipping testQueryOrderFailInvalidField as requested.');
        Util::withDelay(function () {
            $caseName = 'QueryOrderFailInvalidField';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $headers = Util::getHeadersWithSignature(
                'POST',
                self::$queryOrderUrl,
                $jsonDict,
                true,
                true
            );

            try {
                Util::executeApiRequest(
                    'POST',
                    self::$sandboxUrl . self::$queryOrderUrl,
                    $headers,
                    $jsonDict
                );

                $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 400 Bad Request for invalid format
                $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for invalid timestamp format, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();

                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $responseContent,
                    ['partnerReferenceNo' => self::$originalPartnerReferenceCancel]
                );
            } catch (ApiException $e) {
                $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
            }
        });
    }

    /**
     * @skip
     * Should fail with missing or invalid mandatory field
     */
    public function testQueryOrderFailInvalidMandatoryField(): void
    {
        $this->markTestSkipped('Skipping testQueryOrderFailInvalidMandatoryField as requested.');
        Util::withDelay(function () {
            $caseName = 'QueryOrderFailInvalidMandatoryField';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $headers = Util::getHeadersWithSignature(
                'POST',
                self::$queryOrderUrl,
                $jsonDict
            );

            try {
                Util::executeApiRequest(
                    'POST',
                    self::$sandboxUrl . self::$queryOrderUrl,
                    $headers,
                    $jsonDict
                );

                $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
            } catch (ApiException $e) {
                // We expect a 400 Bad Request for invalid format
                $this->assertEquals(400, $e->getCode(), "Expected HTTP 400 Bad Request for invalid timestamp format, got {$e->getCode()}");

                // Get the response body from the exception
                $responseContent = (string)$e->getResponseBody();

                // Use assertFailResponse to validate the error response
                Assertion::assertFailResponse(
                    self::$jsonPathFile,
                    self::$titleCase,
                    $caseName,
                    $responseContent,
                    ['partnerReferenceNo' => self::$originalPartnerReferenceCancel]
                );
            } catch (Exception $e) {
                $this->fail("Expected ApiException but got " . get_class($e) . ": " . $e->getMessage());
            }
        });
    }

    /**
     * @skip
     * Should fail with general error
     */
    public function testQueryOrderFailGeneralError(): void
    {
        Util::withDelay(function () {
            $caseName = 'QueryOrderFailGeneralError';
            $jsonDict = Util::getRequest(self::$jsonPathFile, self::$titleCase, $caseName);
            $jsonDict['transactionDate'] = self::generateDate();
            $requestObj = ObjectSerializer::deserialize($jsonDict, 'Dana\Widget\v1\Model\QueryPaymentRequest');
            try {
                self::$apiInstance->queryPayment($requestObj);
                $this->fail('Expected ApiException was not thrown');
            } catch (ApiException $e) {
                Assertion::assertFailResponse(self::$jsonPathFile, self::$titleCase, $caseName, $e->getResponseBody());
                $this->assertTrue(true);
            }
        });
    }

    public static function createCancelPayment(): string
    {
        $dataOrder = PaymentUtil::createPaymentWidget(
            'PaymentSuccess'
        );

        $jsonDict = Util::getRequest(
            self::$jsonPathFile,
            "CancelOrder",
            "CancelOrderValidScenario"
        );
        $jsonDict['originalPartnerReferenceNo'] = $dataOrder['partnerReferenceNo'];
        $jsonDict['merchantId'] = self::$merchantId;

        $requestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\Widget\v1\Model\CancelOrderRequest'
        );
        self::$apiInstance->cancelOrder($requestObj);
        return $dataOrder['partnerReferenceNo'];
    }

    public static function createPaymentOrder(
        string $originOrder = "PaymentSuccess"
    ): array {
        // Get the request data from the JSON file
        $jsonDict = Util::getRequest(
            self::$jsonPathFile,
            "Payment",
            $originOrder
        );

        // Set a unique partner reference number
        $partnerReferenceNo = Util::generatePartnerReferenceNo();
        $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;
        $jsonDict['merchantId'] = self::$merchantId;

        // Create a CreateOrderByRedirectRequest object from the JSON request data
        $createOrderRequestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\Widget\v1\Model\WidgetPaymentRequest',
        );

        $apiResponse = self::$apiInstance->widgetPayment($createOrderRequestObj);

        $responseArray = json_decode($apiResponse->__toString(), true);
        return [
            'partnerReferenceNo' => $responseArray['partnerReferenceNo'] ?? '',
            'webRedirectUrl' => $responseArray['webRedirectUrl'] ?? ''
        ];
    }

    private static function createPaymentPaid(): string
    {
        $dataOrder = self::createPaymentOrder();

        PaymentUtil::payOrderWidget(
            self::$userPhoneNumber,
            self::$userPin,
            $dataOrder['webRedirectUrl']
        );

        return (string)$dataOrder['partnerReferenceNo'];
    }

    private static function generateDate(): string
    {
        $date = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $formattedDate = $date->format('Y-m-d\TH:i:s+07:00');
        return $formattedDate;
    }
}
