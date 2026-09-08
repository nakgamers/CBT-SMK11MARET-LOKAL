<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($exam['nama']) ?> — <?= esc(cbt_app()) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>

<div class="exam-bar">
  <div class="nm">
    <b><?= esc($exam['nama']) ?></b>
    <small><?= esc($siswa['nama']) ?> &middot; <?= esc($siswa['kelas']) ?></small>
  </div>
  <span id="saveState" class="save-pill">Tersimpan otomatis</span>
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
          <div class="q-text" id="teks<?= (int) $q['id'] ?>"><?= nl2br(esc($q['teks'])) ?>
            <?php if (! empty($q['gambar'])): ?>
              <img class="q-img" src="<?= base_url('uploads/soal/' . $q['gambar']) ?>" alt="Gambar soal <?= $i + 1 ?>">
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
              <span><?= nl2br(esc($isi)) ?></span>
            </label>
          <?php endforeach ?>
          </div>
        </div>
        <div class="q-foot">
          <button class="btn btn-ghost btn-sm" type="button" onclick="ke(<?= $i - 1 ?>)" <?= $i === 0 ? 'disabled' : '' ?>>&larr; Sebelumnya</button>
          <span class="sp"></span>
          <button class="btn btn-ghost btn-sm ksg" type="button" onclick="hapusJawab(this)" disabled>Kosongkan pilihan</button>
          <?php if ($i + 1 < count($soal)): ?>
            <button class="btn btn-sm" type="button" onclick="ke(<?= $i + 1 ?>)">Berikutnya &rarr;</button>
          <?php else: ?>
            <button class="btn btn-sm btn-ok" type="button" onclick="kumpul()">Kumpulkan Jawaban</button>
          <?php endif ?>
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

<form id="fSubmit" method="post" action="<?= site_url('siswa/selesai/' . $exam['id']) ?>" class="hidden">
  <?= csrf_field() ?>
</form>

<script>
const URL_JAWAB = <?= json_encode(site_url('siswa/jawab/' . $exam['id'])) ?>;
const CSRF = { name: <?= json_encode(csrf_token()) ?>, hash: <?= json_encode(csrf_hash()) ?> };
const TOTAL = <?= count($soal) ?>;
let sisa = <?= max(0, (int) $sisa) ?>;
let cur = 0;

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
    alert('Waktu ujian habis. Jawaban Anda dikumpulkan otomatis.');
    document.getElementById('fSubmit').submit();
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
      if (j.habis) { alert('Waktu ujian sudah habis.'); location.href = <?= json_encode(site_url('siswa/hasil/' . $exam['id'])) ?>; return; }
      status(j.error || 'gagal menyimpan', 'bad');
      return;
    }
    if (typeof j.sisa === 'number') sisa = j.sisa;   // sinkron ulang dengan server
    status('tersimpan ✓', 'ok');
  } catch (e) {
    status('koneksi terputus — jawaban belum tersimpan', 'bad');
  }
}

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
  const msg = belum > 0
    ? 'Masih ada ' + belum + ' soal belum dijawab. Tetap kumpulkan sekarang?'
    : 'Kumpulkan jawaban dan akhiri ujian?';
  if (confirm(msg)) document.getElementById('fSubmit').submit();
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

hitung();
tick();
</script>
</body>
</html>
