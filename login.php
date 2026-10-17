<?php
/**
 * Student sign-in.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_student()) {
    redirect('student/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        flash('error', 'Please enter both your e-mail address and password.');
    } else {
        $emailSql = db_escape($email);
        $passwordSql = db_escape($password);
        $row = db_one("SELECT * FROM user WHERE email = '$emailSql' AND password = '$passwordSql'");

        if ($row === null) {
            flash('error', 'Sorry, those credentials do not match our records.');
        } else {
            session_regenerate_id(true);
            $_SESSION['logged'] = $row['email'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['college'] = $row['college'];
            unset($_SESSION['key']);

            flash('success', 'Welcome back, ' . $row['name'] . '!');
            redirect('student/dashboard.php');
        }
    }
}

$pageTitle = 'Sign in';
$panelTitle = 'Welcome back';
$panelSubtitle = 'Sign in to continue to your quiz dashboard.';
$panelIcon = '↪';
$panelBody = static function (): void {
    ?>
    <form class="form-grid" method="post" action="<?= e(app_url('login.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">E-mail address</label>
            <input class="input" type="email" id="email" name="email" autocomplete="email"
                   placeholder="you@example.com" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" type="password" id="password" name="password"
                   autocomplete="current-password" placeholder="Your password" required>
        </div>
        <div class="form-actions">
            <button class="btn btn--primary btn--block" type="submit">Sign in</button>
        </div>
    </form>
    <p class="form-foot">
        New here? <a href="<?= e(app_url('register.php')) ?>">Create an account</a>
        &nbsp;·&nbsp; <a href="<?= e(app_url('admin/login.php')) ?>">Administrator login</a>
    </p>
    <?php
};

require __DIR__ . '/includes/auth-layout.php';
