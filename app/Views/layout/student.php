<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'Ujian') ?> — <?= esc(cbt_app()) ?></title>
<link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
<?php if (cbt_logo('192') !== ''): ?><link rel="apple-touch-icon" href="<?= cbt_logo('192') ?>"><?php endif ?>
<link rel="stylesheet" href="<?= cbt_css_url() ?>">
</head>
<body>
<?php $s = $siswa ?? null; ?>
<div class="stu-top no-print">
  <?php if (cbt_logo('192') !== ''): ?>
    <img class="logo-img" src="<?= cbt_logo('192') ?>" alt="Logo <?= esc(cbt_sekolah(), 'attr') ?>">
  <?php else: ?>
    <div class="logo"><?= esc(strtoupper(mb_substr((string) ($s['nama'] ?? 'S'), 0, 1))) ?></div>
  <?php endif ?>
  <div class="me">
    <b><?= esc($s['nama'] ?? '-') ?></b>
    <span><?= esc($s['nis'] ?? '') ?> &middot; Kelas <?= esc($s['kelas'] ?? '-') ?></span>
  </div>
  <a class="out" href="<?= site_url('logout') ?>">Keluar</a>
</div>
<div class="stu-wrap">
  <?= flash_alerts() ?>
  <?= $this->renderSection('content') ?>
</div>
</body>
</html>
