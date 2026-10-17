<?php
/**
 * Public landing page.
 */

declare(strict_types=1);

require_once __DIR__ . './includes/bootstrap.php';

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
    'Features' => app_url('index.php#features'),
    'Login' => app_url('login.php'),
    'Register' => app_url('register.php'),
];
$bodyClass = 'landing';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero__copy">
        <span class="hero__eyebrow">Online aptitude testing, simplified</span>
        <h1 class="hero__title">Measure aptitude. <em>Improve results.</em></h1>
        <p class="hero__lead">
            <?= e(APP_NAME) ?> lets students attempt timed, topic-wise quizzes and see
            instant scores, while administrators publish quizzes and track performance
            from a single dashboard.
        </p>
        <div class="hero__actions">
            <a class="btn btn--primary btn--lg" href="<?= e(app_url('register.php')) ?>">Create an account</a>
            <a class="btn btn--ghost btn--lg" href="<?= e(app_url('login.php')) ?>">Sign in</a>
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
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
