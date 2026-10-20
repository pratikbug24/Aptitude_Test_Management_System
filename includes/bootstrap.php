<?php
/**
 * Application bootstrap: sessions, helpers and authentication guards.
 *
 * Every page loads this file first. It is safe to include more than once.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ensure_schema();

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

/** Escape a value for safe output inside HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an application URL from a root-relative path. */
function app_url(string $path = ''): string
{
    return url($path);
}

/** Redirect to an application path and stop the request. */
function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------

/**
 * Queue a message that is shown once on the next page load.
 *
 * @param string $type one of success, error, info, warning
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Remove and return all queued flash messages. */
function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------

/** Return the per-session CSRF token, creating it when necessary. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Render the hidden CSRF input for a form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Abort the request unless the submitted token matches the session token.
 * Requests without a session (fresh visitors) are rejected as well.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || $submitted === '' || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session expired. Please go back, reload the page and try again.');
    }
}

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

/** True when a student is signed in. */
function is_student(): bool
{
    return isset($_SESSION['email'], $_SESSION['name']) && !isset($_SESSION['key']);
}

/** True when an administrator is signed in. */
function is_admin(): bool
{
    return isset($_SESSION['key']) && $_SESSION['key'] === 'admin';
}

/** Send anonymous visitors to the student login page. */
function require_student(): void
{
    if (!is_student()) {
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
}

/** Send non-admins to the administrator login page. */
function require_admin(): void
{
    if (!is_admin()) {
        flash('info', 'Administrator sign-in required.');
        redirect('admin/login.php');
    }
}

/** Currently signed-in student's e-mail, or an empty string. */
function current_email(): string
{
    return (string) ($_SESSION['email'] ?? '');
}

/** Currently signed-in display name, or an empty string. */
function current_name(): string
{
    return (string) ($_SESSION['name'] ?? '');
}

// ---------------------------------------------------------------------------
// Test scheduling & registration
// ---------------------------------------------------------------------------

/**
 * Describe where a quiz sits in its attempt window.
 *
 * @param array $quiz a row from the `quiz` table; may carry start_at/end_at.
 * @return array{open: bool, label: string, badge: string, detail: string}
 */
function quiz_window(array $quiz): array
{
    $start = trim((string) ($quiz['start_at'] ?? ''));
    $end = trim((string) ($quiz['end_at'] ?? ''));
    $now = time();

    $startTs = $start !== '' ? strtotime($start) : null;
    $endTs = $end !== '' ? strtotime($end) : null;

    if ($startTs !== null && $now < $startTs) {
        return [
            'open' => false,
            'label' => 'Scheduled',
            'badge' => 'badge--info',
            'detail' => 'Opens ' . date('d M Y, H:i', $startTs),
        ];
    }

    if ($endTs !== null && $now > $endTs) {
        return [
            'open' => false,
            'label' => 'Closed',
            'badge' => 'badge--danger',
            'detail' => 'Closed ' . date('d M Y, H:i', $endTs),
        ];
    }

    return [
        'open' => true,
        'label' => 'Open',
        'badge' => 'badge--success',
        'detail' => 'Open now',
    ];
}

/**
 * True when the given e-mail is registered for the given quiz.
 */
function is_enrolled(string $email, string $eid): bool
{
    $emailSql = db_escape($email);
    $eidSql = db_escape($eid);

    return db_one(
        "SELECT email FROM enrollment WHERE email = '$emailSql' AND eid = '$eidSql'"
    ) !== null;
}

/**
 * Register a candidate for a test. Idempotent: registering twice is a no-op.
 */
function enroll_candidate(string $email, string $eid): void
{
    if ($email === '' || $eid === '' || is_enrolled($email, $eid)) {
        return;
    }

    $emailSql = db_escape($email);
    $eidSql = db_escape($eid);
    db_query("INSERT INTO enrollment (eid, email) VALUES ('$eidSql', '$emailSql')");
}

/**
 * Send the current student back unless they registered for this quiz and it
 * is currently inside its attempt window.
 */
function require_quiz_access(array $quiz): void
{
    $window = quiz_window($quiz);

    if (!is_enrolled(current_email(), (string) $quiz['eid'])) {
        flash('error', 'You are not registered for that test.');
        redirect('student/dashboard.php');
    }

    if (!$window['open']) {
        flash('warning', 'That test is not open right now. ' . $window['detail'] . '.');
        redirect('student/dashboard.php');
    }
}
