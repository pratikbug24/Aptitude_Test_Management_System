<?php
/**
 * Student self-registration.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_student()) {
    redirect('student/dashboard.php');
}

$old = ['name' => '', 'email' => '', 'college' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['name'] = trim((string) ($_POST['name'] ?? ''));
    $old['email'] = trim((string) ($_POST['email'] ?? ''));
    $old['college'] = trim((string) ($_POST['college'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $errors = [];
    if ($old['name'] === '') {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid e-mail address.';
    }
    if (strlen($password) < 4) {
        $errors[] = 'Password must be at least 4 characters long.';
    }
    if ($old['college'] === '') {
        $errors[] = 'Please enter your college name.';
    }

    if (!$errors) {
        $emailSql = db_escape($old['email']);
        if (db_one("SELECT email FROM user WHERE email = '$emailSql'") !== null) {
            $errors[] = 'That e-mail address is already registered. Try signing in instead.';
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
    } else {
        $nameSql = db_escape($old['name']);
        $emailSql = db_escape($old['email']);
        $collegeSql = db_escape($old['college']);
        $passwordSql = db_escape($password);

        $ok = db_query(
            "INSERT INTO user (name, email, password, college)
             VALUES ('$nameSql', '$emailSql', '$passwordSql', '$collegeSql')"
        );

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['logged'] = $old['email'];
            $_SESSION['name'] = $old['name'];
            $_SESSION['email'] = $old['email'];
            $_SESSION['college'] = $old['college'];
            unset($_SESSION['key']);

            flash('success', 'Registration complete. Good luck!');
            redirect('student/dashboard.php');
        }

        flash('error', 'We could not complete your registration. Please try again.');
    }
}

$pageTitle = 'Create account';
$panelTitle = 'Create your account';
$panelSubtitle = 'Register once, then attempt any published quiz.';
$panelIcon = '＋';
$panelBody = static function () use ($old): void {
    ?>
    <form class="form-grid" method="post" action="<?= e(app_url('register.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="name">Full name</label>
            <input class="input" type="text" id="name" name="name" autocomplete="name"
                   placeholder="e.g. Priya Sharma" value="<?= e($old['name']) ?>" required autofocus>
        </div>
        <div class="field">
            <label for="email">E-mail address</label>
            <input class="input" type="email" id="email" name="email" autocomplete="email"
                   placeholder="you@example.com" value="<?= e($old['email']) ?>" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" type="password" id="password" name="password"
                   autocomplete="new-password" placeholder="At least 4 characters" required>
        </div>
        <div class="field">
            <label for="college">College / institute</label>
            <input class="input" type="text" id="college" name="college" autocomplete="organization"
                   placeholder="e.g. National Institute of Technology" value="<?= e($old['college']) ?>" required>
        </div>
        <div class="form-actions">
            <button class="btn btn--primary btn--block" type="submit">Create account</button>
        </div>
    </form>
    <p class="form-foot">
        Already registered? <a href="<?= e(app_url('login.php')) ?>">Sign in</a>
    </p>
    <?php
};

require __DIR__ . '/includes/auth-layout.php';
