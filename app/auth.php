<?php
declare(strict_types=1);

const DUMMY_HASH = '$2y$10$bKs2FWSKuMbIc9O5f8kdPOAAmQISq8x7ikjncWMbjHs6SEma4R01K';   // verified against when the account doesn't exist, so timing doesn't reveal valid IDs

function start_session(): void {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('SCHOOPSESS');
    session_cache_limiter('');   // keep our own Cache-Control: no-store header
    session_set_cookie_params(['lifetime' => 0, 'path' => base_url() ?: '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    $sec = config('security'); $now = time();
    if (isset($_SESSION['uid'])) {
        $idle = $now - ($_SESSION['last'] ?? $now); $age = $now - ($_SESSION['started'] ?? $now);
        if ($idle > $sec['session_idle_minutes'] * 60 || $age > $sec['session_max_hours'] * 3600) { destroy_session(); session_start(); }
    }
    $_SESSION['last'] = $now;
}
function destroy_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']); }
    session_destroy();
}

/** Look up the account behind a login identifier: 12-digit LRN => student, e-mail => staff. */
function find_account(string $ident): ?array {
    if (preg_match('/^\d{12}$/', $ident)) {
        $r = db_one('SELECT LRN AS id, FirstName, MiddleName, LastName, Password, must_change_password, temp_password_expires_at FROM information WHERE LRN=?', 's', [$ident]);
        return $r ? $r + ['kind' => 'student'] : null;
    }
    if (filter_var($ident, FILTER_VALIDATE_EMAIL)) {
        $r = db_one('SELECT TeacherID AS id, FirstName, MiddleName, LastName, Password, Role, must_change_password, temp_password_expires_at FROM teachers WHERE Email=?', 's', [$ident]);
        return $r ? $r + ['kind' => 'staff'] : null;
    }
    return null;
}

function login_throttled(string $ident, string $ip): bool {
    $s = config('security'); $win = (int)$s['lockout_minutes'];
    $a = db_one('SELECT COUNT(*) c FROM login_attempts WHERE success=0 AND identifier=? AND ip=? AND attempted_at > NOW() - INTERVAL ? MINUTE', 'ssi', [$ident, $ip, $win]);
    $b = db_one('SELECT COUNT(*) c FROM login_attempts WHERE success=0 AND ip=? AND attempted_at > NOW() - INTERVAL ? MINUTE', 'si', [$ip, $win]);
    $c = db_one('SELECT COUNT(*) c FROM login_attempts WHERE success=0 AND identifier=? AND attempted_at > NOW() - INTERVAL ? MINUTE', 'si', [$ident, $win]);
    return $a['c'] >= $s['max_failures_per_account_ip'] || $b['c'] >= 30 || $c['c'] >= 25;
}
function record_attempt(string $ident, string $ip, bool $ok): void {
    db_exec('INSERT INTO login_attempts (identifier, ip, success) VALUES (?,?,?)', 'ssi', [mb_substr($ident, 0, 100), $ip, (int)$ok]);
    if (random_int(1, 100) === 1) db_exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 2 DAY');
}

/** @return array{0:bool,1:string} [success, message]. Messages are deliberately generic. */
function attempt_login(string $ident, string $password): array {
    $ident = mb_strtolower(trim($ident)); $ip = client_ip();
    $generic = 'Incorrect LRN/email or password.';
    if ($ident === '' || strlen($ident) > 100 || strlen($password) > 1000) return [false, $generic];
    if (login_throttled($ident, $ip)) return [false, 'Too many failed attempts. Please wait ' . config('security')['lockout_minutes'] . ' minutes and try again.'];

    $acct = find_account($ident);
    $hash = ($acct && !empty($acct['Password'])) ? $acct['Password'] : DUMMY_HASH;
    $ok = password_verify($password, $hash) && $acct && !empty($acct['Password']);
    if (!$ok) { record_attempt($ident, $ip, false); return [false, $generic]; }

    if ((int)$acct['must_change_password'] === 1 && $acct['temp_password_expires_at'] !== null && strtotime($acct['temp_password_expires_at']) < time()) {
        record_attempt($ident, $ip, false);
        return [false, 'Your temporary password has expired. Please ask the school admin to issue a new one.'];
    }
    record_attempt($ident, $ip, true);
    if (password_needs_rehash($acct['Password'], PASSWORD_DEFAULT)) {
        $tbl = $acct['kind'] === 'student' ? ['information', 'LRN'] : ['teachers', 'TeacherID'];
        db_exec("UPDATE {$tbl[0]} SET Password=? WHERE {$tbl[1]}=?", 'ss', [password_hash($password, PASSWORD_DEFAULT), $acct['id']]);
    }
    session_regenerate_id(true);                      // prevents session fixation
    $_SESSION['uid'] = $acct['id']; $_SESSION['kind'] = $acct['kind'];
    $_SESSION['started'] = time(); $_SESSION['last'] = time();
    unset($GLOBALS['__user']);
    $tbl = $acct['kind'] === 'student' ? ['information', 'LRN'] : ['teachers', 'TeacherID'];
    db_exec("UPDATE {$tbl[0]} SET last_login_at=NOW() WHERE {$tbl[1]}=?", 's', [$acct['id']]);
    audit('login', $acct['id'], null, ['id' => $acct['id'], 'role' => $acct['kind'] === 'student' ? 'student' : $acct['Role']]);
    return [true, ''];
}

/** The logged-in account, re-read from the DB on every request (so role changes / password resets take effect immediately). */
function current_user(): ?array {
    if (array_key_exists('__user', $GLOBALS)) return $GLOBALS['__user'];
    $GLOBALS['__user'] = null;
    if (empty($_SESSION['uid'])) return null;
    if ($_SESSION['kind'] === 'student') {
        $r = db_one('SELECT i.LRN AS id, i.FirstName, i.MiddleName, i.LastName, i.Strand, i.Specialization, i.GradeLevel, i.SectionID, i.SchoolYear, i.Password, i.must_change_password, sec.SectionName, sec.StrandName
                     FROM information i JOIN sections sec ON sec.SectionID = i.SectionID WHERE i.LRN=?', 's', [$_SESSION['uid']]);
        if ($r) $r['role'] = 'student';
    } else {
        $r = db_one('SELECT TeacherID AS id, FirstName, MiddleName, LastName, Email, Role AS role, Password, must_change_password FROM teachers WHERE TeacherID=?', 's', [$_SESSION['uid']]);
    }
    // Account removed, or password changed/reset elsewhere => this session is no longer valid.
    $fp = $r ? substr(hash('sha256', (string)$r['Password']), 0, 20) : null;
    if (!$r || empty($r['Password']) || (isset($_SESSION['pwfp']) && !hash_equals($_SESSION['pwfp'], $fp))) { destroy_session(); session_start(); return null; }
    $_SESSION['pwfp'] = $fp;
    $r['must_change'] = (int)$r['must_change_password'] === 1;
    $r['name'] = trim($r['FirstName'] . ' ' . ($r['MiddleName'] ?? '') . ' ' . $r['LastName']);
    unset($r['Password']);
    return $GLOBALS['__user'] = $r;
}
function is_staff(?array $u): bool { return $u && in_array($u['role'], ['teacher', 'admin'], true); }

/** Gate for HTML pages. */
function require_login(string $role = 'any', bool $allowPending = false): array {
    $u = current_user();
    if (!$u) redirect(url('login.php'));
    if ($u['must_change'] && !$allowPending) redirect(url('change-password.php'));
    if ($role === 'staff' && !is_staff($u)) forbid();
    if ($role === 'admin' && $u['role'] !== 'admin') forbid();
    if ($role === 'student' && $u['role'] !== 'student') forbid();
    return $u;
}
/** Gate for JSON endpoints. */
function require_login_api(string $role = 'any'): array {
    $u = current_user();
    if (!$u) json_out(['ok' => false, 'error' => 'Not signed in.'], 401);
    if ($u['must_change']) json_out(['ok' => false, 'error' => 'Password change required.'], 403);
    if (($role === 'staff' && !is_staff($u)) || ($role === 'admin' && $u['role'] !== 'admin') || ($role === 'student' && $u['role'] !== 'student')) json_out(['ok' => false, 'error' => 'Forbidden.'], 403);
    return $u;
}
function forbid(): never {
    http_response_code(403);
    page_start('Access denied', ['public' => false]);
    echo '<main id="main" class="narrow"><div class="card"><h1>Access denied</h1><p>Your account does not have permission to open this page.</p><p><a class="btn" href="' . e(url('index.php')) . '">Back to Home</a></p></div></main>';
    page_end(); exit;
}
