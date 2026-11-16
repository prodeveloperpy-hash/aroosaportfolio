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
$subject = 'A conversation - ' . $service;
$body = "Name: {$name}\nCompany: " . ($company !== '' ? $company : '-') . "\nEmail: {$from}\nRegarding: {$service}\n\n{$message}\n";

function smtp_command($fp, $command) {
  fwrite($fp, $command . "\r\n");
  $data = '';
  while (($line = fgets($fp, 515)) !== false) {
    $data .= $line;
    if (isset($line[3]) && $line[3] === ' ') {
      break;
    }
  }
  return $data;
}

function smtp_send($to, $subject, $body, $reply) {
  $reason = 'The mail server did not accept the note.';
  foreach ([25, 587] as $port) {
    $fp = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 8);
    if (!$fp) {
      $reason = trim($errstr) !== '' ? $errstr : 'Could not reach the mail server.';
      continue;
    }
    stream_set_timeout($fp, 8);
    $banner = '';
    while (($line = fgets($fp, 515)) !== false) {
      $banner .= $line;
      if (isset($line[3]) && $line[3] === ' ') {
        break;
      }
    }
    if (strpos($banner, '220') !== 0) {
      $reason = trim($banner) !== '' ? trim($banner) : $reason;
      fclose($fp);
      continue;
    }
    smtp_command($fp, 'EHLO recruitexa.com');
    $mail = smtp_command($fp, 'MAIL FROM:<info@recruitexa.com>');
    if (strpos($mail, '250') !== 0) {
      $reason = trim($mail);
      smtp_command($fp, 'QUIT');
      fclose($fp);
      continue;
    }
    $rcpt = smtp_command($fp, 'RCPT TO:<' . $to . '>');
    if (strpos($rcpt, '250') !== 0 && strpos($rcpt, '251') !== 0) {
      $reason = trim($rcpt);
      smtp_command($fp, 'QUIT');
      fclose($fp);
      continue;
    }
    $data = smtp_command($fp, 'DATA');
    if (strpos($data, '354') !== 0) {
      $reason = trim($data);
      smtp_command($fp, 'QUIT');
      fclose($fp);
      continue;
    }
    $message = "From: Marium <info@recruitexa.com>\r\n";
    $message .= 'To: <' . $to . ">\r\n";
    $message .= 'Reply-To: <' . $reply . ">\r\n";
    $message .= 'Subject: ' . $subject . "\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
    $message .= "\r\n.\r\n";
    fwrite($fp, $message);
    $done = '';
    while (($line = fgets($fp, 515)) !== false) {
      $done .= $line;
      if (isset($line[3]) && $line[3] === ' ') {
        break;
      }
    }
    smtp_command($fp, 'QUIT');
    fclose($fp);
    if (strpos($done, '250') === 0) {
      return [true, ''];
    }
    $reason = trim($done) !== '' ? trim($done) : $reason;
  }
  error_log('send.php: ' . $reason);
  return [false, $reason];
}

[$sent, $reason] = smtp_send($to, $subject, $body, $from);

if (!$sent) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'The note could not be sent. Write to info@recruitexa.com.']);
  exit;
}

echo json_encode(['ok' => true]);
