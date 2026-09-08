<?= $this->extend('layout/student') ?>

<?= $this->section('content') ?>

<div class="card selfie-card">
  <div class="card-head"><h2>Absen Selfie</h2></div>
  <div class="card-body">
    <p class="muted" style="margin-top:0"><?= esc($exam['nama']) ?> &middot; <?= esc($siswa['nama']) ?> (<?= esc($siswa['nis']) ?>)</p>

    <?php if ($sudah): ?>
      <div class="alert alert-ok">Foto absen Anda sudah tersimpan. Silakan lanjut mengerjakan ujian.</div>
      <div class="selfie-preview">
        <img src="<?= site_url('siswa/absen/' . $exam['id'] . '/lihat/' . $sudah['thumb_path']) ?>" alt="Foto absen Anda">
      </div>
      <div class="btn-row">
        <a class="btn" href="<?= site_url('siswa/ujian/' . $exam['id']) ?>">Lanjut ke Ujian &rarr;</a>
        <button class="btn btn-ghost" type="button" id="btnUlang">Ambil Ulang</button>
      </div>
    <?php endif ?>

    <div id="areaKamera" <?= $sudah ? 'hidden' : '' ?>>
      <div class="alert alert-warn">
        Ambil foto wajah Anda menghadap kamera di tempat terang. Foto ini menjadi bukti kehadiran Anda pada ujian ini.
      </div>

      <canvas id="cv" hidden></canvas>
      <div class="btn-row" id="ctrlAwal">
        <label class="btn" for="fileAlt">&#128247; Ambil Foto Selfie</label>
      </div>
      <div class="btn-row" id="ctrlHasil" hidden>
        <button class="btn" type="button" id="btnKirim">Kirim &amp; Mulai Ujian</button>
        <label class="btn btn-ghost" for="fileAlt">Ambil Ulang</label>
      </div>

      <div class="selfie-preview" id="prev" hidden><img id="prevImg" alt="Pratinjau foto"></div>
      <p class="muted small" id="statusTxt"></p>

      <input type="file" id="fileAlt" accept="image/*" capture="user" hidden>
      <p class="muted small">Tombol ini akan membuka kamera HP secara langsung. Foto tidak dikirim sebelum Anda menekan tombol kirim.</p>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const examId   = <?= (int) $exam['id'] ?>;
  const urlUp    = '<?= site_url('siswa/absen/' . $exam['id'] . '/upload') ?>';
  const urlLanjut= '<?= site_url('siswa/kerjakan/' . $exam['id']) ?>' + <?= $tokenUjian !== '' ? json_encode('?token=' . rawurlencode($tokenUjian)) : '""' ?>;
  const MAX_EDGE = 1280, QUAL = 0.85;
  const CSRF = { name: <?= json_encode(csrf_token()) ?>, hash: <?= json_encode(csrf_hash()) ?> };

  const cv  = document.getElementById('cv'),
        prev= document.getElementById('prev'),
        prevImg = document.getElementById('prevImg'),
        statusTxt = document.getElementById('statusTxt'),
        btnKirim= document.getElementById('btnKirim'),
        area    = document.getElementById('areaKamera');
  let blob = null;
  const btnUlang = document.getElementById('btnUlang');
  if (btnUlang) {
    btnUlang.addEventListener('click', () => {
      area.hidden = false;
      const savedPreview = document.querySelector('.selfie-preview');
      if (savedPreview) savedPreview.hidden = true;
    });
  }

  // fallback: file dari galeri (sudah dikompres ulang via canvas juga)
  document.getElementById('fileAlt').addEventListener('change', function () {
    const f = this.files[0]; if (!f) return;
    const img = new Image();
    img.onload = () => {
      const r = Math.min(1, MAX_EDGE / Math.max(img.width, img.height));
      cv.width = Math.round(img.width * r); cv.height = Math.round(img.height * r);
      cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
      cv.toBlob(b => {
        blob = b; prevImg.src = URL.createObjectURL(b); prev.hidden = false;
        document.getElementById('ctrlAwal').hidden = true;
        document.getElementById('ctrlHasil').hidden = false;
        statusTxt.textContent = 'Ukuran terkompres: ' + Math.round(b.size / 1024) + ' KB';
      }, 'image/jpeg', QUAL);
      URL.revokeObjectURL(img.src);
    };
    img.src = URL.createObjectURL(f);
  });

  btnKirim.addEventListener('click', () => {
    if (!blob) return;
    btnKirim.disabled = true; statusTxt.textContent = 'Mengirim…';
    const fd = new FormData();
    fd.append('selfie', blob, 'selfie.jpg');
    fd.append(CSRF.name, CSRF.hash);
    fetch(urlUp, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(async r => {
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch (_) {
          throw new Error(r.status === 403 ? 'Sesi login sudah habis. Muat ulang halaman dan coba lagi.' : 'Respons server tidak valid (' + r.status + ').');
        }
        if (!r.ok || !d.ok) throw new Error(d.error || 'Upload ditolak server.');
        return d;
      })
      .then(d => {
        statusTxt.textContent = 'Tersimpan (' + d.kb + ' KB). Melanjutkan…';
        setTimeout(() => { location.href = urlLanjut; }, 600);
      })
      .catch(e => { btnKirim.disabled = false; statusTxt.textContent = 'Gagal: ' + e.message; });
  });

})();
</script>

<?= $this->endSection() ?>
