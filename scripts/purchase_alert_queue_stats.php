#!/usr/bin/env php
<?php
declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../config/database.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query(
    "SELECT
        COUNT(*) FILTER (WHERE status = 'pending') AS pending_count,
        COUNT(*) FILTER (WHERE status = 'sending') AS sending_count,
        COUNT(*) FILTER (WHERE status = 'failed') AS failed_count,
        COUNT(*) FILTER (WHERE status = 'sent') AS sent_count,
        COALESCE(
            EXTRACT(EPOCH FROM (
                NOW() - MIN(CASE WHEN status = 'pending' THEN next_attempt_at END)
            )),
            0
        ) AS oldest_pending_age_seconds
     FROM purchase_alert_outbox"
);

$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$output = [
    'pending_count' => (int)($row['pending_count'] ?? 0),
    'sending_count' => (int)($row['sending_count'] ?? 0),
    'failed_count' => (int)($row['failed_count'] ?? 0),
    'sent_count' => (int)($row['sent_count'] ?? 0),
    'oldest_pending_age_seconds' => (int)round((float)($row['oldest_pending_age_seconds'] ?? 0)),
    'generated_at_utc' => gmdate('c'),
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
