<?php
/**
 * Public landing page.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// Signed-in visitors go straight to their workspace.
if (is_admin()) {
    redirect('admin/dashboard.php');
}
if (is_student()) {
    redirect('student/dashboard.php');
}

$quizCount = (int) (db_one('SELECT COUNT(*) AS c FROM quiz')['c'] ?? 0);
$questionCount = (int) (db_one('SELECT COUNT(*) AS c FROM questions')['c'] ?? 0);
$userCount = (int) (db_one('SELECT COUNT(*) AS c FROM user')['c'] ?? 0);

$pageTitle = 'Welcome';
$navItems = [
    'How it works' => app_url('index.php#how-it-works'),
    'Features' => app_url('index.php#features'),
    'User login' => app_url('login.php'),
    'Admin login' => app_url('admin/login.php'),
    'Register' => app_url('register.php'),
];
$bodyClass = 'landing';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero__copy">
        <span class="hero__eyebrow">Prepare for your aptitude test</span>
        <h1 class="hero__title">Prepare for your aptitude test. <em>Track your progress.</em></h1>
        <p class="hero__lead">
            <?= e(APP_NAME) ?> is a complete aptitude test management system. Administrators
            create tests and register candidates, students sign in to solve a timed test,
            and everyone can review the result, rank and answers afterwards.
        </p>
        <div class="hero__actions">
            <a class="btn btn--primary btn--lg" href="<?= e(app_url('register.php')) ?>">Create an account</a>
            <a class="btn btn--ghost btn--lg" href="<?= e(app_url('login.php')) ?>">User login</a>
            <a class="btn btn--ghost btn--lg" href="<?= e(app_url('admin/login.php')) ?>">Admin login</a>
        </div>
        <div class="hero__facts">
            <div class="hero__fact"><strong><?= $quizCount ?></strong><span>Published quizzes</span></div>
            <div class="hero__fact"><strong><?= $questionCount ?></strong><span>Questions available</span></div>
            <div class="hero__fact"><strong><?= $userCount ?></strong><span>Registered students</span></div>
        </div>
    </div>

    <div class="hero__art">
        <h3>Your practice dashboard</h3>
        <ul class="mock-list">
            <li><span>Php &amp; Mysqli</span><span>10 questions</span></li>
            <li><span>IP Networking</span><span>10 questions</span></li>
            <li><span>Quantitative Aptitude</span><span>Coming soon</span></li>
        </ul>
        <p style="margin-top:1.25rem;font-size:.86rem;opacity:.75">
            Sign in to start an attempt. Scores are recorded automatically.
        </p>
    </div>
</section>

<section id="how-it-works" class="mb-2">
    <div class="section-head">
        <div>
            <h2>How it works</h2>
            <p>From creating a test to seeing the final rank — one simple flow.</p>
        </div>
    </div>
    <div class="feature-grid">
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">🛠️</span>
            <h3>1. Create test</h3>
            <p>An administrator defines the test title, number of questions and marking scheme, then publishes it.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">🔗</span>
            <h3>2. Share the registration link</h3>
            <p>Send the registration link to candidates so they can join the test.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">📝</span>
            <h3>3. Register candidates</h3>
            <p>Candidates register with their name, e-mail and college, then sign in to their account.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">⏱️</span>
            <h3>4. Solve test</h3>
            <p>Candidates attempt the timed test question by question with a live progress indicator.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">📊</span>
            <h3>5. View result, rank &amp; answers</h3>
            <p>Instantly see the score, the leaderboard position and a full answer review after submitting.</p>
        </article>
    </div>
</section>

<section id="features" class="mb-2">
    <div class="section-head">
        <div>
            <h2>Everything you need for objective assessment</h2>
            <p>Built for schools, colleges and training institutes.</p>
        </div>
    </div>
    <div class="feature-grid">
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">⚡</span>
            <h3>Instant evaluation</h3>
            <p>Scores are calculated the moment a quiz is submitted — no manual marking.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">📊</span>
            <h3>Detailed analytics</h3>
            <p>Track right answers, wrong answers and total score for every attempt.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">🏆</span>
            <h3>Leaderboard</h3>
            <p>A live ranking view keeps students motivated and competition healthy.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">🛠️</span>
            <h3>Easy quiz builder</h3>
            <p>Administrators create quizzes and questions through guided forms.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">🤖</span>
            <h3>AI test creation</h3>
            <p>Generate a ready-to-use aptitude test with questions and answer keys in seconds.</p>
        </article>
        <article class="feature">
            <span class="feature__icon" aria-hidden="true">🎥</span>
            <h3>Practice like the real test</h3>
            <p>Attempts open in a locked mode: no new tabs and the camera stays on, just like a real aptitude exam.</p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
