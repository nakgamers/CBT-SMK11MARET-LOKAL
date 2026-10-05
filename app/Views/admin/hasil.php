<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= esc($exam['nama']) ?></h1>
    <p class="muted mb0">
      <?= esc($exam['bank_mapel'] ?? '-') ?> &middot;
      <?= tgl_id($exam['mulai_at']) ?> &rarr; <?= tgl_id($exam['selesai_at']) ?> &middot;
      <?= badge_status(\App\Models\ExamModel::statusWaktu($exam)) ?>
    </p>
  </div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/ujian') ?>">&larr; Daftar Ujian</a>
    <?php if ($stat['selesai'] > 0): ?>
      <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/ujian/analisis/' . $exam['id']) ?>">&#128269; Analisis Butir</a>
    <?php else: ?>
      <button class="btn btn-ghost btn-sm" disabled title="Belum ada peserta yang selesai">&#128269; Analisis Butir</button>
    <?php endif ?>
    <?php if ($hasil === []): ?>
      <button class="btn btn-sm" disabled title="Belum ada peserta yang mengerjakan">&#11015; Export Excel</button>
    <?php else: ?>
      <form method="get" action="<?= site_url('admin/ujian/export/' . $exam['id']) ?>" class="btn-row">
        <select name="kelas" aria-label="Pilih rombel untuk export">
          <option value="">Semua rombel</option>
          <?php foreach ($daftarRombel as $rombel): ?>
            <option value="<?= esc($rombel, 'attr') ?>"><?= esc($rombel) ?></option>
          <?php endforeach ?>
        </select>
        <button class="btn btn-sm" type="submit">&#11015; Export Excel</button>
      </form>
    <?php endif ?>
  </div>
</div>

<?php
    // ujian tanpa peserta selesai: tampilkan '–', jangan 0,00 (mengesankan nilai nol)
    $n = static fn (float $v): string => $stat['selesai'] > 0 ? number_format($v, 2, ',', '.') : '–';
?>
<div class="stats">
  <div class="stat"><div class="ic">&#9989;</div><div><b><?= $stat['selesai'] ?></b><span>Selesai</span></div></div>
  <div class="stat"><div class="ic">&#9203;</div><div><b><?= $stat['proses'] ?></b><span>Masih mengerjakan</span></div></div>
  <div class="stat"><div class="ic">&#128202;</div><div><b><?= $n($stat['rata']) ?></b><span>Rata-rata nilai</span></div></div>
  <div class="stat"><div class="ic">&#128200;</div><div><b><?= $n($stat['max']) ?></b><span>Nilai tertinggi</span></div></div>
  <div class="stat"><div class="ic">&#128201;</div><div><b><?= $n($stat['min']) ?></b><span>Nilai terendah</span></div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Daftar Nilai</h2>
    <span class="small dim"><?= count($hasil) ?> peserta</span>
    <input type="search" id="cariNilai" placeholder="Cari nama atau NIS…" aria-label="Cari siswa"
           class="search-in" autocomplete="off">
  </div>
  <?php if ($hasil === []): ?>
    <div class="empty">Belum ada siswa yang mengerjakan ujian ini.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tabelNilai">
        <thead><tr>
          <th>#</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Status</th>
          <th class="center">B</th><th class="center">S</th><th class="center">K</th>
          <th class="center">Nilai</th><th>Dikumpulkan</th><th class="act"><span class="sr-only">Aksi</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($hasil as $i => $h): ?>
          <tr data-nama="<?= esc($h['nama'], 'attr') ?>" data-nis="<?= esc($h['nis'], 'attr') ?>">
            <td><?= $i + 1 ?></td>
            <td class="mono"><?= esc($h['nis']) ?></td>
            <td><b><?= esc($h['nama']) ?></b></td>
            <td><?= esc($h['kelas']) ?></td>
            <td>
              <?php if ($h['status'] === 'selesai'): ?>
                <span class="badge badge-selesai">Selesai</span>
              <?php else: ?>
                <span class="badge badge-berlangsung">Mengerjakan</span>
              <?php endif ?>
            </td>
            <td class="center" style="color:var(--ok)"><b><?= (int) $h['benar'] ?></b></td>
            <td class="center" style="color:var(--bad)"><?= (int) $h['salah'] ?></td>
            <td class="center dim"><?= (int) $h['kosong'] ?></td>
            <td class="center"><b><?= $h['status'] === 'selesai' ? number_format((float) $h['skor'], 2, ',', '.') : '-' ?></b></td>
            <td class="small"><?= $h['submitted_at'] ? tgl_id($h['submitted_at']) : '<span class="dim">-</span>' ?></td>
            <td class="act">
              <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/ujian/jawaban/' . $exam['id'] . '/' . (int) $h['student_id']) ?>">Jawaban</a>
              <form method="post" action="<?= site_url('admin/ujian/reset/' . $exam['id']) ?>" style="display:inline"
                    data-confirm="Reset ujian <?= esc($h['nama'], 'attr') ?>? Jawaban lama akan hilang dan siswa bisa mengerjakan ulang.">
                <?= csrf_field() ?>
                <input type="hidden" name="student_id" value="<?= (int) $h['student_id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Reset</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>

<?php $this->section('js') ?>
<script>
(function(){
  const cari = document.getElementById('cariNilai');
  if(!cari) return;
  const baris = document.querySelectorAll('#tabelNilai tbody tr');
  const kosong = document.createElement('div');
  kosong.className = 'empty';
  kosong.textContent = 'Tidak ada siswa yang cocok.';
  kosong.style.display = 'none';
  cari.closest('.card').querySelector('.table-wrap').prepend(kosong);

  cari.addEventListener('input', () => {
    const q = cari.value.trim().toLowerCase();
    let tampil = 0;
    baris.forEach(tr => {
      const cocok = q === '' || tr.dataset.nama.toLowerCase().includes(q) || tr.dataset.nis.toLowerCase().includes(q);
      tr.style.display = cocok ? '' : 'none';
      if (cocok) tampil++;
    });
    kosong.style.display = tampil === 0 ? 'block' : 'none';
  });
})();
</script>
<?php $this->endSection() ?>

<?php if ($absen !== []): ?>
  <div class="card">
    <div class="card-head"><h2>Belum Mengerjakan</h2><span class="small dim"><?= count($absen) ?> siswa</span></div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Password Kartu</th></tr></thead>
        <tbody>
        <?php foreach ($absen as $s): ?>
          <tr>
            <td class="mono"><?= esc($s['nis']) ?></td>
            <td><?= esc($s['nama']) ?></td>
            <td><?= esc($s['kelas']) ?></td>
            <td class="mono"><?= esc($s['token']) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
