<?php
/**
 * Administrator dashboard: overview, students, ranking, quiz builder and
 * quiz removal.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

$page = (string) ($_GET['page'] ?? 'overview');
$validPages = ['overview', 'students', 'ranking', 'add-quiz', 'add-questions', 'remove-quiz', 'registrations'];
if (!in_array($page, $validPages, true)) {
    $page = 'overview';
}

$pageTitle = 'Admin dashboard';
$navItems = [
    'Overview' => app_url('admin/dashboard.php'),
    'Students' => app_url('admin/dashboard.php?page=students'),
    'Registrations' => app_url('admin/dashboard.php?page=registrations'),
    'Ranking' => app_url('admin/dashboard.php?page=ranking'),
    'Add quiz' => app_url('admin/dashboard.php?page=add-quiz'),
    'Remove quiz' => app_url('admin/dashboard.php?page=remove-quiz'),
    'Log out' => app_url('logout.php'),
];
$activeNav = 'Overview';
$pageHeadings = [
    'overview' => ['Administrator overview', 'Track quizzes, questions, students and attempts at a glance.'],
    'students' => ['Registered students', 'Removing a student also deletes their attempts and ranking entry.'],
    'registrations' => ['Test registrations', 'Share a registration link, then see who signed up for each test.'],
    'ranking' => ['Ranking', 'Students ordered by cumulative score.'],
    'add-quiz' => ['Create a quiz', 'Define the title, the number of questions, the marking scheme and the attempt window.'],
    'remove-quiz' => ['Remove a quiz', 'Deleting a quiz also removes its questions, options and student attempts.'],
];
[$pageHeading, $pageLead] = $pageHeadings[$page] ?? ['', ''];

$overview = ['quizzes' => 0, 'questions' => 0, 'students' => 0, 'attempts' => 0];
$students = [];
$ranking = [];
$quizzes = [];
$builderQuiz = null;
$enrolled = [];
$registrationLink = '';

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
} elseif ($page === 'registrations') {
    $activeNav = 'Registrations';

    // Every quiz, with its registration count, so the administrator can pick
    // one and share its link.
    $quizzes = db_all(
        'SELECT q.*,
                (SELECT COUNT(*) FROM enrollment e WHERE e.eid = q.eid) AS enrolled_count
         FROM quiz q
         ORDER BY q.date DESC'
    );

    $selectedEid = (string) ($_GET['eid'] ?? '');
    if ($selectedEid === '' && $quizzes) {
        $selectedEid = (string) $quizzes[0]['eid'];
    }

    $selectedQuiz = null;
    if ($selectedEid !== '') {
        $selectedQuiz = db_one("SELECT * FROM quiz WHERE eid = '" . db_escape($selectedEid) . "'");
    }

    if ($selectedQuiz !== null) {
        $selectedEidSql = db_escape((string) $selectedQuiz['eid']);
        $enrolled = db_all(
            "SELECT e.email, e.registered_at, u.name, u.college
             FROM enrollment e
             LEFT JOIN user u ON u.email = e.email
             WHERE e.eid = '$selectedEidSql'
             ORDER BY e.registered_at ASC"
        );
        $registrationLink = app_url('quiz_register.php?eid=' . urlencode((string) $selectedQuiz['eid']));
        $pageHeading = 'Registrations: ' . (string) $selectedQuiz['title'];
    }
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
                            <th>Schedule</th>
                            <th>Attempts</th>
                            <th>Created</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($quizzes as $i => $quiz):
                        $window = quiz_window($quiz);
                        ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td><strong><?= e((string) $quiz['title']) ?></strong></td>
                            <td><?= (int) $quiz['question_count'] ?></td>
                            <td><?= (int) $quiz['sahi'] ?></td>
                            <td><?= (int) $quiz['wrong'] ?></td>
                            <td>
                                <span class="badge <?= e($window['badge']) ?>"><?= e($window['label']) ?></span>
                            </td>
                            <td><?= (int) $quiz['attempt_count'] ?></td>
                            <td class="is-muted"><?= e(date('d M Y', strtotime((string) $quiz['date']))) ?></td>
                            <td class="col-actions">
                                <a class="btn btn--ghost btn--sm"
                                   href="<?= e(app_url('admin/dashboard.php?page=registrations&eid=' . urlencode((string) $quiz['eid']))) ?>">Registrations</a>
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
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label for="start_at">Opens at</label>
                    <input class="input" type="datetime-local" id="start_at" name="start_at">
                    <span class="hint">Leave blank to open immediately.</span>
                </div>
                <div class="field">
                    <label for="end_at">Closes at</label>
                    <input class="input" type="datetime-local" id="end_at" name="end_at">
                    <span class="hint">Leave blank to keep it open indefinitely.</span>
                </div>
            </div>
            <p class="hint">
                Candidates can only see and attempt this test if they registered through its
                link, and only between these two times.
            </p>
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

<?php elseif ($page === 'registrations'): ?>

    <?php if (!$quizzes): ?>
        <div class="empty">
            <span class="empty__icon" aria-hidden="true">🔗</span>
            <h3>No tests to share yet</h3>
            <p>Create a test first, then its registration link appears here.</p>
            <p style="margin-top:1rem">
                <a class="btn btn--primary" href="<?= e(app_url('admin/dashboard.php?page=add-quiz')) ?>">Create a test</a>
            </p>
        </div>
    <?php else: ?>

        <div class="card mb-2">
            <div class="card__head">
                <h2 class="card__title">Registration link</h2>
                <p class="card__subtitle">
                    Send this link to the candidates you want in this test. Anyone who registers
                    through it is enrolled for this test only.
                </p>
            </div>
            <?php if ($registrationLink !== ''): ?>
                <?php
                $shareTitle = 'Register for ' . (string) $selectedQuiz['title'];
                $shareText = 'Use this link to register for "' . (string) $selectedQuiz['title'] . '":';
                ?>
                <div class="field">
                    <label for="reg-link">Shareable link</label>
                    <div class="copy-row">
                        <input class="input" type="text" id="reg-link" value="<?= e($registrationLink) ?>" readonly
                               onfocus="this.select()">
                        <button class="btn btn--ghost" type="button" data-copy-target="#reg-link">Copy</button>
                    </div>
                    <span class="hint">Also works for candidates who already have an account — signing in enrolls them.</span>
                </div>
                <div class="share-row">
                    <span class="share-row__label">Share via</span>
                    <a class="btn btn--ghost btn--sm"
                       href="mailto:?subject=<?= rawurlencode($shareTitle) ?>&amp;body=<?= rawurlencode($shareText . "\n\n" . $registrationLink) ?>">E-mail</a>
                    <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener"
                       href="https://wa.me/?text=<?= rawurlencode($shareText . ' ' . $registrationLink) ?>">WhatsApp</a>
                    <a class="btn btn--ghost btn--sm"
                       href="sms:?body=<?= rawurlencode($shareText . ' ' . $registrationLink) ?>">SMS</a>
                    <button class="btn btn--ghost btn--sm" type="button" hidden
                            data-share-url="<?= e($registrationLink) ?>"
                            data-share-title="<?= e($shareTitle) ?>"
                            data-share-text="<?= e($shareText) ?>">More…</button>
                </div>
            <?php else: ?>
                <p class="muted">Select a test below to see its registration link.</p>
            <?php endif; ?>
        </div>

        <div class="table-wrap">
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th>Test</th>
                            <th>Schedule</th>
                            <th>Registered</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($quizzes as $i => $quiz):
                        $window = quiz_window($quiz);
                        $isSelected = $selectedQuiz !== null && (string) $selectedQuiz['eid'] === (string) $quiz['eid'];
                        ?>
                        <tr>
                            <td class="col-num"><?= $i + 1 ?></td>
                            <td><strong><?= e((string) $quiz['title']) ?></strong></td>
                            <td>
                                <span class="badge <?= e($window['badge']) ?>"><?= e($window['label']) ?></span>
                                <div class="is-muted" style="font-size:.8rem;margin-top:.25rem"><?= e($window['detail']) ?></div>
                            </td>
                            <td><?= (int) $quiz['enrolled_count'] ?></td>
                            <td class="col-actions">
                                <a class="btn <?= $isSelected ? 'btn--primary' : 'btn--ghost' ?> btn--sm"
                                   href="<?= e(app_url('admin/dashboard.php?page=registrations&eid=' . urlencode((string) $quiz['eid']))) ?>">
                                    <?= $isSelected ? 'Viewing' : 'View candidates' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($selectedQuiz !== null): ?>
            <div class="section-head" style="margin-top:var(--section-gap)">
                <div>
                    <h2>Candidates registered for this test</h2>
                    <p><?= count($enrolled) ?> candidate(s) enrolled.</p>
                </div>
            </div>

            <?php if (!$enrolled): ?>
                <div class="empty">
                    <span class="empty__icon" aria-hidden="true">👥</span>
                    <h3>No candidates yet</h3>
                    <p>Share the registration link above. Sign-ups will appear here.</p>
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
                                    <th>Registered on</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($enrolled as $i => $row): ?>
                                <tr>
                                    <td class="col-num"><?= $i + 1 ?></td>
                                    <td><strong><?= e((string) ($row['name'] ?? '—')) ?></strong></td>
                                    <td class="is-muted"><?= e((string) ($row['college'] ?? '—')) ?></td>
                                    <td><?= e((string) $row['email']) ?></td>
                                    <td class="is-muted"><?= e(date('d M Y, H:i', strtotime((string) $row['registered_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

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
