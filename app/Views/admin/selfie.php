<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<h1>Absen Selfie</h1>
<p class="muted"><?= (int) $total ?> foto absen &middot; total <?= number_format($totalKB) ?> KB di disk (foto penuh; thumbnail tidak dihitung)</p>

<form class="filter-row" method="get" action="<?= site_url('admin/absen') ?>">
  <select name="ujian" aria-label="Filter ujian">
    <option value="">Semua ujian</option>
    <?php foreach ($exams as $e): ?>
      <option value="<?= (int) $e['id'] ?>" <?= $f['exam_id'] === (int) $e['id'] ? 'selected' : '' ?>><?= esc($e['nama']) ?></option>
    <?php endforeach ?>
  </select>
  <select name="kelas" aria-label="Filter kelas">
    <option value="">Semua kelas</option>
    <?php foreach ($kelas as $k): ?>
      <option value="<?= esc($k) ?>" <?= $f['kelas'] === $k ? 'selected' : '' ?>><?= esc($k) ?></option>
    <?php endforeach ?>
  </select>
  <input type="date" name="tanggal" value="<?= esc($f['tanggal']) ?>" aria-label="Filter tanggal">
  <input type="search" name="q" value="<?= esc($f['q']) ?>" placeholder="Cari nama / NIS…" aria-label="Cari nama atau NIS">
  <button class="btn btn-sm" type="submit">Terapkan</button>
  <?php if ($f['exam_id']): ?>
    <button class="btn btn-ghost btn-sm" type="button" onclick="hapusUjian()">Hapus semua foto ujian ini</button>
  <?php endif ?>
</form>

<?php if ($rows === []): ?>
  <div class="card"><div class="empty"><span class="big">&#128247;</span>Belum ada foto absen.</div></div>
<?php else: ?>
  <div class="selfie-grid">
    <?php foreach ($rows as $r): ?>
      <figure class="selfie-cell">
        <a href="<?= site_url('admin/absen/lihat/' . $r['id'] . '/full') ?>" class="selfie-open"
           data-full="<?= site_url('admin/absen/lihat/' . $r['id'] . '/full') ?>"
           data-cap="<?= esc($r['nama'] . ' — ' . $r['nis'] . ' — ' . $r['kelas'] . ' — ' . $r['ujian'] . ' — ' . tgl_id($r['created_at'], true), 'attr') ?>">
          <img loading="lazy" decoding="async" width="320" height="320"
               src="<?= site_url('admin/absen/lihat/' . $r['id']) ?>"
               alt="Selfie <?= esc($r['nama'], 'attr') ?>">
        </a>
        <figcaption>
          <b><?= esc($r['nama']) ?></b>
          <span><?= esc($r['nis']) ?> &middot; <?= esc($r['kelas']) ?></span>
          <span class="dim"><?= esc($r['ujian']) ?> &middot; <?= tgl_id($r['created_at']) ?> &middot; <?= round($r['file_size'] / 1024) ?> KB</span>
          <form method="post" action="<?= site_url('admin/absen/hapus/' . $r['id']) ?>" class="selfie-del"
                onsubmit="return confirm('Hapus foto milik <?= esc($r['nama'], 'js') ?>?')">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit" aria-label="Hapus foto <?= esc($r['nama'], 'attr') ?>">&#128465;</button>
          </form>
        </figcaption>
      </figure>
    <?php endforeach ?>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= site_url('admin/absen?' . http_build_query(array_merge($f, ['hal' => $i]))) ?>"><?= $i ?></a>
      <?php endfor ?>
    </div>
  <?php endif ?>
<?php endif ?>

<!-- lightbox foto penuh -->
<div class="lightbox" id="lb" hidden>
  <button class="lb-x" type="button" aria-label="Tutup">&times;</button>
  <figure><img id="lbImg" alt="Foto absen penuh"><figcaption id="lbCap"></figcaption></figure>
</div>

<form method="post" id="fHapusUjian" action="<?= $f['exam_id'] ? site_url('admin/absen/hapus-ujian/' . $f['exam_id']) : '' ?>" hidden>
  <?= csrf_field() ?>
</form>

<script>
(function () {
  const lb = document.getElementById('lb'), img = document.getElementById('lbImg'), cap = document.getElementById('lbCap');
  document.querySelectorAll('.selfie-open').forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    img.alt = 'Memuat foto absen penuh…';
    img.src = '';
    lb.hidden = false; document.body.style.overflow = 'hidden';
    img.onload = () => { img.alt = 'Foto absen penuh'; };
    img.onerror = () => {
      img.alt = 'Foto penuh gagal dimuat';
      cap.textContent = a.dataset.cap + ' — foto penuh gagal dimuat';
    };
    img.src = a.dataset.full;
    cap.textContent = a.dataset.cap;
  }));
  function tutup() { lb.hidden = true; img.src = ''; document.body.style.overflow = ''; }
  lb.querySelector('.lb-x').addEventListener('click', tutup);
  lb.addEventListener('click', e => { if (e.target === lb) tutup(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !lb.hidden) tutup(); });

  window.hapusUjian = function () {
    if (confirm('Hapus SEMUA foto absen pada ujian ini? Tidak bisa dibatalkan.')) {
      document.getElementById('fHapusUjian').submit();
    }
  };
})();
</script>

<?= $this->endSection() ?>
