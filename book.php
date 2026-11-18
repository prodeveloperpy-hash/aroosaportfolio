<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const BOOK_TZ = 'America/New_York';
const BOOK_DURATION = 30;
const BOOK_START_HOUR = 9;
const BOOK_END_HOUR = 17;
const BOOK_HORIZON_DAYS = 28;
const BOOK_LEAD_MINUTES = 120;

function book_clean($value, $max) {
  $value = trim(str_replace(["\r", "\n"], ' ', (string)$value));
  return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function book_encode_header($value) {
  if (!preg_match('/[^\x20-\x7E]/', $value)) {
    return $value;
  }
  return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function book_home() {
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

function bookings_path() {
  return rtrim(book_home(), '/\\') . '/recruitexa_bookings.json';
}

function load_bookings() {
  $path = bookings_path();
  if (!is_readable($path)) {
    return [];
  }
  $raw = file_get_contents($path);
  $data = json_decode((string)$raw, true);
  return is_array($data) ? $data : [];
}

function save_bookings($rows) {
  $path = bookings_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
  }
  $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
  $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if (@file_put_contents($tmp, $json) === false) {
    return false;
  }
  if (!@rename($tmp, $path)) {
    @unlink($tmp);
    return false;
  }
  @chmod($path, 0640);
  return true;
}

function tz() {
  return new DateTimeZone(BOOK_TZ);
}

function slot_iso(DateTimeImmutable $start) {
  return $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

function parse_slot($iso) {
  try {
    $dt = new DateTimeImmutable((string)$iso);
    return $dt->setTimezone(tz());
  } catch (Exception $e) {
    return null;
  }
}

function is_open_day(DateTimeImmutable $day) {
  $n = (int)$day->format('N');
  return $n >= 1 && $n <= 5;
}

function taken_keys($rows) {
  $keys = [];
  foreach ($rows as $row) {
    if (!empty($row['start'])) {
      $keys[$row['start']] = true;
    }
  }
  return $keys;
}

function build_slots(DateTimeImmutable $from, DateTimeImmutable $until, array $taken) {
  $zone = tz();
  $now = new DateTimeImmutable('now', $zone);
  $lead = $now->modify('+' . BOOK_LEAD_MINUTES . ' minutes');
  $horizon = $now->setTime(0, 0)->modify('+' . BOOK_HORIZON_DAYS . ' days')->setTime(23, 59);
  $cursor = $from->setTimezone($zone)->setTime(0, 0);
  $endDay = $until->setTimezone($zone)->setTime(0, 0);
  $days = [];
  while ($cursor <= $endDay) {
    $date = $cursor->format('Y-m-d');
    $slots = [];
    if (is_open_day($cursor) && $cursor <= $horizon && $cursor >= $now->setTime(0, 0)) {
      $start = $cursor->setTime(BOOK_START_HOUR, 0);
      $close = $cursor->setTime(BOOK_END_HOUR, 0);
      while ($start < $close) {
        $iso = slot_iso($start);
        if ($start >= $lead && empty($taken[$iso])) {
          $slots[] = [
            'start' => $iso,
            'label' => $start->format('g:i A'),
          ];
        }
        $start = $start->modify('+' . BOOK_DURATION . ' minutes');
      }
    }
    $days[] = [
      'date' => $date,
      'weekday' => (int)$cursor->format('N'),
      'open' => $slots !== [],
      'slots' => $slots,
    ];
    $cursor = $cursor->modify('+1 day');
  }
  return $days;
}

function valid_generated_slot(DateTimeImmutable $start, array $taken) {
  $iso = slot_iso($start);
  if (!empty($taken[$iso])) {
    return false;
  }
  $day = $start->setTime(0, 0);
  $days = build_slots($day, $day, $taken);
  foreach ($days[0]['slots'] ?? [] as $slot) {
    if ($slot['start'] === $iso) {
      return true;
    }
  }
  return false;
}

function ics_for($start, $name, $email, $note) {
  $end = $start->modify('+' . BOOK_DURATION . ' minutes');
  $utcStart = $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
  $utcEnd = $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
  $uid = bin2hex(random_bytes(8)) . '@recruitexa.com';
  $desc = 'Intro call with Recruitexa.\nGuest: ' . $name . ' <' . $email . '>';
  if ($note !== '') {
    $desc .= '\nNote: ' . str_replace(["\r", "\n"], ' ', $note);
  }
  $lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Recruitexa//Intro Call//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:' . $uid,
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $utcStart,
    'DTEND:' . $utcEnd,
    'SUMMARY:Recruitexa intro call — ' . $name,
    'DESCRIPTION:' . $desc,
    'ORGANIZER;CN=Recruitexa:mailto:info@recruitexa.com',
    'ATTENDEE;CN=' . $name . ':mailto:' . $email,
    'LOCATION:Link to follow by email or WhatsApp',
    'END:VEVENT',
    'END:VCALENDAR',
  ];
  return implode("\r\n", $lines) . "\r\n";
}

function deliver_booking_mail($name, $email, $start, $note) {
  $home = book_home();
  $newDir = $home . '/mail/recruitexa.com/info/new';
  $tmpDir = $home . '/mail/recruitexa.com/info/tmp';
  if (!is_dir($newDir)) {
    return false;
  }
  $when = $start->format('l, F j, Y \a\t g:i A T');
  $body = "A 30-minute intro was booked on recruitexa.com.\n\n"
    . "When: {$when}\n"
    . "Guest: {$name}\n"
    . "Email: {$email}\n"
    . "Note: " . ($note !== '' ? $note : '-') . "\n\n"
    . "Open the attached .ics file to add this to your calendar.\n"
    . "Send the guest a Meet/Zoom/WhatsApp link from this thread.\n";
  $ics = ics_for($start, $name, $email, $note);
  $host = preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'recruitexa.com');
  $messageId = '<' . bin2hex(random_bytes(8)) . '@' . $host . '>';
  $boundary = 'rx' . bin2hex(random_bytes(12));
  $mailbox = 'info@recruitexa.com';
  $headers = [
    'Return-Path: <' . $mailbox . '>',
    'Delivered-To: ' . $mailbox,
    'Date: ' . date('r'),
    'From: ' . book_encode_header('Recruitexa') . ' <' . $mailbox . '>',
    'To: ' . $mailbox,
    'Reply-To: ' . book_encode_header($name) . ' <' . $email . '>',
    'Subject: ' . book_encode_header('Intro booked — ' . $start->format('D M j, g:i A')),
    'Message-ID: ' . $messageId,
    'MIME-Version: 1.0',
    'X-Mailer: Recruitexa Calendar',
    'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
  ];
  $text = str_replace("\n", "\r\n", $body);
  $part = '--' . $boundary . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
    . $text . "\r\n\r\n"
    . '--' . $boundary . "\r\n"
    . "Content-Type: text/calendar; charset=UTF-8; method=PUBLISH; name=\"intro.ics\"\r\n"
    . "Content-Disposition: attachment; filename=\"intro.ics\"\r\n"
    . "Content-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($ics))
    . '--' . $boundary . "--\r\n";
  $raw = implode("\r\n", $headers) . "\r\n\r\n" . $part;
  $fname = sprintf('%d.M%dP%d.%s', time(), (int)(microtime(true) * 1000000), getmypid(), $host);
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $month = book_clean($_GET['month'] ?? '', 7);
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = (new DateTimeImmutable('now', tz()))->format('Y-m');
  }
  $from = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00', tz());
  if (!$from) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
  }
  $until = $from->modify('last day of this month');
  $taken = taken_keys(load_bookings());
  echo json_encode([
    'ok' => true,
    'tz' => BOOK_TZ,
    'tzLabel' => (new DateTimeImmutable('now', tz()))->format('T'),
    'duration' => BOOK_DURATION,
    'month' => $month,
    'monthLabel' => $from->format('F Y'),
    'days' => build_slots($from, $until, $taken),
  ]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false]);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
  $payload = $_POST;
}

