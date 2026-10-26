<?php
require __DIR__ . '/../app/bootstrap.php';
$u = require_login('any', true);          // reachable while a temporary password is pending
$forced = $u['must_change'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
    [$tbl, $key] = $u['role'] === 'student' ? ['information', 'LRN'] : ['teachers', 'TeacherID'];
    $row = db_one("SELECT Password FROM $tbl WHERE $key=?", 's', [$u['id']]);
    $forbidden = [$u['id'], $u['FirstName'], $u['LastName'], $u['Email'] ?? ''];
    if (!$row || !password_verify($cur, (string)$row['Password']))      $error = 'Your current (or temporary) password is incorrect.';
    elseif ($new !== $conf)                                              $error = 'The new passwords do not match.';
    elseif (hash_equals($cur, $new))                                     $error = 'Choose a password different from your current one.';
    elseif ($p = password_problem($new, $forbidden))                     $error = $p;
    else {
        db_exec("UPDATE $tbl SET Password=?, must_change_password=0, temp_password_expires_at=NULL, password_changed_at=NOW() WHERE $key=?", 'ss',
            [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        audit('password_changed', $u['id']);
        session_regenerate_id(true);
        $_SESSION['pwfp'] = substr(hash('sha256', (string)db_one("SELECT Password FROM $tbl WHERE $key=?", 's', [$u['id']])['Password']), 0, 20);
        unset($GLOBALS['__user']);
        flash('notice', 'Password updated. You are all set.');
        redirect(url('index.php'));
    }
}
$min = (int)config('security')['password_min_length'];
page_start('Change password', ['active' => 'password']);
?>
<main id="main" class="narrow">
  <div class="card">
    <h1><?= $forced ? 'Choose your own password' : 'Change password' ?></h1>
<?php if ($forced): ?><p class="alert info">You signed in with a temporary password. Pick a new one to continue — nothing else is available until you do.</p><?php endif; ?>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="current"><?= $forced ? 'Temporary password' : 'Current password' ?></label>
      <input id="current" name="current" type="password" autocomplete="current-password" required>
      <label for="new">New password</label>
      <input id="new" name="new" type="password" autocomplete="new-password" minlength="<?= $min ?>" required aria-describedby="rules">
      <p id="rules" class="muted small">At least <?= $min ?> characters. A few random words works well. Don't use your name, LRN or email.</p>
      <label for="confirm">Confirm new password</label>
      <input id="confirm" name="confirm" type="password" autocomplete="new-password" minlength="<?= $min ?>" required>
      <button class="btn primary" type="submit">Save password</button>
<?php if (!$forced): ?> <a class="btn ghost" href="<?= e(url('index.php')) ?>">Cancel</a><?php endif; ?>
    </form>
  </div>
</main>
<?php page_end();
