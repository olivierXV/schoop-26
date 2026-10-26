<?php
define('IS_API', true);
require __DIR__ . '/../../app/bootstrap.php';
require_post_csrf();
$u = require_login_api('staff');

$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in) || !is_array($in['changes'] ?? null)) json_out(['ok' => false, 'error' => 'Invalid request.'], 400);
$sy = (string)($in['sy'] ?? ''); $quarter = (string)($in['quarter'] ?? '');
if (!in_array($sy, school_years(), true) || !in_array($quarter, ['Q1','Q2','Q3','Q4'], true)) json_out(['ok' => false, 'error' => 'Invalid school year or quarter.'], 400);
if (count($in['changes']) === 0 || count($in['changes']) > 1000) json_out(['ok' => false, 'error' => 'Nothing to save, or too many changes at once.'], 400);

// 1. Validate everything and group by (student, subject, grade level) before touching the database.
$groups = [];
foreach ($in['changes'] as $c) {
    if (!is_array($c)) json_out(['ok' => false, 'error' => 'Invalid change.'], 400);
    $lrn = (string)($c['lrn'] ?? ''); $subject = (int)($c['subjectID'] ?? 0); $grade = (string)($c['grade'] ?? ''); $field = (string)($c['field'] ?? '');
    $val = $c['value'] ?? null;
    if (!preg_match('/^\d{12}$/', $lrn) || $subject < 1 || !in_array($grade, ['11','12'], true) || !in_array($field, SCORE_FIELDS, true))
        json_out(['ok' => false, 'error' => 'Invalid change.'], 400);
    if ($val === '' ) $val = null;
    if ($val !== null) {
        if (is_string($val) && preg_match('/^\d{1,3}$/', $val)) $val = (int)$val;
        if (!is_int($val) || $val < 0 || $val > 100) json_out(['ok' => false, 'error' => 'Scores must be whole numbers from 0 to 100.'], 422);
    }
    $groups["$lrn|$subject|$grade"]['changes'][$field] = $val;
    $groups["$lrn|$subject|$grade"] += ['lrn' => $lrn, 'subject' => $subject, 'grade' => $grade];
}
// 2. Authorise: the staff member must teach this subject to this student's current section.
foreach ($groups as $g) {
    if (!staff_may_grade($u['id'], $g['subject'], $g['grade'], $g['lrn'])) {
        audit('grade_edit_denied', $g['lrn'], ['subject' => $g['subject']]);
        json_out(['ok' => false, 'error' => 'You are not assigned to grade one or more of these students.'], 403);
    }
}
// 2b. A subject only runs in one semester; the database rejects other quarters, so give a clear message instead.
$semOfQuarter = in_array($quarter, ['Q1', 'Q2'], true) ? '1' : '2';
foreach ($groups as $g) {
    $sub = db_one('SELECT Semester FROM subject WHERE SubjectID=?', 'i', [$g['subject']]);
    if (!$sub) json_out(['ok' => false, 'error' => 'Unknown subject.'], 400);
    if ($sub['Semester'] !== null && $sub['Semester'] !== $semOfQuarter) json_out(['ok' => false, 'error' => "That subject is not taught in $quarter."], 422);
}
// 3. Write inside one transaction. Averages and the quarter grade are calculated by the database triggers (single source of
//    truth, including the school's minimum-grade rule); we read them back below. Nothing computed is ever accepted from the browser.
$out = [];
db()->begin_transaction();
try {
    foreach ($groups as $g) {
        $row = db_one('SELECT ' . implode(',', SCORE_FIELDS) . ' FROM grades WHERE LRN=? AND SubjectID=? AND Grade=? AND quarter=? AND SchoolYear=? FOR UPDATE',
                      'sisss', [$g['lrn'], $g['subject'], $g['grade'], $quarter, $sy]);
        $old = $row ?? array_fill_keys(SCORE_FIELDS, null);
        $new = array_merge($old, $g['changes']);
        $sql = 'INSERT INTO grades (LRN, TeacherID, SubjectID, Grade, quarter, SchoolYear, ' . implode(',', SCORE_FIELDS) . ') VALUES (?,?,?,?,?,?,' . implode(',', array_fill(0, count(SCORE_FIELDS), '?')) . ')
                ON DUPLICATE KEY UPDATE TeacherID=VALUES(TeacherID), ' . implode(',', array_map(fn($c) => "$c=VALUES($c)", SCORE_FIELDS));
        db_exec($sql, 'ssisss' . str_repeat('i', count(SCORE_FIELDS)), array_merge([$g['lrn'], $u['id'], $g['subject'], $g['grade'], $quarter, $sy], array_map(fn($f) => $new[$f], SCORE_FIELDS)));
        $calc = db_one('SELECT ww_qz_average, pt_average, quarter_grade FROM grades WHERE LRN=? AND SubjectID=? AND Grade=? AND quarter=? AND SchoolYear=?',
                       'sisss', [$g['lrn'], $g['subject'], $g['grade'], $quarter, $sy]);
        $diff = [];
        foreach ($g['changes'] as $f => $v) if (($old[$f] ?? null) !== $v) $diff[$f] = [$old[$f] ?? null, $v];
        if ($diff) audit('grade_edit', $g['lrn'], ['subject' => $g['subject'], 'quarter' => $quarter, 'sy' => $sy, 'changes' => $diff]);
        $out[] = ['lrn' => $g['lrn'], 'subjectID' => $g['subject'],
                  'ww_qz_average' => $calc['ww_qz_average'] === null ? null : (float)$calc['ww_qz_average'],
                  'pt_average'    => $calc['pt_average'] === null ? null : (float)$calc['pt_average'],
                  'quarter_grade' => $calc['quarter_grade'] === null ? null : (int)$calc['quarter_grade']];
    }
    db()->commit();
} catch (Throwable $e) { db()->rollback(); throw $e; }
json_out(['ok' => true, 'saved' => count($out), 'rows' => $out]);
