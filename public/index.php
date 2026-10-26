<?php
require __DIR__ . '/../app/bootstrap.php';
$u = require_login();

$today = date('Y-m-d');
$all = db_all('SELECT announcement, type, start_date, end_date FROM announcements WHERE start_date IS NOT NULL ORDER BY start_date');
$events = []; $upcoming = []; $earlier = [];
foreach ($all as $a) {
    $end = $a['end_date'] ?: $a['start_date'];
    $events[] = ['s' => $a['start_date'], 'e' => $end, 't' => $a['announcement'], 'k' => $a['type']];
    if ($end >= $today) $upcoming[] = $a + ['end' => $end]; else $earlier[] = $a + ['end' => $end];
}
$earlier = array_slice(array_reverse($earlier), 0, 5);
$fmt = function (array $a): string {
    $s = strtotime($a['start_date']); $e = strtotime($a['end'] ?? ($a['end_date'] ?: $a['start_date']));
    return $s === $e ? date('M j, Y', $s) : date('M j', $s) . ' – ' . date('M j, Y', $e);
};
$isStudent = $u['role'] === 'student';
$missing = $isStudent ? missing_subjects($u) : [];
$loads = $isStudent ? [] : staff_assignments($u['id']);

page_start('Home', ['active' => 'home', 'scripts' => ['calendar.js']]);
?>
<main id="main" class="grid2">
  <section class="stack">
    <div class="card profile">
      <p class="eyebrow"><?= $isStudent ? 'Student' : e(ucfirst($u['role'])) ?></p>
      <h1><?= e($u['name']) ?></h1>
<?php if ($isStudent): ?>
      <dl class="facts">
        <div><dt>LRN</dt><dd><?= e($u['id']) ?></dd></div>
        <div><dt>Grade &amp; section</dt><dd>Grade <?= e((string)$u['GradeLevel']) ?> · <?= e($u['SectionName']) ?></dd></div>
        <div><dt>Strand</dt><dd><?= e($u['StrandName']) ?></dd></div>
        <div><dt>School year</dt><dd><?= e($u['SchoolYear']) ?></dd></div>
      </dl>
<?php else: ?>
      <dl class="facts"><div><dt>Teacher ID</dt><dd><?= e($u['id']) ?></dd></div><div><dt>Email</dt><dd><?= e($u['Email'] ?? '') ?></dd></div></dl>
<?php endif; ?>
      <p><a class="btn" href="<?= e(url('dashboard.php')) ?>"><?= $isStudent ? 'View my grades' : 'Open class records' ?></a>
         <a class="btn ghost" href="<?= e(url('change-password.php')) ?>">Change password</a></p>
    </div>

<?php if ($isStudent && $missing): ?>
    <div class="card"><h2>Heads up</h2><p class="muted">No scores recorded yet this semester in:</p>
      <ul class="tags"><?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
<?php elseif ($isStudent): ?>
    <div class="card"><h2>Heads up</h2><p>You have scores recorded in every subject this semester.</p></div>
<?php endif; ?>

<?php if (!$isStudent): ?>
    <div class="card"><h2>My teaching load</h2>
<?php if (!$loads): ?><p class="muted">No subjects are assigned to you yet.</p><?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>Subject</th><th>Section</th><th>Grade</th></tr></thead><tbody>
<?php foreach ($loads as $l): ?><tr><td><?= e($l['SubjectName']) ?></td><td><?= e($l['SectionName']) ?></td><td><?= e((string)$l['Grade']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
<?php endif; ?></div>
<?php endif; ?>

    <div class="card"><h2>Announcements</h2>
<?php if (!$upcoming): ?><p class="muted">No upcoming announcements or holidays.</p><?php else: ?>
      <ul class="events"><?php foreach ($upcoming as $a): ?>
        <li class="<?= e($a['type']) ?>"><span class="when"><?= e($fmt($a)) ?></span><span><?= e($a['announcement']) ?><?= $a['type'] === 'holiday' ? ' <em>(holiday)</em>' : '' ?></span></li>
      <?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($earlier): ?><details><summary>Earlier</summary><ul class="events"><?php foreach ($earlier as $a): ?>
        <li class="past"><span class="when"><?= e($fmt($a)) ?></span><span><?= e($a['announcement']) ?></span></li><?php endforeach; ?></ul></details><?php endif; ?>
    </div>
  </section>

  <section class="card calendar-card" aria-label="Calendar">
    <div id="calendar" data-events="<?= e(json_encode($events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"></div>
    <noscript><p class="muted">Turn on JavaScript to see the calendar. Dates are listed under Announcements.</p></noscript>
  </section>
</main>
<?php page_end();
