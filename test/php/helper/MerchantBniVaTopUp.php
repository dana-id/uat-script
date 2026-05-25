<?php

namespace DanaUat\Helper;

use Dana\Configuration;
use Dana\Env;
use Dana\MerchantManagement\v1\Api\MerchantManagementApi;
use Dana\MerchantManagement\v1\Model\QueryAssetCardListRequest;
use Dana\MerchantManagement\v1\Model\QueryMerchantInfoRequest;
use Exception;

final class MerchantBniVaTopUp
{
    private const DANA_SANDBOX_BASE_URL = 'https://api.sandbox.dana.id';
    private const BNI_VA_TOP_UP_PATH = '/ifcsupergw/bni/topup/merchant/request.htm';

    private const DEFAULT_BNI_CLIENT_ID = '910';
    private const DEFAULT_BNI_SECRET_KEY = '9546d5f69af2ed3bc603834446985628';
    private const BNI_INST_ID = 'BNIC1ID';
    private const MERCHANT_DEPOSIT_ACCOUNT_TYPE = 'MERCHANT_DEPOSIT_ACCOUNT';
    private const MERCHANT_QUERY_LOGIN_TYPE_ROLE = 'ROLE';
    private const MERCHANT_DEPOSIT_TOP_UP_THRESHOLD = 1000000;

    private static bool $done = false;
    private static ?Exception $failure = null;
    private static ?MerchantManagementApi $merchantManagementApi = null;

    private function __construct()
    {
    }

    public static function ensure(): void
    {
        if (self::$done) {
            if (self::$failure !== null) {
                throw self::$failure;
            }
            return;
        }

        try {
            self::bniVaTopUpMerchantOnce();
        } catch (Exception $e) {
            self::$failure = $e;
            throw $e;
        } finally {
            self::$done = true;
        }
    }

    private static function bniVaTopUpMerchantOnce(): void
    {
        $depositBalance = self::queryMerchantDepositTotalAmount();
        if ($depositBalance >= self::MERCHANT_DEPOSIT_TOP_UP_THRESHOLD) {
            echo 'Skipping BNI VA top-up: merchant deposit balance=' . $depositBalance
                . ' >= threshold=' . self::MERCHANT_DEPOSIT_TOP_UP_THRESHOLD . PHP_EOL;
            return;
        }
        echo 'Merchant deposit balance=' . $depositBalance
            . ' < threshold=' . self::MERCHANT_DEPOSIT_TOP_UP_THRESHOLD
            . '; proceeding with BNI VA top-up' . PHP_EOL;

        $virtualAccount = self::queryBniMerchantVirtualAccount();
        self::postBniVaTopUpMerchant($virtualAccount);
    }

    private static function queryMerchantDepositTotalAmount(): int
    {
        $merchantId = getenv('MERCHANT_ID') ?: '';
        if ($merchantId === '') {
            throw new Exception('MERCHANT_ID is required to query merchant info');
        }

        $request = new QueryMerchantInfoRequest([
            'roleId' => $merchantId,
            'loginType' => self::MERCHANT_QUERY_LOGIN_TYPE_ROLE,
            'isQueryAccount' => true,
        ]);

        $response = self::getMerchantManagementApi()->queryMerchantInfo($request);
        $resultInfo = $response->getResponse()->getBody()->getResultInfo();
        if ($resultInfo->getResultStatus() !== 'S') {
            throw new Exception('queryMerchantInfo failed: ' . json_encode([
                'resultStatus' => $resultInfo->getResultStatus(),
                'resultCodeId' => $resultInfo->getResultCodeId(),
                'resultMsg' => $resultInfo->getResultMsg(),
            ]));
        }

        $merchantInformation = $response->getResponse()->getBody()->getMerchantInformation();
        if ($merchantInformation === null || $merchantInformation->getAccounts() === null) {
            throw new Exception('queryMerchantInfo: merchantInformation.accounts missing');
        }

        foreach ($merchantInformation->getAccounts() as $account) {
            if ($account->getAccountType() === self::MERCHANT_DEPOSIT_ACCOUNT_TYPE) {
                return self::parseAccountMappedTotalAmount($account);
            }
        }

        throw new Exception('queryMerchantInfo: ' . self::MERCHANT_DEPOSIT_ACCOUNT_TYPE . ' account not found');
    }

