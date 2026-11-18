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
$intro = clean_line($_POST['intro'] ?? '', 160);
$message = trim(str_replace("\r", '', (string)($_POST['message'] ?? '')));
$message = function_exists('mb_substr') ? mb_substr($message, 0, 4000) : substr($message, 0, 4000);

if (strlen($name) < 2 || !filter_var($replyTo, FILTER_VALIDATE_EMAIL) || strlen($service) < 2 || strlen($message) < 9) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please check the brief and try again.']);
  exit;
}

$domain = 'recruitexa.com';
$intent = strtolower(clean_line($_POST['intent'] ?? '', 40));
$isCareer = $intent === 'career';
$mailbox = ($isCareer ? 'careers' : 'info') . '@' . $domain;
$subject = ($isCareer ? 'Recruitexa career — ' : 'Recruitexa brief — ') . $service;
$body = "Name: {$name}\n"
  . "Company / LinkedIn: " . ($company !== '' ? $company : '-') . "\n"
  . "Email: {$replyTo}\n"
  . "Regarding: {$service}\n"
  . "Timeline: " . ($timeline !== '' ? $timeline : '-') . "\n"
  . "Preferred intro: " . ($intro !== '' ? $intro : '-') . "\n\n"
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

function account_home() {
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
  return $homes[0] ?? '';
}

function discover_mailboxes($domain, $fallback) {
  $boxes = [$fallback];
  $home = account_home();
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
  $mailRoot = $home . '/mail/' . $domain;
  if (is_dir($mailRoot)) {
    foreach (scandir($mailRoot) as $user) {
      if ($user === '.' || $user === '..' || $user[0] === '.') {
        continue;
      }
      $boxDir = $mailRoot . '/' . $user;
      if (is_dir($boxDir . '/new') || is_dir($boxDir)) {
        $boxes[] = strtolower($user) . '@' . $domain;
      }
    }
  }
  return array_slice(array_values(array_unique($boxes)), 0, 40);
}

function read_cv_attachment() {
  if (empty($_FILES['cv']) || !is_uploaded_file((string)($_FILES['cv']['tmp_name'] ?? ''))) {
    return null;
  }
  if ((int)$_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
    return false;
  }
  if ((int)$_FILES['cv']['size'] > 4 * 1024 * 1024) {
    return false;
  }
  $rawName = (string)$_FILES['cv']['name'];
  $ext = strtolower((string)pathinfo($rawName, PATHINFO_EXTENSION));
  $mimes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ];
  if (!isset($mimes[$ext])) {
    return false;
  }
  $bytes = file_get_contents($_FILES['cv']['tmp_name']);
  if ($bytes === false) {
    return false;
  }
  $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $rawName);
  $safe = $safe !== '' ? $safe : ('cv.' . $ext);
  return ['name' => $safe, 'mime' => $mimes[$ext], 'data' => $bytes];
}

function rfc822_message($to, $mailbox, $name, $replyTo, $subject, $body, $attachment = null) {
  $host = preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'recruitexa.com');
  $messageId = '<' . bin2hex(random_bytes(8)) . '@' . $host . '>';
  $headers = [
    'Return-Path: <' . $mailbox . '>',
    'Delivered-To: ' . $to,
    'Date: ' . date('r'),
    'From: ' . encode_header('Recruitexa') . ' <' . $mailbox . '>',
    'To: ' . $to,
    'Reply-To: ' . encode_header($name) . ' <' . $replyTo . '>',
    'Subject: ' . encode_header($subject),
    'Message-ID: ' . $messageId,
    'MIME-Version: 1.0',
    'X-Mailer: Recruitexa',
  ];
  $text = str_replace("\n", "\r\n", $body);
  if (is_array($attachment) && isset($attachment['data'])) {
    $boundary = 'rx' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $part = '--' . $boundary . "\r\n"
      . "Content-Type: text/plain; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: 8bit\r\n\r\n"
      . $text . "\r\n\r\n"
      . '--' . $boundary . "\r\n"
      . 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['name'] . '"' . "\r\n"
      . 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . "\r\n"
      . "Content-Transfer-Encoding: base64\r\n\r\n"
      . chunk_split(base64_encode($attachment['data']))
      . '--' . $boundary . "--\r\n";
    return implode("\r\n", $headers) . "\r\n\r\n" . $part;
  }
  $headers[] = 'Content-Type: text/plain; charset=UTF-8';
  $headers[] = 'Content-Transfer-Encoding: 8bit';
  return implode("\r\n", $headers) . "\r\n\r\n" . $text;
}

