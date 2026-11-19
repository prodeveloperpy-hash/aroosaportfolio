<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/jobs-lib.php';
echo json_encode(['ok' => true, 'jobs' => jobs_public()]);
