<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="stats">
  <div class="stat"><div class="ic">&#128101;</div><div><b><?= $jumlah['siswa'] ?></b><span>Siswa terdaftar</span></div></div>
  <div class="stat"><div class="ic">&#128218;</div><div><b><?= $jumlah['soal'] ?></b><span>Soal di <?= $jumlah['bank'] ?> bank</span></div></div>
  <div class="stat"><div class="ic">&#128221;</div><div><b><?= $jumlah['ujian'] ?></b><span>Ujian dibuat</span></div></div>
  <div class="stat"><div class="ic">&#9203;</div><div><b><?= $jumlah['aktif'] ?></b><span>Sedang berlangsung</span></div></div>
  <div class="stat"><div class="ic">&#9989;</div><div><b><?= $jumlah['attempt'] ?></b><span>Ujian dikumpulkan</span></div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Ujian Terbaru</h2>
    <a class="btn btn-sm" href="<?= site_url('admin/ujian') ?>">Kelola Ujian</a>
  </div>
  <?php if ($terbaru === []): ?>
    <div class="empty">
      <span class="big">&#128203;</span>
      Belum ada ujian. Mulai dari <a href="<?= site_url('admin/bank') ?>">Bank Soal</a>, lalu buat jadwal ujian.
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr>
          <th>Ujian</th><th>Mapel</th><th>Jadwal</th><th>Status</th><th class="center">Peserta</th><th class="act"><span class="sr-only">Aksi</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($terbaru as $e): ?>
          <tr>
            <td><b><?= esc($e['nama']) ?></b><br><span class="small dim"><?= (int) $e['durasi_menit'] ?> menit</span></td>
            <td><?= esc($e['bank_mapel'] ?? '-') ?></td>
            <td class="small"><?= tgl_id($e['mulai_at']) ?><br><span class="dim">s/d <?= tgl_id($e['selesai_at']) ?></span></td>
            <td><?= badge_status($e['status_waktu']) ?></td>
            <td class="center"><?= (int) $e['jumlah_selesai'] ?>/<?= (int) $e['jumlah_peserta'] ?></td>
            <td class="act"><a class="btn btn-ghost btn-sm" href="<?= site_url('admin/ujian/hasil/' . $e['id']) ?>">Hasil</a></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>

<div class="grid-2">
  <div class="card"><div class="card-body">
    <h3>Alur pemakaian</h3>
    <ol class="small muted" style="padding-left:1.1rem;margin:.4rem 0 0">
      <li>Import daftar siswa dari Excel &rarr; cetak kartu login.</li>
      <li>Buat bank soal per mapel, upload soal manual atau import Excel.</li>
      <li>Buat ujian: pilih bank, kelas, durasi, dan rentang waktu.</li>
      <li>Saat jadwal aktif, siswa login pakai NIS + token kartu.</li>
      <li>Nilai muncul otomatis; export ke Excel bila perlu.</li>
    </ol>
  </div></div>
  <div class="card"><div class="card-body">
    <h3>Alamat login siswa</h3>
    <p class="small muted mb0">Bagikan tautan ini (atau cetak di kartu):</p>
    <p class="mono" style="background:#f8fafc;border:1px solid var(--line);border-radius:9px;padding:.55rem .7rem;word-break:break-all">
      <?= esc(cbt_url_login()) ?>
    </p>
    <p class="small dim mb0">Di jaringan lab, ganti <span class="mono">localhost</span> dengan IP komputer server ini.</p>
  </div></div>
</div>

<?= $this->endSection() ?>