function deliver_maildir($to, $mailbox, $name, $replyTo, $subject, $body, $attachment = null) {
  $home = account_home();
  $local = strtolower((string)strstr($to, '@', true));
  $domain = strtolower((string)substr(strrchr($to, '@') ?: '', 1));
  if ($local === '' || $domain === '') {
    return false;
  }
  $box = $home . '/mail/' . $domain . '/' . $local;
  $newDir = $box . '/new';
  $tmpDir = $box . '/tmp';
  if (!is_dir($newDir)) {
    return false;
  }
  $raw = rfc822_message($to, $mailbox, $name, $replyTo, $subject, $body, $attachment);
  $host = preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'host');
  $fname = sprintf('%d.M%dP%d.%s', time(), (int) (microtime(true) * 1000000), getmypid(), $host);
  $target = $newDir . '/' . $fname;
  $tmp = is_dir($tmpDir) && is_writable($tmpDir) ? $tmpDir . '/' . $fname : $target . '.tmp';
  if (@file_put_contents($tmp, $raw) === false) {
    return false;
  }
  if ($tmp !== $target && !@rename($tmp, $target)) {
    @unlink($tmp);
    return false;
  }
  @chmod($target, 0640);
  return true;
}

function send_from_mailbox($to, $mailbox, $name, $replyTo, $subject, $body, $attachment = null) {
  @ini_set('sendmail_from', $mailbox);
  $headerLines = [
    'From: ' . encode_header('Recruitexa') . ' <' . $mailbox . '>',
    'Reply-To: ' . encode_header($name) . ' <' . $replyTo . '>',
    'Return-Path: ' . $mailbox,
    'MIME-Version: 1.0',
    'X-Mailer: Recruitexa',
  ];
  $text = str_replace("\n", "\r\n", $body);
  if (is_array($attachment) && isset($attachment['data'])) {
    $boundary = 'rx' . bin2hex(random_bytes(12));
    $headerLines[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $mailBody = '--' . $boundary . "\r\n"
      . "Content-Type: text/plain; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: 8bit\r\n\r\n"
      . $text . "\r\n\r\n"
      . '--' . $boundary . "\r\n"
      . 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['name'] . '"' . "\r\n"
      . 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . "\r\n"
      . "Content-Transfer-Encoding: base64\r\n\r\n"
      . chunk_split(base64_encode($attachment['data']))
      . '--' . $boundary . "--\r\n";
  } else {
    $headerLines[] = 'Content-Type: text/plain; charset=UTF-8';
    $headerLines[] = 'Content-Transfer-Encoding: 8bit';
    $mailBody = $text;
  }
  $headers = implode("\r\n", $headerLines);
  $encodedSubject = encode_header($subject);
  try {
    if (@mail($to, $encodedSubject, $mailBody, $headers, '-f' . $mailbox)) {
      return true;
    }
  } catch (Throwable $e) {
    /* PHP 8 may reject the -f envelope parameter */
  }
  try {
    return (bool) @mail($to, $encodedSubject, $mailBody, $headers);
  } catch (Throwable $e) {
    return false;
  }
}

$attachment = null;
$fileError = (int)(($_FILES['cv'] ?? [])['error'] ?? UPLOAD_ERR_NO_FILE);
if ($fileError !== UPLOAD_ERR_NO_FILE) {
  $attachment = read_cv_attachment();
  if ($attachment === false) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please attach a PDF, DOC, or DOCX file under 4 MB.']);
    exit;
  }
  if (is_array($attachment)) {
    $body .= "\nCV attached: " . $attachment['name'] . "\n";
  }
}

$recipients = $isCareer ? [$mailbox] : ($config['to'] ?? discover_mailboxes($domain, $mailbox));
if (!is_array($recipients) || $recipients === []) {
  $recipients = [$mailbox];
}
$recipients = array_values(array_unique(array_filter($recipients, static function ($addr) {
  return (bool) filter_var($addr, FILTER_VALIDATE_EMAIL);
})));
if ($recipients === []) {
  $recipients = [$mailbox];
}

$delivered = 0;
foreach ($recipients as $addr) {
  $local = deliver_maildir($addr, $mailbox, $name, $replyTo, $subject, $body, $attachment);
  if ($local) {
    $delivered++;
    continue;
  }
  // Own-domain SMTP follows public MX (Namecheap forwarding) and misses new cPanel boxes.
  $host = strtolower((string) substr(strrchr($addr, '@') ?: '', 1));
  if ($host === $domain) {
    continue;
  }
  try {
    if (send_from_mailbox($addr, $mailbox, $name, $replyTo, $subject, $body, $attachment)) {
      $delivered++;
    }
  } catch (Throwable $e) {
    /* keep delivering remaining inboxes */
  }
}

if ($delivered === 0) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'The note could not be sent. Write to ' . $mailbox . '.']);
  exit;
}

echo json_encode(['ok' => true]);
