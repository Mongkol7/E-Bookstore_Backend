<?php

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Models\CategoryModel;
use PDO;

class CategoryController
{
    private $categoryRepository;

    public function __construct()
    {
        $pdo = require __DIR__ . '/../../config/database.php';
        $this->categoryRepository = new CategoryRepository($pdo);
    }

    public function getCategoryById($id)
    {
        header('Content-Type: application/json');
        try {
            $category = $this->categoryRepository->getById((int)$id);
            if ($category) {
                echo json_encode($category);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Category not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAllCategories()
    {
        header('Content-Type: application/json');
        try {
            $categories = $this->categoryRepository->getAll();
            echo json_encode($categories);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    public function createCategory(){
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
            echo json_encode(['error' => 'Category name is required']);
            return;
        }

        $category = new CategoryModel(null, $name);

        try {
            $this->categoryRepository->create($category);
            http_response_code(201);
            echo json_encode(['message' => 'Category created successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create category: ' . $e->getMessage()]);
        }
    }

    public function updateCategory(){
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
            echo json_encode(['error' => 'Category ID is required']);
            return;
        }

        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Category name is required']);
            return;
        }

        $category = new CategoryModel($id, $name);

        try {
            $this->categoryRepository->update($category);
            http_response_code(200);
            echo json_encode(['message' => 'Category updated successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update category: ' . $e->getMessage()]);
        }
    }

    public function deleteCategory(){
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
            echo json_encode(['error' => 'Category ID is required']);
            return;
        }

        try {
            if ($this->categoryRepository->delete($id)) {
                http_response_code(200);
                echo json_encode(['message' => 'Category deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Category not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
}
