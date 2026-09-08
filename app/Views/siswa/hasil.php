<?= $this->extend('layout/student') ?>

<?= $this->section('content') ?>

<div class="card" style="max-width:720px;margin:0 auto">
  <div class="card-head"><h2>Hasil Ujian</h2><span class="badge badge-selesai">Selesai</span></div>
  <div class="card-body">
    <div class="score-hero">
      <div class="small muted"><?= esc($exam['nama']) ?> &middot; <?= esc($exam['bank_mapel'] ?? '-') ?></div>
      <div class="n"><?= number_format((float) $attempt['skor'], 2, ',', '.') ?></div>
      <div class="small muted">Nilai akhir (skala 100)</div>
      <div class="score-pills">
        <span class="pill ok">Benar <?= (int) $attempt['benar'] ?></span>
        <span class="pill bad">Salah <?= (int) $attempt['salah'] ?></span>
        <span class="pill">Kosong <?= (int) $attempt['kosong'] ?></span>
      </div>
    </div>

    <table class="tbl tbl-kv small">
      <tr><th style="width:40%">Nama</th><td><?= esc($siswa['nama']) ?> (<?= esc($siswa['nis']) ?>)</td></tr>
      <tr><th>Kelas</th><td><?= esc($siswa['kelas']) ?></td></tr>
      <tr><th>Mulai</th><td><?= tgl_id($attempt['started_at']) ?></td></tr>
      <tr><th>Dikumpulkan</th><td><?= tgl_id($attempt['submitted_at']) ?></td></tr>
    </table>

    <div class="btn-row mt">
      <a class="btn btn-ghost" href="<?= site_url('siswa') ?>">&larr; Daftar Ujian</a>
      <button class="btn btn-ghost no-print" onclick="window.print()">Cetak</button>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
