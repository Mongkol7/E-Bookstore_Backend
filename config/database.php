<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

static $pdo = null;
if ($pdo instanceof PDO) {
    return $pdo;
}

static $dotenvLoaded = false;
if (
    !$dotenvLoaded
    && !isset($_ENV['DB_DRIVER'])
    && !isset($_ENV['DATABASE_URL'])
    && !isset($_ENV['DATABASE_PUBLIC_URL'])
) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
    $dotenvLoaded = true;
}

$databaseUrl = $_ENV['DATABASE_URL'] ?? $_ENV['DATABASE_PUBLIC_URL'] ?? null;

$driver = $_ENV['DB_DRIVER'] ?? 'pgsql';
$host = $_ENV['DB_HOST'] ?? ($_ENV['PGHOST'] ?? 'localhost');
$port = $_ENV['DB_PORT'] ?? ($_ENV['PGPORT'] ?? '5432');
$db   = $_ENV['DB_DATABASE'] ?? ($_ENV['PGDATABASE'] ?? 'postgres');
$user = $_ENV['DB_USERNAME'] ?? ($_ENV['PGUSER'] ?? 'postgres');
$pass = $_ENV['DB_PASSWORD'] ?? ($_ENV['PGPASSWORD'] ?? '');
$sslMode = $_ENV['DB_SSLMODE'] ?? ($_ENV['PGSSLMODE'] ?? 'prefer');
$persistent = filter_var($_ENV['DB_PERSISTENT'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

if (is_string($databaseUrl) && $databaseUrl !== '') {
    $parts = parse_url($databaseUrl);
    if (is_array($parts)) {
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true)) {
            $driver = 'pgsql';
        } elseif (in_array($scheme, ['mysql', 'mariadb'], true)) {
            $driver = 'mysql';
        }

        if (!empty($parts['host'])) {
            $host = (string)$parts['host'];
        }
        if (!empty($parts['port'])) {
            $port = (string)$parts['port'];
        }
        if (!empty($parts['path'])) {
            $db = ltrim((string)$parts['path'], '/');
        }
        if (array_key_exists('user', $parts)) {
            $user = urldecode((string)$parts['user']);
        }
        if (array_key_exists('pass', $parts)) {
            $pass = urldecode((string)$parts['pass']);
        }

        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            if (!empty($query['sslmode'])) {
                $sslMode = (string)$query['sslmode'];
            }
        }
    }
}

if ($driver === 'pgsql') {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=$sslMode;connect_timeout=5;";
} else {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => $persistent,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

return $pdo;
