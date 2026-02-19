<?php

namespace App\Controllers;

use App\Repositories\BookRepository;
use App\Models\BookModel;
use PDO;

class BookController
{
    private $bookRepository;

    public function __construct()
    {
        $pdo = require __DIR__ . '/../../config/database.php';
        $this->bookRepository = new BookRepository($pdo);
    }

    public function getBookById($id)
    {
        header('Content-Type: application/json');
        try {
            $book = $this->bookRepository->getById((int)$id);
            if ($book) {
                echo json_encode($book);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAllBooks()
    {
        header('Content-Type: application/json');
        try {
            $books = $this->bookRepository->getAll();
            echo json_encode($books);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    public function saveBook(): bool{
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return false;
        }

        $book = new BookModel(
            null,
            $data['title'],
            $data['description'],
            $data['price'],
            $data['stock'],
            $data['author_id'],
            $data['category_id'],
            $data['image'],
            $data['published_date']
            );
        try {
            $this->bookRepository->save($book);
            http_response_code(201);
            echo json_encode(['message' => 'Book created successfully']);
            return true;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            return false;
        }
    }

    public function updateBook(){
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
            echo json_encode(['error' => 'Book ID is required for update']);
            return;
        }

        $book = new BookModel(
            $id,
            $data['title'],
            $data['description'],
            $data['price'],
            $data['stock'],
            $data['author_id'],
            $data['category_id'],
            $data['image'],
            $data['published_date']
        );

        try {
            $this->bookRepository->update($book);
            http_response_code(200);
            echo json_encode(['message' => 'Book updated successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update book: ' . $e->getMessage()]);
        }
    }
    
    public function deleteBook(){
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
            echo json_encode(['error' => 'Book ID is required for delete']);
            return;
        }

        try {
            if ($this->bookRepository->delete($id)) {
                http_response_code(200);
                echo json_encode(['message' => 'Book deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete book: ' . $e->getMessage()]);
        }
    }
}