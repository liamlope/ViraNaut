<?php
declare(strict_types=1);

define('VIRANAUT_TEST', true);
date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/Support/FakePdo.php';

$root = dirname(__DIR__);
require_once $root . '/viranaut_handlers.php';
require_once $root . '/config.php';
require_once $root . '/ilan.php';
