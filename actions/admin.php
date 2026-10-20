<?php
/**
 * Administrator state-changing handlers (POST only).
 *
 * Actions:
 *   create-quiz    Create a quiz, then redirect to its question builder.
 *   add-questions  Store the questions, options and answers for a quiz.
 *   delete-quiz    Remove a quiz with its questions, options, answers and history.
 *   delete-student Remove a student with their attempts and ranking entry.
 *
 * A CSRF-protected POST is required for every action; the request is always
 * answered with a redirect (post/redirect/get).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf();

$action = (string) ($_GET['action'] ?? '');

switch ($action) {
    case 'create-quiz':
        create_quiz();
        break;

    case 'add-questions':
        add_questions();
        break;

    case 'delete-quiz':
        delete_quiz();
        break;

    case 'delete-student':
        delete_student();
        break;

    default:
        flash('error', 'Unknown action.');
        redirect('admin/dashboard.php');
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

/** Create a quiz from the title, question count, marking scheme and window. */
function create_quiz(): never
{
    $title = trim((string) ($_POST['title'] ?? ''));
    $total = (int) ($_POST['total'] ?? 0);
    $sahi = (int) ($_POST['sahi'] ?? 0);
    $wrong = (int) ($_POST['wrong'] ?? 0);
    $startAt = normalize_datetime((string) ($_POST['start_at'] ?? ''));
    $endAt = normalize_datetime((string) ($_POST['end_at'] ?? ''));

    if ($title === '' || $total < 1 || $total > 100 || $sahi < 0 || $wrong < 0) {
        flash('error', 'Please provide a title, 1–100 questions and valid mark values.');
        redirect('admin/dashboard.php?page=add-quiz');
    }

    if ($startAt !== null && $endAt !== null && strtotime($endAt) <= strtotime($startAt)) {
        flash('error', 'The closing time must be after the opening time.');
        redirect('admin/dashboard.php?page=add-quiz');
    }

    $eid = uniqid();
    $eidSql = db_escape($eid);
    $titleSql = db_escape($title);
    $startSql = $startAt !== null ? "'" . db_escape($startAt) . "'" : 'NULL';
    $endSql = $endAt !== null ? "'" . db_escape($endAt) . "'" : 'NULL';

    $ok = db_query(
        "INSERT INTO quiz (eid, title, sahi, wrong, total, date, start_at, end_at)
         VALUES ('$eidSql', '$titleSql', $sahi, $wrong, $total, NOW(), $startSql, $endSql)"
    );

    if (!$ok) {
        flash('error', 'We could not create that quiz. Please try again.');
        redirect('admin/dashboard.php?page=add-quiz');
    }

    flash('success', 'Quiz created. Now add its questions.');
    redirect('admin/dashboard.php?page=add-questions&eid=' . urlencode($eid));
}

/** Store every question, its four options and the correct answer. */
function add_questions(): never
{
    $eid = (string) ($_GET['eid'] ?? '');
    $eidSql = db_escape($eid);
    $quiz = db_one("SELECT * FROM quiz WHERE eid = '$eidSql'");

    if ($quiz === null) {
        flash('error', 'That quiz could not be found.');
        redirect('admin/dashboard.php?page=add-quiz');
    }

    $total = (int) $quiz['total'];
    if ($total < 1) {
        flash('error', 'That quiz has no questions to add.');
        redirect('admin/dashboard.php');
    }

    for ($i = 1; $i <= $total; $i++) {
        $qns = trim((string) ($_POST['qns' . $i] ?? ''));
        $options = [];
        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $options[$letter] = trim((string) ($_POST[$i . $letter] ?? ''));
        }
        $answer = (string) ($_POST['ans' . $i] ?? 'a');
        if (!isset($options[$answer])) {
            $answer = 'a';
        }

        if ($qns === '' || in_array('', $options, true)) {
            flash('error', "Question $i is incomplete. Every question needs text and four options.");
            redirect('admin/dashboard.php?page=add-questions&eid=' . urlencode($eid));
        }

        $qid = uniqid();
        $qidSql = db_escape($qid);
        $qnsSql = db_escape($qns);
        db_query(
            "INSERT INTO questions (eid, qid, qns, choice, sn)
             VALUES ('$eidSql', '$qidSql', '$qnsSql', 4, $i)"
        );

        $answerId = '';
        foreach ($options as $letter => $text) {
            $optionId = uniqid();
            $optionSql = db_escape($text);
            $optionIdSql = db_escape($optionId);
            db_query(
                "INSERT INTO options (qid, option, optionid)
                 VALUES ('$qidSql', '$optionSql', '$optionIdSql')"
            );
            if ($letter === $answer) {
                $answerId = $optionId;
            }
        }

        $answerIdSql = db_escape($answerId);
        db_query("INSERT INTO answer (qid, ansid) VALUES ('$qidSql', '$answerIdSql')");
    }

    flash('success', 'Quiz published with ' . $total . ' question(s).');
    redirect('admin/dashboard.php');
}

/** Delete a quiz and everything that hangs off it. */
function delete_quiz(): never
{
    $eid = (string) ($_POST['eid'] ?? '');
    $eidSql = db_escape($eid);

    $questions = db_all("SELECT qid FROM questions WHERE eid = '$eidSql'");
    foreach ($questions as $question) {
        $qidSql = db_escape((string) $question['qid']);
        db_query("DELETE FROM options WHERE qid = '$qidSql'");
        db_query("DELETE FROM answer WHERE qid = '$qidSql'");
    }

    db_query("DELETE FROM questions WHERE eid = '$eidSql'");
    db_query("DELETE FROM enrollment WHERE eid = '$eidSql'");
    db_query("DELETE FROM history WHERE eid = '$eidSql'");
    db_query("DELETE FROM quiz WHERE eid = '$eidSql'");

    flash('success', 'Quiz removed.');
    redirect('admin/dashboard.php?page=remove-quiz');
}

/** Delete a student account, their attempts and their ranking entry. */
function delete_student(): never
{
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email === '') {
        flash('error', 'No student selected.');
        redirect('admin/dashboard.php?page=students');
    }

    $emailSql = db_escape($email);
    db_query("DELETE FROM rank WHERE email = '$emailSql'");
    db_query("DELETE FROM history WHERE email = '$emailSql'");
    db_query("DELETE FROM enrollment WHERE email = '$emailSql'");
    db_query("DELETE FROM user WHERE email = '$emailSql'");

    flash('success', 'Student removed.');
    redirect('admin/dashboard.php?page=students');
}

/**
 * Convert a datetime-local value to MySQL's datetime format, or null when the
 * field was left empty.
 */
function normalize_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $ts = strtotime($value);
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
}
