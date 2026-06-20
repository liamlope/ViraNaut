<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
agent_clear_remember_cookie();
session_destroy();
header('Location: login.php');
exit;
