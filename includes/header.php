<?php
$pageTitle = $pageTitle ?? app_config()['app_name'];
$user = current_user();
$flashMessage = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#6B352A">
  <title><?= e($pageTitle) ?> · <?= e(app_config()['app_name']) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
  <script defer src="<?= e(url('assets/js/app.js')) ?>"></script>
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= e(url('')) ?>" aria-label="CareBridge home"><span class="brand-mark">✚</span><span>Care<span class="brand-light">Bridge</span></span></a>
  <nav class="top-nav" aria-label="Main navigation">
    <a href="<?= e(url('')) ?>">Home</a>
    <a href="<?= e(url('providers.php')) ?>">Hospitals & clinics</a>
    <?php if ($user): ?>
      <?php if (($user['role'] ?? '') === 'patient'): ?><a href="<?= e(url('dashboard.php')) ?>">My health</a><a href="<?= e(url('appointments/book.php')) ?>">Appointments</a>
      <?php elseif (($user['role'] ?? '') === 'hospital_admin'): ?><a href="<?= e(url('hospital/dashboard.php')) ?>">Hospital workspace</a>
      <?php elseif (($user['role'] ?? '') === 'platform_admin'): ?><a href="<?= e(url('admin/hospitals.php')) ?>">Administration</a><?php endif; ?>
      <span class="nav-user"><?= e($user['full_name']) ?></span>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="button button-small button-ghost" type="submit">Log out</button></form>
    <?php else: ?>
      <a href="<?= e(url('login.php')) ?>">Login</a>
      <a class="nav-register" href="<?= e(url('register.php')) ?>">Get started</a>
    <?php endif; ?>
  </nav>
</header>
<main class="page-shell">
<?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
