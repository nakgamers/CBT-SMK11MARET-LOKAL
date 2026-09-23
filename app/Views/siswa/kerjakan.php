<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($exam['nama']) ?> — <?= esc(cbt_app()) ?></title>
<link rel="stylesheet" href="<?= cbt_css_url() ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/sweetalert2.min.css') ?>">
<script src="<?= base_url('assets/vendor/sweetalert2.min.js') ?>"></script>
</head>
<body>

<div class="exam-bar">
  <div class="nm">
    <b><?= esc($exam['nama']) ?></b>
    <small><?= esc($siswa['nama']) ?> &middot; <?= esc($siswa['kelas']) ?></small>
  </div>
  <span id="saveState" class="save-pill">Tersimpan otomatis</span>
  <span id="antiCheatState" class="save-pill cheat" role="status" aria-live="assertive" hidden></span>
  <div id="timer" role="timer" aria-live="off">--:--</div>
  <button class="btn btn-ghost btn-sm" type="button" onclick="kumpul()">Kumpulkan</button>
</div>

<div class="exam-layout">
  <div>
    <?php if ($soal === []): ?>
      <div class="q-card"><div class="empty">Bank soal untuk ujian ini masih kosong. Hubungi pengawas.</div></div>
    <?php endif ?>

    <?php foreach ($soal as $i => $q): ?>
      <div class="q-card q-page<?= $i === 0 ? '' : ' hidden' ?>" data-i="<?= $i ?>" data-qid="<?= (int) $q['id'] ?>">
        <div class="q-head">
          <div class="no"><?= $i + 1 ?></div>
          <div class="of">Soal <?= $i + 1 ?> dari <?= count($soal) ?> &middot; bobot <?= (int) $q['bobot'] ?></div>
          <label class="check ragu-lbl">
            <input type="checkbox" class="ragu-box" <?= ! empty($jawab[$q['id']]['ragu']) ? 'checked' : '' ?>>
            <span class="small">Tandai ragu</span>
          </label>
        </div>
        <div class="q-body">
          <div class="q-text" id="teks<?= (int) $q['id'] ?>"><?= cbt_render_soal($q['teks']) ?>
            <?php if (! empty($q['gambar'])): ?>
              <button class="q-image-button" type="button"
                      onclick="lihatGambar(this.querySelector('img'))"
                      aria-label="Perbesar gambar soal <?= $i + 1 ?>">
                <img class="q-img" src="<?= base_url('uploads/soal/' . rawurlencode((string) $q['gambar'])) ?>" alt="Gambar soal <?= $i + 1 ?>">
              </button>
            <?php endif ?>
          </div>

          <div role="radiogroup" aria-labelledby="teks<?= (int) $q['id'] ?>">
          <?php foreach ($q['urut_opsi'] as $k):
              $isi = $q['opsi_' . strtolower($k)];
              $sel = ($jawab[$q['id']]['jawaban'] ?? null) === $k;
          ?>
            <label class="opt<?= $sel ? ' sel' : '' ?>">
              <input type="radio" name="q<?= (int) $q['id'] ?>" value="<?= $k ?>" <?= $sel ? 'checked' : '' ?>>
              <span class="k"><?= $k ?></span>
              <span><?= cbt_render_soal($isi) ?></span>
            </label>
          <?php endforeach ?>
          </div>
        </div>
        <div class="q-foot">
          <button class="btn btn-ghost btn-sm" type="button" onclick="ke(<?= $i - 1 ?>)" <?= $i === 0 ? 'disabled' : '' ?>>&larr; Sebelumnya</button>
          <span class="sp"></span>
          <?php if ($i + 1 < count($soal)): ?>
            <button class="btn btn-sm" type="button" onclick="ke(<?= $i + 1 ?>)">Berikutnya &rarr;</button>
          <?php else: ?>
            <button class="btn btn-sm btn-ok" type="button" onclick="kumpul()">Kumpulkan Jawaban</button>
          <?php endif ?>
          <button class="btn btn-ghost btn-sm ksg" type="button" onclick="hapusJawab(this)" disabled>Kosongkan pilihan</button>
        </div>
      </div>
    <?php endforeach ?>
  </div>

  <div class="side">
    <div class="h">Navigasi Soal</div>
    <div class="nav-grid" id="navGrid">
      <?php foreach ($soal as $i => $q):
          $terisi = ! empty($jawab[$q['id']]['jawaban']);
          $ragu   = ! empty($jawab[$q['id']]['ragu']);
          $cls    = trim(($terisi ? 'done ' : '') . ($ragu ? 'ragu' : ''));
      ?>
        <button type="button" data-n="<?= $i ?>" class="<?= $cls ?><?= $i === 0 ? ' now' : '' ?>"
                <?= $i === 0 ? 'aria-current="true"' : '' ?>
                aria-label="Soal <?= $i + 1 ?><?= $terisi ? ', sudah dijawab' : ', belum dijawab' ?>"
                onclick="ke(<?= $i ?>)"><?= $i + 1 ?></button>
      <?php endforeach ?>
    </div>
    <div class="legend">
      <div><i style="background:#d1fae5;border-color:#6ee7b7"></i> <b>✓</b> Sudah dijawab</div>
      <div><i style="background:#fef3c7;border-color:#fcd34d"></i> <b>?</b> Ditandai ragu</div>
      <div><i style="background:#fff"></i> Belum dijawab</div>
      <div><i style="background:#fff;outline:2px solid var(--brand);outline-offset:1px"></i> Soal yang dibuka</div>
    </div>
    <div class="foot">
      <div class="small muted" style="margin-bottom:.4rem">
        Terjawab <b id="cnt">0</b> / <?= count($soal) ?>
      </div>
      <div class="bar-wrap" role="progressbar" aria-valuemin="0" aria-valuemax="<?= count($soal) ?>" aria-valuenow="0" id="barWrap">
        <div class="bar" id="bar"></div>
      </div>
      <button class="btn btn-danger btn-block" type="button" onclick="kumpul()">Kumpulkan Jawaban</button>
    </div>
  </div>
