<?php
/**
 * Test registration: join one specific test.
 *
 * Opened from the registration link an administrator shares
 * (quiz_register.php?eid=...). A candidate can either create a new account or
 * sign in with an existing one; either way they end up enrolled in the test.
 *
 * General account sign-up and sign-in live in register.php and login.php,
 * which are not tied to any particular test.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$eid = trim((string) ($_REQUEST['eid'] ?? ''));
$eidSql = db_escape($eid);
$quiz = $eid !== '' ? db_one("SELECT * FROM quiz WHERE eid = '$eidSql'") : null;

if ($quiz === null) {
    flash('error', 'That registration link is not valid. Please contact your administrator.');
    redirect('index.php');
}

// A candidate who is already signed in only needs enrolling.
if (is_student()) {
    enroll_candidate(current_email(), $eid);
    flash('success', 'You are registered for "' . (string) $quiz['title'] . '".');
    redirect('student/dashboard.php');
}

$window = quiz_window($quiz);
$old = ['name' => '', 'email' => '', 'college' => ''];
$errors = [];
$mode = (string) ($_POST['mode'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($mode === 'signup') {
        $errors = register_candidate($eid, $old);
    } elseif ($mode === 'signin') {
        $errors = sign_in_candidate($eid);
    } else {
        $errors[] = 'Please choose whether to sign in or create an account.';
    }
}

$pageTitle = 'Register for a test';
$panelTitle = 'Join this test';
$panelSubtitle = 'Register or sign in to be enrolled in "' . (string) $quiz['title'] . '".';
$panelIcon = '🔗';
$panelBody = static function () use ($quiz, $window, $old, $errors, $eid): void {
    ?>
    <div class="card" style="margin-bottom:1.25rem">
        <div class="question-card__meta">
            <span class="badge badge--info"><?= e((string) $quiz['title']) ?></span>
            <span class="badge"><?= (int) $quiz['total'] ?> question(s)</span>
            <span class="badge"><?= (int) $quiz['sahi'] ?> mark(s) per correct answer</span>
            <span class="badge <?= e($window['badge']) ?>"><?= e($window['label']) ?></span>
        </div>
        <p class="is-muted" style="margin:.5rem 0 0"><?= e($window['detail']) ?></p>
        <?php if (!$window['open']): ?>
            <p class="hint" style="margin-top:.5rem">
                You can still register now; you will be able to attempt the test once it opens.
            </p>
        <?php endif; ?>
    </div>

    <?php if ($errors): ?>
        <div class="flash-stack">
            <?php foreach ($errors as $error): ?>
                <div class="flash flash--error">
                    <span class="flash__dot" aria-hidden="true"></span>
                    <?= e($error) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="form-grid" method="post"
          action="<?= e(app_url('quiz_register.php?eid=' . urlencode($eid))) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="signup">
        <h2 class="card__title" style="font-size:1.05rem">Create an account</h2>
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
            <button class="btn btn--primary btn--block" type="submit">Create account &amp; register</button>
        </div>
    </form>

    <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--ink-100)">

    <form class="form-grid" method="post"
          action="<?= e(app_url('quiz_register.php?eid=' . urlencode($eid))) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="signin">
        <h2 class="card__title" style="font-size:1.05rem">Already have an account?</h2>
        <div class="field">
            <label for="login-email">E-mail address</label>
            <input class="input" type="email" id="login-email" name="email" autocomplete="email"
                   placeholder="you@example.com" required>
        </div>
        <div class="field">
            <label for="login-password">Password</label>
            <input class="input" type="password" id="login-password" name="password"
                   autocomplete="current-password" placeholder="Your password" required>
        </div>
        <div class="form-actions">
            <button class="btn btn--ghost btn--block" type="submit">Sign in &amp; register</button>
        </div>
    </form>

    <p class="form-foot">
        <a href="<?= e(app_url('index.php')) ?>">Back to home</a>
    </p>
    <?php
};

require __DIR__ . '/includes/auth-layout.php';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Validate and create a brand-new candidate account, then enrol it.
 *
 * @return string[] validation errors; empty on success (which redirects).
 */
function register_candidate(string $eid, array &$old): array
{
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

    if ($errors) {
        return $errors;
    }

    $emailSql = db_escape($old['email']);
    if (db_one("SELECT email FROM user WHERE email = '$emailSql'") !== null) {
        return ['That e-mail address is already registered. Sign in instead.'];
    }

    $nameSql = db_escape($old['name']);
    $collegeSql = db_escape($old['college']);
    $passwordSql = db_escape($password);

    $ok = db_query(
        "INSERT INTO user (name, email, password, college)
         VALUES ('$nameSql', '$emailSql', '$passwordSql', '$collegeSql')"
    );

    if (!$ok) {
        return ['We could not complete your registration. Please try again.'];
    }

    session_regenerate_id(true);
    $_SESSION['logged'] = $old['email'];
    $_SESSION['name'] = $old['name'];
    $_SESSION['email'] = $old['email'];
    $_SESSION['college'] = $old['college'];
    unset($_SESSION['key']);

    enroll_candidate($old['email'], $eid);
    flash('success', 'Registration complete and you are enrolled. Good luck!');
    redirect('student/dashboard.php');
}

/**
 * Validate existing credentials and enrol the candidate in the test.
 *
 * @return string[] validation errors; empty on success (which redirects).
 */
function sign_in_candidate(string $eid): array
{
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        return ['Please enter both your e-mail address and password.'];
    }

    $emailSql = db_escape($email);
    $passwordSql = db_escape($password);
    $row = db_one("SELECT * FROM user WHERE email = '$emailSql' AND password = '$passwordSql'");

    if ($row === null) {
        return ['Sorry, those credentials do not match our records.'];
    }

    session_regenerate_id(true);
    $_SESSION['logged'] = $row['email'];
    $_SESSION['name'] = $row['name'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['college'] = $row['college'];
    unset($_SESSION['key']);

    enroll_candidate((string) $row['email'], $eid);
    flash('success', 'Welcome back, ' . $row['name'] . '! You are registered for the test.');
    redirect('student/dashboard.php');
}
