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
    <form id="formSoalEditor" method="post" action="<?= site_url('admin/soal/' . $bank['id'] . '/simpan') ?>"
          data-upload-url="<?= site_url('admin/soal/' . $bank['id'] . '/upload-inline') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="q_id">
      <input type="hidden" name="_format" value="rich-v1">
      <div class="modal-head"><h3 id="q_judul">Tambah Soal</h3><button type="button" onclick="closeModal('mSoal')">&times;</button></div>
      <div class="modal-body">
        <div class="alert alert-info small">
          Tempel langsung dari Word. Teks, pangkat, tabel, dan gambar rumus akan dipertahankan serta disimpan lokal.
          Gambar yang gagal dibaca akan diberi peringatan—jangan simpan sebelum diperbaiki.
        </div>

        <div class="field rich-field" data-value="q_teks">
          <label for="q_teks_editor">Pertanyaan</label>
          <textarea id="q_teks" name="teks" class="rich-value" hidden></textarea>
          <div class="rich-toolbar" role="toolbar" aria-label="Format pertanyaan">
            <button type="button" class="btn btn-ghost btn-sm" data-rich-cmd="bold" title="Tebal"><b>B</b></button>
            <button type="button" class="btn btn-ghost btn-sm" data-rich-cmd="italic" title="Miring"><i>I</i></button>
            <button type="button" class="btn btn-ghost btn-sm" data-rich-cmd="superscript" title="Pangkat">x<sup>2</sup></button>
            <button type="button" class="btn btn-ghost btn-sm" data-rich-cmd="subscript" title="Subscript">x<sub>1</sub></button>
            <button type="button" class="btn btn-ghost btn-sm" data-rich-insert="sqrt()">&#8730; akar</button>
            <button type="button" class="btn btn-ghost btn-sm" data-rich-insert="()/()">pecahan</button>
            <button type="button" class="btn btn-ghost btn-sm" data-rich-image>&#128247; gambar</button>
            <input type="file" class="rich-file" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
          </div>
          <div id="q_teks_editor" class="rich-editor" contenteditable="true" role="textbox" aria-multiline="true"
               data-placeholder="Ketik atau tempel pertanyaan dari Word..."></div>
          <div class="rich-status small" aria-live="polite"></div>
          <div class="hint">Notasi natural tetap didukung: <b>sqrt(x+4)</b>, <b>x^2</b>, <b>x_1</b>, <b>(5x-1)/(x+4)</b>.</div>
        </div>

        <div class="alert alert-info small">Pilihan ganda A&ndash;E: kelima opsi wajib diisi.</div>
        <div class="grid-2 rich-options">
          <?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'] as $huruf => $label): ?>
            <div class="field rich-field" data-value="q_<?= $huruf ?>">
              <label for="q_<?= $huruf ?>_editor">Opsi <?= $label ?></label>
              <textarea id="q_<?= $huruf ?>" name="opsi_<?= $huruf ?>" class="rich-value" hidden></textarea>
              <div class="rich-toolbar compact" role="toolbar" aria-label="Format opsi <?= $label ?>">
                <button type="button" class="btn btn-ghost btn-sm" data-rich-cmd="bold" title="Tebal"><b>B</b></button>
                <button type="button" class="btn btn-ghost btn-sm" data-rich-cmd="superscript" title="Pangkat">x<sup>2</sup></button>
                <button type="button" class="btn btn-ghost btn-sm" data-rich-cmd="subscript" title="Subscript">x<sub>1</sub></button>
                <button type="button" class="btn btn-ghost btn-sm" data-rich-insert="sqrt()">&#8730;</button>
                <button type="button" class="btn btn-ghost btn-sm" data-rich-insert="()/()">a/b</button>
                <button type="button" class="btn btn-ghost btn-sm" data-rich-image>&#128247;</button>
                <input type="file" class="rich-file" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
              </div>
              <div id="q_<?= $huruf ?>_editor" class="rich-editor compact" contenteditable="true" role="textbox" aria-multiline="true"
                   data-placeholder="Ketik atau tempel opsi <?= $label ?>..."></div>
              <div class="rich-status small" aria-live="polite"></div>
            </div>
          <?php endforeach ?>
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
          <label for="q_gambar">Gambar utama (opsional)</label>
          <input type="file" id="q_gambar" name="gambar" accept="image/*">
          <div class="hint">Untuk gambar yang berada di tengah kalimat, tempel langsung ke editor atau gunakan tombol kamera.</div>
          <div class="hint" id="q_gambar_now"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" onclick="closeModal('mSoal')">Batal</button>
        <button class="btn" id="q_submit" type="submit">Simpan Soal</button>
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
const RICH_MARKER = '<!--CBT-RICH-V1-->';
const formEditor = document.getElementById('formSoalEditor');
let richPending = 0;
let richRange = null;

