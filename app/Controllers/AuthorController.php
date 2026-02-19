<?php

namespace App\Controllers;

use App\Repositories\AuthorRepository;
use App\Models\AuthorModel;
use PDO;

class AuthorController
{
    private $authorRepository;

    public function __construct()
    {
        $pdo = require __DIR__ . '/../../config/database.php';
        $this->authorRepository = new AuthorRepository($pdo);
    }

    public function getAuthorById($id)
    {
        header('Content-Type: application/json');
        try {
            $author = $this->authorRepository->getById((int)$id);
            if ($author) {
                echo json_encode($author);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Author not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAllAuthors()
    {
        header('Content-Type: application/json');
        try {
            $authors = $this->authorRepository->getAll();
            echo json_encode($authors);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    public function createAuthor(){
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $name = $data['name'] ?? null;
        
        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Author name is required']);
            return;
        }

        $author = new AuthorModel(null, $name, $data['bio'] ?? null);

        try {
            $this->authorRepository->create($author);
            http_response_code(201);
            echo json_encode(['message' => 'Author created successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create author: ' . $e->getMessage()]);
        }
    }

    public function updateAuthor(){
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Author ID is required']);
            return;
        }

        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Author name is required']);
            return;
        }

        $author = new AuthorModel($id, $name, $data['bio'] ?? null);

        try {
            $this->authorRepository->update($author);
            http_response_code(200);
            echo json_encode(['message' => 'Author updated successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update author: ' . $e->getMessage()]);
        }
    }
    public function deleteAuthor(){
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
            echo json_encode(['error' => 'Author ID is required']);
            return;
        }

        try {
            if ($this->authorRepository->delete($id)) {
                http_response_code(200);
                echo json_encode(['message' => 'Author deleted successfully']);
            } else {
                http_response_code(404); // Not Found, if the id did not exist
                echo json_encode(['error' => 'Author not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
