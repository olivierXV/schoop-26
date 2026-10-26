<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('admin');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $tid = trim($_POST['tid'] ?? ''); $fn = clean($_POST['first'] ?? '', 50); $mn = clean($_POST['middle'] ?? '', 50); $ln = clean($_POST['last'] ?? '', 50);
        $email = mb_strtolower(trim($_POST['email'] ?? '')); $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'teacher';
        if (!preg_match('/^\d{7}$/', $tid)) $errors[] = 'Teacher ID must be exactly 7 digits.';
        if (!valid_name($fn) || !valid_name($ln) || ($mn !== '' && !valid_name($mn))) $errors[] = 'Enter valid first and last names.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $errors[] = 'Enter a valid email address.';
        if (!$errors && db_one('SELECT 1 FROM teachers WHERE TeacherID=? OR Email=?', 'ss', [$tid, $email])) $errors[] = 'That Teacher ID or email is already in use.';
        if (!$errors) {
            db_exec('INSERT INTO teachers (TeacherID, FirstName, MiddleName, LastName, Email, Password, Role) VALUES (?,?,?,?,?,NULL,?)', 'ssssss', [$tid, $fn, $mn === '' ? null : $mn, $ln, $email, $role]);
            $t = issue_temp_password('teachers', 'TeacherID', $tid);
            audit($role === 'admin' ? 'admin_created' : 'teacher_created', $tid);
            flash('creds', ['name' => "$fn $ln", 'label' => 'email ' . $email . ', ID', 'id' => $tid] + $t);
            redirect(url('admin/teachers.php'));
        }
    } elseif ($action === 'reset') {
        $tid = (string)($_POST['tid'] ?? '');
        $t = preg_match('/^\d{7}$/', $tid) ? db_one('SELECT FirstName, LastName, Email FROM teachers WHERE TeacherID=?', 's', [$tid]) : null;
        if (!$t) flash('notice', 'Teacher not found.');
        elseif ($tid === $u['id']) flash('notice', 'To change your own password, use “Change password” on the Home page.');
        elseif (!$t['Email']) flash('notice', 'That account has no email address, so it cannot sign in. Add an email in the database first.');
        else {
            $c = issue_temp_password('teachers', 'TeacherID', $tid);
            audit('password_reset_issued', $tid);
            flash('creds', ['name' => $t['FirstName'] . ' ' . $t['LastName'], 'label' => 'email ' . $t['Email'] . ', ID', 'id' => $tid] + $c);
        }
        redirect(url('admin/teachers.php'));
    }
}
$list = db_all('SELECT TeacherID, FirstName, LastName, Email, Role, (Password IS NOT NULL) has_pw, must_change_password mc, last_login_at FROM teachers ORDER BY Role, LastName, FirstName');
page_start('Teachers', ['active' => 'teachers']);
?>
<main id="main" class="wide stack">
<?php credentials_card(); ?>
  <div class="card">
    <h1>Add a teacher or admin</h1>
<?php foreach ($errors as $er): ?><p class="alert error" role="alert"><?= e($er) ?></p><?php endforeach; ?>
    <form method="post" class="formgrid">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>Teacher ID (7 digits)<input name="tid" inputmode="numeric" pattern="\d{7}" maxlength="7" required value="<?= e($_POST['tid'] ?? '') ?>"></label>
      <label>Email<input name="email" type="email" maxlength="100" required value="<?= e($_POST['email'] ?? '') ?>"></label>
      <label>First name<input name="first" maxlength="50" required value="<?= e($_POST['first'] ?? '') ?>"></label>
      <label>Middle name <span class="muted">(optional)</span><input name="middle" maxlength="50" value="<?= e($_POST['middle'] ?? '') ?>"></label>
      <label>Last name<input name="last" maxlength="50" required value="<?= e($_POST['last'] ?? '') ?>"></label>
      <label>Role<select name="role"><option value="teacher">Teacher</option><option value="admin">Admin (can manage accounts)</option></select></label>
      <div class="full"><button class="btn primary" type="submit">Add &amp; generate temporary password</button></div>
    </form>
  </div>
  <div class="card"><h2>Staff accounts</h2>
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Account</th><th>Last sign-in</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($list as $t): ?>
      <tr><td><?= e($t['TeacherID']) ?></td><td><?= e(trim($t['LastName'] . ', ' . $t['FirstName'], ', ')) ?></td><td><?= e($t['Email'] ?? '—') ?></td><td><?= e(ucfirst($t['Role'])) ?></td>
        <td><?= !$t['Email'] ? '<span class="pill warn">No email</span>' : (!$t['has_pw'] ? '<span class="pill warn">No password</span>' : ($t['mc'] ? '<span class="pill info">Temp password pending</span>' : '<span class="pill ok">Active</span>')) ?></td>
        <td><?= $t['last_login_at'] ? e(date('M j, Y', strtotime($t['last_login_at']))) : '—' ?></td>
        <td><?php if ($t['TeacherID'] !== $u['id'] && $t['Email']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="tid" value="<?= e($t['TeacherID']) ?>"><button class="btn small" type="submit">Reset password</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
    </tbody></table></div>
  </div>
</main>
<?php page_end();
