<?php

namespace DanaUat\Widget;

use PHPUnit\Framework\TestCase;
use Dana\Configuration;
use Dana\Env;
use Dana\Widget\v1\Api\WidgetApi;
use DanaUat\Helper\Util;
use Dana\ObjectSerializer;
use \Exception;
class PaymentUtil extends TestCase
{
    private static $apiInstanceWidget;
    private static $configuration;
    private static $merchantId;
    private static $sharedOriginalPartnerReference;
    private static $jsonPathFileWidget = 'resource/request/components/Widget.json';
    public static function payOrderWidget(
        string $phoneNumberUser,
        string $pinUser,
        string $redirectUrlWeb,
    ): void {
        $params = json_encode([
            'phoneNumber' => $phoneNumberUser,
            'pin' => $pinUser,
            'redirectUrl' => $redirectUrlWeb
        ]);

        Util::execFileAutomate(
            "/automate-payment.js",
            $params
        );
    }

    public static function createPaymentWidget(
        string $orderOrigin
    ): array {
        // Set up configuration with authentication settings
        self::$configuration = new Configuration();

        // The Configuration constructor automatically loads values from environment variables
        // But we can manually set them if needed
        self::$configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        self::$configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        self::$configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        self::$configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstanceWidget = new WidgetApi(null, self::$configuration);

        $jsonDict = Util::getRequest(
            self::$jsonPathFileWidget,
            'Payment',
            $orderOrigin
        );

        $jsonDict['merchantId'] = getenv('MERCHANT_ID');
        $jsonDict['partnerReferenceNo'] = self::generatePartnerReferenceNo();

        $requestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\Widget\v1\Model\WidgetPaymentRequest'
        );

        $apiResponse = self::$apiInstanceWidget->widgetPayment($requestObj);
        assert($apiResponse != null, "Order creation failed");

        $responseArray = json_decode($apiResponse->__toString(), true);
        return [
            'partnerReferenceNo' => $responseArray['partnerReferenceNo'] ?? '',
            'webRedirectUrl' => $responseArray['webRedirectUrl'] ?? ''
        ];
    }

    public static function cancelPaymentWidget(
        string $orderOrigin
    ): string {
        $dataOrder = self::createPaymentWidget("PaymentSuccess");
        $jsonDict = Util::getRequest(
            self::$jsonPathFileWidget,
            "CancelOrder",
            "CancelOrderValidScenario"
        );

        $jsonDict['merchantId'] = getenv('MERCHANT_ID');
        $jsonDict['originalPartnerReferenceNo'] = $dataOrder['partnerReferenceNo'];

        $requestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\Widget\v1\Model\CancelOrderRequest'
        );
        $apiResponse = self::$apiInstanceWidget->cancelOrder($requestObj);
        assert($apiResponse != null, "Cancel order failed");
        return (string)$dataOrder['partnerReferenceNo'];
    }

    public static function refundPaymentWidget(
        string $orderOrigin
    ): string {
        $dataOrder = self::payOrderWidget(
            "08123456789",
            "123456",
            $orderOrigin
        );
        $jsonDict = Util::getRequest(
            self::$jsonPathFileWidget,
            "RefundOrder",
            $orderOrigin
        );

        $jsonDict['merchantId'] = getenv('MERCHANT_ID');
        $jsonDict['originalPartnerReferenceNo'] = $dataOrder['partnerReferenceNo'];

        $requestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\Widget\v1\Model\RefundOrderRequest'
        );
        $apiResponse = self::$apiInstanceWidget->refundOrder($requestObj);
        assert($apiResponse != null, "Cancel order failed");
        return (string)$dataOrder['partnerReferenceNo'];
    }

    public static function generatePartnerReferenceNo(): string
    {
        // Generate a UUID v4
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
