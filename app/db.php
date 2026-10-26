<?php
declare(strict_types=1);

function db(): mysqli {
    static $conn = null;
    if ($conn) return $conn;
    $c = config("db");
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // throw, never echo SQL errors
    $conn = new mysqli($c["host"], $c["user"], $c["pass"], $c["name"], (int)($c["port"] ?? 3306));
    $conn->set_charset("utf8mb4");
    return $conn;
}
function db_stmt(string $sql, string $types = "", array $params = []): mysqli_stmt {
    $st = db()->prepare($sql);
    if ($types !== "") $st->bind_param($types, ...$params);
    $st->execute();
    return $st;
}
function db_all(string $sql, string $types = "", array $params = []): array {
    $st = db_stmt($sql, $types, $params);
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function db_one(string $sql, string $types = "", array $params = []): ?array {
    return db_all($sql, $types, $params)[0] ?? null;
}
function db_exec(string $sql, string $types = "", array $params = []): int {
    $st = db_stmt($sql, $types, $params);
    $n = $st->affected_rows;
    $st->close();
    return $n;
}
