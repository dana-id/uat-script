<?php

namespace DanaUat\Widget;

use Dana\Utils\SnapHeader;
use \Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Danauat\Helper\Util;

class OauthUtil
{
    public static function generateSeamlessData(
        $phoneNumber,
        $bizScenario,
        $timeVerified,
        $externalUid,
        $deviceId,
        $skipRegisterConsult = true
    ) {
        $seamlessData = json_encode([
            'phoneNumber' => $phoneNumber,
            'bizScenario' => $bizScenario,
            'timeVerified' => $timeVerified,
            'externalUid' => $externalUid,
            'deviceId' => $deviceId,
            'skipRegisterConsult' => $skipRegisterConsult
        ]);

        return $seamlessData;
    }
    private static function sign($textPayload, $privateKeyMerchant)
    {
        // Mendapatkan objek private key
        $privateKeyObject = self::getPrivateKey($privateKeyMerchant);

        // Membuat tanda tangan menggunakan algoritma SHA256withRSA
        $signature = '';
        if (!openssl_sign($textPayload, $signature, $privateKeyObject, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Gagal membuat tanda tangan');
        }

        // Encode tanda tangan dengan Base64
        return base64_encode($signature);
    }

    public static function getPrivateKey($privateKeyMerchant)
    {
        try {
            // Decode private key dari Base64
            $keyData = base64_decode($privateKeyMerchant);

            // Membuat private key dari data binary PKCS8
            $privateKey = openssl_pkey_get_private("-----BEGIN PRIVATE KEY-----\n" .
                chunk_split(base64_encode($keyData), 64, "\n") .
                "-----END PRIVATE KEY-----\n");

            if ($privateKey === false) {
                throw new Exception("Gagal membuat private key: " . openssl_error_string());
            }

            return $privateKey;
        } catch (Exception $e) {
            throw new Exception("Error saat memproses private key: " . $e->getMessage());
        }
    }
    /**
     * Membuat URL redirect untuk mendapatkan auth code
     * 
     * @param string $partnerId ID partner merchant
     * @param string $channelId ID channel
     * @param string $scope Scope/hak akses yang diminta
     * @param string $redirectUrl URL redirect setelah autentikasi
     * @param string $seamlessData Data seamless dalam format JSON
     * @param string $seamlessSign Tanda tangan dari data seamless
     * @return string URL lengkap untuk mendapatkan auth code
     */
    public static function generateRedirectLinkAuthCode(
        $partnerId,
        $channelId,
        $scope,
        $redirectUrl,
        $seamlessData,
        $seamlessSign
    ) {
        $basePath = "https://m.sandbox.dana.id/";
        $path = "v1.0/get-auth-code";

        $encodedSeamlessData = urlencode($seamlessData);
        $encodedSeamlessSign = urlencode($seamlessSign);

        $url = $basePath . $path . "?" .
            "partnerId=" . $partnerId .
            "&timestamp=2023-08-31T22:27:48+00:00" .
            "&externalId=test" .
            "&channelId=" . $channelId .
            "&scopes=" . $scope .
            "&redirectUrl=" . $redirectUrl .
            "&state=22321" .
            "&seamlessData=" . $encodedSeamlessData .
            "&seamlessSign=" . $encodedSeamlessSign;

        return $url;
    }

    /**
     * Mendapatkan auth code dengan menggunakan credentials user
     * 
     * @param string $partnerId ID partner merchant
     * @param string $channelId ID channel
     * @param string $phoneNumberUser Nomor telepon pengguna
     * @param string $pinUser PIN pengguna
     * @return string Auth code yang didapat dari proses otentikasi
     * @throws Exception Jika terjadi kesalahan dalam proses
     */
    public static function getAuthCode(
        $partnerId,
        $channelId,
        $phoneNumberUser,
        $pinUser
    ) {
        try {
            $seamlessData = self::generateSeamlessData(
                $phoneNumberUser,
                "PAYMENT",
                "2024-12-23T07:44:11+07:00",
                self::generateUUID(),
                "637216gygd76712313",
                true
            );

            echo "SINI1";


            $seamlessSign = self::generateSeamlessSign(
                $seamlessData
            );

            echo "SINI2";

            $urlRedirectAuth = self::generateRedirectLinkAuthCode(
                $partnerId,
                $channelId,
                "DEFAULT_BASIC_PROFILE,QUERY_BALANCE,CASHIER,MINI_DANA",
                "https://google.com",
                $seamlessData,
                $seamlessSign
            );

            return self::getOauthViaWeb($urlRedirectAuth, $phoneNumberUser, $pinUser);
        } catch (Exception $e) {
            throw new Exception("Gagal mendapatkan auth code: " . $e->getMessage());
        }
    }

    /**
     * Fungsi helper untuk menghasilkan UUID (pengganti UUID.randomUUID().toString())
     * 
     * @return string UUID dalam format string
     */
    public static function generateUUID()
    {
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

    /**
     * Menghasilkan tanda tangan seamless dari data yang disediakan
     * 
     * @param string $seamlessData Data seamless yang akan ditandatangani
     * @return string Tanda tangan yang sudah di-encode URL
     * @throws Exception Jika terjadi kesalahan dalam proses penandatanganan
     */
    public static function generateSeamlessSign($seamlessData)
    {
        try {
            // Mengambil private key dari konfigurasi
            $privateKey = getenv('PRIVATE_KEY');

            // Melakukan signing terhadap data
            $signResult = self::sign($seamlessData, $privateKey);

            echo $signResult;
            // Melakukan URL encode pada hasil tanda tangan
            return urlencode($signResult);
        } catch (Exception $e) {
            throw new Exception("Gagal menghasilkan tanda tangan seamless: " . $e->getMessage());
        }
    }
    /**
     * Mendapatkan OAuth code melalui tampilan browser dengan menggunakan Playwright
     * 
     * @param string $urlRedirectLinkAuthCode URL redirect untuk mendapatkan auth code
     * @param string $phoneNumber Nomor telepon pengguna
     * @param string $pin PIN pengguna
     * @return string Auth code yang didapat dari proses otentikasi
     * @throws Exception Jika terjadi kesalahan dalam proses
     */
    /**
     * Mendapatkan kode OAuth melalui tampilan browser menggunakan Playwright
     * 
     * @param string $urlRedirectLinkAuthCode URL redirect untuk mendapatkan auth code
     * @param string $phoneNumber Nomor telepon pengguna
     * @param string $pin PIN pengguna
     * @return string Kode autentikasi yang diperoleh
     */

    public static function getOauthViaWeb($urlRedirectLinkAuthCode, $phoneNumber, $pin)
    {
        $params = json_encode([
            'phoneNumber' => $phoneNumber,
            'pin' => $pin,
            'redirectUrl' => $urlRedirectLinkAuthCode
        ]);

        $output = Util::execFileWithParseParam(
            "/automate-oauth.js",
            $params
        );
        
        // Try to extract only the JSON part from the output
        $jsonOutput = "";
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            // Look for a line that starts with { and ends with }
            if (preg_match('/^\s*\{.*\}\s*$/', $line)) {
                $jsonOutput = $line;
                break;
            }
        }

        // If no JSON-like line was found, use the entire output
        if (empty($jsonOutput)) {
            $jsonOutput = $output;
        }

        // Try to parse JSON output
        $result = json_decode($jsonOutput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse script output as JSON: " . $output);
        }

        // Check if authCode exists and convert to string
        if (!isset($result['authCode']) || empty($result['authCode'])) {
            throw new Exception("Auth code not found in script output: " . $output);
        }

        // Ensure it's returned as string
        return (string)$result['authCode'];
    }
}
