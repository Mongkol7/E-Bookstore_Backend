<?php

namespace App\Controllers;

use App\Repositories\AdminRepository;
use App\Models\AdminModel;
use App\Helpers\JwtHelper;
use App\Helpers\CookieHelper;
use PDO;

class AdminController
{
    private $adminRepository;

    public function __construct()
    {
        $pdo = require __DIR__ . '/../../config/database.php';
        $this->adminRepository = new AdminRepository($pdo);
    }

    public function getAdminById($id)
    {
        header('Content-Type: application/json');
        try {
            $admin = $this->adminRepository->getById((int)$id);
            if ($admin) {
                echo json_encode($admin);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Admin not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAllAdmins()
    {
        header('Content-Type: application/json');
        try {
            $admins = $this->adminRepository->getAll();
            echo json_encode($admins);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    public function createAdmin(){
        header('Content-Type: application/json');
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$firstName || !$lastName || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'First name, last name, email, and password are required']);
            return;
        }

        // Check if admin with this email already exists
        if ($this->adminRepository->findByEmail($email)) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Admin with this email already exists']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $admin = new AdminModel(
            null,
            $firstName,
            $lastName,
            $email,
            $hashedPassword,
            $data['phone'] ?? null,
            $data['address'] ?? null
        );

        try {
            $this->adminRepository->create($admin);
            http_response_code(201);
            echo json_encode(['message' => 'Admin created successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create admin: ' . $e->getMessage()]);
        }
    }

    public function updateAdmin(){
        header('Content-Type: application/json');
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $id = $data['id'] ?? null;
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null; // New password might be provided

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Admin ID is required']);
            return;
        }

        $existingAdmin = $this->adminRepository->getById((int)$id);
        if (!$existingAdmin) {
            http_response_code(404);
            echo json_encode(['error' => 'Admin not found']);
            return;
        }

        // Check if email is being changed and if new email already exists
        if ($email && $email !== $existingAdmin->getEmail()) {
            if ($this->adminRepository->findByEmail($email)) {
                http_response_code(409); // Conflict
                echo json_encode(['error' => 'Admin with this email already exists']);
                return;
            }
            $existingAdmin->setEmail($email);
        }

        if ($firstName) $existingAdmin->setFirstName($firstName);
        if ($lastName) $existingAdmin->setLastName($lastName);
        if ($data['phone']) $existingAdmin->setPhone($data['phone']);
        if ($data['address']) $existingAdmin->setAddress($data['address']);
        
        // If a new password is provided, hash it
        if ($password) {
            $existingAdmin->setPassword(password_hash($password, PASSWORD_DEFAULT));
        }

        $existingAdmin->setLastLogin(date('Y-m-d H:i:s')); // Update last login on any update

        try {
            $this->adminRepository->update($existingAdmin);
            http_response_code(200);
            echo json_encode(['message' => 'Admin updated successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update admin: ' . $e->getMessage()]);
        }
    }

        public function deleteAdmin(){

            $input = file_get_contents("php://input");

            $data = json_decode($input, true);

    

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {

                http_response_code(400);

                echo json_encode(['error' => 'Invalid JSON input']);

                return;

            }

    

            $id = $data['id'] ?? null;

    

            if (!$id) {

                http_response_code(400);

                echo json_encode(['error' => 'Admin ID is required']);

                return;

            }

    

            try {

                if ($this->adminRepository->delete($id)) {

                    http_response_code(200);

                    echo json_encode(['message' => 'Admin deleted successfully']);

                } else {

                    http_response_code(404);

                    echo json_encode(['error' => 'Admin not found']);

                }

            } catch (\Exception $e) {

                http_response_code(500);

                echo json_encode(['error' => $e->getMessage()]);

            }

        }

    

        public function loginAdmin() {

            header('Content-Type: application/json');

            $input = file_get_contents("php://input");

            $data = json_decode($input, true);

    

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {

                http_response_code(400);

                echo json_encode(['error' => 'Invalid JSON input']);

                return;

            }

    

            $email = $data['email'] ?? null;

            $password = $data['password'] ?? null;

    

            if (!$email || !$password) {

                http_response_code(400);

                echo json_encode(['error' => 'Email and password are required']);

                return;

            }

    

            try {

                            $admin = $this->adminRepository->findByEmail($email);

                

                            if ($admin) {
                                $storedPassword = (string)$admin->getPassword();
                                $isHashedPasswordValid = password_verify($password, $storedPassword);
                                $isLegacyPlaintextValid = hash_equals($storedPassword, $password);

                                if (!$isHashedPasswordValid && !$isLegacyPlaintextValid) {
                                    http_response_code(401);
                                    echo json_encode(['error' => 'Invalid credentials']);
                                    return;
                                }

                                // Migrate legacy plaintext password to hash on successful login.
                                if ($isLegacyPlaintextValid) {
                                    $admin->setPassword(password_hash($password, PASSWORD_DEFAULT));
                                }

                                // Update last login (optional but good practice)

                                $admin->setLastLogin(date('Y-m-d H:i:s'));

                                // Note: The update method requires a full AdminModel, potentially with hashed password already set.

                                // For simplicity here, we'll just update the last login timestamp directly if possible,

                                // or refactor AdminRepository::update to handle partial updates more gracefully.

                                // For now, we'll fetch, update, and save the existing admin model to retain other data.

                                $this->adminRepository->update($admin); // Update the admin in the database

                                // Generate JWT
                                $payload = [
                                    'user_id' => $admin->getId(),
                                    'user_type' => 'admin'
                                ];
                                $jwt = JwtHelper::encode($payload);

                                // Set JWT as an HttpOnly cookie
                                setcookie('auth_token', $jwt, CookieHelper::getAuthCookieOptions(time() + (86400 * 30)));
                

                                http_response_code(200);

                                echo json_encode([

                                    'message' => 'Login successful',
                                    'token' => $jwt

                                ]);

                            } else {

                                http_response_code(401);

                                echo json_encode(['error' => 'Invalid credentials']);

                            }

            } catch (\Exception $e) {

                http_response_code(500);

                echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);

            }

        }

    }
