<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false]);
  exit;
}

if (trim((string)($_POST['website'] ?? '')) !== '') {
  echo json_encode(['ok' => true]);
  exit;
}

function clean_line($value, $max) {
  $value = trim(str_replace(["\r", "\n"], ' ', (string)$value));
  return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function encode_header($value) {
  if (!preg_match('/[^\x20-\x7E]/', $value)) {
    return $value;
  }
  return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

$name = clean_line($_POST['name'] ?? '', 120);
$company = clean_line($_POST['company'] ?? '', 160);
$replyTo = clean_line($_POST['email'] ?? '', 160);
$service = clean_line($_POST['service'] ?? '', 160);
$timeline = clean_line($_POST['timeline'] ?? '', 160);
$message = trim(str_replace("\r", '', (string)($_POST['message'] ?? '')));
$message = function_exists('mb_substr') ? mb_substr($message, 0, 4000) : substr($message, 0, 4000);

if (strlen($name) < 2 || !filter_var($replyTo, FILTER_VALIDATE_EMAIL) || strlen($service) < 2 || strlen($message) < 9) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please check the brief and try again.']);
  exit;
}

$domain = 'recruitexa.com';
$mailbox = 'info@' . $domain;
$subject = 'Recruitexa brief — ' . $service;
$body = "Name: {$name}\n"
  . "Company / LinkedIn: " . ($company !== '' ? $company : '-') . "\n"
  . "Email: {$replyTo}\n"
  . "Regarding: {$service}\n"
  . "Timeline: " . ($timeline !== '' ? $timeline : '-') . "\n\n"
  . "{$message}\n";

$config = [
  'host' => 'localhost',
  'user' => $mailbox,
  'pass' => '',
];
if (is_readable(__DIR__ . '/mail-config.php')) {
  $loaded = include __DIR__ . '/mail-config.php';
  if (is_array($loaded)) {
    $config = array_merge($config, $loaded);
  }
}

function discover_mailboxes($domain, $fallback) {
  $boxes = [$fallback];
  $home = getenv('HOME');
  if ($home === false || $home === '') {
    $home = basename(__DIR__) === 'public_html' ? dirname(__DIR__) : dirname(__DIR__);
  }
  $passwd = $home . '/etc/' . $domain . '/passwd';
  if (is_readable($passwd)) {
    foreach (file($passwd, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
      $user = strtolower((string)strtok($line, ':'));
      if ($user === '' || $user[0] === '*' || in_array($user, ['nobody', 'cpanel', 'ftp', 'mailnull'], true)) {
        continue;
      }
      $boxes[] = $user . '@' . $domain;
    }
  }
  return array_slice(array_values(array_unique($boxes)), 0, 20);
}

function mailbox_headers($mailbox, $name, $replyTo) {
  return implode("\r\n", [
    'From: ' . encode_header('Recruitexa') . ' <' . $mailbox . '>',
    'Reply-To: ' . encode_header($name) . ' <' . $replyTo . '>',
    'Return-Path: ' . $mailbox,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: Recruitexa',
  ]);
}

function send_from_mailbox($to, $mailbox, $name, $replyTo, $subject, $body) {
  @ini_set('sendmail_from', $mailbox);
  $headers = mailbox_headers($mailbox, $name, $replyTo);
  $encodedSubject = encode_header($subject);
  $mailBody = str_replace("\n", "\r\n", $body);
  return @mail($to, $encodedSubject, $mailBody, $headers, '-f' . $mailbox);
}

$recipients = $config['to'] ?? discover_mailboxes($domain, $mailbox);
if (!is_array($recipients) || $recipients === []) {
  $recipients = [$mailbox];
}
$recipients = array_values(array_unique(array_filter($recipients, static function ($addr) {
  return (bool)filter_var($addr, FILTER_VALIDATE_EMAIL);
})));
if ($recipients === []) {
  $recipients = [$mailbox];
}

$delivered = 0;
foreach ($recipients as $addr) {
  if (send_from_mailbox($addr, $mailbox, $name, $replyTo, $subject, $body)) {
    $delivered++;
  }
}

if ($delivered === 0) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'The note could not be sent. Write to info@recruitexa.com.']);
  exit;
}

echo json_encode(['ok' => true]);