function nilaiEditor(raw) {
  raw = String(raw ?? '');
  if (raw.startsWith(RICH_MARKER)) return {html: raw.slice(RICH_MARKER.length), lama: false};
  const d = document.createElement('div');
  d.textContent = raw;
  return {html: d.innerHTML.replace(/\r?\n/g, '<br>'), lama: raw !== ''};
}

function setEditor(id, raw) {
  const field = document.getElementById(id).closest('.rich-field');
  const editor = field.querySelector('.rich-editor');
  const nilai = nilaiEditor(raw);
  editor.innerHTML = nilai.html;
  field.classList.toggle('dari-format-lama', nilai.lama);
  field.querySelector('.rich-status').textContent = nilai.lama
    ? 'Soal lama dibuka aman; formatnya tidak diubah kecuali kamu menyimpan edit ini.' : '';
  sinkronkan(field);
}

function formSoal(q) {
  const v = (id, val) => document.getElementById(id).value = val ?? '';
  richPending = 0;
  document.getElementById('q_judul').textContent = q ? 'Edit Soal' : 'Tambah Soal';
  v('q_id', q ? q.id : '');
  setEditor('q_teks', q ? q.teks : '');
  setEditor('q_a', q ? q.opsi_a : '');
  setEditor('q_b', q ? q.opsi_b : '');
  setEditor('q_c', q ? q.opsi_c : '');
  setEditor('q_d', q ? q.opsi_d : '');
  setEditor('q_e', q ? q.opsi_e : '');
  v('q_kunci', q ? q.kunci : 'A');
  v('q_bobot', q ? q.bobot : 1);
  document.getElementById('q_gambar').value = '';
  document.getElementById('q_gambar_now').textContent = q && q.gambar ? 'Gambar saat ini: ' + q.gambar + ' (unggah baru untuk mengganti)' : '';
  openModal('mSoal');
}

function sinkronkan(field) {
  field.querySelector('.rich-value').value = field.querySelector('.rich-editor').innerHTML.trim();
}

function simpanRange(editor) {
  const sel = window.getSelection();
  if (sel && sel.rangeCount && editor.contains(sel.anchorNode)) richRange = sel.getRangeAt(0).cloneRange();
}

function pakaiRange(editor) {
  editor.focus();
  const sel = window.getSelection();
  if (richRange && editor.contains(richRange.commonAncestorContainer)) {
    sel.removeAllRanges();
    sel.addRange(richRange);
    return;
  }
  const range = document.createRange();
  range.selectNodeContents(editor);
  range.collapse(false);
  sel.removeAllRanges();
  sel.addRange(range);
  richRange = range.cloneRange();
}

function sisipNode(editor, node) {
  pakaiRange(editor);
  const sel = window.getSelection();
  const range = sel && sel.rangeCount ? sel.getRangeAt(0) : document.createRange();
  if (!sel || !sel.rangeCount) {
    range.selectNodeContents(editor);
    range.collapse(false);
  }
  const akhirNode = node.nodeType === Node.DOCUMENT_FRAGMENT_NODE ? node.lastChild : node;
  range.deleteContents();
  range.insertNode(node);
  if (akhirNode?.parentNode) range.setStartAfter(akhirNode);
  range.collapse(true);
  sel.removeAllRanges();
  sel.addRange(range);
  richRange = range.cloneRange();
  sinkronkan(editor.closest('.rich-field'));
}

function richStatus(field, pesan, gagal = false) {
  const el = field.querySelector('.rich-status');
  el.textContent = pesan;
  el.classList.toggle('error', gagal);
}

