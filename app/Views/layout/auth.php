<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'Masuk') ?> — <?= esc(cbt_app()) ?></title>
<link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
<?php if (cbt_logo('192') !== ''): ?><link rel="apple-touch-icon" href="<?= cbt_logo('192') ?>"><?php endif ?>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <?php if (cbt_logo('192') !== ''): ?>
      <img class="auth-logo-img" src="<?= cbt_logo('192') ?>" alt="Logo <?= esc(cbt_sekolah(), 'attr') ?>">
    <?php else: ?>
      <div class="auth-logo"><?= esc($icon ?? 'C') ?></div>
    <?php endif ?>
    <h1><?= esc($heading ?? cbt_app()) ?></h1>
    <div class="auth-sub"><?= esc($sub ?? cbt_sekolah()) ?></div>
    <?php if (cbt_moto() !== ''): ?><div class="auth-moto"><?= esc(cbt_moto()) ?></div><?php endif ?>
    <?= flash_alerts() ?>
    <?= $this->renderSection('form') ?>
    <div class="auth-foot muted"><?= $this->renderSection('foot') ?></div>
  </div>
</div>
</body>
</html>
