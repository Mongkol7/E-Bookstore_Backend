#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Helpers\MailHelper;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$options = getopt('', ['limit::', 'max-attempts::']);
$limit = max(1, (int)($options['limit'] ?? 20));
$maxAttempts = max(1, (int)($options['max-attempts'] ?? 6));

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../config/database.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function queueLog(string $message): void
{
    $line = sprintf("[mail-queue] %s %s\n", gmdate('c'), $message);
    fwrite(STDOUT, $line);
}

/**
 * @return array<int, array<string, mixed>>
 */
function claimPendingJobs(PDO $pdo, int $limit): array
{
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare(
            "SELECT id, payload, attempt_count, order_number
             FROM purchase_alert_outbox
             WHERE status = 'pending'
               AND next_attempt_at <= NOW()
             ORDER BY id ASC
             FOR UPDATE SKIP LOCKED
             LIMIT :limit"
        );
        $select->bindValue(':limit', $limit, PDO::PARAM_INT);
        $select->execute();
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            $pdo->commit();
            return [];
        }

        $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $pdo->prepare(
            "UPDATE purchase_alert_outbox
             SET status = 'sending', updated_at = NOW()
             WHERE id IN ($placeholders)"
        );
        $update->execute($ids);

        $pdo->commit();
        return $rows;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function markSent(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare(
        "UPDATE purchase_alert_outbox
         SET status = 'sent',
             sent_at = NOW(),
             last_error = NULL,
             updated_at = NOW()
         WHERE id = :id"
    );
    $stmt->execute(['id' => $id]);
}

function markFailedOrRetry(PDO $pdo, int $id, int $attemptCount, int $maxAttempts, string $error): string
{
    if ($attemptCount >= $maxAttempts) {
        $stmt = $pdo->prepare(
            "UPDATE purchase_alert_outbox
             SET status = 'failed',
                 attempt_count = :attempt_count,
                 last_error = :last_error,
                 updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $id,
            'attempt_count' => $attemptCount,
            'last_error' => $error,
        ]);
        return 'failed';
    }

    $delaySeconds = min(300, (int)pow(2, min(8, $attemptCount)));
    $stmt = $pdo->prepare(
        "UPDATE purchase_alert_outbox
         SET status = 'pending',
             attempt_count = :attempt_count,
             next_attempt_at = NOW() + (:delay_seconds * INTERVAL '1 second'),
             last_error = :last_error,
             updated_at = NOW()
         WHERE id = :id"
    );
    $stmt->execute([
        'id' => $id,
        'attempt_count' => $attemptCount,
        'delay_seconds' => $delaySeconds,
        'last_error' => $error,
    ]);

    return 'retry';
}

try {
    $jobs = claimPendingJobs($pdo, $limit);
    if (!$jobs) {
        queueLog('No pending jobs');
        exit(0);
    }

    queueLog(sprintf('Claimed %d job(s)', count($jobs)));

    $sent = 0;
    $failed = 0;
    $retried = 0;

    foreach ($jobs as $job) {
        $jobId = (int)$job['id'];
        $orderNumber = (string)($job['order_number'] ?? 'N/A');
        $attemptCount = (int)($job['attempt_count'] ?? 0) + 1;

        try {
            $decoded = json_decode((string)($job['payload'] ?? ''), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid payload JSON');
            }

            $order = is_array($decoded['order'] ?? null) ? $decoded['order'] : null;
            $ctx = is_array($decoded['ctx'] ?? null) ? $decoded['ctx'] : null;
            if ($order === null || $ctx === null) {
                throw new RuntimeException('Missing order/ctx in payload');
            }

            MailHelper::sendPurchaseAlert($order, $ctx);
            markSent($pdo, $jobId);
            $sent++;
            queueLog(sprintf('Sent job #%d order=%s', $jobId, $orderNumber));
        } catch (Throwable $error) {
            $result = markFailedOrRetry($pdo, $jobId, $attemptCount, $maxAttempts, $error->getMessage());
            if ($result === 'failed') {
                $failed++;
                queueLog(sprintf('Failed job #%d order=%s attempts=%d error=%s', $jobId, $orderNumber, $attemptCount, $error->getMessage()));
            } else {
                $retried++;
                queueLog(sprintf('Retry scheduled job #%d order=%s attempts=%d error=%s', $jobId, $orderNumber, $attemptCount, $error->getMessage()));
            }
        }
    }

    queueLog(sprintf('Summary sent=%d retried=%d failed=%d', $sent, $retried, $failed));
    exit(0);
} catch (Throwable $error) {
    queueLog('Fatal error: ' . $error->getMessage());
    exit(1);
}
