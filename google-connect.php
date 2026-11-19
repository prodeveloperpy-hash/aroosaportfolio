<?php
require_once __DIR__ . '/google.php';

function google_h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$cfg = google_load();
$clientId = (string)($cfg['client_id'] ?? '');
$secret = (string)($cfg['client_secret'] ?? '');
$refresh = (string)($cfg['refresh_token'] ?? '');
$connected = $refresh !== '';
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_id'], $_POST['client_secret'])) {
  $clientId = preg_replace('/\s+/', '', (string)$_POST['client_id']);
  $secret = preg_replace('/\s+/', '', (string)$_POST['client_secret']);
  if ($clientId !== '' && $secret !== '') {
    google_save(['client_id' => $clientId, 'client_secret' => $secret]);
    header('Location: google-connect.php?keys=1');
    exit;
  }
  $err = 'Paste both the client ID and the client secret from the JSON file, then save.';
}

if (isset($_GET['code']) && $clientId !== '' && $secret !== '') {
  $res = google_http('POST', 'https://oauth2.googleapis.com/token', [
    'Content-Type' => 'application/x-www-form-urlencoded',
  ], http_build_query([
    'code' => (string)$_GET['code'],
    'client_id' => $clientId,
    'client_secret' => $secret,
    'redirect_uri' => google_redirect_uri(),
    'grant_type' => 'authorization_code',
  ]));
  $newRefresh = (string)($res['json']['refresh_token'] ?? '');
  $access = (string)($res['json']['access_token'] ?? '');
  if ($access === '') {
    $detail = trim((string)($res['json']['error'] ?? '') . ' ' . (string)($res['json']['error_description'] ?? ''));
    if ($detail === '' && (string)($res['curl'] ?? '') !== '') {
      $detail = (string)$res['curl'];
    }
    if ($detail === '' && (int)($res['code'] ?? 0) > 0) {
      $detail = 'HTTP ' . (int)$res['code'];
    }
    $err = 'Google did not return a token. Connect again.'
      . ($detail !== '' ? ' (' . $detail . ')' : '');
  } else {
    $save = [
      'access_token' => $access,
      'expires' => time() + (int)($res['json']['expires_in'] ?? 3500) - 30,
    ];
    if ($newRefresh !== '') {
      $save['refresh_token'] = $newRefresh;
    }
    google_save($save);
    $connected = true;
    $msg = 'Google Calendar is connected. Intro bookings will create a Meet link and a calendar invite.';
    header('Location: google-connect.php?ok=1');
    exit;
  }
}

if (isset($_GET['ok'])) {
  $msg = 'Google Calendar is connected. Intro bookings will create a Meet link and a calendar invite.';
  $connected = true;
}

if (isset($_GET['keys'])) {
  $msg = 'API keys saved. Connect the Google Calendar next.';
}

if ((string)($_GET['error'] ?? '') !== '') {
  $err = 'Google access was denied. Use info@recruitexa.com, then click Continue — not Cancel.';
}

$authUrl = '';
if ($clientId !== '') {
  $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => google_redirect_uri(),
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/calendar',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'include_granted_scopes' => 'true',
    'login_hint' => 'info@recruitexa.com',
  ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connect Google Calendar | Recruitexa</title>
  <link rel="stylesheet" href="styles.css?v=112">
</head>
<body>
  <main class="wrap intro-cal" style="margin-top:2rem;max-width:36rem">
    <p class="kicker">Google Calendar API</p>
    <h1 style="font-size:1.4rem">Intro calls → Google Meet</h1>
    <p class="intro-cal-note">Connect Marium’s Google account once. New bookings add the event to Google Calendar, create a Meet link, and Google emails the invite to the guest.</p>
    <?php if ($msg !== ''): ?><p class="form-note"><?php echo google_h($msg); ?></p><?php endif; ?>
    <?php if ($err !== ''): ?><p class="form-note"><?php echo google_h($err); ?></p><?php endif; ?>
    <p class="intro-cal-note">Redirect URI for the OAuth client: <strong><?php echo google_h(google_redirect_uri()); ?></strong></p>
    <?php
      $needKeys = ($clientId === '' || $secret === '' || (isset($_GET['reset']) && !isset($_GET['keys'])) || str_contains($err, 'invalid_client'));
    ?>
    <?php if ($needKeys): ?>
      <form method="post" action="google-connect.php" class="rx-cal-form" style="display:grid;gap:0.8rem">
        <label class="field field-wide"><span>OAuth client ID</span><input name="client_id" required placeholder="....apps.googleusercontent.com" value="<?php echo google_h($clientId); ?>"></label>
        <label class="field field-wide"><span>OAuth client secret</span><input name="client_secret" required placeholder="GOCSPX-..."></label>
        <button class="btn btn-cyan" type="submit">Save API keys</button>
      </form>
    <?php elseif ($authUrl !== ''): ?>
      <p><?php echo $connected ? 'Calendar is connected. Reconnect if the Meet link stops appearing.' : 'Keys are saved. Connect the calendar account.'; ?></p>
      <p><a class="btn btn-cyan" href="<?php echo google_h($authUrl); ?>">Connect Google Calendar</a></p>
      <p><a href="google-connect.php?reset=1">Replace API keys</a></p>
    <?php endif; ?>
    <p><a href="index.html#book">Back to booking</a></p>
  </main>
</body>
</html>
