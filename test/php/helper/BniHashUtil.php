<?php

namespace DanaUat\Helper;

final class BniHashUtil
{
    private function __construct()
    {
    }

    public static function hashData(string $jsonData, string $clientId, string $secretKey): string
    {
        $time = substr((string) (int) (microtime(true) * 1000), 0, 10);
        $time = strrev($time);
        return self::doubleEncrypt($time . '.' . $jsonData, $clientId, $secretKey);
    }

    private static function doubleEncrypt(string $input, string $clientId, string $secretKey): string
    {
        $encrypted = self::encrypt($input, $clientId);
        $encrypted = self::encrypt($encrypted, $secretKey);
        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    private static function encrypt(string $data, string $key): string
    {
        $keyLen = strlen($key);
        $result = '';
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $keyChar = $key[($i + $keyLen - 1) % $keyLen];
            $result .= chr((ord($data[$i]) + ord($keyChar)) % 128);
        }
        return $result;
    }
}
