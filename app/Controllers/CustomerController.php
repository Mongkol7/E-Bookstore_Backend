<?php

namespace App\Controllers;

use App\Repositories\CustomerRepository;
use App\Models\CustomerModel;
use App\Helpers\JwtHelper;
use App\Helpers\CookieHelper;
use PDO;

class CustomerController
{
    private $customerRepository;

    public function __construct()
    {
        $pdo = require __DIR__ . '/../../config/database.php';
        $this->customerRepository = new CustomerRepository($pdo);
    }

    public function getCustomerById($id)
    {
        header('Content-Type: application/json');
        try {
            $customer = $this->customerRepository->getById((int)$id);
            if ($customer) {
                echo json_encode($customer);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Customer not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAllCustomers()
    {
        header('Content-Type: application/json');
        try {
            $customers = $this->customerRepository->getAll();
            echo json_encode($customers);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    public function createCustomer(){
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
        $email = isset($data['email']) ? trim((string)$data['email']) : null;
        $password = isset($data['password']) ? (string)$data['password'] : null;

        if (!$firstName || !$lastName || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'First name, last name, email, and password are required']);
            return;
        }

        // Check if customer with this email already exists
        if ($this->customerRepository->findByEmail($email)) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Customer with this email already exists']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $customer = new CustomerModel(
            null,
            $firstName,
            $lastName,
            $email,
            $hashedPassword,
            $data['phone'] ?? null,
            $data['address'] ?? null
        );

        try {
            $this->customerRepository->create($customer);
            http_response_code(201);
            echo json_encode(['message' => 'Customer created successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create customer: ' . $e->getMessage()]);
        }
    }

        public function updateCustomer(){

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

                echo json_encode(['error' => 'Customer ID is required']);

                return;

            }

    

            $existingCustomer = $this->customerRepository->getById((int)$id);

            if (!$existingCustomer) {

                http_response_code(404);

                echo json_encode(['error' => 'Customer not found']);

                return;

            }

    

            // Check if email is being changed and if new email already exists

            if ($email && $email !== $existingCustomer->getEmail()) {

                if ($this->customerRepository->findByEmail($email)) {

                    http_response_code(409); // Conflict

                    echo json_encode(['error' => 'Customer with this email already exists']);

                    return;

                }

                $existingCustomer->setEmail($email);

            }

    

            if ($firstName) $existingCustomer->setFirstName($firstName);

            if ($lastName) $existingCustomer->setLastName($lastName);

            if ($data['phone']) $existingCustomer->setPhone($data['phone']);

            if ($data['address']) $existingCustomer->setAddress($data['address']);

            

            // If a new password is provided, hash it

            if ($password) {

                $existingCustomer->setPassword(password_hash($password, PASSWORD_DEFAULT));

            }

    

            $existingCustomer->setLastLogin(date('Y-m-d H:i:s')); // Update last login on any update

    

            try {

                $this->customerRepository->update($existingCustomer);

                http_response_code(200);

                echo json_encode(['message' => 'Customer updated successfully']);

            } catch (\Exception $e) {

                http_response_code(500);

                echo json_encode(['error' => 'Failed to update customer: ' . $e->getMessage()]);

            }

        }

    public function deleteCustomer(){
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
            echo json_encode(['error' => 'Customer ID is required']);
            return;
        }

        try {
            if ($this->customerRepository->delete($id)) {
                http_response_code(200);
                echo json_encode(['message' => 'Customer deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Customer not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function loginCustomer() {
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
            $customer = $this->customerRepository->findByEmail($email);

            if ($customer) {
                $storedPassword = (string)$customer->getPassword();
                $isHashedPasswordValid = password_verify($password, $storedPassword);
                $isLegacyPlaintextValid = hash_equals($storedPassword, $password);

                if (!$isHashedPasswordValid && !$isLegacyPlaintextValid) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid credentials']);
                    return;
                }

                // Migrate legacy plaintext password to hash on successful login.
                if ($isLegacyPlaintextValid) {
                    $customer->setPassword(password_hash($password, PASSWORD_DEFAULT));
                }

                // Update last login (optional but good practice)
                $customer->setLastLogin(date('Y-m-d H:i:s'));
                $this->customerRepository->update($customer); 

                // Generate JWT
                $payload = [
                    'user_id' => $customer->getId(),
                    'user_type' => 'customer'
                ];
                $jwt = JwtHelper::encode($payload);

                // Set JWT as an HttpOnly cookie
                setcookie('auth_token', $jwt, CookieHelper::getAuthCookieOptions(time() + (86400 * 30)));

                http_response_code(200);
                echo json_encode([
                    'message' => 'Login successful',
                    'customer' => [
                        'id' => $customer->getId(),
                        'first_name' => $customer->getFirstName(),
                        'last_name' => $customer->getLastName(),
                        'email' => $customer->getEmail(),
                        'phone' => $customer->getPhone(),
                        'address' => $customer->getAddress(),
                        'last_login' => $customer->getLastLogin()
                    ]
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