</div>

<div class="image-lightbox" id="imageLightbox" hidden role="dialog" aria-modal="true" aria-label="Pratinjau gambar soal">
  <button type="button" class="image-lightbox-close" onclick="tutupGambar()" aria-label="Tutup">&times;</button>
  <img id="imageLightboxImg" alt="Gambar soal diperbesar">
</div>

<form id="fSubmit" method="post" action="<?= site_url('siswa/selesai/' . $exam['id']) ?>" class="hidden">
  <?= csrf_field() ?>
</form>

<script>
const URL_JAWAB = <?= json_encode(site_url('siswa/jawab/' . $exam['id'])) ?>;
const URL_PELANGGARAN = <?= json_encode(site_url('siswa/pelanggaran/' . $exam['id'])) ?>;
const URL_GANGGUAN_KONEKSI = <?= json_encode(site_url('siswa/gangguan-koneksi/' . $exam['id'])) ?>;
const URL_LOGIN = <?= json_encode(site_url('login')) ?>;
const CSRF = { name: <?= json_encode(csrf_token()) ?>, hash: <?= json_encode(csrf_hash()) ?> };
const TOTAL = <?= count($soal) ?>;
let sisa = <?= max(0, (int) $sisa) ?>;
let cur = 0;
let antiCheatMengirim = false;
let gangguanMengirim = false;
let koneksiTerputus = false;
let gangguanPending = Number(sessionStorage.getItem('cbt_gangguan_pending') || 0);
let submitDisengaja = false;

const pages = [...document.querySelectorAll('.q-page')];
const navBtn = [...document.querySelectorAll('#navGrid button')];
const elTimer = document.getElementById('timer');
const elCnt = document.getElementById('cnt');
const elSave = document.getElementById('saveState');

