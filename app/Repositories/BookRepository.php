<?php

namespace App\Repositories;

use App\Models\BookModel;
use PDO;

class BookRepository
{
    private $pdo;
    private ?bool $hasRatingColumn = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function booksTableHasRatingColumn(): bool
    {
        if ($this->hasRatingColumn !== null) {
            return $this->hasRatingColumn;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.columns WHERE table_name = 'books' AND column_name = 'rating' LIMIT 1"
            );
            $stmt->execute();
            $this->hasRatingColumn = (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->hasRatingColumn = false;
        }

        return $this->hasRatingColumn;
    }

    public function getAll(): array
    {
        $ratingSelect = $this->booksTableHasRatingColumn() ? ', b.rating as rating' : '';
        $stmt = $this->pdo->query("SELECT b.*, a.name as author_name, c.name as category_name{$ratingSelect} FROM books b JOIN authors a ON b.author_id = a.id JOIN categories c ON b.category_id = c.id ORDER BY b.id ASC");
        $books = [];
        try {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $books[] = new BookModel(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['price'],
                    $row['stock'],
                    $row['author_id'],
                    $row['category_id'],
                    $row['image'],
                    $row['published_date'],
                    $row['author_name'],
                    $row['category_name'],
                    isset($row['rating']) ? (float)$row['rating'] : null
                );
            }
            return $books;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            return [];
        }
    }
    public function getById(int $id): ?BookModel
    {
        $ratingSelect = $this->booksTableHasRatingColumn() ? ', b.rating as rating' : '';
        $stmt = $this->pdo->prepare("SELECT b.*, a.name as author_name, c.name as category_name{$ratingSelect} FROM books b JOIN authors a ON b.author_id = a.id JOIN categories c ON b.category_id = c.id WHERE b.id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new BookModel(
            $row['id'],
            $row['title'],
            $row['description'],
            $row['price'],
            $row['stock'],
            $row['author_id'],
            $row['category_id'],
            $row['image'],
            $row['published_date'],
            $row['author_name'],
            $row['category_name'],
            isset($row['rating']) ? (float)$row['rating'] : null
        );
    }
        public function save(BookModel $book): bool{
            $sql = "CALL createBook(:title, :author_id, :category_id, :price, :stock, :description, :published_date, :image)";
    
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':title', $book->getTitle());
            $stmt->bindValue(':author_id', $book->getAuthorId());
            $stmt->bindValue(':category_id', $book->getCategoryId());
            $stmt->bindValue(':price', $book->getPrice());
            $stmt->bindValue(':stock', $book->getStock());
            $stmt->bindValue(':description', $book->getDescription());
            $stmt->bindValue(':published_date', $book->getPublishedDate());
            $stmt->bindValue(':image', $book->getImage());
            return $stmt->execute();
        }

        public function update(BookModel $book): bool{
            $sql = "CALL updateBook(:id, :title, :author_id, :category_id, :price, :stock, :description, :published_date, :image)";
    
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $book->getId());
            $stmt->bindValue(':title', $book->getTitle());
            $stmt->bindValue(':author_id', $book->getAuthorId());
            $stmt->bindValue(':category_id', $book->getCategoryId());
            $stmt->bindValue(':price', $book->getPrice());
            $stmt->bindValue(':stock', $book->getStock());
            $stmt->bindValue(':description', $book->getDescription());
            $stmt->bindValue(':published_date', $book->getPublishedDate());
            $stmt->bindValue(':image', $book->getImage());
            return $stmt->execute();
        }
        
        public function delete(int $id): bool{
            $sql = "DELETE FROM books WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id);
            return $stmt->execute();
        }
}
