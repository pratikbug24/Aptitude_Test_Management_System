<?php
/**
 * Administrator dashboard: overview, students, ranking, quiz builder and
 * quiz removal.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$page = (string) ($_GET['page'] ?? 'overview');
$validPages = ['overview', 'students', 'ranking', 'add-quiz', 'add-questions', 'remove-quiz'];
if (!in_array($page, $validPages, true)) {
    $page = 'overview';
}

$pageTitle = 'Admin dashboard';
$navItems = [
    'Overview' => app_url('admin/dashboard.php'),
    'Students' => app_url('admin/dashboard.php?page=students'),
    'Ranking' => app_url('admin/dashboard.php?page=ranking'),
    'Add quiz' => app_url('admin/dashboard.php?page=add-quiz'),
    'Remove quiz' => app_url('admin/dashboard.php?page=remove-quiz'),
    'Log out' => app_url('logout.php'),
];
$activeNav = 'Overview';
$pageHeadings = [
    'overview' => ['Administrator overview', 'Track quizzes, questions, students and attempts at a glance.'],
    'students' => ['Registered students', 'Removing a student also deletes their attempts and ranking entry.'],
    'ranking' => ['Ranking', 'Students ordered by cumulative score.'],
    'add-quiz' => ['Create a quiz', 'Define the title, the number of questions and the marking scheme.'],
    'remove-quiz' => ['Remove a quiz', 'Deleting a quiz also removes its questions, options and student attempts.'],
];
[$pageHeading, $pageLead] = $pageHeadings[$page] ?? ['', ''];

$overview = ['quizzes' => 0, 'questions' => 0, 'students' => 0, 'attempts' => 0];
$students = [];
$ranking = [];
$quizzes = [];
$builderQuiz = null;

if ($page === 'overview') {
    $overview = [
        'quizzes' => (int) (db_one('SELECT COUNT(*) AS c FROM quiz')['c'] ?? 0),
        'questions' => (int) (db_one('SELECT COUNT(*) AS c FROM questions')['c'] ?? 0),
        'students' => (int) (db_one('SELECT COUNT(*) AS c FROM user')['c'] ?? 0),
        'attempts' => (int) (db_one('SELECT COUNT(*) AS c FROM history')['c'] ?? 0),
    ];
    $quizzes = db_all(
        'SELECT q.*,
                (SELECT COUNT(*) FROM questions qs WHERE qs.eid = q.eid) AS question_count,
                (SELECT COUNT(*) FROM history h WHERE h.eid = q.eid) AS attempt_count
         FROM quiz q
         ORDER BY q.date DESC'
    );
} elseif ($page === 'students') {
    $activeNav = 'Students';
    $students = db_all('SELECT * FROM user ORDER BY name ASC');
} elseif ($page === 'ranking') {
    $activeNav = 'Ranking';
    $ranking = db_all(
        'SELECT r.email, r.score, u.name, u.college
         FROM rank r
         LEFT JOIN user u ON u.email = r.email
         ORDER BY r.score DESC, r.time ASC'
    );
} elseif ($page === 'remove-quiz') {
    $activeNav = 'Remove quiz';
    $quizzes = db_all(
        'SELECT q.*, (SELECT COUNT(*) FROM questions qs WHERE qs.eid = q.eid) AS question_count
         FROM quiz q
         ORDER BY q.date DESC'
    );
} elseif ($page === 'add-questions') {
    $activeNav = 'Add quiz';
    $eid = (string) ($_GET['eid'] ?? '');
    $eidSql = db_escape($eid);
    $builderQuiz = db_one("SELECT * FROM quiz WHERE eid = '$eidSql'");

    if ($builderQuiz === null) {
        flash('error', 'That quiz could not be found. Please create it again.');
        redirect('admin/dashboard.php?page=add-quiz');
    }
    $pageHeading = 'Add questions: ' . (string) $builderQuiz['title'];
    $pageLead = 'Provide the question text, four options and the correct answer.';
} elseif ($page === 'add-quiz') {
    $activeNav = 'Add quiz';
}

require dirname(__DIR__) . '/includes/header.php';
?>

<?php if ($page === 'overview'): ?>

    <div class="stat-grid">
        <div class="stat">
            <div class="stat__label">Quizzes</div>
            <div class="stat__value"><?= $overview['quizzes'] ?></div>
            <div class="stat__hint">Published tests</div>
        </div>
        <div class="stat stat--teal">
            <div class="stat__label">Questions</div>
            <div class="stat__value"><?= $overview['questions'] ?></div>
            <div class="stat__hint">Across all quizzes</div>
        </div>
        <div class="stat stat--amber">
            <div class="stat__label">Students</div>
            <div class="stat__value"><?= $overview['students'] ?></div>
            <div class="stat__hint">Registered accounts</div>
        </div>
        <div class="stat stat--rose">
            <div class="stat__label">Attempts</div>
            <div class="stat__value"><?= $overview['attempts'] ?></div>
            <div class="stat__hint">Recorded quiz submissions</div>
        </div>
    </div>

    <div class="section-head">
        <div>
            <h2>Quiz catalogue</h2>
            <p>Everything currently available to students.</p>
        </div>
        <a class="btn btn--primary" href="<?= e(app_url('admin/dashboard.php?page=add-quiz')) ?>">Add a quiz</a>
    </div>

    <?php if (!$quizzes): ?>
        <div class="empty">
            <span class="empty__icon" aria-hidden="true">📚</span>
            <h3>No quizzes yet</h3>
            <p>Create your first quiz to make it available to students.</p>
            <p style="margin-top:1rem">
                <a class="btn btn--primary" href="<?= e(app_url('admin/dashboard.php?page=add-quiz')) ?>">Create a quiz</a>
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th>Title</th>
                            <th>Questions</th>
                            <th>Marks / correct</th>
                            <th>Penalty / wrong</th>
                            <th>Attempts</th>
                            <th>Created</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($quizzes as $i => $quiz): ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td><strong><?= e((string) $quiz['title']) ?></strong></td>
                            <td><?= (int) $quiz['question_count'] ?></td>
                            <td><?= (int) $quiz['sahi'] ?></td>
                            <td><?= (int) $quiz['wrong'] ?></td>
                            <td><?= (int) $quiz['attempt_count'] ?></td>
                            <td class="is-muted"><?= e(date('d M Y', strtotime((string) $quiz['date']))) ?></td>
                            <td class="col-actions">
                                <a class="btn btn--danger btn--sm"
                                   href="<?= e(app_url('admin/dashboard.php?page=remove-quiz')) ?>">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'students'): ?>

    <?php if (!$students): ?>
        <div class="empty">
            <span class="empty__icon" aria-hidden="true">👥</span>
            <h3>No students registered</h3>
            <p>Student accounts appear here as soon as people sign up.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th>Name</th>
                            <th>College</th>
                            <th>E-mail</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $i => $student): ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td><strong><?= e((string) $student['name']) ?></strong></td>
                            <td class="is-muted"><?= e((string) $student['college']) ?></td>
                            <td><?= e((string) $student['email']) ?></td>
                            <td class="col-actions">
                                <form method="post" action="<?= e(app_url('actions/admin.php?action=delete-student')) ?>"
                                      onsubmit="return confirm('Delete this student and all their attempts?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="email" value="<?= e((string) $student['email']) ?>">
                                    <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                                </form>
                            </td>
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
            <h3>No rankings yet</h3>
            <p>Rankings appear once students submit quizzes.</p>
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

<?php elseif ($page === 'add-quiz'): ?>

    <div class="card" style="max-width:720px">
        <form class="form-grid" method="post" action="<?= e(app_url('actions/admin.php?action=create-quiz')) ?>" data-guard>
            <?= csrf_field() ?>
            <div class="field">
                <label for="title">Quiz title</label>
                <input class="input" type="text" id="title" name="title"
                       placeholder="e.g. Quantitative Aptitude — Level 1" required autofocus>
                <span class="hint">Shown to students on their dashboard.</span>
            </div>
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label for="total">Number of questions</label>
                    <input class="input" type="number" id="total" name="total" min="1" max="100" value="10" required>
                </div>
                <div class="field">
                    <label for="sahi">Marks for a correct answer</label>
                    <input class="input" type="number" id="sahi" name="sahi" min="0" max="100" value="1" required>
                </div>
                <div class="field">
                    <label for="wrong">Marks deducted for a wrong answer</label>
                    <input class="input" type="number" id="wrong" name="wrong" min="0" max="100" value="0" required>
                    <span class="hint">Enter a positive number; it is subtracted on a wrong answer.</span>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn--primary" type="submit">Continue to questions</button>
                <a class="btn btn--ghost" href="<?= e(app_url('admin/dashboard.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($page === 'add-questions' && $builderQuiz): ?>

    <?php $total = (int) $builderQuiz['total']; ?>
    <form method="post"
          action="<?= e(app_url('actions/admin.php?action=add-questions&eid=' . urlencode((string) $builderQuiz['eid']))) ?>"
          data-guard>
        <?= csrf_field() ?>
        <?php for ($i = 1; $i <= $total; $i++): ?>
            <fieldset class="fieldset">
                <legend>Question <?= $i ?></legend>
                <div class="form-grid">
                    <div class="field">
                        <label for="qns<?= $i ?>">Question text</label>
                        <textarea class="textarea" id="qns<?= $i ?>" name="qns<?= $i ?>"
                                  placeholder="Type the question…" required></textarea>
                    </div>
                    <div class="form-grid form-grid--2">
                        <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                            <div class="field">
                                <label for="<?= $i . $letter ?>">Option <?= $letter ?></label>
                                <input class="input" type="text" id="<?= $i . $letter ?>" name="<?= $i . $letter ?>"
                                       placeholder="Option <?= $letter ?>" required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="field">
                        <label for="ans<?= $i ?>">Correct answer</label>
                        <select class="select" id="ans<?= $i ?>" name="ans<?= $i ?>" required>
                            <option value="a">Option a</option>
                            <option value="b">Option b</option>
                            <option value="c">Option c</option>
                            <option value="d">Option d</option>
                        </select>
                    </div>
                </div>
            </fieldset>
        <?php endfor; ?>

        <div class="form-actions">
            <button class="btn btn--primary" type="submit">Publish quiz</button>
            <a class="btn btn--ghost" href="<?= e(app_url('admin/dashboard.php?page=add-quiz')) ?>">Cancel</a>
        </div>
    </form>

<?php elseif ($page === 'remove-quiz'): ?>

    <?php if (!$quizzes): ?>
        <div class="empty">
            <span class="empty__icon" aria-hidden="true">🗑️</span>
            <h3>Nothing to remove</h3>
            <p>There are no quizzes published at the moment.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th>Title</th>
                            <th>Questions</th>
                            <th>Maximum marks</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($quizzes as $i => $quiz):
                        $maxMarks = (int) $quiz['sahi'] * (int) $quiz['total'];
                        ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td><strong><?= e((string) $quiz['title']) ?></strong></td>
                            <td><?= (int) $quiz['question_count'] ?></td>
                            <td><?= $maxMarks ?></td>
                            <td class="col-actions">
                                <form method="post" action="<?= e(app_url('actions/admin.php?action=delete-quiz')) ?>"
                                      onsubmit="return confirm('Remove this quiz, its questions and all attempts?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="eid" value="<?= e((string) $quiz['eid']) ?>">
                                    <button class="btn btn--danger btn--sm" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
