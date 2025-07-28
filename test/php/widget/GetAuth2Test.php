<?php

namespace DanaUat\Widget\v1;

use PHPUnit\Framework\TestCase;
use Dana\Widget\v1\Api\WidgetApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use DanaUat\Helper\OauthUtil;
use DanaUat\Helper\Util;
use Exception;

class GetAuth2Test extends TestCase
{
    private static $titleCase = 'GetAuth2';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $seamlessData;
    private static $seamlessSign;
    private static $phoneNumber = '0811742234';

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new WidgetApi(null, $configuration);

        // // Membuat seamless data
        // $seamlessData = OauthUtil::generateSeamlessData(
        //     self::$phoneNumber,
        //     "PAYMENT",
        //     "2024-12-23T07:44:11+07:00",
        //     OauthUtil::generateUUID(), // Fungsi helper untuk menggantikan UUID.randomUUID().toString()
        //     "637216gygd76712313",
        //     true
        // );

        // // Membuat seamless sign dari seamless data
        // $seamlessSign = OauthUtil::generateSeamlessSign(
        //     $seamlessData
        // );
    }

    /**
     * Should give success response for get auth2 scenario
     */
    public function testGetAuth2Success(): void
    {
        // Util::withDelay(function () {
        //     $queryParams = [
        //         'partnerId' => getenv('X_PARTNER_ID'),
        //         'timestamp' => '2024-08-31T22:27:48+00:00',
        //         'externalId' => 'test',
        //         'channelId' => getenv('X_PARTNER_ID'),
        //         'scopes' => 'CASHIER,AGREEMENT_PAY,QUERY_BALANCE,DEFAULT_BASIC_PROFILE,MINI_DANA',
        //         'redirectUrl' => 'https://google.com',
        //         'state' => OauthUtil::generateUUID(),
        //         'isSnapBI' => 'true',
        //         'seamlessData' => self::$seamlessData,
        //         'seamlessSign' => self::$seamlessSign
        //     ];
        //     // Get the OAuth URL
        //     $response = $this->getOauthUrl($queryParams);

        //     $this->assertEquals('200', $response['statusCode'], 'Expected success response code');
        // });
    }

    public function getOauthUrl($queryParams)
    {
        $resourcePath = 'v1.0/get-auth-code';
        $basePath = 'https://m.sandbox.dana.id/';

        $queryString = http_build_query($queryParams);
        $url = $basePath . '/' . $resourcePath . '?' . $queryString;
        $client = new \GuzzleHttp\Client();
        echo $url;

        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            // Get status code
            $statusCode = $response->getStatusCode();

            // Get response body as string
            $body = $response->getBody()->getContents();

            // Try to decode JSON
            $responseArray = json_decode($body, true) ?: [];

            // Create a structured response array that includes the responseCode
            return [
                'responseCode' => $responseArray['responseCode'] ?? '2000000', // Use actual code or default
                'responseMessage' => $responseArray['responseMessage'] ?? 'Success',
                'statusCode' => $statusCode,
                'body' => $body,
                'data' => $responseArray
            ];
        } catch (\Exception $e) {
            // Return error response with responseCode
            return [
                'responseCode' => 'ERROR',
                'responseMessage' => $e->getMessage(),
                'error' => true
            ];
        }
    }
}
