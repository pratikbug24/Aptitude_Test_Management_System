<?php
/**
 * Application configuration.
 *
 * Every value can be overridden with an environment variable so the same
 * code can run on a developer laptop and on a shared host without edits.
 */

declare(strict_types=1);

// --- Database -------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sourcecodester_exam');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));

// --- Application ----------------------------------------------------------
define('APP_NAME', 'Aptitude Test Management System');
define('APP_SHORT_NAME', 'ATMS');
define('APP_TAGLINE', 'Practise, measure and improve aptitude skills');

// Absolute filesystem path to the project root.
define('APP_ROOT', dirname(__DIR__));

/**
 * Base URL of the application, used to build links that work no matter which
 * sub-directory the project is served from. Override with APP_BASE_URL when
 * the automatic guess is not correct (for example behind a reverse proxy).
 */
function app_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configured = getenv('APP_BASE_URL');
    if ($configured) {
        return $base = rtrim($configured, '/');
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Strip everything from the first known entry-point directory onwards.
    foreach (['/student/', '/admin/', '/actions/', '/auth/'] as $marker) {
        $pos = strpos($script, $marker);
        if ($pos !== false) {
            return $base = rtrim(substr($script, 0, $pos), '/');
        }
    }

    return $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
}

/** Build an absolute application URL from a root-relative path. */
function url(string $path = ''): string
{
    return app_base_url() . '/' . ltrim($path, '/');
}
