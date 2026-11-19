<?php
session_name('rxjobs');
session_start();
require_once __DIR__ . '/jobs-lib.php';

function jobs_h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$store = jobs_load();
$msg = '';
$err = '';
$authed = !empty($_SESSION['rx_jobs_ok']);

if (isset($_GET['logout'])) {
  $_SESSION = [];
  session_destroy();
  header('Location: careers-admin.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'setup') {
    $pass = (string)($_POST['password'] ?? '');
    $again = (string)($_POST['password2'] ?? '');
    if (strlen($pass) < 8) {
      $err = 'Use at least 8 characters.';
    } elseif ($pass !== $again) {
      $err = 'Those passwords did not match.';
    } elseif ($store['hash'] !== '') {
      $err = 'A password is already set.';
    } else {
      $store['hash'] = password_hash($pass, PASSWORD_DEFAULT);
      jobs_save($store);
      $_SESSION['rx_jobs_ok'] = 1;
      $authed = true;
      $msg = 'Admin password saved. Post a role below.';
    }
  } elseif ($action === 'login') {
    $pass = (string)($_POST['password'] ?? '');
    if ($store['hash'] === '' || !password_verify($pass, $store['hash'])) {
      usleep(400000);
      $err = 'That password was not accepted.';
    } else {
      $_SESSION['rx_jobs_ok'] = 1;
      $authed = true;
    }
  } elseif ($authed && $action === 'save') {
    $id = jobs_clean($_POST['id'] ?? '', 32);
    $title = jobs_clean($_POST['title'] ?? '', 120);
    $type = jobs_clean($_POST['type'] ?? '', 80);
    $summary = jobs_clean($_POST['summary'] ?? '', 600);
    $opens = jobs_date($_POST['opens'] ?? '');
    $closes = jobs_date($_POST['closes'] ?? '');
    $open = isset($_POST['open']);
    $needs = jobs_lines($_POST['needs'] ?? '');
    $questions = jobs_questions_from_post();
    if ($title === '' || $summary === '') {
      $err = 'Add a title and a short summary.';
    } elseif ($opens !== '' && $closes !== '' && $closes < $opens) {
      $err = 'Closing date must be on or after the opening date.';
    } else {
      $row = [
        'title' => $title,
        'type' => $type !== '' ? $type : 'Remote',
        'summary' => $summary,
        'opens' => $opens,
        'closes' => $closes,
        'open' => $open,
        'needs' => $needs,
        'questions' => $questions,
      ];
      if ($id === '') {
        $row['id'] = bin2hex(random_bytes(6));
        $row['created'] = gmdate('c');
        $store['jobs'][] = $row;
        $msg = 'Role posted on the careers page.';
      } else {
        foreach ($store['jobs'] as &$job) {
          if (($job['id'] ?? '') === $id) {
            $job = array_merge($job, $row);
            $msg = 'Role updated.';
            break;
          }
        }
        unset($job);
      }
      jobs_save($store);
    }
  } elseif ($authed && $action === 'toggle') {
    $id = jobs_clean($_POST['id'] ?? '', 32);
    foreach ($store['jobs'] as &$job) {
      if (($job['id'] ?? '') === $id) {
        $job['open'] = empty($job['open']);
        $msg = !empty($job['open']) ? 'Role is live.' : 'Role is hidden.';
        break;
      }
    }
    unset($job);
    jobs_save($store);
  } elseif ($authed && $action === 'delete') {
    $id = jobs_clean($_POST['id'] ?? '', 32);
    $store['jobs'] = array_values(array_filter($store['jobs'], static function ($job) use ($id) {
      return ($job['id'] ?? '') !== $id;
    }));
    jobs_save($store);
    $msg = 'Role removed.';
  }
  $store = jobs_load();
}

