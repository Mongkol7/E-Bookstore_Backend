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
        $htmlBody = self::buildHtmlBody(
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
        $textBody = self::buildTextBody(
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

        $provider = strtolower(self::envString('MAIL_PROVIDER', 'auto'));
        if ($provider === 'auto') {
            $provider = self::envString('RESEND_API_KEY') !== '' ? 'resend' : 'smtp';
        }
        if ($provider === 'resend') {
            self::sendViaResend($recipient, $subject, $htmlBody, $textBody);
            return;
        }

        self::sendViaSmtp($recipient, $subject, $htmlBody, $textBody);
    }

    private static function sendViaSmtp(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        $mailer = self::buildMailer();
        $mailer->addAddress($recipient);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $htmlBody;
        $mailer->AltBody = $textBody;
        $mailer->send();
    }

    private static function sendViaResend(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        $apiKey = self::envString('RESEND_API_KEY');
        if ($apiKey === '') {
            throw new \RuntimeException('RESEND_API_KEY is required when MAIL_PROVIDER=resend');
        }

        $fromAddress = self::envString('MAIL_FROM_ADDRESS');
        if ($fromAddress === '') {
            throw new \RuntimeException('MAIL_FROM_ADDRESS is required when MAIL_PROVIDER=resend');
        }

        $fromName = self::envString('MAIL_FROM_NAME', 'Ecommerce Store');
        $endpoint = self::envString('RESEND_API_URL', 'https://api.resend.com/emails');
        $timeout = max(1, self::envInt('MAIL_TIMEOUT', 20));

        self::postJson(
            $endpoint,
            [
                'from' => trim($fromName . ' <' . $fromAddress . '>'),
                'to' => [$recipient],
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => $textBody,
            ],
            [
                'Authorization: Bearer ' . $apiKey,
            ],
            $timeout
        );
    }

    private static function postJson(string $url, array $payload, array $extraHeaders, int $timeoutSeconds): void
    {
        $json = json_encode($payload);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode email payload');
        }

        $headers = array_merge(
            ['Content-Type: application/json'],
            $extraHeaders
        );

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('Failed to initialize cURL');
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSeconds);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);

            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($responseBody === false) {
                throw new \RuntimeException('Resend API request failed: ' . $curlError);
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException('Resend API error HTTP ' . $statusCode . ': ' . self::truncate((string)$responseBody, 500));
            }
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $json,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $statusCode = 0;
        if (isset($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $matches) === 1) {
                $statusCode = (int)$matches[1];
            }
        }

        if ($responseBody === false) {
            throw new \RuntimeException('Resend API request failed');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('Resend API error HTTP ' . $statusCode . ': ' . self::truncate((string)$responseBody, 500));
        }
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
        $mail->Timeout = max(1, self::envInt('MAIL_TIMEOUT', 20));
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
        $safeOrderNumber = htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8');
        $safeOrderDate = htmlspecialchars($orderDate, ENT_QUOTES, 'UTF-8');
        $safeUser = htmlspecialchars($userType, ENT_QUOTES, 'UTF-8') . ' #' . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8');
        $safeShippingName = htmlspecialchars($shippingName, ENT_QUOTES, 'UTF-8');
        $safeShippingEmail = htmlspecialchars($shippingEmail, ENT_QUOTES, 'UTF-8');
        $safeShippingPhone = htmlspecialchars($shippingPhone, ENT_QUOTES, 'UTF-8');

        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = htmlspecialchars((string)($item['title'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
            $qty = (int)($item['quantity'] ?? 0);
            $price = self::money($item['price'] ?? 0);
            $rows[] =
                '<tr>' .
                '<td style="padding:10px 12px;border-bottom:1px solid #233147;color:#e2e8f0;">' . $title . '</td>' .
                '<td style="padding:10px 12px;border-bottom:1px solid #233147;color:#e2e8f0;text-align:center;">' . $qty . '</td>' .
                '<td style="padding:10px 12px;border-bottom:1px solid #233147;color:#e2e8f0;text-align:right;">$' . $price . '</td>' .
                '</tr>';
        }

        $itemsHtml = implode('', $rows);
        if ($itemsHtml === '') {
            $itemsHtml = '<tr><td colspan="3" style="padding:12px;color:#cbd5e1;text-align:center;">No items recorded.</td></tr>';
        }

        return
            '<!doctype html>' .
            '<html><body style="margin:0;padding:0;background:#0b1220;font-family:Segoe UI,Arial,sans-serif;color:#e2e8f0;">' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0b1220;padding:24px 12px;">' .
            '<tr><td align="center">' .
            '<table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#111827;border:1px solid #233147;border-radius:16px;overflow:hidden;">' .
            '<tr><td style="padding:22px 24px;background:linear-gradient(90deg,#0f766e,#0ea5a3);">' .
            '<div style="font-size:20px;font-weight:700;color:#ecfeff;">&#x1F6D2; New Purchase Alert</div>' .
            '<div style="margin-top:4px;font-size:13px;color:#ccfbf1;">A new order was placed on E-Bookstore.</div>' .
            '</td></tr>' .
            '<tr><td style="padding:20px 24px;">' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #233147;border-radius:12px;background:#0f172a;">' .
            '<tr><td style="padding:14px 16px;font-size:14px;color:#cbd5e1;">' .
            '<div><strong style="color:#f8fafc;">&#x1F522; Order:</strong> ' . $safeOrderNumber . '</div>' .
            '<div style="margin-top:6px;"><strong style="color:#f8fafc;">&#x1F552; Date (UTC):</strong> ' . $safeOrderDate . '</div>' .
            '<div style="margin-top:6px;"><strong style="color:#f8fafc;">&#x1F464; User:</strong> ' . $safeUser . '</div>' .
            '</td></tr></table>' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:14px;border:1px solid #233147;border-radius:12px;background:#0f172a;">' .
            '<tr><td style="padding:14px 16px;font-size:14px;color:#cbd5e1;">' .
            '<div style="font-weight:600;color:#f8fafc;margin-bottom:8px;">&#x1F4CD; Shipping Contact</div>' .
            '<div><strong style="color:#f8fafc;">Name:</strong> ' . $safeShippingName . '</div>' .
            '<div style="margin-top:6px;"><strong style="color:#f8fafc;">Email:</strong> ' . $safeShippingEmail . '</div>' .
            '<div style="margin-top:6px;"><strong style="color:#f8fafc;">Phone:</strong> ' . $safeShippingPhone . '</div>' .
            '</td></tr></table>' .
            '<div style="margin-top:18px;font-size:15px;font-weight:600;color:#f8fafc;">&#x1F4E6; Order Items</div>' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:10px;border:1px solid #233147;border-radius:12px;overflow:hidden;background:#0f172a;">' .
            '<thead><tr>' .
            '<th style="padding:10px 12px;text-align:left;background:#172033;color:#a5f3fc;font-size:12px;letter-spacing:0.4px;text-transform:uppercase;">Item</th>' .
            '<th style="padding:10px 12px;text-align:center;background:#172033;color:#a5f3fc;font-size:12px;letter-spacing:0.4px;text-transform:uppercase;">Qty</th>' .
            '<th style="padding:10px 12px;text-align:right;background:#172033;color:#a5f3fc;font-size:12px;letter-spacing:0.4px;text-transform:uppercase;">Unit Price</th>' .
            '</tr></thead>' .
            '<tbody>' . $itemsHtml . '</tbody>' .
            '</table>' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:16px;border:1px solid #233147;border-radius:12px;background:#0f172a;">' .
            '<tr><td style="padding:14px 16px;font-size:14px;color:#cbd5e1;">' .
            '<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Subtotal</span><strong style="color:#f8fafc;">$' . $subtotal . '</strong></div>' .
            '<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Tax</span><strong style="color:#f8fafc;">$' . $tax . '</strong></div>' .
            '<div style="display:flex;justify-content:space-between;margin-bottom:10px;"><span>Shipping</span><strong style="color:#f8fafc;">$' . $shipping . '</strong></div>' .
            '<div style="border-top:1px solid #233147;padding-top:10px;display:flex;justify-content:space-between;">' .
            '<span style="font-size:15px;font-weight:700;color:#f8fafc;">&#x1F4B0; Total</span>' .
            '<span style="font-size:17px;font-weight:700;color:#2dd4bf;">$' . $total . '</span>' .
            '</div>' .
            '</td></tr></table>' .
            '<div style="margin-top:16px;font-size:12px;color:#94a3b8;">Automated notification from E-Bookstore mail queue worker.</div>' .
            '</td></tr></table>' .
            '</td></tr></table>' .
            '</body></html>';
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
        $lines[] = '🛒 New Purchase Alert';
        $lines[] = 'Order Number: ' . $orderNumber;
        $lines[] = 'Order Date (UTC): ' . $orderDate;
        $lines[] = 'User: ' . $userType . ' #' . $userId;
        $lines[] = 'Shipping Name: ' . $shippingName;
        $lines[] = 'Shipping Email: ' . $shippingEmail;
        $lines[] = 'Shipping Phone: ' . $shippingPhone;
        $lines[] = '';
        $lines[] = '📦 Items:';

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
        $lines[] = '💰 Total: $' . self::money($order['total'] ?? 0);

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

    private static function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, $max) . '...';
    }
}
