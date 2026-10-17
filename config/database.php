<?php
/**
 * Shared database connection helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Return the shared mysqli connection, opening it on first use. */
function db(): mysqli
{
    static $con = null;
    if ($con instanceof mysqli) {
        return $con;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $con = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($con->connect_errno) {
        http_response_code(500);
        exit('Database connection failed: ' . $con->connect_error);
    }
    $con->set_charset('utf8mb4');

    return $con;
}

/** Run a query and return the result set (or false on failure). */
function db_query(string $sql): mysqli_result|bool
{
    return db()->query($sql);
}

/** Fetch a single row as an associative array, or null when there is none. */
function db_one(string $sql): ?array
{
    $result = db_query($sql);
    if (!$result instanceof mysqli_result) {
        return null;
    }
    $row = $result->fetch_assoc();
    $result->free();

    return $row ?: null;
}

/** Fetch every row of a query as an associative array. */
function db_all(string $sql): array
{
    $result = db_query($sql);
    if (!$result instanceof mysqli_result) {
        return [];
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    return $rows;
}

/** Escape a value for safe interpolation into a SQL string literal. */
function db_escape(string $value): string
{
    return db()->real_escape_string($value);
}
