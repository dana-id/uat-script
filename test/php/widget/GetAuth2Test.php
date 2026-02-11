<?php

namespace DanaUat\Widget;

use PHPUnit\Framework\TestCase;
use Dana\Widget\v1\Api\WidgetApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use DanaUat\Helper\OauthUtil;
use DanaUat\Helper\Util;

class GetAuth2Test extends TestCase
{
    private static $titleCase = 'GetAuth2';
    private static $jsonPathFile = 'resource/request/components/Widget.json';
    private static $apiInstance;
    private static $seamlessData;
    private static $seamlessSign;
    private static $phoneNumber = '083811223355';

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        $configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        $configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        $configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        $configuration->setApiKey('ENV', Env::SANDBOX);
        self::$apiInstance = new WidgetApi(null, $configuration);
    }

    /**
     * Should give success response for get auth2 scenario
     */
    public function testGetAuth2Success(): void
    {
        $this->markTestSkipped('Skipping testGetAuth2Success ');
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
}
