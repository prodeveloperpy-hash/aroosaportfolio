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
  if (function_exists('mb_substr')) {
    return mb_substr($value, 0, $max);
  }
  return substr($value, 0, $max);
}

$name = clean_line($_POST['name'] ?? '', 120);
$company = clean_line($_POST['company'] ?? '', 160);
$from = clean_line($_POST['email'] ?? '', 160);
$service = clean_line($_POST['service'] ?? '', 160);
$message = trim(str_replace("\r", '', (string)($_POST['message'] ?? '')));
if (function_exists('mb_substr')) {
  $message = mb_substr($message, 0, 4000);
} else {
  $message = substr($message, 0, 4000);
}

if (strlen($name) < 2 || !filter_var($from, FILTER_VALIDATE_EMAIL) || strlen($service) < 2 || strlen($message) < 9) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please check the brief and try again.']);
  exit;
}

$to = 'info@recruitexa.com';
$subject = 'A conversation — ' . $service;
$body = "Name: {$name}\nCompany: " . ($company !== '' ? $company : '—') . "\nEmail: {$from}\nRegarding: {$service}\n\n{$message}\n";
$headers = "From: Marium <info@recruitexa.com>\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$sent = mail($to, $subject, $body, $headers, '-finfo@recruitexa.com');

if (!$sent) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'The note could not be sent. Write to info@recruitexa.com.']);
  exit;
}

echo json_encode(['ok' => true]);
