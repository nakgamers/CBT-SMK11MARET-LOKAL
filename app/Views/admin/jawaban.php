<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= esc($siswa['nama']) ?></h1>
    <p class="muted mb0">
      <span class="mono"><?= esc($siswa['nis']) ?></span> &middot; <?= esc($siswa['kelas']) ?> &middot;
      <?= esc($exam['nama']) ?>
    </p>
  </div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/ujian/hasil/' . $exam['id']) ?>">&larr; Daftar Nilai</a>
  </div>
</div>

<?php
    $selesai = $attempt['status'] === 'selesai';

    // poin bobot: dipakai di ringkasan agar guru bisa mengaudit angka nilainya
    $poin = $totalBobot = 0;
    foreach ($daftar as $d) {
        $totalBobot += (int) $d['bobot'];
        if ($d['benar'] === true) {
            $poin += (int) $d['bobot'];
        }
    }
?>

<div class="stats">
  <div class="stat"><div class="ic">&#127942;</div><div>
    <b><?= $selesai ? number_format((float) $attempt['skor'], 2, ',', '.') : '–' ?></b><span>Nilai</span>
  </div></div>
  <div class="stat"><div class="ic">&#9989;</div><div><b><?= (int) $attempt['benar'] ?></b><span>Benar</span></div></div>
  <div class="stat"><div class="ic">&#10060;</div><div><b><?= (int) $attempt['salah'] ?></b><span>Salah</span></div></div>
  <div class="stat"><div class="ic">&#9711;</div><div><b><?= (int) $attempt['kosong'] ?></b><span>Kosong</span></div></div>
</div>

<div class="card">
  <div class="card-head"><h2>Ringkasan Pengerjaan</h2></div>
  <div class="card-body">
    <table class="tbl tbl-kv small">
      <tr><th style="width:34%">Ujian</th><td><?= esc($exam['nama']) ?> &middot; <?= esc($exam['bank_mapel'] ?? '-') ?></td></tr>
      <tr><th>Mulai mengerjakan</th><td><?= tgl_id($attempt['started_at']) ?></td></tr>
      <tr><th>Batas waktu peserta</th><td><?= tgl_id($attempt['deadline_at']) ?></td></tr>
      <tr><th>Dikumpulkan</th><td>
        <?= $attempt['submitted_at'] ? tgl_id($attempt['submitted_at']) : '<span class="dim">belum dikumpulkan</span>' ?>
      </td></tr>
      <tr><th>Skor mentah</th><td>
        <?= (int) $attempt['benar'] ?> benar dari <?= count($daftar) ?> soal
        <?php if ($totalBobot > 0): ?>
          &middot; <?= $poin ?>/<?= $totalBobot ?> poin bobot
        <?php endif ?>
      </td></tr>
    </table>
  </div>
</div>

<?php if (! $selesai): ?>
  <div class="alert alert-warn">
    Siswa ini <b>masih mengerjakan</b>. Jawaban di bawah adalah kondisi tersimpan
    saat ini dan status benar/salah dihitung sementara &mdash; nilai resmi baru
    ditetapkan setelah dikumpulkan.
  </div>
<?php endif ?>

<div class="card">
  <div class="card-head">
    <h2>Jawaban per Nomor</h2>
    <span class="small dim"><?= count($daftar) ?> soal &middot; urutan sesuai yang dilihat siswa</span>
  </div>

  <?php if ($daftar === []): ?>
    <div class="empty">Tidak ada soal yang tercatat untuk attempt ini.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr>
          <th class="center">No</th>
          <th>Soal</th>
          <th class="center">Jawaban</th>
          <th class="center">Kunci</th>
          <th class="center">Hasil</th>
          <th class="center">Bobot</th>
        </tr></thead>
        <tbody>
        <?php foreach ($daftar as $d): ?>
          <tr>
            <td class="center"><b><?= (int) $d['no'] ?></b></td>
            <td style="min-width:300px">
              <div class="small"><?= esc(mb_strimwidth(trim(preg_replace('/\s+/', ' ', $d['teks'])), 0, 120, '…')) ?></div>
              <div class="opsi-mini">
                <?php foreach (\App\Models\QuestionModel::OPSI as $k): ?>
                  <?php
                      $isiOpsi = (string) ($d['opsi_' . strtolower($k)] ?? '');
                      $kelas   = 'o';
                      if ($k === $d['kunci']) {
                          $kelas .= ' kunci';
                      }
                      if ($d['jawaban'] !== null && strtoupper((string) $d['jawaban']) === $k) {
                          $kelas .= ' dipilih';
                      }
                  ?>
                  <span class="<?= $kelas ?>"><b><?= $k ?>.</b> <?= esc(mb_strimwidth($isiOpsi, 0, 24, '…')) ?></span>
                <?php endforeach ?>
              </div>
              <?php if ($d['ragu']): ?><span class="badge badge-belum">ditandai ragu</span><?php endif ?>
            </td>
            <td class="center">
              <?php if ($d['jawaban'] === null || $d['jawaban'] === ''): ?>
                <span class="dim" title="Tidak dijawab">–</span>
              <?php else: ?>
                <span class="mono"><b><?= esc(strtoupper($d['jawaban'])) ?></b></span>
              <?php endif ?>
            </td>
            <td class="center mono"><?= esc($d['kunci']) ?></td>
            <td class="center">
              <?php if ($d['benar'] === null): ?>
                <span class="badge badge-nonaktif">&#9711; Kosong</span>
              <?php elseif ($d['benar']): ?>
                <span class="badge badge-berlangsung">&#10003; Benar</span>
              <?php else: ?>
                <span class="badge badge-lewat">&#10007; Salah</span>
              <?php endif ?>
            </td>
            <td class="center dim"><?= (int) $d['bobot'] ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>

<?= $this->endSection() ?>