function ke(i) {
  if (i < 0 || i >= TOTAL) return;
  pages[cur].classList.add('hidden');
  navBtn[cur]?.classList.remove('now');
  navBtn[cur]?.removeAttribute('aria-current');
  cur = i;
  pages[cur].classList.remove('hidden');
  navBtn[cur]?.classList.add('now');
  navBtn[cur]?.setAttribute('aria-current', 'true');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function hitung() {
  const n = navBtn.filter(b => b.classList.contains('done')).length;
  elCnt.textContent = n;
  document.getElementById('bar').style.width = TOTAL ? (n / TOTAL * 100) + '%' : '0';
  document.getElementById('barWrap').setAttribute('aria-valuenow', n);
}

/* soal yang sudah terjawab (resume): aktifkan tombol Kosongkan */
pages.forEach(p => {
  if (p.querySelector('input[type=radio]:checked')) p.querySelector('.ksg').disabled = false;
});

/* ---- timer: hitung dari sisa detik milik server, bukan jam lokal ---- */
function tick() {
  if (sisa <= 0) {
    elTimer.textContent = '00:00';
    Swal.fire({
      icon: 'warning',
      title: 'Waktu ujian habis',
      text: 'Jawaban Anda akan dikumpulkan otomatis.',
      confirmButtonText: 'Mengerti',
      allowOutsideClick: false,
      allowEscapeKey: false,
    }).then(() => {
      submitDisengaja = true;
      document.getElementById('fSubmit').submit();
    });
    return;
  }
  sisa--;
  const h = Math.floor(sisa / 3600), m = Math.floor((sisa % 3600) / 60), s = sisa % 60;
  const p = n => String(n).padStart(2, '0');
  elTimer.textContent = (h > 0 ? p(h) + ':' : '') + p(m) + ':' + p(s);
  elTimer.className = sisa <= 60 ? 'crit' : (sisa <= 300 ? 'warn' : '');
  setTimeout(tick, 1000);
}

function status(txt, warna) {
  elSave.textContent = txt;
  elSave.className = 'save-pill' + (warna ? ' ' + warna : '');
}

async function kirim(qid, jawaban, ragu) {
  const body = new FormData();
  body.append(CSRF.name, CSRF.hash);
  body.append('question_id', qid);
  body.append('jawaban', jawaban ?? '');
  if (ragu !== undefined) body.append('ragu', ragu ? 1 : 0);

  status('menyimpan…');
  try {
    const r = await fetch(URL_JAWAB, {
      method: 'POST', body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) {
      if (j.habis) {
        Swal.fire({
          icon: 'warning',
          title: 'Waktu ujian habis',
          text: 'Jawaban Anda akan dikumpulkan otomatis.',
          confirmButtonText: 'Lihat hasil',
          allowOutsideClick: false,
          allowEscapeKey: false,
        }).then(() => { location.href = <?= json_encode(site_url('siswa/hasil/' . $exam['id'])) ?>; });
        return;
      }
      status(j.error || 'gagal menyimpan', 'bad');
      return;
    }
    if (typeof j.sisa === 'number') sisa = j.sisa;   // sinkron ulang dengan server
    status('tersimpan ✓', 'ok');
  } catch (e) {
    tampilkanHasilAutosaveGagal();
  }
}

function resetJawabanLokal() {
  pages.forEach(page => {
    page.querySelectorAll('input[type=radio]').forEach(radio => { radio.checked = false; });
    page.querySelectorAll('.opt').forEach(option => option.classList.remove('sel'));
    page.querySelectorAll('.ragu-box').forEach(box => { box.checked = false; });
    const kosongkan = page.querySelector('.ksg');
    if (kosongkan) kosongkan.disabled = true;
  });
  navBtn.forEach(button => button.classList.remove('done', 'ragu'));
  hitung();
}

async function laporPelanggaran() {
  if (antiCheatMengirim || submitDisengaja) return;
  antiCheatMengirim = true;
  resetJawabanLokal();

  const antiCheatState = document.getElementById('antiCheatState');
  antiCheatState.hidden = false;
  antiCheatState.textContent = 'Terdeteksi keluar dari tab — jawaban direset';
  status('jawaban direset', 'bad');

  const body = new FormData();
  body.append(CSRF.name, CSRF.hash);
  try {
    const response = await fetch(URL_PELANGGARAN, {
      method: 'POST',
      body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      keepalive: true,
    });
    const hasil = await response.json().catch(() => ({}));
    if (hasil.dihentikan || hasil.login === false) {
      Swal.fire({
        icon: 'error',
        title: 'Ujian dihentikan',
        text: hasil.dihentikan
          ? 'Terdeteksi curang 3 kali. Ujian dinyatakan selesai dan Anda akan keluar dari sistem.'
          : 'Sesi Anda sudah berakhir.',
        confirmButtonText: 'Kembali ke login',
        allowOutsideClick: false,
        allowEscapeKey: false,
      }).then(() => window.location.replace(hasil.redirect || URL_LOGIN));
      return;
    }
    if (response.ok && hasil.pelanggaran) {
      antiCheatState.textContent = 'Terdeteksi curang ' + hasil.pelanggaran + '/3 — semua jawaban direset';
    } else if (!response.ok) {
      antiCheatState.textContent = 'Pelanggaran gagal dicatat. Hubungi pengawas.';
    }
  } catch (error) {
    // Jangan menyatakan reset berhasil bila request ke server gagal.
    antiCheatState.textContent = 'Pelanggaran belum tercatat — periksa koneksi';
    status('gagal mencatat anti-cheat', 'bad');
  } finally {
    antiCheatMengirim = false;
  }
}

/* Tab berpindah/ditutup: visibilitychange lebih terukur daripada blur, yang bisa terpicu
 * oleh klik dialog browser, keyboard virtual, atau elemen UI lain. */
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') laporPelanggaran();
});

