<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

class JwtHelper
{
    private static $secretKey = null;
    private static $algo = 'HS256';

    private static function getSecretKey()
    {
        if (self::$secretKey === null) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
            self::$secretKey = $_ENV['JWT_SECRET'] ?? 'your_super_secret_key'; // Fallback for safety
        }
        return self::$secretKey;
    }

    public static function encode(array $payload, int $expirationMinutes = 60): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + ($expirationMinutes * 60);

        $payload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expirationTime
        ]);

        return JWT::encode($payload, self::getSecretKey(), self::$algo);
    }

    public static function decode(string $jwt): ?object
    {
        try {
            return JWT::decode($jwt, new Key(self::getSecretKey(), self::$algo));
        } catch (\Exception $e) {
            // Log the error for debugging, but don't expose it to the user
            error_log("JWT decoding error: " . $e->getMessage());
            return null;
        }
    }
}
