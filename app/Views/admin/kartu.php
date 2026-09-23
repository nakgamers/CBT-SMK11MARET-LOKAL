<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kartu Login Siswa</title>
<link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
<link rel="stylesheet" href="<?= cbt_css_url() ?>">
</head>
<body style="background:#fff">
<div style="max-width:900px;margin:0 auto;padding:1rem">

  <div class="no-print" style="margin-bottom:1rem">
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
      <div style="flex:1">
        <h1 style="margin:0">Kartu Login Siswa</h1>
        <span class="small muted">
          <?= count($siswa) ?> kartu<?= $kelas !== '' ? ' — kelas ' . esc($kelas) : ' (semua kelas)' ?>.
          Cetak lalu potong per kotak.
        </span>
      </div>
      <button class="btn" onclick="window.print()">&#128424; Cetak</button>
      <a class="btn btn-ghost" href="<?= site_url('admin/siswa') ?>">Kembali</a>
    </div>

    <form method="get" class="btn-row mt">
      <select name="kelas" style="max-width:200px">
        <option value="">Semua kelas</option>
        <?php foreach ($daftarKelas as $k): ?>
          <option value="<?= esc($k, 'attr') ?>" <?= $kelas === $k ? 'selected' : '' ?>><?= esc($k) ?></option>
        <?php endforeach ?>
      </select>
      <button class="btn btn-ghost btn-sm" type="submit">Tampilkan</button>
    </form>

    <div class="alert alert-warn mt">
      Lembar ini memuat kredensial seluruh siswa — simpan seperti dokumen ujian.
      Alamat yang tercetak: <b class="mono"><?= esc(cbt_url_login()) ?></b>.
      Bila siswa mengakses dari jaringan lab, buka halaman ini lewat IP server
      (mis. <span class="mono">http://192.168.1.10:8145/admin/siswa/kartu</span>) agar
      alamat pada kartu ikut memakai IP tersebut, atau set <span class="mono">cbt.alamatLogin</span> di <span class="mono">.env</span>.
    </div>
  </div>

  <?php if ($siswa === []): ?>
    <div class="empty">Tidak ada siswa aktif untuk dicetak.</div>
  <?php endif ?>

  <div class="cards-sheet">
    <?php foreach ($siswa as $s): ?>
      <div class="login-card">
        <div class="lc-head">
          <?php if (cbt_logo('192') !== ''): ?>
            <img class="lg-img" src="<?= cbt_logo('192') ?>" alt="">
          <?php else: ?>
            <div class="lg"><?= esc(mb_substr(cbt_sekolah(), 0, 1)) ?></div>
          <?php endif ?>
          <div>
            <b><?= esc(cbt_sekolah()) ?></b>
            <span>Kartu Login Ujian</span>
          </div>
        </div>
        <div class="row"><span>Nama</span><b><?= esc($s['nama']) ?></b></div>
        <div class="row"><span>NIS</span><b class="mono"><?= esc($s['nis']) ?></b></div>
        <div class="row"><span>Kelas</span><b><?= esc($s['kelas']) ?></b></div>
        <div class="tok">
          <span>Password</span>
          <b><?= esc($s['token']) ?></b>
        </div>
        <div class="cara">Buka alamat di bawah, isi <b>NIS</b> lalu <b>Password</b>:</div>
        <div class="url mono"><?= esc(cbt_url_login()) ?></div>
      </div>
    <?php endforeach ?>
  </div>
</div>
</body>
</html>
