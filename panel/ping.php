<?php
/**
 * Lightweight health check — no DB. If this fails, Apache/path/SSL is the problem.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => true,
    'service' => 'vira-web-panel',
    'php' => PHP_VERSION,
    'time' => date('c'),
], JSON_UNESCAPED_UNICODE);
