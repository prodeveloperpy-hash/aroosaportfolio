<?php

function jobs_home() {
  $homes = [];
  $env = getenv('HOME');
  if (is_string($env) && $env !== '') {
    $homes[] = $env;
  }
  $doc = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
  if ($doc !== '') {
    $homes[] = dirname($doc);
  }
  if (basename(__DIR__) === 'public_html') {
    $homes[] = dirname(__DIR__);
  }
  $homes[] = dirname(__DIR__);
  foreach (array_unique($homes) as $home) {
    if ($home !== '' && (is_dir($home . '/mail') || is_dir($home . '/etc'))) {
      return $home;
    }
  }
  return $homes[0] ?? sys_get_temp_dir();
}

function jobs_store_path() {
  return rtrim(jobs_home(), '/\\') . '/recruitexa_jobs.json';
}

function jobs_load() {
  $path = jobs_store_path();
  if (!is_readable($path)) {
    return ['hash' => '', 'jobs' => []];
  }
  $raw = json_decode((string)file_get_contents($path), true);
  if (!is_array($raw)) {
    return ['hash' => '', 'jobs' => []];
  }
  if (!isset($raw['jobs']) || !is_array($raw['jobs'])) {
    $raw['jobs'] = [];
  }
  $raw['hash'] = (string)($raw['hash'] ?? '');
  return $raw;
}

function jobs_save(array $data) {
  $path = jobs_store_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
  }
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  return @file_put_contents($path, $json) !== false;
}

function jobs_clean($value, $max) {
  $value = trim(str_replace(["\r", "\n"], ' ', (string)$value));
  return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function jobs_date($value) {
  $value = jobs_clean($value, 10);
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function jobs_today() {
  return (new DateTimeImmutable('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
}

function jobs_is_live(array $job) {
  if (empty($job['open'])) {
    return false;
  }
  $closes = (string)($job['closes'] ?? '');
  if ($closes !== '' && $closes < jobs_today()) {
    return false;
  }
  return true;
}

function jobs_public() {
  $open = [];
  foreach (jobs_load()['jobs'] as $job) {
    if (!jobs_is_live($job)) {
      continue;
    }
    $open[] = [
      'id' => (string)($job['id'] ?? ''),
      'title' => (string)($job['title'] ?? ''),
      'type' => (string)($job['type'] ?? 'Remote'),
      'summary' => (string)($job['summary'] ?? ''),
      'opens' => (string)($job['opens'] ?? ''),
      'closes' => (string)($job['closes'] ?? ''),
    ];
  }
  return $open;
}
