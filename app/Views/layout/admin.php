<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'Admin') ?> — <?= esc(cbt_app()) ?></title>
<link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
<?php if (cbt_logo('192') !== ''): ?><link rel="apple-touch-icon" href="<?= cbt_logo('192') ?>"><?php endif ?>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<?php $seg = service('uri')->getSegment(2) ?: ''; ?>
<div class="shell">
  <div class="sb-veil no-print" id="veil" onclick="sb()"></div>

  <aside class="sidebar no-print" id="sb">
    <div class="brand">
      <?php if (cbt_logo('192') !== ''): ?>
        <img class="logo-img" src="<?= cbt_logo('192') ?>" alt="Logo <?= esc(cbt_sekolah(), 'attr') ?>">
      <?php else: ?>
        <div class="logo"><?= esc(mb_substr(cbt_sekolah(), 0, 1)) ?></div>
      <?php endif ?>
      <div>
        <b><?= esc(cbt_app()) ?></b>
        <span>Panel Admin</span>
      </div>
    </div>
    <nav class="nav">
      <a href="<?= site_url('admin') ?>" class="<?= $seg === '' ? 'on' : '' ?>">&#9636; Dashboard</a>
      <div class="sep">Data</div>
      <a href="<?= site_url('admin/siswa') ?>" class="<?= $seg === 'siswa' ? 'on' : '' ?>">&#128101; Siswa &amp; Kartu</a>
      <a href="<?= site_url('admin/bank') ?>" class="<?= in_array($seg, ['bank', 'soal'], true) ? 'on' : '' ?>">&#128218; Bank Soal</a>
      <div class="sep">Ujian</div>
      <a href="<?= site_url('admin/ujian') ?>" class="<?= $seg === 'ujian' ? 'on' : '' ?>">&#128221; Jadwal &amp; Hasil</a>
      <a href="<?= site_url('admin/absen') ?>" class="<?= $seg === 'absen' ? 'on' : '' ?>">&#128247; Absen Selfie</a>
      <div class="sep">Akun</div>
      <a href="<?= site_url('admin/akun') ?>" class="<?= $seg === 'akun' ? 'on' : '' ?>">&#128273; Ganti Password</a>
    </nav>
    <div class="foot">
      <div class="sekolah"><?= esc(cbt_sekolah()) ?></div>
      <?php if (cbt_moto() !== ''): ?><div class="moto"><?= esc(cbt_moto()) ?></div><?php endif ?>
      <a href="<?= site_url('admin/logout') ?>">Keluar &rarr;</a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar no-print">
      <button class="hamb" onclick="sb()" aria-label="Menu">&#9776;</button>
      <div class="title"><?= esc($title ?? 'Dashboard') ?></div>
      <div class="who">
        <b><?= esc(session('admin_nama')) ?></b>
        <span class="dim">Administrator</span>
      </div>
    </div>
    <div class="content">
      <?= flash_alerts() ?>
      <?= $this->renderSection('content') ?>
    </div>
  </div>
</div>

<script>
function sb(){document.getElementById('sb').classList.toggle('open');document.getElementById('veil').classList.toggle('on');}
function openModal(id){document.getElementById(id).classList.add('on');}
function closeModal(id){document.getElementById(id).classList.remove('on');}
document.addEventListener('click',function(e){
  if(e.target.classList.contains('modal-bg')) e.target.classList.remove('on');
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape') document.querySelectorAll('.modal-bg.on').forEach(m=>m.classList.remove('on'));
});
/* form dengan data-confirm minta konfirmasi sebelum submit (hapus/reset) */
document.addEventListener('submit',function(e){
  var m=e.target.getAttribute('data-confirm');
  if(m && !confirm(m)) e.preventDefault();
});
</script>
<?= $this->renderSection('js') ?>
</body>
</html>
