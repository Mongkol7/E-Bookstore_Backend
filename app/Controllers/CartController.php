<?php

namespace App\Controllers;

class CartController
{
    private $pdo;
    private ?string $salesColumn = null;

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
        $sql = "SELECT b.id, b.title, b.price, b.stock, b.image, a.name AS author_name, c.name AS category_name
                FROM books b
                LEFT JOIN authors a ON a.id = b.author_id
                LEFT JOIN categories c ON c.id = b.category_id
                WHERE b.id = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $bookId]);
        $book = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $book ?: null;
    }

    private function reduceBookStock(int $bookId, int $quantity): bool
    {
        $sql = "UPDATE books SET stock = stock - :quantity WHERE id = :id AND stock >= :quantity";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $bookId,
            'quantity' => $quantity,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function resolveSalesColumn(): ?string
    {
        if ($this->salesColumn !== null) {
            return $this->salesColumn;
        }

        try {
            $stmt = $this->pdo->query(
                "SELECT column_name FROM information_schema.columns WHERE table_name = 'books' AND column_name IN ('sales_count','sold')"
            );
            $columns = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (in_array('sales_count', $columns, true)) {
                $this->salesColumn = 'sales_count';
                return $this->salesColumn;
            }
            if (in_array('sold', $columns, true)) {
                $this->salesColumn = 'sold';
                return $this->salesColumn;
            }
        } catch (\Exception $e) {
            // Ignore schema lookup errors and fallback to no sales update.
        }

        return null;
    }

    private function increaseBookSales(int $bookId, int $quantity): void
    {
        $salesColumn = $this->resolveSalesColumn();
        if ($salesColumn === null) {
            return;
        }

        $sql = "UPDATE books SET {$salesColumn} = COALESCE({$salesColumn}, 0) + :quantity WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $bookId,
            'quantity' => $quantity,
        ]);
    }

    private function hydrateCartItems(array $items): array
    {
        $hydrated = [];

        foreach ($items as $item) {
            $bookId = (int)($item['id'] ?? 0);
            if ($bookId > 0) {
                $book = $this->getBookForCart($bookId);
                if ($book) {
                    if (
                        !isset($item['category']) ||
                        (string)$item['category'] === '' ||
                        (string)$item['category'] === 'Unknown Category'
                    ) {
                        $item['category'] = (string)($book['category_name'] ?? 'Unknown Category');
                    }

                    $item['stock'] = (int)($book['stock'] ?? 0);
                }
            } elseif (!isset($item['stock'])) {
                $item['stock'] = 0;
            }

            $hydrated[] = $item;
        }

        return $hydrated;
    }

    private function hydrateOrderItems(array $items): array
    {
        return $this->hydrateCartItems($items);
    }

    private function hydrateOrders(array $orders): array
    {
        $hydrated = [];

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $order['items'] = $this->hydrateOrderItems(is_array($order['items'] ?? null) ? $order['items'] : []);
            $hydrated[] = $order;
        }

        return $hydrated;
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
            $store['cart'] = $this->hydrateCartItems($store['cart']);
            $this->saveUserStore($ctx, $store);
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
                    $newQuantity = max(1, (int)($item['quantity'] ?? 1) + $quantity);
                    if ($newQuantity > (int)($book['stock'] ?? 0)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Requested quantity exceeds available stock']);
                        return;
                    }
                    $item['quantity'] = $newQuantity;
                    if (!isset($item['category']) || (string)$item['category'] === '') {
                        $item['category'] = (string)($book['category_name'] ?? 'Unknown Category');
                    }
                    $item['stock'] = (int)($book['stock'] ?? 0);
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                if ($quantity > (int)($book['stock'] ?? 0)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Requested quantity exceeds available stock']);
                    return;
                }
                $store['cart'][] = [
                    'id' => (int)$book['id'],
                    'title' => (string)$book['title'],
                    'author' => (string)($book['author_name'] ?? 'Unknown Author'),
                    'category' => (string)($book['category_name'] ?? 'Unknown Category'),
                    'price' => (float)$book['price'],
                    'stock' => (int)($book['stock'] ?? 0),
                    'quantity' => $quantity,
                    'imageUrl' => (string)($book['image'] ?? ''),
                    'added_at' => gmdate('c'),
                ];
            }

            $store['cart'] = $this->hydrateCartItems($store['cart']);
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
                    $book = $this->getBookForCart($bookId);
                    if (!$book) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Book not found']);
                        return;
                    }
                    if ($quantity > (int)($book['stock'] ?? 0)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Requested quantity exceeds available stock']);
                        return;
                    }
                    $item['quantity'] = $quantity;
                    $item['stock'] = (int)($book['stock'] ?? 0);
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

            $store['cart'] = $this->hydrateCartItems($store['cart']);
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
            $store['cart'] = $this->hydrateCartItems($store['cart']);
            $items = $store['cart'];

            if (count($items) === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cart is empty']);
                return;
            }

            foreach ($items as $item) {
                $bookId = (int)($item['id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                if ($bookId <= 0 || $quantity <= 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid cart item']);
                    return;
                }

                $book = $this->getBookForCart($bookId);
                if (!$book) {
                    http_response_code(404);
                    echo json_encode(['error' => "Book not found for cart item #{$bookId}"]);
                    return;
                }

                if ($quantity > (int)($book['stock'] ?? 0)) {
                    http_response_code(400);
                    echo json_encode(['error' => "Insufficient stock for {$book['title']}"]);
                    return;
                }
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

            $this->pdo->beginTransaction();
            foreach ($items as $item) {
                $bookId = (int)($item['id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                if (!$this->reduceBookStock($bookId, $quantity)) {
                    $this->pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['error' => "Insufficient stock for {$item['title']}"]);
                    return;
                }
                $this->increaseBookSales($bookId, $quantity);
            }

            array_unshift($store['orders'], $order);
            $store['cart'] = [];
            $this->saveUserStore($ctx, $store);
            $this->pdo->commit();

            echo json_encode([
                'message' => 'Order placed successfully',
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
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
            $store['orders'] = $this->hydrateOrders($store['orders']);
            $this->saveUserStore($ctx, $store);
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
            $store['orders'] = $this->hydrateOrders($store['orders']);
            $this->saveUserStore($ctx, $store);
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