function antreGangguanKoneksi() {
  gangguanPending++;
  sessionStorage.setItem('cbt_gangguan_pending', String(gangguanPending));
}

async function kirimGangguanKeServer() {
  if (gangguanMengirim || submitDisengaja || !navigator.onLine) return;
  gangguanMengirim = true;
  try {
    while (gangguanPending > 0 && !submitDisengaja) {
      const body = new FormData();
      body.append(CSRF.name, CSRF.hash);
      const response = await fetch(URL_GANGGUAN_KONEKSI, {
        method: 'POST', body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        keepalive: true,
      });
      const hasil = await response.json().catch(() => ({}));
      if (hasil.dihentikan || hasil.login === false) {
        Swal.fire({
          icon: 'error',
          title: 'Ujian dihentikan',
          text: hasil.dihentikan
            ? 'Koneksi terputus 5 kali. Ujian dinyatakan selesai dan Anda akan keluar dari sistem.'
            : 'Sesi Anda sudah berakhir.',
          confirmButtonText: 'Kembali ke login',
          allowOutsideClick: false,
          allowEscapeKey: false,
        }).then(() => window.location.replace(hasil.redirect || URL_LOGIN));
        return;
      }
      if (!response.ok || !hasil.gangguan) throw new Error('Gagal mencatat gangguan koneksi');
      gangguanPending--;
      sessionStorage.setItem('cbt_gangguan_pending', String(gangguanPending));
      status('gangguan koneksi ' + hasil.gangguan + '/5', 'bad');
    }
  } catch (error) {
    status('koneksi terputus — menunggu koneksi kembali', 'bad');
  } finally {
    gangguanMengirim = false;
  }
}

function laporGangguanKoneksi() {
  if (gangguanMengirim || submitDisengaja) return;
  antreGangguanKoneksi();
  status('koneksi terputus — melaporkan…', 'bad');
  kirimGangguanKeServer();
}

function tampilkanHasilAutosaveGagal() {
  laporGangguanKoneksi();
}

