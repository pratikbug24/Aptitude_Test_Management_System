<?php
/**
 * Student dashboard: quiz list, quiz runner, results, history and ranking.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_student();

$email = current_email();
$name = current_name();
$emailSql = db_escape($email);

$page = (string) ($_GET['page'] ?? 'home');
$validPages = ['home', 'quiz', 'result', 'history', 'ranking'];
if (!in_array($page, $validPages, true)) {
    $page = 'home';
}

$pageTitle = 'Dashboard';
$activeNav = 'Home';
$pageHeadings = [
    'home' => ['Welcome back, ' . $name, 'Choose a quiz to attempt, review past results or check the leaderboard.'],
    'history' => ['Your attempt history', 'A record of every quiz you have completed.'],
    'ranking' => ['Leaderboard', 'Students ranked by total score across all attempts.'],
];
[$pageHeading, $pageLead] = $pageHeadings[$page] ?? ['', ''];
$navItems = [
    'Home' => app_url('student/dashboard.php'),
    'History' => app_url('student/dashboard.php?page=history'),
    'Ranking' => app_url('student/dashboard.php?page=ranking'),
    'Log out' => app_url('logout.php'),
];

// ---------------------------------------------------------------------------
// Gather view data for the requested page.
// ---------------------------------------------------------------------------
$quizzes = [];
$history = [];
$ranking = [];
$quizMeta = null;
$question = null;
$options = [];
$result = null;
$stats = ['attempts' => 0, 'score' => 0, 'best' => 0];

if ($page === 'home') {
    $activeNav = 'Home';
    $quizzes = db_all('SELECT * FROM quiz ORDER BY date DESC');

    $row = db_one("SELECT COUNT(*) AS attempts, COALESCE(SUM(score), 0) AS score FROM history WHERE email = '$emailSql'");
    $stats['attempts'] = (int) ($row['attempts'] ?? 0);
    $stats['score'] = (int) ($row['score'] ?? 0);
    $stats['best'] = (int) (db_one("SELECT COALESCE(MAX(score), 0) AS best FROM history WHERE email = '$emailSql'")['best'] ?? 0);
} elseif ($page === 'history') {
    $activeNav = 'History';
    $history = db_all(
        "SELECT h.*, q.title
         FROM history h
         LEFT JOIN quiz q ON q.eid = h.eid
         WHERE h.email = '$emailSql'
         ORDER BY h.date DESC"
    );
} elseif ($page === 'ranking') {
    $activeNav = 'Ranking';
    $ranking = db_all(
        "SELECT r.email, r.score, u.name, u.college
         FROM rank r
         LEFT JOIN user u ON u.email = r.email
         ORDER BY r.score DESC, r.time ASC"
    );
} elseif ($page === 'quiz') {
    $eid = (string) ($_GET['eid'] ?? '');
    $sn = max(1, (int) ($_GET['n'] ?? 1));
    $eidSql = db_escape($eid);

    $quizMeta = db_one("SELECT * FROM quiz WHERE eid = '$eidSql'");
    if ($quizMeta === null) {
        flash('error', 'That quiz could not be found.');
        redirect('student/dashboard.php');
    }

    $total = (int) $quizMeta['total'];
    if ($sn > $total) {
        redirect('student/dashboard.php?page=result&eid=' . urlencode($eid));
    }

    $question = db_one("SELECT * FROM questions WHERE eid = '$eidSql' AND sn = $sn");
    if ($question === null) {
        flash('error', 'That question could not be found.');
        redirect('student/dashboard.php');
    }

    $qidSql = db_escape((string) $question['qid']);
    $options = db_all("SELECT * FROM options WHERE qid = '$qidSql'");
    $pageHeading = 'Attempting: ' . (string) $quizMeta['title'];
    $pageLead = 'Answer each question and submit to move on. Your score updates instantly.';
} elseif ($page === 'result') {
    $eid = (string) ($_GET['eid'] ?? '');
    $eidSql = db_escape($eid);

    $quizMeta = db_one("SELECT * FROM quiz WHERE eid = '$eidSql'");
    $result = db_one("SELECT * FROM history WHERE eid = '$eidSql' AND email = '$emailSql'");
    $overall = db_one("SELECT score FROM rank WHERE email = '$emailSql'");

    if ($result === null) {
        flash('info', 'No attempt found for that quiz yet.');
        redirect('student/dashboard.php');
    }
    $result['overall'] = (int) ($overall['score'] ?? 0);
}

require dirname(__DIR__) . '/includes/header.php';
?>

<?php if ($page === 'home'): ?>

    <div class="stat-grid">
        <div class="stat">
            <div class="stat__label">Quizzes available</div>
            <div class="stat__value"><?= count($quizzes) ?></div>
            <div class="stat__hint">Published by administrators</div>
        </div>
        <div class="stat stat--teal">
            <div class="stat__label">Your attempts</div>
            <div class="stat__value"><?= $stats['attempts'] ?></div>
            <div class="stat__hint">Quizzes you have finished</div>
        </div>
        <div class="stat stat--amber">
            <div class="stat__label">Total score</div>
            <div class="stat__value"><?= $stats['score'] ?></div>
            <div class="stat__hint">Marks earned across attempts</div>
        </div>
        <div class="stat stat--rose">
            <div class="stat__label">Best score</div>
            <div class="stat__value"><?= $stats['best'] ?></div>
            <div class="stat__hint">Your highest single attempt</div>
        </div>
    </div>

    <div class="section-head">
        <div>
            <h2>Available quizzes</h2>
            <p>Pick a topic to begin. Attempted quizzes can be restarted.</p>
        </div>
    </div>

    <?php if (!$quizzes): ?>
        <div class="empty">
            <span class="empty__icon" aria-hidden="true">📝</span>
            <h3>No quizzes published yet</h3>
            <p>Please check back later — your administrator has not added a quiz so far.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data">
                    <caption class="visually-hidden">Quizzes you can attempt</caption>
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th>Topic</th>
                            <th>Questions</th>
                            <th>Maximum marks</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($quizzes as $i => $quiz):
                        $eid = (string) $quiz['eid'];
                        $eidSql = db_escape($eid);
                        $attempted = db_one("SELECT score FROM history WHERE eid = '$eidSql' AND email = '$emailSql'") !== null;
                        $maxMarks = (int) $quiz['sahi'] * (int) $quiz['total'];
                        ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td>
                                <strong><?= e((string) $quiz['title']) ?></strong>
                                <?php if ($attempted): ?>
                                    <span class="badge badge--success">Attempted</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $quiz['total'] ?></td>
                            <td><?= $maxMarks ?></td>
                            <td class="col-actions">
                                <?php if ($attempted): ?>
                                    <a class="btn btn--amber btn--sm"
                                       href="<?= e(app_url('student/dashboard.php?page=quiz&eid=' . urlencode($eid) . '&n=1')) ?>">Restart</a>
                                <?php else: ?>
                                    <a class="btn btn--success btn--sm"
                                       href="<?= e(app_url('student/dashboard.php?page=quiz&eid=' . urlencode($eid) . '&n=1')) ?>">Start</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'quiz' && $question): ?>

    <?php
    $total = (int) $quizMeta['total'];
    $progress = (int) round(($sn / $total) * 100);
    $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
    ?>
    <div class="question-card">
        <div class="quiz-progress">
            <span class="quiz-progress__count">Question <?= $sn ?> of <?= $total ?></span>
            <span class="progress"><span class="progress__bar" data-value="<?= $progress ?>"></span></span>
            <span class="quiz-progress__count"><?= $progress ?>%</span>
        </div>

        <div class="card">
            <div class="question-card__meta">
                <span class="badge badge--info"><?= e((string) $quizMeta['title']) ?></span>
                <span class="badge"><?= (int) $quizMeta['sahi'] ?> mark(s) for a correct answer</span>
                <span class="badge"><?= (int) $quizMeta['wrong'] ?> mark(s) deducted for a wrong answer</span>
            </div>

            <p class="question-card__text"><?= nl2br(e((string) $question['qns'])) ?></p>

            <form method="post"
                  action="<?= e(app_url('actions/quiz.php?eid=' . urlencode((string) $quizMeta['eid']) . '&n=' . $sn)) ?>"
                  data-guard>
                <?= csrf_field() ?>
                <div class="option-list">
                    <?php foreach ($options as $i => $option): ?>
                        <label class="option">
                            <input type="radio" name="ans" value="<?= e((string) $option['optionid']) ?>" required>
                            <span class="option__key" aria-hidden="true"><?= $letters[$i] ?? ($i + 1) ?></span>
                            <span class="option__text"><?= e((string) $option['option']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">
                        <?= $sn >= $total ? 'Finish quiz' : 'Save and next' ?>
                    </button>
                    <a class="btn btn--ghost" href="<?= e(app_url('student/dashboard.php')) ?>">Save and exit</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($page === 'result' && $result): ?>

    <?php
    $totalQuestions = (int) $result['level'];
    $right = (int) $result['sahi'];
    $wrong = (int) $result['wrong'];
    $score = (int) $result['score'];
    $maxMarks = $quizMeta ? (int) $quizMeta['sahi'] * (int) $quizMeta['total'] : 0;
    $accuracy = $totalQuestions > 0 ? (int) round(($right / $totalQuestions) * 100) : 0;
    ?>
    <div class="result-hero">
        <h2><?= e((string) ($quizMeta['title'] ?? 'Quiz')) ?></h2>
        <div class="result-hero__score"><?= $score ?><small> / <?= $maxMarks ?></small></div>
        <p>You answered <?= $right ?> of <?= $totalQuestions ?> questions correctly (<?= $accuracy ?>% accuracy).</p>
    </div>

    <div class="metric-grid">
        <div class="metric metric--info">
            <div class="metric__value"><?= $totalQuestions ?></div>
            <div class="metric__label">Questions attempted</div>
        </div>
        <div class="metric metric--success">
            <div class="metric__value"><?= $right ?></div>
            <div class="metric__label">Correct answers</div>
        </div>
        <div class="metric metric--danger">
            <div class="metric__value"><?= $wrong ?></div>
            <div class="metric__label">Wrong answers</div>
        </div>
        <div class="metric metric--amber">
            <div class="metric__value"><?= $result['overall'] ?></div>
            <div class="metric__label">Overall score</div>
        </div>
    </div>

    <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(app_url('student/dashboard.php')) ?>">Back to quizzes</a>
        <a class="btn btn--ghost" href="<?= e(app_url('student/dashboard.php?page=history')) ?>">View history</a>
        <a class="btn btn--ghost" href="<?= e(app_url('student/dashboard.php?page=ranking')) ?>">See ranking</a>
    </div>

<?php elseif ($page === 'history'): ?>

    <?php if (!$history): ?>
        <div class="empty">
            <span class="empty__icon" aria-hidden="true">📈</span>
            <h3>No attempts yet</h3>
            <p>Once you complete a quiz, its result will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th>Quiz</th>
                            <th>Questions</th>
                            <th>Correct</th>
                            <th>Wrong</th>
                            <th>Score</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $i => $row): ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td><strong><?= e((string) ($row['title'] ?? 'Deleted quiz')) ?></strong></td>
                            <td><?= (int) $row['level'] ?></td>
                            <td><span class="badge badge--success"><?= (int) $row['sahi'] ?></span></td>
                            <td><span class="badge badge--danger"><?= (int) $row['wrong'] ?></span></td>
                            <td><strong><?= (int) $row['score'] ?></strong></td>
                            <td class="is-muted"><?= e(date('d M Y, H:i', strtotime((string) $row['date']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'ranking'): ?>

    <?php if (!$ranking): ?>
        <div class="empty">
            <span class="empty__icon" aria-hidden="true">🏆</span>
            <h3>The leaderboard is empty</h3>
            <p>Rankings appear as soon as students complete their first quiz.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="col-num">Rank</th>
                            <th>Student</th>
                            <th>College</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ranking as $i => $row):
                        $place = $i + 1;
                        $medalClass = $place <= 3 ? ' rank-medal--' . $place : '';
                        ?>
                        <tr>
                            <td class="col-num"><span class="rank-medal<?= $medalClass ?>"><?= $place ?></span></td>
                            <td><strong><?= e((string) ($row['name'] ?? $row['email'])) ?></strong></td>
                            <td class="is-muted"><?= e((string) ($row['college'] ?? '—')) ?></td>
                            <td><strong><?= (int) $row['score'] ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
