<?php

namespace App\Controllers;

class CartController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = require __DIR__ . '/../../config/database.php';
    }

    private function getAuthContext(): array
    {
        $authUser = $_SERVER['user'] ?? null;
        if (!is_array($authUser)) {
            throw new \Exception('Unauthorized');
        }

        $userId = (int)($authUser['user_id'] ?? 0);
        $userType = (string)($authUser['user_type'] ?? '');

        if ($userId <= 0 || ($userType !== 'customer' && $userType !== 'admin')) {
            throw new \Exception('Invalid authentication context');
        }

        if ($userType === 'customer') {
            return [
                'table' => 'customers',
                'column' => 'order_history',
                'user_id' => $userId,
                'user_type' => $userType,
            ];
        }

        return [
            'table' => 'admins',
            'column' => 'processed_orders',
            'user_id' => $userId,
            'user_type' => $userType,
        ];
    }

    private function loadUserStore(array $ctx): array
    {
        $sql = "SELECT {$ctx['column']} AS payload FROM {$ctx['table']} WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $ctx['user_id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \Exception('User not found');
        }

        $decoded = json_decode($row['payload'] ?? '[]', true);

        // New format: { cart: [...], orders: [...] }
        if (is_array($decoded) && array_key_exists('cart', $decoded) && array_key_exists('orders', $decoded)) {
            return [
                'cart' => is_array($decoded['cart']) ? array_values($decoded['cart']) : [],
                'orders' => is_array($decoded['orders']) ? array_values($decoded['orders']) : [],
            ];
        }

        // Backward compatibility:
        // - old cart-only array format in the same JSONB field.
        if (is_array($decoded)) {
            $first = $decoded[0] ?? null;
            $looksLikeOrder = is_array($first) && (
                array_key_exists('orderNumber', $first) ||
                array_key_exists('shippingAddress', $first) ||
                array_key_exists('orderDate', $first)
            );

            if ($looksLikeOrder) {
                return ['cart' => [], 'orders' => array_values($decoded)];
            }

            return ['cart' => array_values($decoded), 'orders' => []];
        }

        return ['cart' => [], 'orders' => []];
    }

    private function saveUserStore(array $ctx, array $store): void
    {
        $payload = [
            'cart' => array_values($store['cart'] ?? []),
            'orders' => array_values($store['orders'] ?? []),
        ];

        $sql = "UPDATE {$ctx['table']} SET {$ctx['column']} = :payload::jsonb WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'payload' => json_encode($payload),
            'id' => $ctx['user_id'],
        ]);
    }

    private function getBookForCart(int $bookId): ?array
    {
        $sql = "SELECT b.id, b.title, b.price, b.image, a.name AS author_name
                FROM books b
                LEFT JOIN authors a ON a.id = b.author_id
                WHERE b.id = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $bookId]);
        $book = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $book ?: null;
    }

    private function normalizeShippingAddress(array $payload): array
    {
        $input = is_array($payload['shippingAddress'] ?? null) ? $payload['shippingAddress'] : [];
        return [
            'name' => (string)($input['name'] ?? ''),
            'street' => (string)($input['street'] ?? ''),
            'city' => (string)($input['city'] ?? ''),
            'state' => (string)($input['state'] ?? ''),
            'zipCode' => (string)($input['zipCode'] ?? ''),
            'country' => (string)($input['country'] ?? 'United States'),
            'phone' => (string)($input['phone'] ?? ''),
            'email' => (string)($input['email'] ?? ''),
        ];
    }

    public function getCart()
    {
        header('Content-Type: application/json');
        try {
            $ctx = $this->getAuthContext();
            $store = $this->loadUserStore($ctx);
            echo json_encode([
                'items' => $store['cart'],
                'user_type' => $ctx['user_type'],
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function addToCart()
    {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        $bookId = (int)($payload['book_id'] ?? 0);
        $quantity = max(1, (int)($payload['quantity'] ?? 1));

        if ($bookId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'book_id is required']);
            return;
        }

        try {
            $ctx = $this->getAuthContext();
            $store = $this->loadUserStore($ctx);
            $book = $this->getBookForCart($bookId);
            if (!$book) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
                return;
            }

            $found = false;
            foreach ($store['cart'] as &$item) {
                if ((int)($item['id'] ?? 0) === $bookId) {
                    $item['quantity'] = max(1, (int)($item['quantity'] ?? 1) + $quantity);
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                $store['cart'][] = [
                    'id' => (int)$book['id'],
                    'title' => (string)$book['title'],
                    'author' => (string)($book['author_name'] ?? 'Unknown Author'),
                    'price' => (float)$book['price'],
                    'quantity' => $quantity,
                    'imageUrl' => (string)($book['image'] ?? ''),
                    'added_at' => gmdate('c'),
                ];
            }

            $this->saveUserStore($ctx, $store);
            echo json_encode(['message' => 'Added to cart', 'items' => $store['cart']]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function updateQuantity()
    {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        $bookId = (int)($payload['book_id'] ?? 0);
        $quantity = (int)($payload['quantity'] ?? 0);

        if ($bookId <= 0 || $quantity < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'book_id and quantity (>=1) are required']);
            return;
        }

        try {
            $ctx = $this->getAuthContext();
            $store = $this->loadUserStore($ctx);
            $updated = false;
            foreach ($store['cart'] as &$item) {
                if ((int)($item['id'] ?? 0) === $bookId) {
                    $item['quantity'] = $quantity;
                    $updated = true;
                    break;
                }
            }
            unset($item);

            if (!$updated) {
                http_response_code(404);
                echo json_encode(['error' => 'Item not found in cart']);
                return;
            }

            $this->saveUserStore($ctx, $store);
            echo json_encode(['message' => 'Cart updated', 'items' => $store['cart']]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function removeFromCart()
    {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);
        $bookId = (int)($payload['book_id'] ?? 0);

        if ($bookId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'book_id is required']);
            return;
        }

        try {
            $ctx = $this->getAuthContext();
            $store = $this->loadUserStore($ctx);
            $store['cart'] = array_values(
                array_filter(
                    $store['cart'],
                    static fn($item) => (int)($item['id'] ?? 0) !== $bookId
                )
            );
            $this->saveUserStore($ctx, $store);
            echo json_encode(['message' => 'Item removed', 'items' => $store['cart']]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function checkout()
    {
        header('Content-Type: application/json');
        $payload = json_decode(file_get_contents('php://input'), true);

        try {
            $ctx = $this->getAuthContext();
            $store = $this->loadUserStore($ctx);
            $items = $store['cart'];

            if (count($items) === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cart is empty']);
                return;
            }

            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0);
            }
            $tax = $subtotal * 0.10;
            $shipping = $subtotal > 50 ? 0.0 : 5.99;
            $total = $subtotal + $tax + $shipping;

            $order = [
                'id' => (string)(round(microtime(true) * 1000) . random_int(100, 999)),
                'orderNumber' => 'ORD-' . gmdate('Ymd-His') . '-' . random_int(100, 999),
                'orderDate' => gmdate('c'),
                'status' => 'processing',
                'items' => array_values($items),
                'subtotal' => round($subtotal, 2),
                'tax' => round($tax, 2),
                'shipping' => round($shipping, 2),
                'discount' => 0,
                'total' => round($total, 2),
                'shippingAddress' => $this->normalizeShippingAddress(is_array($payload) ? $payload : []),
                'paymentMethod' => (string)($payload['paymentMethod'] ?? 'Card'),
                'paymentStatus' => 'paid',
                'timeline' => [
                    [
                        'status' => 'Order Placed',
                        'date' => gmdate('c'),
                        'completed' => true,
                        'icon' => 'check',
                    ],
                ],
            ];

            array_unshift($store['orders'], $order);
            $store['cart'] = [];
            $this->saveUserStore($ctx, $store);

            echo json_encode([
                'message' => 'Order placed successfully',
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getOrders()
    {
        header('Content-Type: application/json');
        try {
            $ctx = $this->getAuthContext();
            $store = $this->loadUserStore($ctx);
            echo json_encode(['orders' => $store['orders']]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getOrderById($orderId)
    {
        header('Content-Type: application/json');
        try {
            $ctx = $this->getAuthContext();
            $store = $this->loadUserStore($ctx);
            foreach ($store['orders'] as $order) {
                if ((string)($order['id'] ?? '') === (string)$orderId) {
                    echo json_encode(['order' => $order]);
                    return;
                }
            }
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
