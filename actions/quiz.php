<?php
/**
 * Records a student's answer to one question and advances the attempt.
 *
 * The quiz runner posts here with the chosen option. The answer is compared
 * with the stored key, the attempt row is updated, and the student is sent to
 * the next question — or to the result page once the last question is done.
 *
 * A CSRF-protected POST is required; the request always ends in a redirect.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf();

$email = current_email();
$emailSql = db_escape($email);

$eid = (string) ($_GET['eid'] ?? '');
$eidSql = db_escape($eid);
$sn = max(1, (int) ($_GET['n'] ?? 1));

$quiz = db_one("SELECT * FROM quiz WHERE eid = '$eidSql'");
if ($quiz === null) {
    flash('error', 'That quiz could not be found.');
    redirect('student/dashboard.php');
}

require_quiz_access($quiz);

$total = (int) $quiz['total'];
if ($sn > $total) {
    redirect('student/dashboard.php?page=result&eid=' . urlencode($eid));
}

$question = db_one("SELECT * FROM questions WHERE eid = '$eidSql' AND sn = $sn");
if ($question === null) {
    flash('error', 'That question could not be found.');
    redirect('student/dashboard.php');
}

$qidSql = db_escape((string) $question['qid']);
$chosen = (string) ($_POST['ans'] ?? '');
$correct = db_one("SELECT ansid FROM answer WHERE qid = '$qidSql'");
$isCorrect = $correct !== null && (string) $correct['ansid'] === $chosen;

// Starting a fresh attempt (or restarting one) clears the previous result and
// removes its contribution from the ranking, so scores never double up.
if ($sn === 1) {
    reset_attempt($emailSql, $eidSql);
    db_query(
        "INSERT INTO history (email, eid, score, level, sahi, wrong, date)
         VALUES ('$emailSql', '$eidSql', 0, 0, 0, 0, NOW())"
    );
}

$attempt = db_one("SELECT * FROM history WHERE eid = '$eidSql' AND email = '$emailSql'");
if ($attempt === null) {
    flash('error', 'Your attempt could not be loaded. Please start again.');
    redirect('student/dashboard.php');
}

$score = (int) $attempt['score'];
$right = (int) $attempt['sahi'];
$wrongCount = (int) $attempt['wrong'];

if ($isCorrect) {
    $score += (int) $quiz['sahi'];
    $right++;
} else {
    $score -= (int) $quiz['wrong'];
    $wrongCount++;
}

db_query(
    "UPDATE history
     SET score = $score, level = $sn, sahi = $right, wrong = $wrongCount, date = NOW()
     WHERE email = '$emailSql' AND eid = '$eidSql'"
);

if ($sn < $total) {
    redirect('student/dashboard.php?page=quiz&eid=' . urlencode($eid) . '&n=' . ($sn + 1));
}

update_rank($emailSql, $score);
redirect('student/dashboard.php?page=result&eid=' . urlencode($eid));

// ---------------------------------------------------------------------------
// Attempt bookkeeping
// ---------------------------------------------------------------------------

/**
 * Remove any existing attempt for this quiz and subtract its score from the
 * student's cumulative rank, so a restart replaces rather than accumulates.
 */
function reset_attempt(string $emailSql, string $eidSql): void
{
    $previous = db_one("SELECT score FROM history WHERE email = '$emailSql' AND eid = '$eidSql'");
    if ($previous === null) {
        return;
    }

    $oldScore = (int) $previous['score'];
    db_query("DELETE FROM history WHERE email = '$emailSql' AND eid = '$eidSql'");

    $rank = db_one("SELECT score FROM rank WHERE email = '$emailSql'");
    if ($rank !== null && $oldScore !== 0) {
        $adjusted = (int) $rank['score'] - $oldScore;
        db_query("UPDATE rank SET score = $adjusted, time = NOW() WHERE email = '$emailSql'");
    }
}

/** Add this attempt's score to the student's cumulative ranking entry. */
function update_rank(string $emailSql, int $score): void
{
    $rank = db_one("SELECT score FROM rank WHERE email = '$emailSql'");
    if ($rank === null) {
        db_query("INSERT INTO rank (email, score, time) VALUES ('$emailSql', $score, NOW())");
        return;
    }

    $cumulative = (int) $rank['score'] + $score;
    db_query("UPDATE rank SET score = $cumulative, time = NOW() WHERE email = '$emailSql'");
}
