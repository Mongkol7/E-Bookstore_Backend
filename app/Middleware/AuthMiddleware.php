<?php

namespace App\Middleware;

use App\Helpers\JwtHelper;
use Exception;

class AuthMiddleware
{
    public static function authenticate()
    {
        header('Content-Type: application/json');

        if (!isset($_COOKIE['auth_token'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication token missing']);
            exit();
        }

        $token = $_COOKIE['auth_token'];

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
}
