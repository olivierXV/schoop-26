<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user()) redirect(url(current_user()['must_change'] ? 'change-password.php' : 'index.php'));

$error = '';
$ident = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $ident = clean($_POST['userinfo'] ?? '', 100);
    [$ok, $error] = attempt_login($ident, (string)($_POST['password'] ?? ''));
    if ($ok) redirect(url(current_user()['must_change'] ? 'change-password.php' : 'index.php'));
    if (str_starts_with($error, 'Too many')) http_response_code(429);
}
page_start('Sign in', ['public' => true]);
?>
<main id="main" class="login">
  <section class="login-hero" aria-hidden="true"><div><h2>Welcome back</h2><p>Check your grades, announcements and school calendar in one place.</p></div></section>
  <section class="login-card card">
    <h1>Sign in</h1>
    <p class="muted">Students use their 12-digit LRN. Teachers and admins use their school email.</p>
<?php if ($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <label for="userinfo">LRN or email</label>
      <input id="userinfo" name="userinfo" type="text" inputmode="email" autocomplete="username" maxlength="100" value="<?= e($ident) ?>" required autofocus>
      <label for="password">Password</label>
      <div class="pw"><input id="password" name="password" type="password" autocomplete="current-password" maxlength="1000" required>
        <button type="button" class="btn ghost small" data-toggle-pw="password" aria-label="Show password">Show</button></div>
      <button class="btn primary block" type="submit">Sign in</button>
    </form>
    <p class="muted small">First time here? Your school admin will give you a temporary password. You will be asked to choose your own right after you sign in. Forgot your password? Ask the admin to reset it.</p>
  </section>
</main>
<?php page_end();
