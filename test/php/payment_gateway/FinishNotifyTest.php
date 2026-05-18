<?php

namespace DanaUat\PaymentGateway;

use PHPUnit\Framework\TestCase;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;

class FinishNotifyTest extends TestCase
{
    private static $titleCase = 'CreateOrder';
    private static $jsonPathFile = 'resource/request/components/PaymentGateway.json';
    private static $apiInstance;
    private static $createOrderRequestCaseFinishNotify = 'CreateOrderApi';
    private static $createOrderAssertCaseFinishNotify = 'CreateOrderApi';
    private static $notificationN8nURL = 'https://n8n.automation.dana.id/webhook/3676a08f-b06e-416c-b6cd-bea04f71c4d5';
    private static $finishNotifyDefaultValidUpToOffsetSeconds = 360;
    private static $finishNotifyValidUpToOffsetExpiredSeconds = 2 * 60 + 15;

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);

        self::$apiInstance = new PaymentGatewayApi(null, $configuration);
    }

    private static function patchCreateOrderAPIForFinishNotify(array &$jsonDict, string $amount): void
    {
        if (isset($jsonDict['amount']) && is_array($jsonDict['amount'])) {
            $jsonDict['amount']['value'] = $amount;
        }
        $jsonDict['payOptionDetails'] = [
            [
                'payMethod' => 'VIRTUAL_ACCOUNT',
                'payOption' => 'VIRTUAL_ACCOUNT_CIMB',
                'transAmount' => [
                    'value' => $amount,
                    'currency' => 'IDR',
                ],
            ],
        ];
        if (isset($jsonDict['urlParams']) && is_array($jsonDict['urlParams'])) {
            foreach ($jsonDict['urlParams'] as $idx => $u) {
                if (isset($u['type']) && $u['type'] === 'NOTIFICATION') {
                    $jsonDict['urlParams'][$idx]['url'] = self::$notificationN8nURL;
                }
            }
        }
    }

    /**
     * @return array{partnerReferenceNo: string, body: string}
     */
    private static function createOrderAPIFinishNotifyOnce(string $amount, string $validUpTo = ''): array
    {
        return Util::runWithRetry(function () use ($amount, $validUpTo) {
            $jsonDict = Util::getRequest(
                self::$jsonPathFile,
                self::$titleCase,
                self::$createOrderRequestCaseFinishNotify
            );

            $partnerReferenceNo = Util::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = $partnerReferenceNo;
            if ($validUpTo !== '') {
                $jsonDict['validUpTo'] = $validUpTo;
            } else {
                $jsonDict['validUpTo'] = Util::generateFormattedDate(self::$finishNotifyDefaultValidUpToOffsetSeconds, 7);
            }
            self::patchCreateOrderAPIForFinishNotify($jsonDict, $amount);

            $createOrderRequestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\PaymentGateway\v1\Model\CreateOrderByApiRequest',
            );
            $createOrderRequestObj->setPartnerReferenceNo($partnerReferenceNo);

            $apiResponse = self::$apiInstance->createOrder($createOrderRequestObj);

            return [
                'partnerReferenceNo' => $partnerReferenceNo,
                'body' => $apiResponse->__toString(),
            ];
        }, 3, 2000);
    }

    public function testTransactionSuccessNotify(): void
    {
        Util::withDelay(function () {
            $result = self::createOrderAPIFinishNotifyOnce('11011.00', '');
            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                self::$createOrderAssertCaseFinishNotify,
                $result['body'],
                ['partnerReferenceNo' => $result['partnerReferenceNo']]
            );
            Util::payVirtualAccountSandbox(Util::paymentCodeFromCreateOrderResponse($result['body']));
            $this->assertTrue(true);
        });
    }

    public function testInternalServerErrorNotify(): void
    {
        Util::withDelay(function () {
            $result = self::createOrderAPIFinishNotifyOnce('11012.00', '');
            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                self::$createOrderAssertCaseFinishNotify,
                $result['body'],
                ['partnerReferenceNo' => $result['partnerReferenceNo']]
            );
            Util::payVirtualAccountSandbox(Util::paymentCodeFromCreateOrderResponse($result['body']));
            $this->assertTrue(true);
        });
    }

    public function testExpiredNotify(): void
    {
        Util::withDelay(function () {
            $validUpTo = Util::generateFormattedDate(self::$finishNotifyValidUpToOffsetExpiredSeconds, 7);
            $result = self::createOrderAPIFinishNotifyOnce('11013.00', $validUpTo);
            Assertion::assertResponse(
                self::$jsonPathFile,
                self::$titleCase,
                self::$createOrderAssertCaseFinishNotify,
                $result['body'],
                ['partnerReferenceNo' => $result['partnerReferenceNo']]
            );
            $this->assertTrue(true);
        });
    }
}
