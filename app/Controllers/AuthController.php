<?php

namespace App\Controllers;

use App\Repositories\AdminRepository;
use App\Repositories\CustomerRepository;
use App\Helpers\JwtHelper;
use App\Helpers\CookieHelper;

class AuthController
{
    public function login()
    {
        header('Content-Type: application/json');

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $email = isset($payload['email']) ? trim((string)$payload['email']) : '';
        $password = isset($payload['password']) ? (string)$payload['password'] : '';

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            return;
        }

        try {
            $pdo = require __DIR__ . '/../../config/database.php';
            $stmt = $pdo->prepare(
                "SELECT id, first_name, last_name, email, password, phone, address, 'customer' AS user_type
                 FROM customers
                 WHERE LOWER(email) = LOWER(:email)
                 UNION ALL
                 SELECT id, first_name, last_name, email, password, phone, address, 'admin' AS user_type
                 FROM admins
                 WHERE LOWER(email) = LOWER(:email)
                 LIMIT 1"
            );
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
                return;
            }

            $storedPassword = (string)($user['password'] ?? '');
            $isHashedPasswordValid = password_verify($password, $storedPassword);
            $isLegacyPlaintextValid = hash_equals($storedPassword, $password);

            if (!$isHashedPasswordValid && !$isLegacyPlaintextValid) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
                return;
            }

            $table = $user['user_type'] === 'admin' ? 'admins' : 'customers';
            $updateSql = "UPDATE {$table} SET last_login = :last_login";
            $updateParams = [
                'last_login' => date('Y-m-d H:i:s'),
                'id' => (int)$user['id'],
            ];

            // Migrate legacy plaintext password to hash on successful login.
            if ($isLegacyPlaintextValid) {
                $updateSql .= ", password = :password";
                $updateParams['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $updateSql .= " WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);

            $jwtPayload = [
                'user_id' => (int)$user['id'],
                'user_type' => (string)$user['user_type'],
            ];
            $jwt = JwtHelper::encode($jwtPayload);

            setcookie('auth_token', $jwt, CookieHelper::getAuthCookieOptions(time() + (86400 * 30)));

            http_response_code(200);
            echo json_encode([
                'message' => 'Login successful',
                'token' => $jwt,
                'user' => [
                    'id' => (int)$user['id'],
                    'first_name' => (string)($user['first_name'] ?? ''),
                    'last_name' => (string)($user['last_name'] ?? ''),
                    'email' => (string)($user['email'] ?? ''),
                    'phone' => $user['phone'] ?? null,
                    'address' => $user['address'] ?? null,
                    'role' => ucfirst((string)$user['user_type']),
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
        }
    }

    public function getProfile()
    {
        header('Content-Type: application/json');

        $authUser = $_SERVER['user'] ?? null;
        if (!is_array($authUser)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $userId = (int)($authUser['user_id'] ?? 0);
        $userType = $authUser['user_type'] ?? '';

        if ($userId <= 0 || ($userType !== 'customer' && $userType !== 'admin')) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid authentication context']);
            return;
        }

        try {
            $pdo = require __DIR__ . '/../../config/database.php';

            if ($userType === 'customer') {
                $repository = new CustomerRepository($pdo);
                $user = $repository->getById($userId);
            } else {
                $repository = new AdminRepository($pdo);
                $user = $repository->getById($userId);
            }

            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                return;
            }

            $fullName = trim($user->getFirstName() . ' ' . $user->getLastName());
            if ($fullName === '') {
                $fullName = 'Unknown User';
            }

            http_response_code(200);
            echo json_encode([
                'user' => [
                    'id' => $user->getId(),
                    'name' => $fullName,
                    'role' => ucfirst($userType),
                    'email' => $user->getEmail(),
                    'first_name' => $user->getFirstName(),
                    'last_name' => $user->getLastName(),
                    'phone' => $user->getPhone(),
                    'address' => $user->getAddress(),
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch profile: ' . $e->getMessage()]);
        }
    }

    public function logout()
    {
        header('Content-Type: application/json');
        
        // Unset the auth_token cookie by setting its expiration to a past time
        setcookie('auth_token', '', CookieHelper::getAuthCookieOptions(time() - 3600));

        http_response_code(200);
        echo json_encode(['message' => 'Logged out successfully']);
    }
}
