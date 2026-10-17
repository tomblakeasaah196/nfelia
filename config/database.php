<?php
/**
 * Nfelia Database Configuration
 * Reads from .env — never hardcode credentials here
 */

if (!function_exists('load_env')) {
    function load_env(string $path): void {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            // Strip one layer of surrounding quotes if present: VALUE, "VALUE" and
            // 'VALUE' must all yield the same string. Without this, a quoted
            // password authenticates with the quote characters included.
            if (strlen($value) >= 2
                && (($value[0] === '"' && $value[-1] === '"')
                 || ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            $_ENV[trim($key)] = $value;
        }
    }
}

load_env(__DIR__ . '/../.env');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $name = $_ENV['DB_NAME'] ?? 'smartqaq_nfelia_core';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';
    $port = $_ENV['DB_PORT'] ?? '3306';

    if ($user === '' || $pass === '') {
        error_log('DB Connection Failed: DB_USER or DB_PASS missing from .env (check the file exists and is readable).');
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed.']));
    }

    try {
        // NOTE: no COLLATE here on purpose. utf8mb4_0900_ai_ci is MySQL 8 only
        // and does not exist on MariaDB, which made the connection itself fail
        // (SQLSTATE[HY000] [1273] Unknown collation) after the server move.
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('DB Connection Failed: [' . $e->getCode() . '] ' . $e->getMessage());
        http_response_code(500);
        $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        die(json_encode([
            'error' => 'Database connection failed.',
            'detail' => $debug ? $e->getMessage() : null,
        ]));
    }

    // Session tuning is best-effort only: a server that lacks one of these
    // must never take the whole site down again.
    foreach ([
        "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        "SET time_zone = '+01:00'",
    ] as $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            error_log('DB session setup skipped (' . $stmt . '): ' . $e->getMessage());
        }
    }

    return $pdo;
}
