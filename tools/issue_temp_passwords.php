<?php
// CLI only. Issues temporary passwords (stored hashed; plaintext is printed ONCE here).
//   php tools/issue_temp_passwords.php --missing          every account that has no password yet
//   php tools/issue_temp_passwords.php --id 9999992       one specific LRN (12 digits) or Teacher ID (7 digits) — also the way to recover a locked-out admin
//   add --out=credentials.csv to write a file (mode 0600) instead of printing. Hand the passwords out privately, then delete the file.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);   // credential files are created owner-only
define('CLI_TOOL', true);
require __DIR__ . '/../app/bootstrap.php';

$opt = getopt('', ['missing', 'id:', 'out:']);
if (!isset($opt['missing']) && !isset($opt['id'])) { fwrite(STDERR, "Usage: --missing | --id <LRN|TeacherID> [--out=file.csv]\n"); exit(1); }

$targets = [];
if (isset($opt['id'])) {
    $id = (string)$opt['id'];
    if (preg_match('/^\d{12}$/', $id))   $r = db_one('SELECT LRN id, FirstName, LastName, NULL email FROM information WHERE LRN=?', 's', [$id]);
    elseif (preg_match('/^\d{7}$/', $id)) $r = db_one('SELECT TeacherID id, FirstName, LastName, Email email FROM teachers WHERE TeacherID=?', 's', [$id]);
    else $r = null;
    if (!$r) { fwrite(STDERR, "No such account.\n"); exit(1); }
    $targets[] = $r + ['kind' => strlen($id) === 12 ? 'student' : 'staff'];
} else {
    foreach (db_all("SELECT LRN id, FirstName, LastName, NULL email FROM information WHERE Password IS NULL OR Password=''") as $r) $targets[] = $r + ['kind' => 'student'];
    foreach (db_all("SELECT TeacherID id, FirstName, LastName, Email email FROM teachers WHERE Password IS NULL OR Password=''") as $r) $targets[] = $r + ['kind' => 'staff'];
}
$rows = []; $skipped = [];
foreach ($targets as $t) {
    if ($t['kind'] === 'staff' && !$t['email']) { $skipped[] = $t['id'] . ' ' . $t['FirstName'] . ' ' . $t['LastName']; continue; }   // staff sign in by email
    $x = $t['kind'] === 'student' ? issue_temp_password('information', 'LRN', $t['id']) : issue_temp_password('teachers', 'TeacherID', $t['id']);
    audit('password_reset_issued', $t['id'], ['via' => 'cli'], ['id' => 'cli', 'role' => 'system']);
    $rows[] = [$t['kind'], $t['id'], trim($t['FirstName'] . ' ' . $t['LastName']), $t['email'] ?? '', $x['password'], $x['expires']];
}
$out = fopen(isset($opt['out']) ? $opt['out'] : 'php://stdout', 'w');
if (isset($opt['out'])) chmod($opt['out'], 0600);
fputcsv($out, ['kind', 'sign_in_with', 'name', 'email', 'temporary_password', 'expires']);
foreach ($rows as $r) { $r[1] = $r[0] === 'student' ? $r[1] : $r[3]; fputcsv($out, $r); }
fwrite(STDERR, count($rows) . " temporary password(s) issued. They must be changed at first sign-in.\n");
if ($skipped) fwrite(STDERR, "Skipped (no email, cannot sign in until one is added):\n  " . implode("\n  ", $skipped) . "\n");
