<?php

namespace App\Controllers;

use App\Repositories\AdminRepository;
use App\Repositories\CustomerRepository;
use App\Helpers\JwtHelper;
use App\Helpers\CookieHelper;
use App\Helpers\MailHelper;

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

    public function requestPasswordResetCode()
    {
        header('Content-Type: application/json');

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $email = isset($payload['email']) ? trim((string)$payload['email']) : '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid email is required']);
            return;
        }

        try {
            $pdo = require __DIR__ . '/../../config/database.php';
            $this->ensurePasswordResetTable($pdo);

            $user = $this->findResetUserByEmail($pdo, $email);
            if ($user) {
                $ttlMinutes = max(5, (int)($_ENV['PASSWORD_RESET_CODE_TTL_MINUTES'] ?? 15));
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = gmdate('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
                $fullName = trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? '')));

                $pdo->beginTransaction();

                $invalidateStmt = $pdo->prepare(
                    "UPDATE password_reset_codes
                     SET used_at = NOW()
                     WHERE user_type = :user_type
                       AND user_id = :user_id
                       AND used_at IS NULL"
                );
                $invalidateStmt->execute([
                    'user_type' => (string)$user['user_type'],
                    'user_id' => (int)$user['id'],
                ]);

                $insertStmt = $pdo->prepare(
                    "INSERT INTO password_reset_codes
                    (email, user_type, user_id, code_hash, expires_at, created_at)
                    VALUES (:email, :user_type, :user_id, :code_hash, :expires_at, NOW())"
                );
                $insertStmt->execute([
                    'email' => (string)$user['email'],
                    'user_type' => (string)$user['user_type'],
                    'user_id' => (int)$user['id'],
                    'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                    'expires_at' => $expiresAt,
                ]);

                $pdo->commit();

                MailHelper::sendPasswordResetCode(
                    (string)$user['email'],
                    $code,
                    $fullName,
                    $ttlMinutes
                );
            }

            http_response_code(200);
            echo json_encode([
                'message' => 'If an account exists for that email, a verification code has been sent.',
            ]);
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => 'Unable to process password reset request']);
        }
    }

    public function confirmPasswordReset()
    {
        header('Content-Type: application/json');

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $email = isset($payload['email']) ? trim((string)$payload['email']) : '';
        $code = isset($payload['code']) ? trim((string)$payload['code']) : '';
        $newPassword = isset($payload['newPassword']) ? (string)$payload['newPassword'] : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid email is required']);
            return;
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            http_response_code(400);
            echo json_encode(['error' => 'Verification code must be 6 digits']);
            return;
        }

        if ($newPassword === '') {
            http_response_code(400);
            echo json_encode(['error' => 'New password is required']);
            return;
        }

        try {
            $pdo = require __DIR__ . '/../../config/database.php';
            $this->ensurePasswordResetTable($pdo);

            $lookupStmt = $pdo->prepare(
                "SELECT id, user_type, user_id, code_hash, expires_at
                 FROM password_reset_codes
                 WHERE LOWER(email) = LOWER(:email)
                   AND used_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT 10"
            );
            $lookupStmt->execute(['email' => $email]);
            $rows = $lookupStmt->fetchAll(\PDO::FETCH_ASSOC);

            $matchedRow = null;
            foreach ($rows as $row) {
                $expiresTs = strtotime((string)($row['expires_at'] ?? ''));
                if ($expiresTs !== false && $expiresTs < time()) {
                    continue;
                }

                if (password_verify($code, (string)($row['code_hash'] ?? ''))) {
                    $matchedRow = $row;
                    break;
                }
            }

            if (!$matchedRow) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid or expired verification code']);
                return;
            }

            $userType = (string)($matchedRow['user_type'] ?? '');
            if ($userType !== 'customer' && $userType !== 'admin') {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid reset context']);
                return;
            }

            $table = $userType === 'admin' ? 'admins' : 'customers';
            $userId = (int)($matchedRow['user_id'] ?? 0);
            if ($userId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid reset context']);
                return;
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            $updatePasswordStmt = $pdo->prepare(
                "UPDATE {$table} SET password = :password WHERE id = :id"
            );
            $updatePasswordStmt->execute([
                'password' => $passwordHash,
                'id' => $userId,
            ]);

            if ($updatePasswordStmt->rowCount() < 1) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Account not found']);
                return;
            }

            $consumeStmt = $pdo->prepare(
                "UPDATE password_reset_codes
                 SET used_at = NOW()
                 WHERE user_type = :user_type
                   AND user_id = :user_id
                   AND used_at IS NULL"
            );
            $consumeStmt->execute([
                'user_type' => $userType,
                'user_id' => $userId,
            ]);

            $pdo->commit();

            http_response_code(200);
            echo json_encode(['message' => 'Password reset successful. Please login with your new password.']);
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => 'Unable to reset password']);
        }
    }

    private function findResetUserByEmail(\PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, first_name, last_name, email, 'customer' AS user_type
             FROM customers
             WHERE LOWER(email) = LOWER(:email)
             UNION ALL
             SELECT id, first_name, last_name, email, 'admin' AS user_type
             FROM admins
             WHERE LOWER(email) = LOWER(:email)
             LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function ensurePasswordResetTable(\PDO $pdo): void
    {
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS password_reset_codes (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    user_type VARCHAR(20) NOT NULL,
                    user_id BIGINT NOT NULL,
                    code_hash TEXT NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    used_at DATETIME NULL,
                    INDEX idx_password_reset_codes_lookup (email, used_at, created_at),
                    INDEX idx_password_reset_codes_user (user_type, user_id, used_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS password_reset_codes (
                id BIGSERIAL PRIMARY KEY,
                email TEXT NOT NULL,
                user_type TEXT NOT NULL,
                user_id BIGINT NOT NULL,
                code_hash TEXT NOT NULL,
                expires_at TIMESTAMPTZ NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                used_at TIMESTAMPTZ NULL
            )"
        );

        $pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_password_reset_codes_lookup
             ON password_reset_codes (email, used_at, created_at DESC)"
        );

        $pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_password_reset_codes_user
             ON password_reset_codes (user_type, user_id, used_at)"
        );
    }
}
