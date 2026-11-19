<?php

function google_home() {
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

function google_store_path() {
  return rtrim(google_home(), '/\\') . '/recruitexa_google.json';
}

function google_load() {
  $data = [];
  $local = __DIR__ . '/google-config.php';
  if (is_readable($local)) {
    $loaded = include $local;
    if (is_array($loaded)) {
      $data = $loaded;
    }
  }
  $path = google_store_path();
  if (is_readable($path)) {
    $raw = json_decode((string)file_get_contents($path), true);
    if (is_array($raw)) {
      $data = array_merge($data, $raw);
    }
  }
  return $data;
}

function google_save(array $data) {
  $path = google_store_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
  }
  $current = google_load();
  $merged = array_merge($current, $data);
  $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  return @file_put_contents($path, $json) !== false;
}

function google_redirect_uri() {
  return 'https://recruitexa.com/google-connect.php';
}

function google_http($method, $url, $headers = [], $body = null) {
  $headerLines = [];
  foreach ($headers as $key => $value) {
    $headerLines[] = is_int($key) ? $value : ($key . ': ' . $value);
  }
  if ($body !== null && !preg_grep('/^content-type:/i', $headerLines)) {
    $headerLines[] = 'Content-Type: application/json';
  }
  $payload = null;
  if ($body !== null) {
    $payload = is_string($body) ? $body : json_encode($body);
  }
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    if (strtoupper($method) === 'POST') {
      curl_setopt($ch, CURLOPT_POST, true);
    } else {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    if ($payload !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$raw, true);
    return [
      'code' => $code,
      'json' => is_array($json) ? $json : [],
      'raw' => (string)$raw,
      'curl' => (string)$curlErr,
    ];
  }
  $opts = [
    'http' => [
      'method' => $method,
      'header' => implode("\r\n", $headerLines),
      'timeout' => 20,
      'ignore_errors' => true,
    ],
  ];
  if ($body !== null) {
    $opts['http']['content'] = is_string($body) ? $body : json_encode($body);
  }
  $raw = @file_get_contents($url, false, stream_context_create($opts));
  $code = 0;
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $code = (int)$m[1];
  }
  $json = json_decode((string)$raw, true);
  return ['code' => $code, 'json' => is_array($json) ? $json : [], 'raw' => (string)$raw, 'curl' => ''];
}

function google_token() {
  $cfg = google_load();
  $clientId = (string)($cfg['client_id'] ?? '');
  $secret = (string)($cfg['client_secret'] ?? '');
  $refresh = (string)($cfg['refresh_token'] ?? '');
  if ($clientId === '' || $secret === '' || $refresh === '') {
    return '';
  }
  $expires = (int)($cfg['expires'] ?? 0);
  $access = (string)($cfg['access_token'] ?? '');
  if ($access !== '' && $expires > time() + 60) {
    return $access;
  }
  $res = google_http('POST', 'https://oauth2.googleapis.com/token', [
    'Content-Type' => 'application/x-www-form-urlencoded',
  ], http_build_query([
    'client_id' => $clientId,
    'client_secret' => $secret,
    'refresh_token' => $refresh,
    'grant_type' => 'refresh_token',
  ]));
  $access = (string)($res['json']['access_token'] ?? '');
  if ($access === '') {
    return '';
  }
  google_save([
    'access_token' => $access,
    'expires' => time() + (int)($res['json']['expires_in'] ?? 3500) - 30,
  ]);
  return $access;
}

function google_busy_ranges(DateTimeImmutable $from, DateTimeImmutable $until) {
  $token = google_token();
  if ($token === '') {
    return [];
  }
  $res = google_http('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', [
    'Authorization' => 'Bearer ' . $token,
  ], [
    'timeMin' => $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
    'timeMax' => $until->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
    'items' => [['id' => 'primary']],
  ]);
  $busy = [];
  foreach (($res['json']['calendars']['primary']['busy'] ?? []) as $block) {
    try {
      $busy[] = [
        'start' => new DateTimeImmutable((string)$block['start']),
        'end' => new DateTimeImmutable((string)$block['end']),
      ];
    } catch (Exception $e) {
      continue;
    }
  }
  return $busy;
}

function google_slot_busy(DateTimeImmutable $start, DateTimeImmutable $end, array $busy) {
  foreach ($busy as $block) {
    if ($start < $block['end'] && $end > $block['start']) {
      return true;
    }
  }
  return false;
}

function google_create_meet(DateTimeImmutable $start, $name, $email, $note) {
  $token = google_token();
  if ($token === '') {
    return ['meet' => '', 'error' => 'not_connected'];
  }
  $end = $start->modify('+30 minutes');
  $zone = $start->getTimezone()->getName();
  $desc = 'Recruitexa intro call.' . "\nGuest: {$name} <{$email}>";
  if ($note !== '') {
    $desc .= "\nNote: " . $note;
  }
  $res = google_http(
    'POST',
    'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all',
    ['Authorization' => 'Bearer ' . $token],
    [
      'summary' => 'Recruitexa intro — ' . $name,
      'description' => $desc,
      'start' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => $zone],
      'end' => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => $zone],
      'attendees' => [
        ['email' => $email, 'displayName' => $name],
      ],
      'conferenceData' => [
        'createRequest' => [
          'requestId' => bin2hex(random_bytes(8)),
          'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
        ],
      ],
    ]
  );
  $event = $res['json'];
  $meet = (string)($event['hangoutLink'] ?? '');
  if ($meet === '') {
    $meet = (string)($event['conferenceData']['entryPoints'][0]['uri'] ?? '');
  }
  return [
    'meet' => $meet,
    'htmlLink' => (string)($event['htmlLink'] ?? ''),
    'id' => (string)($event['id'] ?? ''),
  ];
}