async function uploadGambar(file, field, placeholder) {
  if (!file || !/^image\/(png|jpeg|gif|webp)$/i.test(file.type) || file.size > 2 * 1024 * 1024) {
    const gagal = document.createElement('span');
    gagal.className = 'rich-missing-image';
    gagal.textContent = '[Gambar ditolak: gunakan PNG/JPG/GIF/WebP maksimal 2 MB]';
    placeholder.replaceWith(gagal);
    richStatus(field, 'Ada gambar yang ditolak. Perbaiki sebelum menyimpan.', true);
    return;
  }
  richPending++;
  richStatus(field, 'Mengunggah gambar rumus…');
  const data = new FormData();
  data.append('gambar', file, file.name || 'rumus.png');
  data.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
  try {
    const r = await fetch(formEditor.dataset.uploadUrl, {method: 'POST', body: data, credentials: 'same-origin'});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.error || 'Upload gagal');
    const img = document.createElement('img');
    img.src = j.path;
    img.alt = 'Rumus';
    img.className = 'rich-inline-image';
    placeholder.replaceWith(img);
    richStatus(field, 'Gambar rumus tersimpan lokal.');
  } catch (e) {
    const gagal = document.createElement('span');
    gagal.className = 'rich-missing-image';
    gagal.textContent = '[Gambar gagal diunggah]';
    placeholder.replaceWith(gagal);
    richStatus(field, e.message || 'Gambar gagal diunggah.', true);
  } finally {
    richPending--;
    sinkronkan(field);
  }
}

function placeholderGambar(editor, teks = 'Mengunggah gambar…') {
  const span = document.createElement('span');
  span.className = 'rich-uploading';
  span.textContent = '[' + teks + ']';
  sisipNode(editor, span);
  return span;
}

function bersihkanHtmlClipboard(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const boleh = new Set(['P','DIV','BR','STRONG','B','EM','I','U','S','SUP','SUB','UL','OL','LI','TABLE','THEAD','TBODY','TFOOT','TR','TD','TH','IMG','SPAN']);
  const buang = new Set(['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','FORM','INPUT','BUTTON','TEXTAREA','SELECT','OPTION','LINK','META','BASE','SVG','MATH','CANVAS','VIDEO','AUDIO']);
  doc.body.querySelectorAll('*').forEach(el => {
    if (buang.has(el.tagName)) { el.remove(); return; }
    // VML Word memakai v:imagedata. Ubah menjadi IMG agar kegagalannya terlihat.
    if (el.tagName.toLowerCase().includes('imagedata')) {
      const img = doc.createElement('img');
      img.src = el.getAttribute('src') || '';
      el.replaceWith(img);
      el = img;
    }
    if (!boleh.has(el.tagName)) { el.replaceWith(...el.childNodes); return; }
    const src = el.tagName === 'IMG' ? (el.getAttribute('src') || '') : '';
    const alt = el.tagName === 'IMG' ? (el.getAttribute('alt') || 'Rumus') : '';
    [...el.attributes].forEach(a => el.removeAttribute(a.name));
    if (el.tagName === 'IMG') { el.setAttribute('src', src); el.setAttribute('alt', alt); }
  });
  return doc.body;
}

function gambarDariRtf(rtf) {
  const hasil = [];
  const blok = String(rtf || '').match(/\{\\pict[\s\S]*?\}/gi) || [];
  blok.forEach(pict => {
    const png = /\\pngblip\b/i.test(pict);
    const jpg = /\\jpe?gblip\b/i.test(pict);
    if (!png && !jpg) return;
    const hex = pict
      .replace(/\\[a-z]+-?\d*\s?/gi, '')
      .replace(/[^0-9a-f]/gi, '');
    if (hex.length < 8 || hex.length % 2 || hex.length > 4 * 1024 * 1024) return;
    const bytes = new Uint8Array(hex.length / 2);
    for (let i = 0; i < hex.length; i += 2) bytes[i / 2] = parseInt(hex.slice(i, i + 2), 16);
    const benar = png
      ? bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47
      : bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff;
    if (benar) hasil.push({type: png ? 'image/png' : 'image/jpeg', bytes});
  });
  return hasil;
}

