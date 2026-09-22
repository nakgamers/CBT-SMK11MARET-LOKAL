<?= $this->extend('layout/student') ?>

<?= $this->section('content') ?>

<div class="card" style="max-width:620px;margin:0 auto">
  <div class="card-head"><h2><?= esc($exam['nama']) ?></h2><?= badge_status('berlangsung') ?></div>
  <div class="card-body">
    <table class="tbl tbl-kv" style="font-size:.9rem">
      <tr><th style="width:42%">Mata Pelajaran</th><td><?= esc($exam['bank_mapel'] ?? '-') ?></td></tr>
      <tr><th>Jumlah Soal</th><td><?= (int) $jumlah ?> soal pilihan ganda</td></tr>
      <tr><th>Durasi</th><td><?= (int) $exam['durasi_menit'] ?> menit</td></tr>
      <tr><th>Rentang Waktu</th><td><span class="nowrap"><?= tgl_id($exam['mulai_at']) ?></span> &rarr; <span class="nowrap"><?= tgl_id($exam['selesai_at']) ?></span></td></tr>
      <tr><th>Peserta</th><td><?= esc($siswa['nama']) ?> (<?= esc($siswa['nis']) ?>)</td></tr>
    </table>

    <div class="alert alert-warn mt">
      Timer berjalan sejak Anda menekan <b>Mulai</b> dan <b>tidak berhenti</b> walau halaman ditutup.
      Jawaban tersimpan otomatis setiap kali Anda memilih opsi.
    </div>

    <form method="get" action="<?= site_url('siswa/kerjakan/' . $exam['id']) ?>">
      <?php if (! empty($exam['token'])): ?>
        <div class="field">
          <label for="token">Token Ujian</label>
          <input type="text" id="token" name="token" required maxlength="10" autocomplete="off"
                 style="text-transform:uppercase;letter-spacing:.2em" placeholder="Diberikan pengawas">
        </div>
      <?php endif ?>

      <div class="pacta">
        <b>Pakta Integritas</b>
        <label class="check">
          <input type="checkbox" id="pacta" required>
          <span>Data di atas adalah benar, saya sebagai siswa/i <?= esc(cbt_sekolah()) ?> siap mengikuti ujian dengan jujur tanpa kecurangan.</span>
        </label>
      </div>

      <div class="btn-row btn-row-mulai">
        <button class="btn" type="submit">Mulai Ujian &rarr;</button>
        <a class="btn btn-ghost" href="<?= site_url('siswa') ?>">Kembali</a>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