if (trim((string)($payload['website'] ?? '')) !== '') {
  echo json_encode(['ok' => true]);
  exit;
}

$name = book_clean($payload['name'] ?? '', 120);
$email = book_clean($payload['email'] ?? '', 160);
$note = book_clean($payload['note'] ?? '', 800);
$start = parse_slot($payload['start'] ?? '');

if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$start) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please add your name, email, and a time.']);
  exit;
}

$path = bookings_path();
$dir = dirname($path);
if (!is_dir($dir)) {
  @mkdir($dir, 0750, true);
}
$lock = fopen($path . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) {
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'Please try that time again.']);
  exit;
}

$rows = load_bookings();
$taken = taken_keys($rows);
if (!valid_generated_slot($start, $taken)) {
  flock($lock, LOCK_UN);
  fclose($lock);
  http_response_code(409);
  echo json_encode(['ok' => false, 'error' => 'That time was just taken. Please pick another slot.']);
  exit;
}

$row = [
  'id' => bin2hex(random_bytes(8)),
  'start' => slot_iso($start),
  'name' => $name,
  'email' => $email,
  'note' => $note,
  'created' => gmdate('c'),
];
$rows[] = $row;
if (count($rows) > 400) {
  $rows = array_slice($rows, -400);
}
if (!save_bookings($rows)) {
  flock($lock, LOCK_UN);
  fclose($lock);
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'The booking could not be saved. Write to info@recruitexa.com.']);
  exit;
}

flock($lock, LOCK_UN);
fclose($lock);

deliver_booking_mail($name, $email, $start, $note);

echo json_encode([
  'ok' => true,
  'when' => $start->format('l, F j, Y \a\t g:i A T'),
]);
