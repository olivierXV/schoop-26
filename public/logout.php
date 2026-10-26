<?php
require __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('index.php'));   // logging out is a POST so other sites can't force it
require_post_csrf();
destroy_session();
redirect(url('login.php'));
