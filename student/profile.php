<?php
/**
 * Student profile: progress chart, performance summary and account options.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_student();

$email = current_email();
$emailSql = db_escape($email);

$user = db_one("SELECT * FROM user WHERE email = '$emailSql'");
if ($user === null) {
    flash('error', 'That account could not be found.');
    redirect('logout.php');
}

$hasPassword = (string) $user['password'] !== '';

// Initial shown in the avatar; falls back to the e-mail when no name is set.
$initial = strtoupper(substr(trim((string) $user['name']) !== ''
    ? (string) $user['name']
    : (string) $user['email'], 0, 1));

// ---------------------------------------------------------------------------
// Progress data
// ---------------------------------------------------------------------------
$attempts = db_all(
    "SELECT h.*, q.title
     FROM history h
     LEFT JOIN quiz q ON q.eid = h.eid
     WHERE h.email = '$emailSql'
     ORDER BY h.date ASC"
);

$attemptCount = count($attempts);
$totalScore = 0;
$totalRight = 0;
$totalQuestions = 0;
$best = 0;

$series = [];
foreach ($attempts as $attempt) {
    $level = (int) $attempt['level'];
    $right = (int) $attempt['sahi'];
    $score = (int) $attempt['score'];
    $accuracy = $level > 0 ? (int) round(($right / $level) * 100) : 0;

    $totalScore += $score;
    $totalRight += $right;
    $totalQuestions += $level;
    $best = max($best, $score);

    $series[] = [
        'title' => (string) ($attempt['title'] ?? 'Deleted quiz'),
        'date' => (string) $attempt['date'],
        'score' => $score,
        'accuracy' => $accuracy,
    ];
}

$averageAccuracy = $totalQuestions > 0 ? (int) round(($totalRight / $totalQuestions) * 100) : 0;
$averageScore = $attemptCount > 0 ? (int) round($totalScore / $attemptCount) : 0;

// The chart shows the most recent attempts, oldest first.
$chartSeries = array_slice($series, -12);
$chartPoints = [];

$width = 660;
$height = 240;
$padLeft = 42;
$padRight = 18;
$padTop = 18;
$padBottom = 34;
$plotWidth = $width - $padLeft - $padRight;
$plotHeight = $height - $padTop - $padBottom;

$count = count($chartSeries);
foreach ($chartSeries as $i => $point) {
    $x = $count > 1
        ? $padLeft + ($i * ($plotWidth / ($count - 1)))
        : $padLeft + ($plotWidth / 2);
    $y = $padTop + $plotHeight * (1 - ($point['accuracy'] / 100));

    $chartPoints[] = [
        'x' => round($x, 1),
        'y' => round($y, 1),
        'accuracy' => $point['accuracy'],
        'score' => $point['score'],
        'title' => $point['title'],
        'date' => date('d M Y', strtotime($point['date'])),
    ];
}

// Best score per quiz, for the topic breakdown.
$byTopic = [];
foreach ($series as $point) {
    $byTopic[$point['title']] = max($byTopic[$point['title']] ?? 0, $point['score']);
}
arsort($byTopic);
$byTopic = array_slice($byTopic, 0, 6, true);
$topicMax = $byTopic ? max($byTopic) : 0;

// Leaderboard position across all students.
$rankRow = db_one("SELECT score FROM rank WHERE email = '$emailSql'");
$position = null;
if ($rankRow !== null) {
    $higher = db_one("SELECT COUNT(*) AS c FROM rank WHERE score > " . (int) $rankRow['score']);
    $position = (int) ($higher['c'] ?? 0) + 1;
}
$totalRanked = (int) (db_one("SELECT COUNT(*) AS c FROM rank")['c'] ?? 0);

$enrolledCount = (int) (db_one(
    "SELECT COUNT(*) AS c FROM enrollment WHERE email = '$emailSql'"
)['c'] ?? 0);

$pageTitle = 'Your profile';
$activeNav = 'Profile';
$pageHeading = 'Your profile';
$pageLead = 'Track your progress, review your performance and manage your account.';
$navItems = [
    'Home' => app_url('student/dashboard.php'),
    'History' => app_url('student/dashboard.php?page=history'),
    'Ranking' => app_url('student/dashboard.php?page=ranking'),
    'Profile' => app_url('student/profile.php'),
    'Log out' => app_url('logout.php'),
];

require dirname(__DIR__) . '/includes/header.php';
?>

<div class="profile-hero">
    <span class="profile-hero__avatar" aria-hidden="true"><?= e($initial) ?></span>
    <div class="profile-hero__body">
        <h2><?= e((string) $user['name']) ?></h2>
        <p class="profile-hero__email"><?= e((string) $user['email']) ?></p>
        <div class="profile-hero__meta">
            <?php if ((string) $user['college'] !== ''): ?>
                <span class="badge"><?= e((string) $user['college']) ?></span>
            <?php endif; ?>
            <span class="badge badge--info"><?= $enrolledCount ?> test(s) joined</span>
            <?php if ($position !== null): ?>
                <span class="badge badge--amber">Rank #<?= $position ?> of <?= $totalRanked ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat">
        <div class="stat__label">Attempts</div>
        <div class="stat__value"><?= $attemptCount ?></div>
        <div class="stat__hint">Quizzes you have completed</div>
    </div>
    <div class="stat stat--teal">
        <div class="stat__label">Total score</div>
        <div class="stat__value"><?= $totalScore ?></div>
        <div class="stat__hint">Marks earned across attempts</div>
    </div>
    <div class="stat stat--amber">
        <div class="stat__label">Average score</div>
        <div class="stat__value"><?= $averageScore ?></div>
        <div class="stat__hint">Per attempt</div>
    </div>
    <div class="stat stat--rose">
        <div class="stat__label">Accuracy</div>
        <div class="stat__value"><?= $averageAccuracy ?>%</div>
        <div class="stat__hint">Correct answers overall</div>
    </div>
</div>

<div class="section-head">
    <div>
        <h2>Progress chart</h2>
        <p>Accuracy for each of your last <?= count($chartSeries) ?> attempt(s), oldest first.</p>
    </div>
</div>

<?php if (!$chartPoints): ?>
    <div class="empty">
        <span class="empty__icon" aria-hidden="true">📊</span>
        <h3>No progress to show yet</h3>
        <p>Complete a quiz and your accuracy chart will appear here.</p>
        <p style="margin-top:1rem">
            <a class="btn btn--primary" href="<?= e(app_url('student/dashboard.php')) ?>">Go to your tests</a>
        </p>
    </div>
<?php else: ?>
    <div class="card">
        <svg class="chart" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img"
             aria-label="Accuracy per attempt, from <?= $chartPoints[0]['accuracy'] ?>% to <?= end($chartPoints)['accuracy'] ?>%">
            <?php foreach ([100, 75, 50, 25, 0] as $tick): ?>
                <?php $tickY = $padTop + $plotHeight * (1 - ($tick / 100)); ?>
                <line class="chart__grid" x1="<?= $padLeft ?>" y1="<?= round($tickY, 1) ?>"
                      x2="<?= $width - $padRight ?>" y2="<?= round($tickY, 1) ?>"></line>
                <text class="chart__tick" x="<?= $padLeft - 10 ?>" y="<?= round($tickY + 4, 1) ?>"
                      text-anchor="end"><?= $tick ?>%</text>
            <?php endforeach; ?>

            <?php if (count($chartPoints) > 1): ?>
                <polygon class="chart__area"
                         points="<?= e(implode(' ', array_map(
                             static fn (array $p): string => $p['x'] . ',' . $p['y'],
                             $chartPoints
                         ))) ?> <?= end($chartPoints)['x'] ?>,<?= $padTop + $plotHeight ?> <?= $chartPoints[0]['x'] ?>,<?= $padTop + $plotHeight ?>"></polygon>
                <polyline class="chart__line"
                          points="<?= e(implode(' ', array_map(
                              static fn (array $p): string => $p['x'] . ',' . $p['y'],
                              $chartPoints
                          ))) ?>"></polyline>
            <?php endif; ?>

            <?php foreach ($chartPoints as $point): ?>
                <circle class="chart__point" cx="<?= $point['x'] ?>" cy="<?= $point['y'] ?>" r="5">
                    <title><?= e($point['title'] . ' — ' . $point['accuracy'] . '% (' . $point['score'] . ' marks, ' . $point['date'] . ')') ?></title>
                </circle>
                <text class="chart__label" x="<?= $point['x'] ?>" y="<?= $height - 12 ?>"
                      text-anchor="middle"><?= e(date('d/m', strtotime($point['date']))) ?></text>
            <?php endforeach; ?>
        </svg>
    </div>
<?php endif; ?>

<?php if ($byTopic): ?>
    <div class="section-head" style="margin-top:var(--section-gap)">
        <div>
            <h2>Best score by test</h2>
            <p>Your highest score in each test you have attempted.</p>
        </div>
    </div>
    <div class="card">
        <div class="bar-list">
            <?php foreach ($byTopic as $title => $score): ?>
                <div class="bar-row">
                    <span class="bar-row__label"><?= e((string) $title) ?></span>
                    <span class="bar-row__track">
                        <span class="bar-row__fill" style="width:<?= $topicMax > 0 ? (int) round(($score / $topicMax) * 100) : 0 ?>%"></span>
                    </span>
                    <span class="bar-row__value"><?= (int) $score ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="section-head" style="margin-top:var(--section-gap)">
    <div>
        <h2>Profile settings</h2>
        <p>Keep your details up to date. Your e-mail address identifies your account and cannot be changed.</p>
    </div>
</div>

<div class="profile-grid">
    <div class="card">
        <div class="card__head">
            <h3 class="card__title" style="font-size:1.05rem">Your details</h3>
        </div>
        <form class="form-grid" method="post" action="<?= e(app_url('actions/profile.php?action=update-profile')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Full name</label>
                <input class="input" type="text" id="name" name="name" autocomplete="name"
                       value="<?= e((string) $user['name']) ?>" required>
            </div>
            <div class="field">
                <label for="email">E-mail address</label>
                <input class="input" type="email" id="email" value="<?= e((string) $user['email']) ?>" disabled>
                <span class="hint">Contact an administrator to change your e-mail address.</span>
            </div>
            <div class="field">
                <label for="college">College / institute</label>
                <input class="input" type="text" id="college" name="college" autocomplete="organization"
                       placeholder="e.g. National Institute of Technology"
                       value="<?= e((string) $user['college']) ?>">
            </div>
            <div class="form-actions">
                <button class="btn btn--primary" type="submit">Save changes</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card__head">
            <h3 class="card__title" style="font-size:1.05rem">
                <?= $hasPassword ? 'Change password' : 'Set a password' ?>
            </h3>
            <p class="card__subtitle">
                <?php if ($hasPassword): ?>
                    Enter your current password, then choose a new one.
                <?php else: ?>
                    Your account was created from a registration link and has no password yet.
                    Setting one lets you sign in from any device.
                <?php endif; ?>
            </p>
        </div>
        <form class="form-grid" method="post" action="<?= e(app_url('actions/profile.php?action=update-password')) ?>">
            <?= csrf_field() ?>
            <?php if ($hasPassword): ?>
                <div class="field">
                    <label for="current_password">Current password</label>
                    <input class="input" type="password" id="current_password" name="current_password"
                           autocomplete="current-password" required>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="new_password">New password</label>
                <input class="input" type="password" id="new_password" name="new_password"
                       autocomplete="new-password" placeholder="At least 4 characters" required>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm new password</label>
                <input class="input" type="password" id="confirm_password" name="confirm_password"
                       autocomplete="new-password" required>
            </div>
            <div class="form-actions">
                <button class="btn btn--primary" type="submit">
                    <?= $hasPassword ? 'Change password' : 'Set password' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="section-head" style="margin-top:var(--section-gap)">
    <div>
        <h2>Recent activity</h2>
        <p>Your five most recent attempts.</p>
    </div>
</div>

<?php if (!$attempts): ?>
    <div class="empty">
        <span class="empty__icon" aria-hidden="true">🗂️</span>
        <h3>Nothing here yet</h3>
        <p>Your attempts will be listed here once you complete a quiz.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th>Test</th>
                        <th>Correct</th>
                        <th>Wrong</th>
                        <th>Score</th>
                        <th>Accuracy</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice(array_reverse($attempts), 0, 5) as $i => $row):
                    $level = (int) $row['level'];
                    $right = (int) $row['sahi'];
                    $accuracy = $level > 0 ? (int) round(($right / $level) * 100) : 0;
                    ?>
                    <tr>
                        <td class="col-num"><?= $i + 1 ?></td>
                        <td><strong><?= e((string) ($row['title'] ?? 'Deleted quiz')) ?></strong></td>
                        <td><span class="badge badge--success"><?= $right ?></span></td>
                        <td><span class="badge badge--danger"><?= (int) $row['wrong'] ?></span></td>
                        <td><strong><?= (int) $row['score'] ?></strong></td>
                        <td><?= $accuracy ?>%</td>
                        <td class="is-muted"><?= e(date('d M Y, H:i', strtotime((string) $row['date']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="form-foot">
        <a href="<?= e(app_url('student/dashboard.php?page=history')) ?>">See your full history</a>
    </p>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
