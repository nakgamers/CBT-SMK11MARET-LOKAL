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
      <div style="margin-bottom:.7rem"><?= cbt_render_soal($q['teks']) ?></div>
      <?php if (! empty($q['gambar'])): ?>
        <img class="q-img" style="max-height:180px" src="<?= base_url('uploads/soal/' . $q['gambar']) ?>" alt="Gambar soal <?= $i + 1 ?>">
      <?php endif ?>
      <?php foreach (['A', 'B', 'C', 'D', 'E'] as $k):
          $isi = $q['opsi_' . strtolower($k)];
          if ($isi === null || $isi === '') { continue; }
      ?>
        <div class="opt<?= strtoupper((string) $q['kunci']) === $k ? ' benar' : '' ?>" style="cursor:default;padding:.45rem .7rem;margin-bottom:.35rem">
          <span class="k"><?= $k ?></span><span><?= cbt_render_soal($isi) ?></span>
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
        <div class="field">
          <label for="q_teks">Pertanyaan</label>
          <textarea id="q_teks" name="teks" required rows="3"></textarea>
          <div class="rumus-bar">
            <span class="small muted">Bantu rumus:</span>
            <button type="button" class="btn btn-ghost btn-sm" data-sisip="sqrt(">&#8730; akar</button>
            <button type="button" class="btn btn-ghost btn-sm" data-sisip="^2">x&sup2; pangkat</button>
            <button type="button" class="btn btn-ghost btn-sm" data-sisip="_1">x&#8321; subscript</button>
            <button type="button" class="btn btn-ghost btn-sm" data-sisip="(/)">pecahan</button>
          </div>
          <div class="hint">
            Tulis rumus dengan notasi natural: <b>sqrt(x+4)</b>, <b>x^2</b>, <b>x_1</b>,
            <b>(5x-1)/(x+4)</b>. Pecahan dengan tanda kurung otomatis ditumpuk.
          </div>
          <div class="rumus-preview" id="prevTeks"></div>
        </div>
        <div class="alert alert-info small">Pilihan ganda A&ndash;E: kelima opsi wajib diisi.</div>
        <div class="grid-2">
          <div class="field"><label for="q_a">Opsi A</label><textarea id="q_a" name="opsi_a" required rows="2"></textarea><div class="rumus-preview" id="prevA"></div></div>
          <div class="field"><label for="q_b">Opsi B</label><textarea id="q_b" name="opsi_b" required rows="2"></textarea><div class="rumus-preview" id="prevB"></div></div>
          <div class="field"><label for="q_c">Opsi C</label><textarea id="q_c" name="opsi_c" required rows="2"></textarea><div class="rumus-preview" id="prevC"></div></div>
          <div class="field"><label for="q_d">Opsi D</label><textarea id="q_d" name="opsi_d" required rows="2"></textarea><div class="rumus-preview" id="prevD"></div></div>
          <div class="field"><label for="q_e">Opsi E</label><textarea id="q_e" name="opsi_e" required rows="2"></textarea><div class="rumus-preview" id="prevE"></div></div>
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
  perbaruiSemuaPreview();
  openModal('mSoal');
}

/* ---- render rumus: versi JS dari cbt_rumus_html() PHP ----
   Preview ditarik dari textarea (sumber terpercaya), lalu di-escape
   di sini sebelum dipakai untuk membangun tag rumus. */
function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
function renderRumus(teks) {
  let t = escapeHtml(teks);
  // akar
  t = t.replace(/sqrt\(([^()]*)\)/g, (_, isi) =>
    '<span class="mtk-akar">&#8730;<span class="mtk-akar-isi">' + isi + '</span></span>');
  // pangkat
  t = t.replace(/\^(\([^()]*\)|[0-9A-Za-z.+-]+)/g, (_, isi) => {
    if (isi[0] === '(') isi = isi.slice(1, -1);
    return '<sup>' + isi + '</sup>';
  });
  // subscript
  t = t.replace(/_(\([^()]*\)|[0-9A-Za-z.+-]+)/g, (_, isi) => {
    if (isi[0] === '(') isi = isi.slice(1, -1);
    return '<sub>' + isi + '</sub>';
  });
  // pecahan tumpuk
  t = t.replace(/\(([^()]*)\)\/\(([^()]*)\)/g, (_, atas, bawah) =>
    '<span class="mtk-frac"><span class="mtk-atas">' + atas + '</span><span class="mtk-bawah">' + bawah + '</span></span>');
  // simbol
  t = t
    .replace(/&lt;=/g, '&le;').replace(/&gt;=/g, '&ge;')
    .replace(/&lt;&gt;/g, '&ne;').replace(/!=/g, '&ne;')
    .replace(/\+-/g, '&plusmn;').replace(/\*/g, '&times;')
    .replace(/-&gt;/g, '&rarr;');
  // baris baru
  return t.replace(/\n/g, '<br>');
}

/* pasang preview untuk satu textarea */
function pasangPreview(textareaId, previewId) {
  const ta = document.getElementById(textareaId);
  const pv = document.getElementById(previewId);
  if (!ta || !pv) return;
  const perbarui = () => {
    const isi = ta.value.trim();
    pv.innerHTML = isi === '' ? '' : '<span class="small muted">Tampilan siswa:</span> ' + renderRumus(isi);
    pv.style.display = isi === '' ? 'none' : '';
  };
  ta.addEventListener('input', perbarui);
  perbarui();
}

function perbaruiSemuaPreview() {
  const pasangan = [['q_teks','prevTeks'],['q_a','prevA'],['q_b','prevB'],['q_c','prevC'],['q_d','prevD'],['q_e','prevE']];
  pasangan.forEach(([ta, pv]) => {
    const el = document.getElementById(pv);
    if (el) {
      const sumber = document.getElementById(ta);
      el.innerHTML = sumber && sumber.value.trim() !== ''
        ? '<span class="small muted">Tampilan siswa:</span> ' + renderRumus(sumber.value)
        : '';
      el.style.display = sumber && sumber.value.trim() !== '' ? '' : 'none';
    }
  });
}

/* tombol bantu rumus: sisipkan notasi di posisi kursor */
document.querySelectorAll('[data-sisip]').forEach(btn => {
  btn.addEventListener('click', () => {
    const tujuan = btn.closest('.field').querySelector('textarea');
    if (!tujuan) return;
    const nama = btn.dataset.sisip;
    const mulai = tujuan.selectionStart ?? tujuan.value.length;
    const akhir = tujuan.selectionEnd ?? tujuan.value.length;
    let sisip = nama;
    // "(/)" -> ( pembilang )/( penyebut ) posisi siap diketik
    if (sisip === '(/)') sisip = '()/()';
    tujuan.value = tujuan.value.slice(0, mulai) + sisip + tujuan.value.slice(akhir);
    // letakkan kursor di tempat yang masuk akal
    const pos = sisip === '()/()' ? mulai + 1 : mulai + sisip.length;
    tujuan.focus();
    tujuan.setSelectionRange(pos, pos);
    tujuan.dispatchEvent(new Event('input'));
  });
});

/* pasang preview saat halaman terbuka */
[['q_teks','prevTeks'],['q_a','prevA'],['q_b','prevB'],['q_c','prevC'],['q_d','prevD'],['q_e','prevE']]
  .forEach(([ta, pv]) => pasangPreview(ta, pv));
</script>
<?= $this->endSection() ?>
