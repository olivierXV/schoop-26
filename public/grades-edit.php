<?php
require __DIR__ . '/../app/bootstrap.php';
$u = require_login('staff');
$f = staff_filters($u);
$rows = $f['sec'] ? staff_class_rows($u['id'], $f['sec'], $f['q'], $f['sy']) : [];
page_start('Edit grades', ['active' => 'dashboard', 'scripts' => ['grades-edit.js']]);
?>
<main id="main" class="wide">
  <div class="card">
    <div class="head-row"><h1>Edit grades</h1><a class="btn ghost" href="<?= e(url('dashboard.php?' . http_build_query(['sy' => $f['sy'], 'section' => $f['sec'], 'quarter' => $f['q']]))) ?>">Back to class records</a></div>
<?php if (!$f['sections']): ?><p class="muted">No sections are assigned to you yet.</p>
<?php else: staff_filter_form($f, url('grades-edit.php')); ?>
    <p class="muted">Type scores (whole numbers, 0–100). Averages and the quarter grade are calculated automatically when you save. Leave a box empty if there is no score yet.</p>
    <div id="editor" data-endpoint="<?= e(url('api/grades-save.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-sy="<?= e($f['sy']) ?>" data-quarter="<?= e($f['q']) ?>">
<?php if (!$rows): ?><p class="muted">No students in this section.</p><?php else: render_class_table($rows, true); endif; ?>
      <div class="savebar" role="region" aria-label="Save changes">
        <span id="status" role="status" aria-live="polite">No changes yet.</span>
        <button id="save" class="btn primary" type="button" disabled>Save changes</button>
      </div>
    </div>
<?php endif; ?>
  </div>
</main>
<?php page_end();
