<?php
/**
 * Student profile handlers (POST only).
 *
 * Actions:
 *   update-profile   Save the candidate's display name and college.
 *   update-password  Set a password, or change an existing one.
 *
 * A CSRF-protected POST is required for every action; the request is always
 * answered with a redirect (post/redirect/get).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf();

$action = (string) ($_GET['action'] ?? '');

switch ($action) {
    case 'update-profile':
        update_profile();
        break;

    case 'update-password':
        update_password();
        break;

    default:
        flash('error', 'Unknown action.');
        redirect('student/profile.php');
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

/** Save the candidate's display name and college. */
function update_profile(): never
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $college = trim((string) ($_POST['college'] ?? ''));

    if ($name === '') {
        flash('error', 'Please enter your full name.');
        redirect('student/profile.php');
    }

    $emailSql = db_escape(current_email());
    $nameSql = db_escape($name);
    $collegeSql = db_escape($college);

    db_query("UPDATE user SET name = '$nameSql', college = '$collegeSql' WHERE email = '$emailSql'");

    $_SESSION['name'] = $name;
    $_SESSION['college'] = $college;

    flash('success', 'Your profile has been updated.');
    redirect('student/profile.php');
}

/** Set a password on a passwordless account, or change an existing one. */
function update_password(): never
{
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $emailSql = db_escape(current_email());
    $user = db_one("SELECT * FROM user WHERE email = '$emailSql'");

    if ($user === null) {
        flash('error', 'That account could not be found.');
        redirect('login.php');
    }

    $stored = (string) $user['password'];

    // Accounts created from a registration link start without a password.
    if ($stored !== '' && !hash_equals($stored, $current)) {
        flash('error', 'Your current password is incorrect.');
        redirect('student/profile.php');
    }

    if (strlen($new) < 4) {
        flash('error', 'The new password must be at least 4 characters long.');
        redirect('student/profile.php');
    }

    if ($new !== $confirm) {
        flash('error', 'The new password and its confirmation do not match.');
        redirect('student/profile.php');
    }

    $newSql = db_escape($new);
    db_query("UPDATE user SET password = '$newSql' WHERE email = '$emailSql'");

    flash('success', $stored === ''
        ? 'Password set. You can now sign in with it.'
        : 'Your password has been changed.');

    redirect('student/profile.php');
}