$needSetup = ($store['hash'] === '');
$edit = null;
if ($authed && isset($_GET['edit'])) {
  $editId = jobs_clean($_GET['edit'] ?? '', 32);
  foreach ($store['jobs'] as $job) {
    if (($job['id'] ?? '') === $editId) {
      $edit = $job;
      break;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Careers admin | Recruitexa</title>
  <link rel="stylesheet" href="styles.css?v=114">
</head>
<body>
  <main class="wrap intro-cal" style="margin-top:2rem;max-width:40rem">
    <p class="kicker">Recruitexa only</p>
    <h1 style="font-size:1.4rem">Post roles on Careers</h1>
    <p class="intro-cal-note">This page is not linked on the public site. One Recruitexa login. Live roles appear on careers.html. Open applications stay even when no role is posted.</p>
    <?php if ($msg !== ''): ?><p class="form-note"><?php echo jobs_h($msg); ?></p><?php endif; ?>
    <?php if ($err !== ''): ?><p class="form-note"><?php echo jobs_h($err); ?></p><?php endif; ?>

    <?php if ($needSetup): ?>
      <form method="post" class="rx-cal-form" style="display:grid;gap:0.8rem">
        <input type="hidden" name="action" value="setup">
        <label class="field field-wide"><span>Set admin password</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
        <label class="field field-wide"><span>Repeat password</span><input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
        <button class="btn btn-cyan" type="submit">Save password</button>
      </form>
    <?php elseif (!$authed): ?>
      <form method="post" class="rx-cal-form" style="display:grid;gap:0.8rem">
        <input type="hidden" name="action" value="login">
        <label class="field field-wide"><span>Admin password</span><input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-cyan" type="submit">Sign in</button>
      </form>
    <?php else: ?>
      <p><a href="careers.html" target="_blank" rel="noopener">View careers page</a> · <a href="careers-admin.php?logout=1">Sign out</a></p>
      <form method="post" class="rx-cal-form" style="display:grid;gap:0.8rem;margin:1.2rem 0 2rem">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo jobs_h($edit['id'] ?? ''); ?>">
        <label class="field field-wide"><span>Role title</span><input name="title" required maxlength="120" value="<?php echo jobs_h($edit['title'] ?? ''); ?>" placeholder="Talent sourcer"></label>
        <label class="field field-wide"><span>Type</span><input name="type" maxlength="80" value="<?php echo jobs_h($edit['type'] ?? 'Remote'); ?>" placeholder="Remote · contract"></label>
        <label class="field"><span>Opening date</span><input name="opens" type="date" value="<?php echo jobs_h($edit['opens'] ?? ''); ?>"></label>
        <label class="field"><span>Closing date (optional)</span><input name="closes" type="date" value="<?php echo jobs_h($edit['closes'] ?? ''); ?>"></label>
        <label class="field field-wide"><span>Summary</span><textarea name="summary" required maxlength="600" rows="4" placeholder="What the seat does, and who should apply."><?php echo jobs_h($edit['summary'] ?? ''); ?></textarea></label>
        <label class="field field-wide"><span>To apply for this role you need (one per line)</span><textarea name="needs" maxlength="1200" rows="4" placeholder="US hours overlap&#10;2+ years talent sourcing&#10;Fluent English"><?php echo jobs_h(implode("\n", (array)($edit['needs'] ?? []))); ?></textarea></label>
        <p class="intro-cal-note" style="margin:0">Extra questions become extra fields on that job’s apply form. Leave a row blank to skip it.</p>
        <?php
          $qs = (array)($edit['questions'] ?? []);
          $byId = [];
          foreach ($qs as $q) {
            if (!empty($q['id'])) $byId[$q['id']] = $q;
          }
          for ($i = 1; $i <= 6; $i++):
            $q = $byId['q' . $i] ?? ($qs[$i - 1] ?? []);
        ?>
        <div class="field field-wide" style="display:grid;grid-template-columns:1fr auto;gap:0.6rem;align-items:end">
          <label><span>Extra question <?php echo $i; ?></span><input name="q<?php echo $i; ?>" maxlength="160" value="<?php echo jobs_h($q['label'] ?? ''); ?>" placeholder="<?php echo $i === 1 ? 'Which ATS have you used?' : ''; ?>"></label>
          <label style="flex-direction:row;align-items:center;gap:0.4rem;padding-bottom:0.55rem"><input type="checkbox" name="q<?php echo $i; ?>_req" value="1" <?php echo !empty($q['required']) ? 'checked' : ''; ?>> Required</label>
        </div>
        <?php endfor; ?>
        <label class="field field-wide" style="flex-direction:row;align-items:center;gap:0.5rem"><input type="checkbox" name="open" value="1" <?php echo empty($edit) || !empty($edit['open']) ? 'checked' : ''; ?>> Show on the careers page (after the opening date)</label>
        <button class="btn btn-cyan" type="submit"><?php echo $edit ? 'Update role' : 'Post role'; ?></button>
      </form>
      <?php if (!$store['jobs']): ?>
        <p class="intro-cal-note">No roles yet. The public page still shows the open application.</p>
      <?php else: ?>
        <div class="career-jobs">
          <?php foreach ($store['jobs'] as $job): ?>
            <article class="career-job">
              <p class="kicker"><?php echo jobs_is_live($job) ? 'Live' : 'Hidden'; ?></p>
              <h3><?php echo jobs_h($job['title'] ?? ''); ?></h3>
              <p><?php echo jobs_h($job['type'] ?? ''); ?></p>
              <p><?php
                $opens = (string)($job['opens'] ?? '');
                $closes = (string)($job['closes'] ?? '');
                echo 'Opens: ' . jobs_h($opens !== '' ? $opens : 'now');
                if ($closes !== '') {
                  echo ' · Closes: ' . jobs_h($closes);
                }
              ?></p>
              <p><?php echo jobs_h($job['summary'] ?? ''); ?></p>
              <?php if (!empty($job['needs'])): ?>
                <p><strong>Must-haves:</strong> <?php echo jobs_h(implode(' · ', (array)$job['needs'])); ?></p>
              <?php endif; ?>
              <?php if (!empty($job['questions'])): ?>
                <p><strong>Form questions:</strong> <?php echo jobs_h(implode(' · ', array_map(static function ($q) {
                  return (string)($q['label'] ?? '');
                }, (array)$job['questions']))); ?></p>
              <?php endif; ?>
              <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.8rem">
                <form method="post">
                  <input type="hidden" name="id" value="<?php echo jobs_h($job['id'] ?? ''); ?>">
                  <button class="btn btn-ghost" name="action" value="toggle" type="submit"><?php echo !empty($job['open']) ? 'Hide' : 'Publish'; ?></button>
                </form>
                <a class="btn btn-ghost" href="careers-admin.php?edit=<?php echo jobs_h($job['id'] ?? ''); ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this role from Careers?')">
                  <input type="hidden" name="id" value="<?php echo jobs_h($job['id'] ?? ''); ?>">
                  <button class="btn btn-cyan" name="action" value="delete" type="submit">Delete</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
