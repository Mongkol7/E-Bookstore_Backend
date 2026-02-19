<?php

namespace App\Helpers;

class CookieHelper
{
    public static function getAuthCookieOptions(int $expires): array
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $originHost = is_string($origin) ? (parse_url($origin, PHP_URL_HOST) ?: '') : '';
        $requestHost = (string)($_SERVER['HTTP_HOST'] ?? '');

        $isCrossSite = $originHost !== '' && $requestHost !== '' && strcasecmp($originHost, $requestHost) !== 0;
        $configuredSameSite = trim((string)($_ENV['COOKIE_SAMESITE'] ?? ''));
        $sameSite = $configuredSameSite !== '' ? $configuredSameSite : ($isCrossSite ? 'None' : 'Lax');
        $sameSite = self::normalizeSameSite($sameSite);

        $isHttps =
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $secure = $sameSite === 'None' ? true : $isHttps;
        $path = (string)($_ENV['COOKIE_PATH'] ?? '/');
        $domain = trim((string)($_ENV['COOKIE_DOMAIN'] ?? ''));

        $options = [
            'expires' => $expires,
            'path' => $path !== '' ? $path : '/',
            'httponly' => true,
            'samesite' => $sameSite,
            'secure' => $secure,
        ];

        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        return $options;
    }

    private static function normalizeSameSite(string $sameSite): string
    {
        $value = strtolower(trim($sameSite));
        if ($value === 'none') {
            return 'None';
        }
        if ($value === 'strict') {
            return 'Strict';
        }
        return 'Lax';
    }
}
