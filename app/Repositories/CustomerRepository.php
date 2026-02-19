<?php

namespace App\Repositories;

use App\Models\CustomerModel;
use PDO;

class CustomerRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getById(int $id): ?CustomerModel
    {
        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, email, password, phone, address, created_at, last_login FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new CustomerModel(
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['password'],
            $row['phone'],
            $row['address'],
            $row['created_at'],
            $row['last_login']
        );
    }

    public function findByEmail(string $email): ?CustomerModel
    {
        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, email, password, phone, address, created_at, last_login FROM customers WHERE LOWER(email) = LOWER(:email)');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new CustomerModel(
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['password'],
            $row['phone'],
            $row['address'],
            $row['created_at'],
            $row['last_login']
        );
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, first_name, last_name, email, password, phone, address, created_at, last_login FROM customers ORDER BY id ASC');
        $customers = [];
        try {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $customers[] = new CustomerModel(
                    $row['id'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['password'],
                    $row['phone'],
                    $row['address'],
                    $row['created_at'],
                    $row['last_login']
                );
            }
            return $customers;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            return [];
        }
    }

    public function create(CustomerModel $customer): bool{
        $sql = "INSERT INTO customers (first_name, last_name, email, password, phone, address) VALUES (:first_name, :last_name, :email, :password, :phone, :address)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':first_name', $customer->getFirstName());
        $stmt->bindValue(':last_name', $customer->getLastName());
        $stmt->bindValue(':email', $customer->getEmail());
        $stmt->bindValue(':password', $customer->getPassword());
        $stmt->bindValue(':phone', $customer->getPhone());
        $stmt->bindValue(':address', $customer->getAddress());

        return $stmt->execute();
    }

    public function update(CustomerModel $customer): bool{
        $sql = "UPDATE customers SET first_name = :first_name, last_name = :last_name, email = :email, password = :password, phone = :phone, address = :address, last_login = :last_login WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':first_name', $customer->getFirstName());
        $stmt->bindValue(':last_name', $customer->getLastName());
        $stmt->bindValue(':email', $customer->getEmail());
        $stmt->bindValue(':password', $customer->getPassword());
        $stmt->bindValue(':phone', $customer->getPhone());
        $stmt->bindValue(':address', $customer->getAddress());
        $stmt->bindValue(':last_login', $customer->getLastLogin());
        $stmt->bindValue(':id', $customer->getId());

        return $stmt->execute();
    }

    public function delete(int $id): bool{
        $sql = "DELETE FROM customers WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }
}