async function prosesPaste(e, editor) {
  e.preventDefault();
  const field = editor.closest('.rich-field');
  const cd = e.clipboardData;
  const html = cd.getData('text/html');
  const teks = cd.getData('text/plain');
  const files = [...cd.items].filter(i => i.kind === 'file' && i.type.startsWith('image/')).map(i => i.getAsFile()).filter(Boolean);
  const rtfFiles = gambarDariRtf(cd.getData('text/rtf')).map((g, i) =>
    new File([g.bytes], 'rumus-word-' + (i + 1) + (g.type === 'image/png' ? '.png' : '.jpg'), {type: g.type}));
  const clipboardFiles = files.length ? files : rtfFiles;
  const target = html ? bersihkanHtmlClipboard(html) : null;
  const gambar = target ? [...target.querySelectorAll('img')] : [];
  const upload = [];

  if (target) {
    gambar.forEach((img, i) => {
      const src = img.getAttribute('src') || '';
      const ph = document.createElement('span');
      ph.className = 'rich-uploading';
      ph.textContent = '[Memproses gambar rumus…]';
      img.replaceWith(ph);
      if (src.startsWith('data:image/')) {
        upload.push(fetch(src).then(r => r.blob()).then(b => uploadGambar(new File([b], 'rumus.png', {type: b.type}), field, ph)));
      } else if (src.startsWith('/uploads/soal-inline/')) {
        const aman = document.createElement('img'); aman.src = src; aman.alt = 'Rumus'; aman.className = 'rich-inline-image'; ph.replaceWith(aman);
      } else if (clipboardFiles[i]) {
        upload.push(uploadGambar(clipboardFiles[i], field, ph));
      } else {
        ph.textContent = '[Gambar rumus Word tidak terbaca—tempel gambarnya sendiri atau gunakan tombol kamera]';
        ph.className = 'rich-missing-image';
        richStatus(field, 'Word tidak memberikan data untuk sebagian gambar rumus.', true);
      }
    });
    const frag = document.createDocumentFragment();
    [...target.childNodes].forEach(n => frag.appendChild(n));
    sisipNode(editor, frag);
  } else if (clipboardFiles.length) {
    clipboardFiles.forEach(file => upload.push(uploadGambar(file, field, placeholderGambar(editor))));
  } else {
    sisipNode(editor, document.createTextNode(teks));
  }
  sinkronkan(field);
  await Promise.allSettled(upload);
}

document.querySelectorAll('.rich-field').forEach(field => {
  const editor = field.querySelector('.rich-editor');
  const file = field.querySelector('.rich-file');
  editor.addEventListener('input', () => sinkronkan(field));
  editor.addEventListener('keyup', () => simpanRange(editor));
  editor.addEventListener('mouseup', () => simpanRange(editor));
  editor.addEventListener('focus', () => simpanRange(editor));
  editor.addEventListener('paste', e => prosesPaste(e, editor));

  field.querySelectorAll('[data-rich-cmd]').forEach(btn => {
    btn.addEventListener('mousedown', e => e.preventDefault());
    btn.addEventListener('click', () => {
      pakaiRange(editor);
      document.execCommand(btn.dataset.richCmd, false);
      sinkronkan(field);
    });
  });
  field.querySelectorAll('[data-rich-insert]').forEach(btn => {
    btn.addEventListener('mousedown', e => e.preventDefault());
    btn.addEventListener('click', () => sisipNode(editor, document.createTextNode(btn.dataset.richInsert)));
  });
  field.querySelector('[data-rich-image]').addEventListener('click', () => file.click());
  file.addEventListener('change', () => {
    if (file.files[0]) uploadGambar(file.files[0], field, placeholderGambar(editor));
    file.value = '';
  });
});

formEditor.addEventListener('submit', e => {
  document.querySelectorAll('.rich-field').forEach(sinkronkan);
  const kosong = [...document.querySelectorAll('.rich-field')].find(field => {
    const ed = field.querySelector('.rich-editor');
    return ed.textContent.trim() === '' && !ed.querySelector('img');
  });
  const gagal = formEditor.querySelector('.rich-missing-image');
  if (richPending > 0 || gagal || kosong) {
    e.preventDefault();
    const pesan = richPending > 0 ? 'Tunggu sampai semua gambar selesai diunggah.'
      : gagal ? 'Ada gambar rumus yang gagal dibaca. Hapus tanda merah lalu tempel gambarnya sendiri atau gunakan tombol kamera.'
      : 'Pertanyaan dan semua opsi A–E wajib diisi.';
    if (window.Swal) Swal.fire({icon: 'warning', title: 'Belum bisa disimpan', text: pesan}); else alert(pesan);
    (kosong?.querySelector('.rich-editor'))?.focus();
  }
});
</script>
<?= $this->endSection() ?>
