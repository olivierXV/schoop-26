<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('admin');
$errors = [];
$sections = db_all('SELECT SectionID, SectionName, StrandName FROM sections ORDER BY SectionName');
$secById = array_column($sections, null, 'SectionID');
$years = school_years();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $lrn = trim($_POST['lrn'] ?? ''); $fn = clean($_POST['first'] ?? ''); $mn = clean($_POST['middle'] ?? ''); $ln = clean($_POST['last'] ?? '');
        $grade = $_POST['grade'] ?? ''; $secId = (int)($_POST['section'] ?? 0); $sy = $_POST['sy'] ?? '';
        if (!preg_match('/^\d{12}$/', $lrn)) $errors[] = 'LRN must be exactly 12 digits.';
        if (!valid_name($fn) || !valid_name($ln) || ($mn !== '' && !valid_name($mn))) $errors[] = 'Enter valid first and last names (letters, spaces, . \' - only).';
        if (!in_array($grade, ['11', '12'], true)) $errors[] = 'Choose a grade level.';
        if (!isset($secById[$secId])) $errors[] = 'Choose a section.';
        if (!in_array($sy, $years, true)) $errors[] = 'Choose a school year.';
        if (!$errors && db_one('SELECT 1 FROM information WHERE LRN=?', 's', [$lrn])) $errors[] = 'A student with that LRN already exists.';
        if (!$errors) {
            [$strand, $spec] = array_pad(explode('-', $secById[$secId]['StrandName'], 2), 2, null);   // e.g. "ICT-Programming" => ICT / Programming
            db_exec('INSERT INTO information (LRN, FirstName, MiddleName, LastName, Strand, Specialization, GradeLevel, Section, Password, SectionID, SchoolYear) VALUES (?,?,?,?,?,?,?,?,NULL,?,?)',
                'ssssssssis', [$lrn, $fn, $mn === '' ? null : $mn, $ln, $strand, $spec, $grade, $secById[$secId]['SectionName'], $secId, $sy]);
            $t = issue_temp_password('information', 'LRN', $lrn);
            audit('student_created', $lrn);
            flash('creds', ['name' => "$fn $ln", 'label' => 'LRN', 'id' => $lrn] + $t);
            redirect(url('admin/students.php'));
        }
    } elseif ($action === 'reset') {
        $lrn = (string)($_POST['lrn'] ?? '');
        $s = preg_match('/^\d{12}$/', $lrn) ? db_one('SELECT FirstName, LastName FROM information WHERE LRN=?', 's', [$lrn]) : null;
        if ($s) {
            $t = issue_temp_password('information', 'LRN', $lrn);
            audit('password_reset_issued', $lrn);
            flash('creds', ['name' => $s['FirstName'] . ' ' . $s['LastName'], 'label' => 'LRN', 'id' => $lrn] + $t);
        } else flash('notice', 'Student not found.');
        redirect(url('admin/students.php'));
    }
}
$q = clean($_GET['q'] ?? '', 60);
$like = '%' . addcslashes($q, '%_\\') . '%';
$list = db_all("SELECT i.LRN, i.FirstName, i.LastName, i.GradeLevel, i.Section, i.SchoolYear, (i.Password IS NOT NULL) has_pw, i.must_change_password mc, i.last_login_at
                FROM information i WHERE (? = '' OR i.LRN LIKE ? OR i.LastName LIKE ? OR i.FirstName LIKE ?) ORDER BY i.LastName, i.FirstName LIMIT 100", 'ssss', [$q, $like, $like, $like]);
page_start('Students', ['active' => 'students']);
?>
<main id="main" class="wide stack">
<?php credentials_card(); ?>
  <div class="card">
    <h1>Add a student</h1>
<?php foreach ($errors as $er): ?><p class="alert error" role="alert"><?= e($er) ?></p><?php endforeach; ?>
    <form method="post" class="formgrid">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>LRN (12 digits)<input name="lrn" inputmode="numeric" pattern="\d{12}" maxlength="12" required value="<?= e($_POST['lrn'] ?? '') ?>"></label>
      <label>First name<input name="first" maxlength="100" required value="<?= e($_POST['first'] ?? '') ?>"></label>
      <label>Middle name <span class="muted">(optional)</span><input name="middle" maxlength="100" value="<?= e($_POST['middle'] ?? '') ?>"></label>
      <label>Last name<input name="last" maxlength="100" required value="<?= e($_POST['last'] ?? '') ?>"></label>
      <label>Grade level<select name="grade" required><option value="">Select…</option><option value="11">Grade 11</option><option value="12">Grade 12</option></select></label>
      <label>Section<select name="section" required><option value="">Select…</option><?php foreach ($sections as $s): ?><option value="<?= (int)$s['SectionID'] ?>"><?= e($s['SectionName']) ?> — <?= e($s['StrandName']) ?></option><?php endforeach; ?></select></label>
      <label>School year<select name="sy" required><?php foreach ($years as $y): ?><option><?= e($y) ?></option><?php endforeach; ?></select></label>
      <div class="full"><button class="btn primary" type="submit">Add student &amp; generate temporary password</button>
        <span class="muted small">Strand and specialization are set automatically from the section.</span></div>
    </form>
  </div>
  <div class="card">
    <div class="head-row"><h2>Students</h2>
      <form method="get" class="inline"><label class="sr-only" for="q">Search</label><input id="q" name="q" type="search" placeholder="Search name or LRN" value="<?= e($q) ?>"><button class="btn" type="submit">Search</button></form></div>
    <div class="table-wrap"><table><thead><tr><th>LRN</th><th>Name</th><th>Grade / Section</th><th>Account</th><th>Last sign-in</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($list as $s): ?>
      <tr><td><?= e($s['LRN']) ?></td><td><?= e($s['LastName'] . ', ' . $s['FirstName']) ?></td><td><?= e($s['GradeLevel'] . ' · ' . $s['Section']) ?></td>
        <td><?= !$s['has_pw'] ? '<span class="pill warn">No password</span>' : ($s['mc'] ? '<span class="pill info">Temp password pending</span>' : '<span class="pill ok">Active</span>') ?></td>
        <td><?= $s['last_login_at'] ? e(date('M j, Y', strtotime($s['last_login_at']))) : '—' ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="lrn" value="<?= e($s['LRN']) ?>"><button class="btn small" type="submit">Reset password</button></form></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="muted">No students found.</td></tr><?php endif; ?>
    </tbody></table></div>
    <p class="muted small">Showing up to 100 matches.</p>
  </div>
</main>
<?php page_end();
