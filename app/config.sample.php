<?php
// Copy this file to config.local.php and fill it in. config.local.php is git-ignored.
// (Alternatively set SCHOOP_DB_HOST / SCHOOP_DB_USER / SCHOOP_DB_PASS / SCHOOP_DB_NAME env vars.)
return [
    "db" => [
        "host" => "127.0.0.1",
        "port" => 3306,
        "user" => "schoop_app",      // a least-privilege user, NOT root (see README)
        "pass" => "CHANGE_ME",
        "name" => "schoop",
    ],
    "app" => [
        "current_semester" => 2,      // 1 = Q1/Q2, 2 = Q3/Q4 (was hard-coded in the old code)
    ],
    "security" => [
        "password_min_length"  => 10,
        "temp_password_days"   => 7,   // how long an admin-issued temporary password stays valid
        "session_idle_minutes" => 30,
        "session_max_hours"    => 8,
        "max_failures_per_account_ip" => 5,   // failed logins per (account + IP) ...
        "lockout_minutes"      => 15,         // ... within this window
    ],
];
