<?php
require __DIR__ . '/../app/bootstrap.php';
$u = require_login();
$isStudent = $u['role'] === 'student';

if ($isStudent) {
    $years = school_years();
    $sy    = in_array($_GET['sy'] ?? '', $years, true) ? $_GET['sy'] : $u['SchoolYear'];
    $grade = in_array($_GET['grade'] ?? '', ['11', '12'], true) ? $_GET['grade'] : (string)$u['GradeLevel'];
    $sem   = in_array($_GET['sem'] ?? '', ['1', '2'], true) ? (int)$_GET['sem'] : current_semester();
    $rows  = student_semester_grades($u['id'], $grade, $sem, $sy);
    [$qa, $qb] = quarters_for($sem);
    $groups = ['Core' => [], 'Applied &amp; Immersion' => [], 'Specialized' => []];
    foreach ($rows as $r) $groups[$r['Type'] === 'Core' ? 'Core' : ($r['Type'] === 'Specialized' ? 'Specialized' : 'Applied &amp; Immersion')][] = $r;
} else {
    $f = staff_filters($u);
    $rows = $f['sec'] ? staff_class_rows($u['id'], $f['sec'], $f['q'], $f['sy']) : [];
    $secName = '';
    foreach ($f['sections'] as $s) if ((int)$s['SectionID'] === $f['sec']) $secName = $s['SectionName'];
}
page_start('Grades', ['active' => 'dashboard', 'letterhead' => true]);
?>
<main id="main" class="wide">
  <div class="card">
    <div class="head-row"><h1><?= $isStudent ? 'My grades' : 'Class records' ?></h1>
      <div class="actions no-print">
<?php if (!$isStudent && $f['sec']): ?><a class="btn primary" href="<?= e(url('grades-edit.php?' . http_build_query(['sy' => $f['sy'], 'section' => $f['sec'], 'quarter' => $f['q']]))) ?>">Edit grades</a><?php endif; ?>
        <button class="btn" type="button" data-print>Print / Save as PDF</button>
      </div></div>

<?php if ($isStudent): ?>
    <form class="filters no-print" method="get">
      <label>School year<select name="sy"><?php foreach ($years as $y): ?><option<?= $y === $sy ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></label>
      <label>Grade<select name="grade"><?php foreach (['11', '12'] as $g): ?><option value="<?= $g ?>"<?= $g === $grade ? ' selected' : '' ?>>Grade <?= $g ?></option><?php endforeach; ?></select></label>
      <label>Semester<select name="sem"><option value="1"<?= $sem === 1 ? ' selected' : '' ?>>1st (Q1–Q2)</option><option value="2"<?= $sem === 2 ? ' selected' : '' ?>>2nd (Q3–Q4)</option></select></label>
      <button class="btn" type="submit">Show</button>
    </form>
    <p class="print-meta"><strong><?= e($u['name']) ?></strong> · LRN <?= e($u['id']) ?> · Grade <?= e($grade) ?> <?= e($u['SectionName']) ?> · SY <?= e($sy) ?> · Semester <?= $sem ?></p>
<?php if (!$rows): ?><p class="muted">No subjects found for this selection.</p><?php else: ?>
    <div class="table-wrap"><table class="records"><thead><tr><th>Subject</th><th><?= $qa ?></th><th><?= $qb ?></th><th>Semester grade</th></tr></thead><tbody>
<?php foreach ($groups as $label => $list): if (!$list) continue; ?>
      <tr class="group"><th colspan="4" scope="colgroup"><?= $label ?></th></tr>
<?php foreach ($list as $r): ?>
      <tr><th scope="row"><?= e($r['SubjectName']) ?></th><td><?= grade_chip($r['qa']) ?></td><td><?= grade_chip($r['qb']) ?></td><td><?= grade_chip($r['final']) ?></td></tr>
<?php endforeach; endforeach; ?>
    </tbody></table></div>
    <p class="muted small">Colour bands follow the DepEd descriptors: 90+ Outstanding · 85–89 Very Satisfactory · 80–84 Satisfactory · 75–79 Fairly Satisfactory · below 75 Did Not Meet Expectations. The semester grade is the average of both quarters.</p>
<?php endif; ?>

<?php else: ?>
<?php if (!$f['sections']): ?><p class="muted">No sections are assigned to you yet. Ask the school admin to assign your subjects.</p><?php else: staff_filter_form($f, url('dashboard.php')); ?>
    <p class="print-meta"><strong><?= e($secName) ?></strong> · <?= e($f['q']) ?> · SY <?= e($f['sy']) ?></p>
<?php if (!$rows): ?><p class="muted">No students in this section.</p><?php else: render_class_table($rows, false); endif; endif; ?>
<?php endif; ?>
  </div>
</main>
<?php page_end(['letterhead' => true]);
