<?php
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/core/auth.php';

if (!is_installed()) { header('Location: ../install/'); exit; }
if (is_logged_in()) { header('Location: ./'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited()) {
        $error = __("login_rate_limited");
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (login($username, $password)) {
            header('Location: ./');
            exit;
        } else {
            $error = __("login_error");
            // Small delay to slow brute force
            sleep(1);
        }
    }
}

$csrf = generate_csrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= htmlspecialchars(site_title()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --accent: <?= theme_color() ?>;
    --bg: #0f0f13;
    --surface: #18181f;
    --border: #2a2a35;
    --text: #e8e8f0;
    --muted: #6b6b80;
  }
  body { background: var(--bg); color: var(--text); font-family: 'Outfit', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
  .wrap { width: 100%; max-width: 400px; }
  .logo { text-align: center; margin-bottom: 2rem; }
  .logo h1 { font-size: 2rem; font-weight: 700; letter-spacing: -0.03em; }
  .logo h1 span { color: var(--accent); }
  .logo p { color: var(--muted); font-size: 0.9rem; margin-top: 0.25rem; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 2rem; }
  .field { margin-bottom: 1.1rem; }
  label { display: block; font-size: 0.82rem; font-weight: 500; color: var(--muted); margin-bottom: 0.45rem; letter-spacing: 0.02em; }
  input { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem 1rem; color: var(--text); font-family: inherit; font-size: 0.95rem; transition: border-color 0.2s; }
  input:focus { outline: none; border-color: var(--accent); }
  .btn { width: 100%; background: var(--accent); color: #fff; border: none; border-radius: 8px; padding: 0.85rem; font-family: inherit; font-size: 0.95rem; font-weight: 600; cursor: pointer; margin-top: 0.25rem; transition: opacity 0.2s; }
  .btn:hover { opacity: 0.85; }
  .error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); border-radius: 8px; padding: 0.7rem 1rem; color: #f87171; font-size: 0.875rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
  .back { text-align: center; margin-top: 1.25rem; }
  .back a { color: var(--muted); font-size: 0.85rem; text-decoration: none; }
  .back a:hover { color: var(--text); }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <h1>Flux<span>CMS</span></h1>
    <p><?= __("login_title") ?></p>
  </div>
  <div class="card">
    <?php if ($error): ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn"><?= __("login_btn") ?></button>
    </form>
  </div>
  <div class="back"><a href="<?= base_url() ?>/"><?= __("login_back") ?></a></div>
</div>
</body>
</html>
