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

/**
 * Bring an existing database up to the schema the current code expects.
 *
 * Fresh installs already ship with these objects (see
 * database/sourcecodester_exam.sql); this keeps databases created from an
 * older dump working without a manual migration. It is idempotent and cheap
 * (a handful of information_schema lookups), so it runs once per request.
 */
function ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $con = db();

    if (!table_exists('enrollment')) {
        $con->query(
            'CREATE TABLE IF NOT EXISTS `enrollment` (
                `eid` text NOT NULL,
                `email` varchar(50) NOT NULL,
                `registered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
    }

    foreach (['start_at' => 'datetime NULL DEFAULT NULL', 'end_at' => 'datetime NULL DEFAULT NULL'] as $column => $definition) {
        if (!column_exists('quiz', $column)) {
            $con->query("ALTER TABLE `quiz` ADD COLUMN `$column` $definition");
        }
    }
}

/** True when the named table exists in the current database. */
function table_exists(string $table): bool
{
    $name = db_escape($table);
    return db_one("SHOW TABLES LIKE '$name'") !== null;
}

/** True when the named column exists on a table in the current database. */
function column_exists(string $table, string $column): bool
{
    $tableSql = db_escape($table);
    $columnSql = db_escape($column);
    return db_one(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '$tableSql'
           AND COLUMN_NAME = '$columnSql'"
    ) !== null;
}
