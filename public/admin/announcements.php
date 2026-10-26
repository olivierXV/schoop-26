<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('admin');
$errors = [];
$validDate = fn(string $d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec('DELETE FROM announcements WHERE id=?', 'i', [$id]);
        audit('announcement_deleted', (string)$id); flash('notice', 'Announcement deleted.'); redirect(url('admin/announcements.php'));
    }
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0); $text = trim((string)($_POST['text'] ?? '')); $type = ($_POST['type'] ?? '') === 'holiday' ? 'holiday' : 'announcement';
        $start = trim($_POST['start'] ?? ''); $end = trim($_POST['end'] ?? '');
        if ($text === '' || mb_strlen($text) > 500) $errors[] = 'Write the announcement (up to 500 characters).';
        if (!$validDate($start)) $errors[] = 'Choose a valid start date.';
        if ($end !== '' && (!$validDate($end) || $end < $start)) $errors[] = 'End date must be on or after the start date.';
        if (!$errors) {
            $endV = $end === '' ? null : $end;
            if ($id) { db_exec('UPDATE announcements SET announcement=?, type=?, start_date=?, end_date=? WHERE id=?', 'ssssi', [$text, $type, $start, $endV, $id]); audit('announcement_updated', (string)$id); }
            else     { db_exec('INSERT INTO announcements (announcement, type, start_date, end_date) VALUES (?,?,?,?)', 'ssss', [$text, $type, $start, $endV]); audit('announcement_created'); }
            flash('notice', 'Announcement saved.'); redirect(url('admin/announcements.php'));
        }
    }
}
$editing = isset($_GET['edit']) ? db_one('SELECT * FROM announcements WHERE id=?', 'i', [(int)$_GET['edit']]) : null;
$list = db_all('SELECT * FROM announcements ORDER BY COALESCE(start_date, "9999-12-31") DESC, id DESC');
page_start('Announcements', ['active' => 'announcements']);
?>
<main id="main" class="wide stack">
  <div class="card">
    <h1><?= $editing ? 'Edit announcement' : 'New announcement' ?></h1>
<?php foreach ($errors as $er): ?><p class="alert error" role="alert"><?= e($er) ?></p><?php endforeach; ?>
    <form method="post" class="formgrid">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
      <label class="full">Text<textarea name="text" rows="3" maxlength="500" required><?= e($_POST['text'] ?? $editing['announcement'] ?? '') ?></textarea></label>
      <label>Type<select name="type"><?php $t = $_POST['type'] ?? $editing['type'] ?? 'announcement'; ?><option value="announcement"<?= $t === 'announcement' ? ' selected' : '' ?>>Announcement</option><option value="holiday"<?= $t === 'holiday' ? ' selected' : '' ?>>Holiday</option></select></label>
      <label>Start date<input type="date" name="start" required value="<?= e($_POST['start'] ?? $editing['start_date'] ?? '') ?>"></label>
      <label>End date <span class="muted">(optional)</span><input type="date" name="end" value="<?= e($_POST['end'] ?? $editing['end_date'] ?? '') ?>"></label>
      <div class="full"><button class="btn primary" type="submit">Save</button><?php if ($editing): ?> <a class="btn ghost" href="<?= e(url('admin/announcements.php')) ?>">Cancel</a><?php endif; ?></div>
    </form>
  </div>
  <div class="card"><h2>All announcements</h2>
    <div class="table-wrap"><table><thead><tr><th>Dates</th><th>Text</th><th>Type</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($list as $a): ?>
      <tr><td><?= e($a['start_date'] ?? '—') ?><?= $a['end_date'] && $a['end_date'] !== $a['start_date'] ? ' → ' . e($a['end_date']) : '' ?></td><td><?= nl2br(e($a['announcement'])) ?></td><td><?= e($a['type']) ?></td>
        <td class="nowrap"><a class="btn small" href="<?= e(url('admin/announcements.php?edit=' . (int)$a['id'])) ?>">Edit</a>
          <form method="post" class="inline" data-confirm="Delete this announcement?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn small danger" type="submit">Delete</button></form></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="4" class="muted">No announcements yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</main>
<?php page_end();
