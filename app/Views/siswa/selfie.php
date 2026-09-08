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

      <div class="selfie-stage">
        <video id="vid" autoplay playsinline muted></video>
        <canvas id="cv" hidden></canvas>
        <div class="selfie-hint" id="hint">Tekan tombol kamera untuk memulai.</div>
      </div>

      <div class="btn-row" id="ctrlAwal">
        <button class="btn" type="button" id="btnBuka">&#128247; Aktifkan Kamera</button>
        <button class="btn" type="button" id="btnSnap" disabled>&#128247; Ambil Foto</button>
      </div>
      <div class="btn-row" id="ctrlHasil" hidden>
        <button class="btn" type="button" id="btnKirim">Kirim &amp; Mulai Ujian</button>
        <button class="btn btn-ghost" type="button" id="btnUlang2">Ambil Ulang</button>
      </div>

      <div class="selfie-preview" id="prev" hidden><img id="prevImg" alt="Pratinjau foto"></div>
      <p class="muted small" id="statusTxt"></p>

      <details class="selfie-alt">
        <summary>Kamera tidak bisa dibuka? Unggah foto dari galeri HP</summary>
        <input type="file" id="fileAlt" accept="image/jpeg,image/png,image/webp" capture="user">
      </details>
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

  const vid = document.getElementById('vid'),
        cv  = document.getElementById('cv'),
        hint= document.getElementById('hint'),
        prev= document.getElementById('prev'),
        prevImg = document.getElementById('prevImg'),
        statusTxt = document.getElementById('statusTxt'),
        btnBuka = document.getElementById('btnBuka'),
        btnSnap = document.getElementById('btnSnap'),
        btnKirim= document.getElementById('btnKirim'),
        area    = document.getElementById('areaKamera');
  let stream = null, blob = null;

  async function bukaKamera() {
    hint.hidden = false;
    hint.textContent = 'Meminta izin kamera…';
    btnBuka.disabled = true;
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      hint.textContent = 'Kamera hanya tersedia melalui HTTPS. Gunakan unggah dari galeri HP.';
      btnBuka.disabled = false;
      return;
    }
    try {
      if (stream) stream.getTracks().forEach(t => t.stop());
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 960 } },
        audio: false
      });
      vid.srcObject = stream;
      await vid.play();
      btnSnap.disabled = false;
      btnBuka.textContent = 'Kamera Aktif';
      hint.hidden = true;
    } catch (e) {
      btnBuka.disabled = false;
      btnSnap.disabled = true;
      document.querySelector('.selfie-alt').open = true;
      const msg = e.name === 'NotAllowedError'
        ? 'Izin kamera ditolak. Izinkan Kamera di pengaturan browser/HP, lalu tekan Aktifkan Kamera lagi.'
        : e.name === 'NotFoundError'
          ? 'Kamera tidak ditemukan di perangkat ini.'
          : 'Kamera gagal dibuka (' + (e.name || 'error') + '). Gunakan unggah dari galeri HP.';
      hint.textContent = msg;
    }
  }

  btnBuka.addEventListener('click', bukaKamera);

  function resizeToBlob(cb) {
    const w = vid.videoWidth || 640, h = vid.videoHeight || 480;
    const r = Math.min(1, MAX_EDGE / Math.max(w, h));
    cv.width = Math.round(w * r); cv.height = Math.round(h * r);
    cv.getContext('2d').drawImage(vid, 0, 0, cv.width, cv.height);
    cv.toBlob(b => cb(b), 'image/jpeg', QUAL);
  }

  btnSnap.addEventListener('click', () => {
    resizeToBlob(b => {
      if (!b) { statusTxt.textContent = 'Gagal membuat gambar.'; return; }
      blob = b;
      prevImg.src = URL.createObjectURL(b);
      prev.hidden = false;
      document.getElementById('ctrlAwal').hidden = true;
      document.getElementById('ctrlHasil').hidden = false;
      statusTxt.textContent = 'Ukuran terkompres: ' + Math.round(b.size / 1024) + ' KB';
    });
  });

  function resetAmbil() {
    blob = null; prev.hidden = true;
    document.getElementById('ctrlHasil').hidden = true;
    document.getElementById('ctrlAwal').hidden = false;
    statusTxt.textContent = '';
  }
  document.getElementById('btnUlang2').addEventListener('click', resetAmbil);
  document.getElementById('btnUlang').addEventListener('click', () => {
    area.hidden = false;
    document.querySelector('.selfie-preview').hidden = true;
    bukaKamera();
  });

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
        if (stream) stream.getTracks().forEach(t => t.stop());
        statusTxt.textContent = 'Tersimpan (' + d.kb + ' KB). Melanjutkan…';
        setTimeout(() => { location.href = urlLanjut; }, 600);
      })
      .catch(e => { btnKirim.disabled = false; statusTxt.textContent = 'Gagal: ' + e.message; });
  });

  // Jangan meminta kamera otomatis: iOS/Safari dan beberapa browser HP hanya
  // menampilkan permission setelah ada klik pengguna.
})();
</script>

<?= $this->endSection() ?>
