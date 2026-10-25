<?php
/**
 * Test registration: join one specific test.
 *
 * Opened from the registration link an administrator shares
 * (quiz_register.php?eid=...). The candidate supplies only their full name and
 * e-mail address; they are enrolled and signed in straight away.
 *
 * Accounts created here are passwordless — the invited e-mail address is the
 * identity. A candidate whose address already has a password (an account made
 * through register.php) is enrolled and then asked to sign in.
 *
 * General account sign-up and sign-in live in register.php and login.php.
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
$old = ['name' => '', 'email' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $errors = join_test($eid, $old);
}

$pageTitle = 'Register for a test';
$panelTitle = 'Join this test';
$panelSubtitle = 'Enter your name and e-mail address to join "' . (string) $quiz['title'] . '".';
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
        <div class="form-actions">
            <button class="btn btn--primary btn--block" type="submit">Register &amp; join this test</button>
        </div>
    </form>

    <p class="form-foot">
        Already have an account? Enter the same e-mail address and we will ask you to sign in.
    </p>
    <?php
};

require __DIR__ . '/includes/auth-layout.php';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Enrol a candidate for a test from just a name and e-mail address.
 *
 * A new address gets a passwordless account and is signed in. An address that
 * already exists is enrolled; passwordless accounts are signed in, while
 * accounts with a real password are sent to the sign-in screen.
 *
 * @return string[] validation errors; empty on success (which redirects).
 */
function join_test(string $eid, array &$old): array
{
    $old['name'] = trim((string) ($_POST['name'] ?? ''));
    $old['email'] = trim((string) ($_POST['email'] ?? ''));

    $errors = [];
    if ($old['name'] === '') {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid e-mail address.';
    }
    if ($errors) {
        return $errors;
    }

    $emailSql = db_escape($old['email']);
    $existing = db_one("SELECT * FROM user WHERE email = '$emailSql'");

    if ($existing === null) {
        $nameSql = db_escape($old['name']);
        // Passwordless account: the invited e-mail address is the identity.
        $ok = db_query(
            "INSERT INTO user (name, email, password, college)
             VALUES ('$nameSql', '$emailSql', '', '')"
        );
        if (!$ok) {
            return ['We could not complete your registration. Please try again.'];
        }

        enroll_candidate($old['email'], $eid);
        start_student_session($old['name'], $old['email'], '');
        flash('success', 'Registration complete and you are enrolled. Good luck!');
        redirect('student/dashboard.php');
    }

    // A known address is always enrolled for this test.
    enroll_candidate((string) $existing['email'], $eid);

    if ((string) $existing['password'] === '') {
        start_student_session(
            (string) $existing['name'],
            (string) $existing['email'],
            (string) $existing['college']
        );
        flash('success', 'Welcome back, ' . $existing['name'] . '! You are registered for the test.');
        redirect('student/dashboard.php');
    }

    flash('info', 'You already have an account. Please sign in to attempt the test.');
    redirect('login.php');
}

/** Begin a signed-in student session for the given candidate. */
function start_student_session(string $name, string $email, string $college): void
{
    session_regenerate_id(true);
    $_SESSION['logged'] = $email;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['college'] = $college;
    unset($_SESSION['key']);
}
