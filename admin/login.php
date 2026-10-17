<?php
/**
 * Administrator sign-in.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_admin()) {
    redirect('admin/dashboard.php');
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
        $row = db_one("SELECT email FROM admin WHERE email = '$emailSql' AND password = '$passwordSql'");

        if ($row === null) {
            flash('error', 'Sorry, those administrator credentials are not valid.');
        } else {
            session_regenerate_id(true);
            $_SESSION['name'] = 'Administrator';
            $_SESSION['key'] = 'admin';
            $_SESSION['email'] = $row['email'];

            flash('success', 'Signed in as administrator.');
            redirect('admin/dashboard.php');
        }
    }
}

$pageTitle = 'Administrator sign in';
$panelTitle = 'Administrator access';
$panelSubtitle = 'Manage quizzes, students and rankings.';
$panelIcon = '⚙';
$panelBody = static function (): void {
    ?>
    <form class="form-grid" method="post" action="<?= e(app_url('admin/login.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Administrator e-mail</label>
            <input class="input" type="email" id="email" name="email" autocomplete="username"
                   placeholder="admin@example.com" required autofocus>
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
        Student instead? <a href="<?= e(app_url('login.php')) ?>">Student sign in</a>
    </p>
    <?php
};

require dirname(__DIR__) . '/includes/auth-layout.php';