window.addEventListener('offline', () => {
  koneksiTerputus = true;
  laporGangguanKoneksi();
});
window.addEventListener('online', () => {
  koneksiTerputus = false;
  status('koneksi kembali — menyinkronkan…', 'ok');
  kirimGangguanKeServer();
});
if (gangguanPending > 0 && navigator.onLine) kirimGangguanKeServer();

/* pilih opsi */
document.addEventListener('change', e => {
  if (e.target.matches('.q-page input[type=radio]')) {
    const page = e.target.closest('.q-page');
    page.querySelectorAll('.opt').forEach(o => o.classList.remove('sel'));
    e.target.closest('.opt').classList.add('sel');
    navBtn[+page.dataset.i]?.classList.add('done');
    page.querySelector('.ksg').disabled = false;
    hitung();
    kirim(page.dataset.qid, e.target.value);
  }
  if (e.target.matches('.ragu-box')) {
    const page = e.target.closest('.q-page');
    navBtn[+page.dataset.i]?.classList.toggle('ragu', e.target.checked);
    const pilih = page.querySelector('input[type=radio]:checked');
    kirim(page.dataset.qid, pilih ? pilih.value : '', e.target.checked);
  }
});

function hapusJawab(btn) {
  const page = btn.closest('.q-page');
  page.querySelectorAll('input[type=radio]').forEach(r => r.checked = false);
  page.querySelectorAll('.opt').forEach(o => o.classList.remove('sel'));
  navBtn[+page.dataset.i]?.classList.remove('done');
  btn.disabled = true;
  hitung();
  kirim(page.dataset.qid, '');
}

function kumpul() {
  const belum = TOTAL - navBtn.filter(b => b.classList.contains('done')).length;
  const adaYangKosong = belum > 0;
  const sisaText = adaYangKosong
    ? 'Masih ada <b>' + belum + ' soal</b> yang belum dijawab.'
    : 'Semua soal sudah dijawab.';

  Swal.fire({
    icon: adaYangKosong ? 'warning' : 'question',
    title: 'Kumpulkan jawaban?',
    html: sisaText + '<br><span style="font-size:.9rem;color:#64748b">Setelah dikumpulkan, jawaban tidak dapat diubah lagi.</span>',
    showCancelButton: true,
    confirmButtonText: 'Ya, kumpulkan',
    cancelButtonText: 'Kembali mengerjakan',
    reverseButtons: true,
    focusCancel: true,
    allowOutsideClick: false,
  }).then(result => {
    if (result.isConfirmed) {
      submitDisengaja = true;
      document.getElementById('fSubmit').submit();
    }
  });
}

/* keyboard: 1-5 pilih opsi, panah pindah soal */
document.addEventListener('keydown', e => {
  if (e.target.matches('input,textarea')) return;
  if (e.key === 'ArrowRight') ke(cur + 1);
  if (e.key === 'ArrowLeft') ke(cur - 1);
  const idx = ['1','2','3','4','5'].indexOf(e.key);
  if (idx > -1) {
    const r = pages[cur].querySelectorAll('input[type=radio]')[idx];
    if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
  }
});

window.addEventListener('beforeunload', e => { e.preventDefault(); e.returnValue = ''; });

/* ---- lightbox gambar soal ---- */
function lihatGambar(img) {
  if (!img) return;
  const box = document.getElementById('imageLightbox');
  const target = document.getElementById('imageLightboxImg');
  target.src = img.currentSrc || img.src;
  target.alt = img.alt || 'Gambar soal diperbesar';
  box.hidden = false;
  document.querySelector('.image-lightbox-close')?.focus();
}
function tutupGambar() {
  const box = document.getElementById('imageLightbox');
  const target = document.getElementById('imageLightboxImg');
  box.hidden = true;
  target.removeAttribute('src');
}
document.getElementById('imageLightbox')?.addEventListener('click', e => {
  if (e.target.id === 'imageLightbox') tutupGambar();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') tutupGambar();
});

hitung();
tick();
</script>
</body>
</html>
