<?php

namespace App\Repositories;

use App\Models\AdminModel;
use PDO;

class AdminRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getById(int $id): ?AdminModel
    {
        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, email, password, phone, address, created_at, last_login FROM admins WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new AdminModel(
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

    public function findByEmail(string $email): ?AdminModel
    {
        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, email, password, phone, address, created_at, last_login FROM admins WHERE LOWER(email) = LOWER(:email)');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new AdminModel(
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
        $stmt = $this->pdo->query('SELECT id, first_name, last_name, email, password, phone, address, created_at, last_login FROM admins ORDER BY id ASC');
        $admins = [];
        try {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $admins[] = new AdminModel(
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
            return $admins;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            return [];
        }
    }

    public function create(AdminModel $admin): bool{
        $sql = "INSERT INTO admins (first_name, last_name, email, password, phone, address) VALUES (:first_name, :last_name, :email, :password, :phone, :address)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':first_name', $admin->getFirstName());
        $stmt->bindValue(':last_name', $admin->getLastName());
        $stmt->bindValue(':email', $admin->getEmail());
        $stmt->bindValue(':password', $admin->getPassword());
        $stmt->bindValue(':phone', $admin->getPhone());
        $stmt->bindValue(':address', $admin->getAddress());

        return $stmt->execute();
    }

    public function update(AdminModel $admin): bool{
        $sql = "UPDATE admins SET first_name = :first_name, last_name = :last_name, email = :email, password = :password, phone = :phone, address = :address, last_login = :last_login WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':first_name', $admin->getFirstName());
        $stmt->bindValue(':last_name', $admin->getLastName());
        $stmt->bindValue(':email', $admin->getEmail());
        $stmt->bindValue(':password', $admin->getPassword());
        $stmt->bindValue(':phone', $admin->getPhone());
        $stmt->bindValue(':address', $admin->getAddress());
        $stmt->bindValue(':last_login', $admin->getLastLogin());
        $stmt->bindValue(':id', $admin->getId());

        return $stmt->execute();
    }

    public function delete(int $id): bool{
        $sql = "DELETE FROM admins WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }
}
