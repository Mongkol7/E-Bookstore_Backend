<?php

namespace App\Repositories;

use App\Models\AuthorModel;
use PDO;

class AuthorRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM authors ORDER BY id ASC');
        $authors = [];
        try {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $authors[] = new AuthorModel(
                    $row['id'],
                    $row['name'],
                    $row['bio']
                );
            }
            return $authors;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            return [];
        }
    }
    public function getById(int $id): ?AuthorModel
    {
        $stmt = $this->pdo->prepare('SELECT * FROM authors WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new AuthorModel(
            $row['id'],
            $row['name'],
            $row['bio']
        );
    }

    public function create(AuthorModel $author): bool{
        $sql = "INSERT INTO authors (name, bio) VALUES (:name, :bio)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $author->getName());
        $stmt->bindValue(':bio', $author->getBio());
        return $stmt->execute();
    }

    public function update(AuthorModel $author): bool{
        $sql = "UPDATE authors SET name = :name, bio = :bio WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':name', $author->getName());
        $stmt->bindValue(':bio', $author->getBio());
        $stmt->bindValue(':id', $author->getId());
        return $stmt->execute();
    }
    public function delete(int $id): bool{
        $sql = "DELETE FROM authors WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }
}