    /**
     * @param object $account
     */
    private static function parseAccountMappedTotalAmount($account): int
    {
        $accountData = json_decode(json_encode($account), true);
        if (!is_array($accountData)) {
            throw new Exception('deposit account: invalid account payload');
        }

        $mappedTotalAmount = $accountData['mappedTotalAmount'] ?? null;
        if (is_array($mappedTotalAmount) && array_key_exists('amount', $mappedTotalAmount)) {
            return self::parseAmountValue($mappedTotalAmount['amount']);
        }

        $totalAmount = $accountData['totalAmount'] ?? '';
        if (is_string($totalAmount) && $totalAmount !== '') {
            $parsed = json_decode($totalAmount, true);
            if (is_array($parsed) && array_key_exists('amount', $parsed)) {
                return self::parseAmountValue($parsed['amount']);
            }
        }

        throw new Exception('deposit account amount missing in queryMerchantInfo response');
    }

    private static function parseAmountValue($value): int
    {
        if (is_string($value)) {
            return (int) $value;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }

        throw new Exception('unsupported amount format: ' . json_encode($value));
    }

    private static function queryBniMerchantVirtualAccount(): string
    {
        $memberId = getenv('MERCHANT_ID') ?: '';
        if ($memberId === '') {
            throw new Exception('MERCHANT_ID is required to query merchant BNI VA');
        }

        $request = new QueryAssetCardListRequest([
            'memberId' => $memberId,
            'enableOnly' => 'true',
            'assetTypeList' => ['VA_ACCOUNT'],
        ]);

        $response = self::getMerchantManagementApi()->queryAssetCardList($request);
        $resultInfo = $response->getResponse()->getBody()->getResultInfo();
        if ($resultInfo->getResultStatus() !== 'S') {
            throw new Exception('queryAssetCardList failed: ' . json_encode([
                'resultStatus' => $resultInfo->getResultStatus(),
                'resultCodeId' => $resultInfo->getResultCodeId(),
                'resultMsg' => $resultInfo->getResultMsg(),
            ]));
        }

        foreach ($response->getResponse()->getBody()->getAssetCardList() ?? [] as $card) {
            if ($card->getAssetType() === 'VA_ACCOUNT' && $card->getInstId() === self::BNI_INST_ID) {
                $cardIndexNo = $card->getCardIndexNo() ?? '';
                if ($cardIndexNo !== '') {
                    return $cardIndexNo;
                }
            }
        }

        throw new Exception('BNI VA card not found in assetCardList');
    }

    private static function postBniVaTopUpMerchant(string $virtualAccount): void
    {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $integrationBody = [
            'trx_amount' => '1000',
            'trx_id' => $now->format('YmdHis'),
            'virtual_account' => $virtualAccount,
            'customer_name' => 'rudy',
            'payment_amount' => '1000000000',
            'cumulative_payment_amount' => '1000',
            'payment_ntb' => '233171',
            'datetime_payment' => $now->format('Y-m-d H:i:s'),
            'datetime_payment_iso8601' => $now->format('Y-m-d\TH:i:sP'),
        ];

        $clientId = getenv('BNI_VA_TOP_UP_CLIENT_ID') ?: self::DEFAULT_BNI_CLIENT_ID;
        $secretKey = getenv('BNI_VA_TOP_UP_SECRET_KEY') ?: self::DEFAULT_BNI_SECRET_KEY;
        $integrationJson = json_encode($integrationBody);
        $data = BniHashUtil::hashData($integrationJson, $clientId, $secretKey);

        $payload = json_encode(['client_id' => $clientId, 'data' => $data]);
        self::httpPostJson(self::DANA_SANDBOX_BASE_URL . self::BNI_VA_TOP_UP_PATH, $payload, true);
    }

    private static function getMerchantManagementApi(): MerchantManagementApi
    {
        if (self::$merchantManagementApi === null) {
            self::$merchantManagementApi = new MerchantManagementApi(null, self::danaConfiguration());
        }

        return self::$merchantManagementApi;
    }

    private static function danaConfiguration(): Configuration
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('CLIENT_SECRET', getenv('CLIENT_SECRET'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        return $configuration;
    }

    private static function httpPostJson(string $url, string $body, bool $checkBniStatus): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseText = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($responseText === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('HTTP request failed: ' . $error);
        }
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new Exception('HTTP ' . $statusCode . ': ' . $responseText);
        }

        if ($checkBniStatus) {
            $parsed = json_decode($responseText, true);
            if (is_array($parsed) && isset($parsed['status']) && $parsed['status'] !== '' && $parsed['status'] !== '000') {
                throw new Exception('BNI VA top-up rejected: ' . $responseText);
            }
        }
    }
}
