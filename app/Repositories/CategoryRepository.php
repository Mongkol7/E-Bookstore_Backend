<?php

namespace App\Repositories;

use App\Models\CategoryModel;
use PDO;

class CategoryRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getById(int $id): ?CategoryModel
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new CategoryModel(
            $row['id'],
            $row['name']
        );
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM categories ORDER BY id ASC');
        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = new CategoryModel(
                $row['id'],
                $row['name']
            );
        }
        return $categories;
    }
    public function create(CategoryModel $category): bool{
        $sql = "INSERT INTO categories (name) VALUES (:name)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $category->getName());
        return $stmt->execute();
    }

    public function update(CategoryModel $category): bool{
        $sql = "UPDATE categories SET name = :name WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $category->getName());
        $stmt->bindValue(':id', $category->getId());
        return $stmt->execute();
    }
    public function delete(int $id): bool{
        $sql = "DELETE FROM categories WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }
}
