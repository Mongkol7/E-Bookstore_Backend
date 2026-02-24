<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;

class MailHelper
{
    public static function sendPurchaseAlert(array $order, array $ctx): void
    {
        if (!self::envBool('MAIL_ENABLED', true)) {
            error_log('[mail] Purchase alert skipped: MAIL_ENABLED=false');
            return;
        }

        $recipient = self::envString('PURCHASE_ALERT_TO');
        if ($recipient === '') {
            error_log('[mail] Purchase alert skipped: PURCHASE_ALERT_TO is empty');
            return;
        }

        $mailer = self::buildMailer();
        $subjectPrefix = self::envString('PURCHASE_ALERT_SUBJECT_PREFIX', '[New Purchase]');
        $orderNumber = (string)($order['orderNumber'] ?? 'N/A');
        $orderDate = (string)($order['orderDate'] ?? gmdate('c'));
        $userType = (string)($ctx['user_type'] ?? 'unknown');
        $userId = (string)($ctx['user_id'] ?? '0');
        $shippingAddress = is_array($order['shippingAddress'] ?? null) ? $order['shippingAddress'] : [];
        $items = is_array($order['items'] ?? null) ? $order['items'] : [];

        $shippingName = (string)($shippingAddress['name'] ?? '');
        $shippingEmail = (string)($shippingAddress['email'] ?? '');
        $shippingPhone = (string)($shippingAddress['phone'] ?? '');

        $subject = trim($subjectPrefix . ' ' . $orderNumber);
        $mailer->addAddress($recipient);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = self::buildHtmlBody(
            $orderNumber,
            $orderDate,
            $userType,
            $userId,
            $shippingName,
            $shippingEmail,
            $shippingPhone,
            $items,
            $order
        );
        $mailer->AltBody = self::buildTextBody(
            $orderNumber,
            $orderDate,
            $userType,
            $userId,
            $shippingName,
            $shippingEmail,
            $shippingPhone,
            $items,
            $order
        );

        $mailer->send();
    }

    private static function buildMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $host = self::envString('MAIL_HOST');
        if ($host === '') {
            throw new \RuntimeException('MAIL_HOST is required when MAIL_ENABLED=true');
        }

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = self::envInt('MAIL_PORT', 587);
        $mail->Timeout = max(1, self::envInt('MAIL_TIMEOUT', 3));
        $mail->CharSet = 'UTF-8';

        $encryption = strtolower(self::envString('MAIL_ENCRYPTION', 'tls'));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $username = self::envString('MAIL_USERNAME');
        $password = self::envString('MAIL_PASSWORD');
        $mail->SMTPAuth = ($username !== '' || $password !== '');
        $mail->Username = $username;
        $mail->Password = $password;

        $fromAddress = self::envString('MAIL_FROM_ADDRESS', $username !== '' ? $username : 'no-reply@example.com');
        $fromName = self::envString('MAIL_FROM_NAME', 'Ecommerce Store');
        $mail->setFrom($fromAddress, $fromName);

        return $mail;
    }

    private static function buildHtmlBody(
        string $orderNumber,
        string $orderDate,
        string $userType,
        string $userId,
        string $shippingName,
        string $shippingEmail,
        string $shippingPhone,
        array $items,
        array $order
    ): string {
        $subtotal = self::money($order['subtotal'] ?? 0);
        $tax = self::money($order['tax'] ?? 0);
        $shipping = self::money($order['shipping'] ?? 0);
        $total = self::money($order['total'] ?? 0);

        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = htmlspecialchars((string)($item['title'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
            $qty = (int)($item['quantity'] ?? 0);
            $price = self::money($item['price'] ?? 0);
            $rows[] = "<tr><td>{$title}</td><td>{$qty}</td><td>\${$price}</td></tr>";
        }

        $itemsHtml = implode('', $rows);
        if ($itemsHtml === '') {
            $itemsHtml = '<tr><td colspan="3">No items recorded.</td></tr>';
        }

        return
            '<h2>New purchase received</h2>' .
            '<p><strong>Order Number:</strong> ' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Order Date (UTC):</strong> ' . htmlspecialchars($orderDate, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>User:</strong> ' . htmlspecialchars($userType, ENT_QUOTES, 'UTF-8') . ' #' . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Shipping Name:</strong> ' . htmlspecialchars($shippingName, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Shipping Email:</strong> ' . htmlspecialchars($shippingEmail, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<p><strong>Shipping Phone:</strong> ' . htmlspecialchars($shippingPhone, ENT_QUOTES, 'UTF-8') . '</p>' .
            '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">' .
            '<thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th></tr></thead>' .
            '<tbody>' . $itemsHtml . '</tbody>' .
            '</table>' .
            '<p><strong>Subtotal:</strong> $' . $subtotal . '</p>' .
            '<p><strong>Tax:</strong> $' . $tax . '</p>' .
            '<p><strong>Shipping:</strong> $' . $shipping . '</p>' .
            '<p><strong>Total:</strong> $' . $total . '</p>';
    }

    private static function buildTextBody(
        string $orderNumber,
        string $orderDate,
        string $userType,
        string $userId,
        string $shippingName,
        string $shippingEmail,
        string $shippingPhone,
        array $items,
        array $order
    ): string {
        $lines = [];
        $lines[] = 'New purchase received';
        $lines[] = 'Order Number: ' . $orderNumber;
        $lines[] = 'Order Date (UTC): ' . $orderDate;
        $lines[] = 'User: ' . $userType . ' #' . $userId;
        $lines[] = 'Shipping Name: ' . $shippingName;
        $lines[] = 'Shipping Email: ' . $shippingEmail;
        $lines[] = 'Shipping Phone: ' . $shippingPhone;
        $lines[] = '';
        $lines[] = 'Items:';

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = (string)($item['title'] ?? 'Item');
            $qty = (int)($item['quantity'] ?? 0);
            $price = self::money($item['price'] ?? 0);
            $lines[] = '- ' . $title . ' | Qty: ' . $qty . ' | Unit: $' . $price;
        }

        $lines[] = '';
        $lines[] = 'Subtotal: $' . self::money($order['subtotal'] ?? 0);
        $lines[] = 'Tax: $' . self::money($order['tax'] ?? 0);
        $lines[] = 'Shipping: $' . self::money($order['shipping'] ?? 0);
        $lines[] = 'Total: $' . self::money($order['total'] ?? 0);

        return implode("\n", $lines);
    }

    private static function envString(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $default;
        return trim((string)$value);
    }

    private static function envInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return (int)$value;
    }

    private static function envBool(string $key, bool $default): bool
    {
        $value = $_ENV[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function money($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }
}
