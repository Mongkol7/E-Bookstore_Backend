<?php

namespace App\Repositories;

use App\Models\BookModel;
use PDO;

class BookRepository
{
    private $pdo;
    private ?bool $hasRatingColumn = null;
    private ?bool $hasSalesCountColumn = null;
    private ?bool $hasSoldColumn = null;

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

    private function booksTableHasSalesCountColumn(): bool
    {
        if ($this->hasSalesCountColumn !== null) {
            return $this->hasSalesCountColumn;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.columns WHERE table_name = 'books' AND column_name = 'sales_count' LIMIT 1"
            );
            $stmt->execute();
            $this->hasSalesCountColumn = (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->hasSalesCountColumn = false;
        }

        return $this->hasSalesCountColumn;
    }

    private function booksTableHasSoldColumn(): bool
    {
        if ($this->hasSoldColumn !== null) {
            return $this->hasSoldColumn;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.columns WHERE table_name = 'books' AND column_name = 'sold' LIMIT 1"
            );
            $stmt->execute();
            $this->hasSoldColumn = (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->hasSoldColumn = false;
        }

        return $this->hasSoldColumn;
    }

    private function getSalesSelectSql(): string
    {
        if ($this->booksTableHasSalesCountColumn()) {
            return ', b.sales_count as sales_count';
        }

        if ($this->booksTableHasSoldColumn()) {
            return ', b.sold as sales_count';
        }

        return '';
    }

    public function getAll(): array
    {
        $ratingSelect = $this->booksTableHasRatingColumn() ? ', b.rating as rating' : '';
        $salesSelect = $this->getSalesSelectSql();
        $stmt = $this->pdo->query("SELECT b.*, a.name as author_name, c.name as category_name{$ratingSelect}{$salesSelect} FROM books b JOIN authors a ON b.author_id = a.id JOIN categories c ON b.category_id = c.id ORDER BY b.id ASC");
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
                    isset($row['rating']) ? (float)$row['rating'] : null,
                    isset($row['sales_count']) ? (int)$row['sales_count'] : null
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
        $salesSelect = $this->getSalesSelectSql();
        $stmt = $this->pdo->prepare("SELECT b.*, a.name as author_name, c.name as category_name{$ratingSelect}{$salesSelect} FROM books b JOIN authors a ON b.author_id = a.id JOIN categories c ON b.category_id = c.id WHERE b.id = :id");
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
            isset($row['rating']) ? (float)$row['rating'] : null,
            isset($row['sales_count']) ? (int)$row['sales_count'] : null
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
