<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\BookController;
use App\Controllers\AuthorController;
use App\Controllers\CategoryController;
use App\Controllers\CustomerController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Middleware\AuthMiddleware;

$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptBasePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptBasePath = rtrim($scriptBasePath, '/');
if ($scriptBasePath !== '' && $scriptBasePath !== '.') {
    if (strpos($request_path, $scriptBasePath) === 0) {
        $request_path = substr($request_path, strlen($scriptBasePath));
    }
}
$request_path = '/' . ltrim($request_path, '/');
$request_method = $_SERVER['REQUEST_METHOD']; // Get request method

// Authentication middleware disabled

if ($request_path === '') {
    header('Content-Type: application/json');
    try {
        // Attempt to connect to the database to verify connectivity
        require __DIR__ . '/../config/database.php';
        echo json_encode(['status' => 'success', 'message' => 'Database connected successfully']);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    }

// --- API Routes ---

// Books
} elseif ($request_path === '/api/books') {
    $controller = new BookController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getAllBooks();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/books/post') {
    AuthMiddleware::authenticate();
    $controller = new BookController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->saveBook();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/books/put') {
    AuthMiddleware::authenticate();
    $controller = new BookController();
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller->updateBook();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/books/delete') {
    AuthMiddleware::authenticate();
    $controller = new BookController();
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller->deleteBook();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }

// Authors
} elseif ($request_path === '/api/authors') { // This block handles GET for Authors
    AuthMiddleware::authenticate();
    $controller = new AuthorController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getAllAuthors();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/authors/post') { // For POST /api/authors/post
    AuthMiddleware::authenticate();
    $controller = new AuthorController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->createAuthor();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/authors/put') { // For PUT /api/authors/put
    AuthMiddleware::authenticate();
    $controller = new AuthorController();
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller->updateAuthor();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/authors/delete') { // For DELETE /api/authors/delete
    AuthMiddleware::authenticate();
    $controller = new AuthorController();
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller->deleteAuthor();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }

// Categories
} elseif ($request_path === '/api/categories') { // This block handles GET for Categories
    AuthMiddleware::authenticate();
    $controller = new CategoryController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getAllCategories();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/categories/post') { // New block for POST /api/categories/post
    AuthMiddleware::authenticate();
    $controller = new CategoryController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->createCategory();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/categories/put') {
    AuthMiddleware::authenticate();
    $controller = new CategoryController();
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller->updateCategory();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/categories/delete') {
    AuthMiddleware::authenticate();
    $controller = new CategoryController();
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller->deleteCategory();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }

// Customers
} elseif ($request_path === '/api/customers') { // This block handles GET for Customers
    AuthMiddleware::authenticate();
    $controller = new CustomerController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getAllCustomers();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/customers/post') { // New block for POST /api/customers/post
    $controller = new CustomerController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->createCustomer();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/customers/put') {
    AuthMiddleware::authenticate();
    $controller = new CustomerController();
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller->updateCustomer();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/customers/delete') {
    AuthMiddleware::authenticate();
    $controller = new CustomerController();
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller->deleteCustomer();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
// Admins
} elseif ($request_path === '/api/admins') { // This block handles GET for Admins
    AuthMiddleware::authenticate();
    $controller = new AdminController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getAllAdmins();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/admins/post') { // New block for POST /api/admins/post
    $controller = new AdminController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->createAdmin();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/admins/put') {
    AuthMiddleware::authenticate();
    $controller = new AdminController();
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller->updateAdmin();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/admins/delete') {
    AuthMiddleware::authenticate();
    $controller = new AdminController();
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller->deleteAdmin();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
// GET Book by ID
} elseif (preg_match('/^\/api\/books\/(\d+)$/', $request_path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new BookController();
    $controller->getBookById($matches[1]);

// GET Author by ID
} elseif (preg_match('/^\/api\/authors\/(\d+)$/', $request_path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    AuthMiddleware::authenticate();
    $controller = new AuthorController();
    $controller->getAuthorById($matches[1]);

// GET Category by ID
} elseif (preg_match('/^\/api\/categories\/(\d+)$/', $request_path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    AuthMiddleware::authenticate();
    $controller = new CategoryController();
    $controller->getCategoryById($matches[1]);

// GET Customer by ID
} elseif (preg_match('/^\/api\/customers\/(\d+)$/', $request_path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    AuthMiddleware::authenticate();
    $controller = new CustomerController();
    $controller->getCustomerById($matches[1]);

// GET Admin by ID
} elseif (preg_match('/^\/api\/admins\/(\d+)$/', $request_path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    AuthMiddleware::authenticate();
    $controller = new AdminController();
    $controller->getAdminById($matches[1]);

// Authentication
} elseif ($request_path === '/api/customers/login') {
    $controller = new CustomerController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->loginCustomer();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/admins/login') {
    $controller = new AdminController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->loginAdmin();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/login') {
    $controller = new AuthController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->login();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/auth/profile') {
    AuthMiddleware::authenticate();
    $controller = new AuthController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getProfile();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/logout') {
    AuthMiddleware::authenticate(); // Ensure only authenticated users can logout
    $controller = new AuthController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->logout();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/cart') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getCart();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/cart/add') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->addToCart();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/cart/quantity') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $controller->updateQuantity();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/cart/remove') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller->removeFromCart();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/cart/checkout') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->checkout();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/admin/dashboard/orders') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getAdminDashboardOrders();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif ($request_path === '/api/orders') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getOrders();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} elseif (preg_match('/^\/api\/orders\/([^\/]+)$/', $request_path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    AuthMiddleware::authenticate();
    $controller = new CartController();
    $controller->getOrderById($matches[1]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'request_path' => $request_path]);
}
