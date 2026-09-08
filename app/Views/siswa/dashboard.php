<?= $this->extend('layout/student') ?>

<?= $this->section('content') ?>

<h1>Daftar Ujian</h1>
<p class="muted">Kelas <?= esc($siswa['kelas']) ?> &middot; <?= count($exams) ?> ujian terdaftar</p>

<?php if ($exams === []): ?>
  <div class="card"><div class="empty">
    <span class="big">&#128203;</span>
    Belum ada ujian untuk kelas Anda.
  </div></div>
<?php endif ?>

<?php foreach ($exams as $e):
    $st  = $e['status_waktu'];
    $att = $e['attempt'];
    $sudahSelesai = $att && $att['status'] === 'selesai';
?>
  <div class="exam-card">
    <div class="info">
      <h3><?= esc($e['nama']) ?></h3>
      <div class="muted small"><?= esc($e['bank_mapel'] ?? '-') ?><?= $e['bank_nama'] ? ' — ' . esc($e['bank_nama']) : '' ?></div>
      <div class="exam-meta">
        <span><span aria-hidden="true">&#128337;</span> <?= (int) $e['durasi_menit'] ?> menit</span>
        <span><span aria-hidden="true">&#128198;</span> <span class="nowrap"><?= tgl_id($e['mulai_at']) ?></span> &rarr; <span class="nowrap"><?= tgl_id($e['selesai_at']) ?></span></span>
        <?php if (! empty($e['token'])): ?><span><span aria-hidden="true">&#128273;</span> Perlu token</span><?php endif ?>
      </div>
    </div>
    <div class="aksi">
      <div>
        <?php if ($sudahSelesai): ?>
          <span class="badge badge-selesai">Sudah dikerjakan</span>
        <?php else: ?>
          <?= badge_status($st) ?>
        <?php endif ?>
      </div>

      <?php if ($sudahSelesai): ?>
        <?php if ((int) $e['tampilkan_hasil'] === 1): ?>
          <a class="btn btn-ghost btn-sm" href="<?= site_url('siswa/hasil/' . $e['id']) ?>">Lihat Hasil</a>
        <?php else: ?>
          <span class="small dim">Nilai diumumkan pengawas</span>
        <?php endif ?>
      <?php elseif ($st === 'berlangsung'): ?>
        <a class="btn" href="<?= site_url('siswa/ujian/' . $e['id']) ?>">
          <?= $att ? 'Lanjutkan' : 'Mulai' ?> &rarr;
        </a>
      <?php elseif ($st === 'belum'): ?>
        <button class="btn" disabled>Belum dibuka</button>
      <?php else: ?>
        <button class="btn" disabled>Ditutup</button>
      <?php endif ?>
    </div>
  </div>
<?php endforeach ?>

<?= $this->endSection() ?>
