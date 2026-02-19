<?php

namespace App\Middleware;

use App\Helpers\JwtHelper;
use Exception;

class AuthMiddleware
{
    public static function authenticate()
    {
        header('Content-Type: application/json');

        $token = self::resolveToken();
        if ($token === null || $token === '') {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication token missing']);
            exit();
        }

        try {
            $decoded = JwtHelper::decode($token);

            if (!$decoded) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid or expired token']);
                exit();
            }

            // Store the decoded user data in a global for access.
            $_SERVER['user'] = (array) $decoded;

        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token: ' . $e->getMessage()]);
            exit();
        }
    }

    private static function resolveToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
        if (is_string($authHeader) && preg_match('/^\s*Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim((string)$matches[1]);
        }

        if (isset($_COOKIE['auth_token']) && is_string($_COOKIE['auth_token'])) {
            return $_COOKIE['auth_token'];
        }

        return null;
    }
}
