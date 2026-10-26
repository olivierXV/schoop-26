<?php
declare(strict_types=1);

function theme_class(?array $u): string {
    if (!$u) return 'theme-brand';
    if ($u['role'] === 'student') return 'theme-' . strtolower(preg_replace('/[^A-Za-z]/', '', (string)$u['Strand']));
    return 'theme-staff';
}

/** Opens the HTML document and the site header. $opts: active, public, letterhead */
function page_start(string $title, array $opts = []): void {
    $u = empty($opts['public']) ? current_user() : null;
    $active = $opts['active'] ?? '';
    $link = fn(string $key, string $href, string $label) =>
        '<a href="' . e(url($href)) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title><?= e($title) ?> · SCHOOP</title>
<link rel="icon" href="<?= e(url('assets/img/school.png')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
<?php foreach (($opts['scripts'] ?? []) as $s): ?><script src="<?= e(url('assets/js/' . $s)) ?>" defer></script>
<?php endforeach; ?>
</head>
<body class="<?= e(theme_class($u)) ?>">
<a class="skip" href="#main">Skip to content</a>
<?php if (!empty($opts['letterhead'])): ?><img class="print-only letterhead" src="<?= e(url('assets/img/pdf/header.png')) ?>" alt=""><?php endif; ?>
<header class="topbar">
  <a class="brand" href="<?= e(url($u ? 'index.php' : 'login.php')) ?>">
    <img src="<?= e(url('assets/img/school.png')) ?>" alt="" width="40" height="40">
    <span><strong>SCHOOP</strong><small>SHSSN3 School Portal</small></span>
  </a>
<?php if ($u): ?>
  <nav aria-label="Main">
    <?= $link('home', 'index.php', 'Home') ?>
    <?= $link('dashboard', 'dashboard.php', 'Grades') ?>
<?php if ($u['role'] === 'admin'): ?>
    <details class="menu"><summary>Manage</summary>
      <div><?= $link('students', 'admin/students.php', 'Students') ?><?= $link('teachers', 'admin/teachers.php', 'Teachers') ?><?= $link('announcements', 'admin/announcements.php', 'Announcements') ?></div>
    </details>
<?php endif; ?>
  </nav>
  <div class="who">
    <span class="chip" title="<?= e($u['name']) ?>"><?= e($u['FirstName']) ?> · <?= e(ucfirst($u['role'])) ?></span>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn ghost" type="submit">Log out</button></form>
  </div>
<?php else: ?>
  <nav aria-label="Main"><?= $link('about', 'about.php', 'About') ?></nav>
<?php endif; ?>
</header>
<?php
    if ($msg = flash_pull('notice')) echo '<div class="notice" role="status">' . e((string)$msg) . '</div>';
}

function page_end(array $opts = []): void {
    ?>
<?php if (!empty($opts['letterhead'])): ?><img class="print-only letterfoot" src="<?= e(url('assets/img/pdf/footer.png')) ?>" alt=""><?php endif; ?>
<footer class="foot">SCHOOP · Senior High School, San Nicolas III, Bacoor City · Your records are private. Never share your password.</footer>
</body>
</html>
<?php
}
