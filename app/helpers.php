<?php
declare(strict_types=1);

// ---------- output / routing ----------
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_url(): string {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return preg_replace('#/(admin|api)$#', '', $dir);
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}
function is_https(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }
function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45); }   // X-Forwarded-For is client-controlled: ignored

function send_security_headers(): void {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
         . "object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header_remove('X-Powered-By');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
    // Student records must never be cached by browsers or shared proxies.
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
}

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool {
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}
/** Call at the top of every state-changing request. */
function require_post_csrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Method not allowed.'); }
    if (!csrf_ok()) {
        if (defined('IS_API')) json_out(['ok' => false, 'error' => 'Security token expired. Reload the page.'], 419);
        http_response_code(419); exit('Your session expired. Go back, reload the page and try again.');
    }
}

// ---------- flash messages ----------
function flash(string $key, mixed $val): void { $_SESSION['flash'][$key] = $val; }
function flash_pull(string $key): mixed { $v = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $v; }

// ---------- audit ----------
function audit(string $action, ?string $target = null, ?array $detail = null, ?array $actor = null): void {
    $actor ??= current_user();
    $type = $actor['role'] ?? 'system';
    db_exec('INSERT INTO audit_log (actor_type, actor_id, action, target, detail, ip) VALUES (?,?,?,?,?,?)', 'ssssss',
        [$type, $actor['id'] ?? null, $action, $target, $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null, client_ip()]);
}

// ---------- validation ----------
function clean(?string $s, int $max = 100): string { return mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$s)), 0, $max); }
function valid_name(string $s): bool { return (bool)preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .'’-]{0,98}$/u", $s); }

// ---------- passwords ----------
function random_temp_password(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';   // no 0/O/1/l/I
    $groups = [];
    for ($g = 0; $g < 3; $g++) {
        $s = '';
        for ($i = 0; $i < 4; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $groups[] = $s;
    }
    return implode('-', $groups);   // e.g. Kp7m-Qx4t-Rw9n  (~68 bits)
}
/** Returns an error string, or null if the password is acceptable. */
function password_problem(string $pw, array $forbidden = []): ?string {
    $min = (int)config('security')['password_min_length'];
    if (mb_strlen($pw) < $min) return "Use at least $min characters.";
    if (mb_strlen($pw) > 128) return 'Use at most 128 characters.';
    $l = mb_strtolower($pw);
    foreach ($forbidden as $f) if ($f !== '' && $f !== null && str_contains($l, mb_strtolower((string)$f))) return 'Do not include your LRN, name or email in the password.';
    if (preg_match('/^(.)\1+$/u', $pw) || in_array($l, ['password123', 'qwertyuiop', '1234567890', 'password12', 'iloveyou12', 'schoolportal'], true)) return 'That password is too easy to guess.';
    return null;
}

// ---------- grades ----------
const SCORE_FIELDS = ['ww1','ww2','ww3','ww4','qz1','qz2','qz3','qz4','pt1','pt2','pt3','pt4','qa'];
// NOTE: ww_qz_average, pt_average and quarter_grade are calculated by the database triggers (calculate_grades /
// before_update_grades / enforce_min_quarter_grade) — one source of truth. The app never computes or writes them.
/** DepEd descriptor bands used for the colour chip. */
function grade_band(?int $g): array {
    if ($g === null) return ['none', '—'];
    return match (true) {
        $g >= 90 => ['outstanding', 'Outstanding'],
        $g >= 85 => ['very-satisfactory', 'Very Satisfactory'],
        $g >= 80 => ['satisfactory', 'Satisfactory'],
        $g >= 75 => ['fairly-satisfactory', 'Fairly Satisfactory'],
        default  => ['did-not-meet', 'Did Not Meet Expectations'],
    };
}

/** Issue a fresh temporary password for an account and return the plaintext (shown once, never stored). */
function issue_temp_password(string $table, string $keyCol, string $id): array {
    $days = (int)config('security')['temp_password_days'];
    $pw = random_temp_password();
    db_exec("UPDATE $table SET Password=?, must_change_password=1, temp_password_expires_at = NOW() + INTERVAL $days DAY WHERE $keyCol=?", 'ss', [password_hash($pw, PASSWORD_DEFAULT), $id]);
    $exp = db_one("SELECT temp_password_expires_at e FROM $table WHERE $keyCol=?", 's', [$id])['e'];
    return ['password' => $pw, 'expires' => $exp];
}
function credentials_card(): void {
    $c = flash_pull('creds');
    if (!$c) return;
    echo '<div class="card creds" role="alert"><h2>Temporary password — shown only once</h2>'
       . '<p>Give this to <strong>' . e($c['name']) . '</strong> (' . e($c['label']) . ' <code>' . e($c['id']) . '</code>) in person or privately. '
       . 'They must choose their own password at first sign-in. It expires ' . e(date('M j, Y g:i A', strtotime($c['expires']))) . '.</p>'
       . '<p class="pwbox"><code id="temp-pw">' . e($c['password']) . '</code> <button class="btn small" type="button" data-copy="temp-pw">Copy</button></p>'
       . '<p class="muted small">Leaving or reloading this page hides it permanently. If lost, use “Reset password” to issue a new one.</p></div>';
}
