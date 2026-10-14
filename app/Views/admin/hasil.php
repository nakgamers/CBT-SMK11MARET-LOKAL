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
      <form method="post" action="<?= site_url('admin/ujian/reset-all/' . $exam['id']) ?>" style="display:inline"
            data-confirm="Reset SEMUA peserta? Seluruh jawaban ujian ini akan dihapus dan semua siswa bisa mengerjakan ulang.">
        <?= csrf_field() ?>
        <button class="btn btn-danger btn-sm" type="submit">&#x21BA; Reset Semua</button>
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

<?php if (!empty($difinalisasi)): ?><div class="alert alert-success"><?= (int)$difinalisasi ?> attempt kedaluwarsa otomatis difinalisasi.</div><?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>Daftar Nilai</h2>
    <span class="small dim"><?= count($hasil) ?> peserta</span>
    <button id="btnResetMassal" class="btn btn-danger btn-sm" disabled>
      Reset (<span id="jmlCeklis">0</span>) terpilih
    </button>
    <input type="search" id="cariNilai" placeholder="Cari nama atau NIS…" aria-label="Cari siswa"
           class="search-in" autocomplete="off">
  </div>
  <?php if ($hasil === []): ?>
    <div class="empty">Belum ada siswa yang mengerjakan ujian ini.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tabelNilai">
        <thead><tr>
          <th style="width:36px"><input type="checkbox" id="cekSemua" aria-label="Pilih semua siswa"></th>
          <th>#</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Status</th>
          <th class="center">B</th><th class="center">S</th><th class="center">K</th>
          <th class="center sortable" id="sortNilai" title="Klik untuk urutkan nilai">Nilai<span class="sort-ind"></span></th><th>Dikumpulkan</th><th class="act"><span class="sr-only">Aksi</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($hasil as $i => $h): ?>
          <tr data-nama="<?= esc($h['nama'], 'attr') ?>" data-nis="<?= esc($h['nis'], 'attr') ?>" data-skor="<?= $h['status'] === 'selesai' ? (float) $h['skor'] : -1 ?>">
            <td><input type="checkbox" class="cekSiswa" value="<?= (int) $h['student_id'] ?>"></td>
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
  const tabel = document.getElementById('tabelNilai');
  const cekSemua = document.getElementById('cekSemua');
  const btnReset = document.getElementById('btnResetMassal');
  const jmlCeklis = document.getElementById('jmlCeklis');

  function getBaris() {
    return tabel ? Array.from(tabel.querySelectorAll('tbody tr')) : [];
  }

  function getVisibleCek() {
    return getBaris()
      .filter(tr => tr.style.display !== 'none')
      .map(tr => tr.querySelector('.cekSiswa'))
      .filter(Boolean);
  }

  function updateCekSemua() {
    if (!cekSemua) return;
    const vis = getVisibleCek();
    cekSemua.checked = vis.length > 0 && vis.every(cb => cb.checked);
  }

  function updateStatus() {
    const checked = document.querySelectorAll('.cekSiswa:checked');
    const n = checked.length;
    if (jmlCeklis) jmlCeklis.textContent = n;
    if (btnReset) btnReset.disabled = n === 0;
    updateCekSemua();
  }

  if (cari && tabel) {
    const kosong = document.createElement('div');
    kosong.className = 'empty';
    kosong.textContent = 'Tidak ada siswa yang cocok.';
    kosong.style.display = 'none';
    cari.closest('.card').querySelector('.table-wrap').prepend(kosong);

    cari.addEventListener('input', () => {
      const q = cari.value.trim().toLowerCase();
      let tampil = 0;
      getBaris().forEach(tr => {
        const cocok = q === '' || tr.dataset.nama.toLowerCase().includes(q) || tr.dataset.nis.toLowerCase().includes(q);
        tr.style.display = cocok ? '' : 'none';
        if (cocok) tampil++;
      });
      kosong.style.display = tampil === 0 ? 'block' : 'none';
      updateCekSemua();
    });
  }

  const thNilai = document.getElementById('sortNilai');
  const tbody = document.querySelector('#tabelNilai tbody');
  let arah = 0; // 0 = belum sort, 1 = desc, -1 = asc
  if (thNilai && tbody) {
    const ind = thNilai.querySelector('.sort-ind');
    thNilai.addEventListener('click', () => {
      arah = arah === 1 ? -1 : 1;
      const rows = Array.from(tbody.querySelectorAll('tr'));
      rows.sort((a, b) => {
        const sa = parseFloat(a.dataset.skor), sb = parseFloat(b.dataset.skor);
        const aBlm = sa < 0, bBlm = sb < 0;
        if (aBlm && bBlm) return 0;
        if (aBlm) return 1;   // belum selesai selalu di bawah
        if (bBlm) return -1;
        return arah === 1 ? sb - sa : sa - sb;
      });
      rows.forEach((tr, i) => {
        tbody.appendChild(tr);
        const no = tr.querySelector('.cekSiswa') ? tr.children[1] : tr.querySelector('td:first-child');
        if (no) no.textContent = i + 1;
      });
      if (ind) ind.textContent = arah === 1 ? '▼' : '▲';
      if (cari) cari.dispatchEvent(new Event('input')); // terapkan ulang filter cari
    });
  }

  if (cekSemua) {
    cekSemua.addEventListener('change', () => {
      getVisibleCek().forEach(cb => { cb.checked = cekSemua.checked; });
      updateStatus();
    });
  }

  if (tabel) {
    tabel.addEventListener('change', e => {
      if (e.target.classList.contains('cekSiswa')) {
        updateStatus();
      }
    });
  }

  if (btnReset) {
    btnReset.addEventListener('click', () => {
      const checked = Array.from(document.querySelectorAll('.cekSiswa:checked'));
      if (checked.length === 0) return;
      if (!confirm('Reset ' + checked.length + ' siswa? Jawaban lama akan hilang dan mereka bisa mengerjakan ulang.')) {
        return;
      }
      const form = document.createElement('form');
      form.method = 'post';
      form.action = '<?= site_url('admin/ujian/reset/' . $exam['id']) ?>';
      form.style.display = 'none';

      const csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = '<?= csrf_token() ?>';
      csrf.value = document.querySelector('input[name="<?= csrf_token() ?>"]')?.value || '<?= csrf_hash() ?>';
      form.appendChild(csrf);

      checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'student_id[]';
        input.value = cb.value;
        form.appendChild(input);
      });

      document.body.appendChild(form);
      form.submit();
    });
  }
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
