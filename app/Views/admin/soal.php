<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= esc($bank['nama']) ?></h1>
    <p class="muted mb0"><?= esc($bank['mapel']) ?> &middot; <?= count($soal) ?> soal</p>
  </div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/bank') ?>">&larr; Bank Soal</a>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/soal/template') ?>">&#11015; Template Excel</a>
    <button class="btn btn-ghost btn-sm" onclick="openModal('mImport')">&#128228; Import Excel</button>
    <button class="btn btn-sm" onclick="formSoal()">+ Tambah Soal</button>
  </div>
</div>

<?php if ($soal === []): ?>
  <div class="card"><div class="empty">
    <span class="big">&#128221;</span>
    Bank ini masih kosong. Tambah soal manual atau import dari Excel.
  </div></div>
<?php endif ?>

<?php foreach ($soal as $i => $q): ?>
  <div class="card">
    <div class="card-head">
      <h3>Soal <?= $i + 1 ?></h3>
      <span class="badge badge-selesai">Kunci: <?= esc($q['kunci']) ?></span>
      <span class="badge badge-nonaktif">Bobot <?= (int) $q['bobot'] ?></span>
      <button class="btn btn-ghost btn-sm" onclick='formSoal(<?= json_encode($q, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
      <form method="post" action="<?= site_url('admin/soal/' . $bank['id'] . '/hapus/' . $q['id']) ?>" style="display:inline"
            data-confirm="Hapus soal ini?">
        <?= csrf_field() ?>
        <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
      </form>
    </div>
    <div class="card-body">
      <div style="margin-bottom:.7rem"><?= nl2br(esc($q['teks'])) ?></div>
      <?php if (! empty($q['gambar'])): ?>
        <img class="q-img" style="max-height:180px" src="<?= base_url('uploads/soal/' . $q['gambar']) ?>" alt="Gambar soal <?= $i + 1 ?>">
      <?php endif ?>
      <?php foreach (['A', 'B', 'C', 'D', 'E'] as $k):
          $isi = $q['opsi_' . strtolower($k)];
          if ($isi === null || $isi === '') { continue; }
      ?>
        <div class="opt<?= strtoupper((string) $q['kunci']) === $k ? ' benar' : '' ?>" style="cursor:default;padding:.45rem .7rem;margin-bottom:.35rem">
          <span class="k"><?= $k ?></span><span><?= nl2br(esc($isi)) ?></span>
        </div>
      <?php endforeach ?>
    </div>
  </div>
<?php endforeach ?>

<!-- modal soal -->
<div class="modal-bg" id="mSoal">
  <div class="modal wide">
    <form method="post" action="<?= site_url('admin/soal/' . $bank['id'] . '/simpan') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="q_id">
      <div class="modal-head"><h3 id="q_judul">Tambah Soal</h3><button type="button" onclick="closeModal('mSoal')">&times;</button></div>
      <div class="modal-body">
        <div class="field"><label for="q_teks">Pertanyaan</label><textarea id="q_teks" name="teks" required rows="3"></textarea></div>
        <div class="alert alert-info small">Pilihan ganda A&ndash;E: kelima opsi wajib diisi.</div>
        <div class="grid-2">
          <div class="field"><label for="q_a">Opsi A</label><textarea id="q_a" name="opsi_a" required rows="2"></textarea></div>
          <div class="field"><label for="q_b">Opsi B</label><textarea id="q_b" name="opsi_b" required rows="2"></textarea></div>
          <div class="field"><label for="q_c">Opsi C</label><textarea id="q_c" name="opsi_c" required rows="2"></textarea></div>
          <div class="field"><label for="q_d">Opsi D</label><textarea id="q_d" name="opsi_d" required rows="2"></textarea></div>
          <div class="field"><label for="q_e">Opsi E</label><textarea id="q_e" name="opsi_e" required rows="2"></textarea></div>
          <div>
            <div class="field"><label for="q_kunci">Kunci Jawaban</label>
              <select id="q_kunci" name="kunci" required>
                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach ?>
              </select>
            </div>
            <div class="field"><label for="q_bobot">Bobot Nilai</label><input type="number" id="q_bobot" name="bobot" value="1" min="1" max="100"></div>
          </div>
        </div>
        <div class="field">
          <label for="q_gambar">Gambar (opsional)</label>
          <input type="file" id="q_gambar" name="gambar" accept="image/*">
          <div class="hint" id="q_gambar_now"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" onclick="closeModal('mSoal')">Batal</button>
        <button class="btn" type="submit">Simpan Soal</button>
      </div>
    </form>
  </div>
</div>

<!-- modal import -->
<div class="modal-bg" id="mImport">
  <div class="modal">
    <form method="post" action="<?= site_url('admin/soal/' . $bank['id'] . '/import') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-head"><h3>Import Soal dari Excel</h3><button type="button" onclick="closeModal('mImport')">&times;</button></div>
      <div class="modal-body">
        <div class="alert alert-info">
          Format file: satu baris <b>SOAL</b> diikuti lima baris <b>JAWABAN</b>.
          Kolom: <b>No, Jenis, Kode, Isi, Status Jawaban, Tingkat kesulitan Soal</b>.
          Isi <b>1</b> pada Status Jawaban untuk menandai jawaban benar. Satu soal harus memiliki tepat satu jawaban benar.
        </div>
        <div class="field">
          <label for="berkas">Berkas .xlsx / .xls / .csv</label>
          <input type="file" id="berkas" name="berkas" accept=".xlsx,.xls,.csv" required>
        </div>
        <a class="small" href="<?= site_url('admin/soal/template') ?>">Unduh template</a>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" onclick="closeModal('mImport')">Batal</button>
        <button class="btn" type="submit">Import</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
function formSoal(q) {
  const v = (id, val) => document.getElementById(id).value = val ?? '';
  document.getElementById('q_judul').textContent = q ? 'Edit Soal' : 'Tambah Soal';
  v('q_id', q ? q.id : '');
  v('q_teks', q ? q.teks : '');
  v('q_a', q ? q.opsi_a : '');
  v('q_b', q ? q.opsi_b : '');
  v('q_c', q ? q.opsi_c : '');
  v('q_d', q ? q.opsi_d : '');
  v('q_e', q ? q.opsi_e : '');
  v('q_kunci', q ? q.kunci : 'A');
  v('q_bobot', q ? q.bobot : 1);
  document.getElementById('q_gambar').value = '';
  document.getElementById('q_gambar_now').textContent = q && q.gambar ? 'Gambar saat ini: ' + q.gambar + ' (unggah baru untuk mengganti)' : '';
  openModal('mSoal');
}
</script>
<?= $this->endSection() ?>
