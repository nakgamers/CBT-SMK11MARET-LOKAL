<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Jadwal &amp; Hasil Ujian</h1>
    <p class="muted mb0">Status dihitung otomatis dari rentang waktu: belum dimulai, berlangsung, atau berakhir.</p>
  </div>
  <button class="btn btn-sm" onclick="formUjian()">+ Buat Ujian</button>
</div>

<div class="card">
  <?php if ($exams === []): ?>
    <div class="empty"><span class="big">&#128221;</span>Belum ada ujian.
      <?php if ($banks === []): ?><br><span class="small">Buat <a href="<?= site_url('admin/bank') ?>">bank soal</a> terlebih dahulu.</span><?php endif ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr>
          <th>Ujian</th><th>Bank / Mapel</th><th>Kelas</th><th>Rentang Waktu</th>
          <th class="center">Soal</th><th class="center">Durasi</th><th>Status</th><th class="center">Peserta</th><th class="act"><span class="sr-only">Aksi</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($exams as $e): ?>
          <tr>
            <td>
              <b><?= esc($e['nama']) ?></b>
              <?php if (! empty($e['token'])): ?><br><span class="small dim">Token: <span class="mono"><?= esc($e['token']) ?></span></span><?php endif ?>
            </td>
            <td class="small"><?= esc($e['bank_nama'] ?? '-') ?><br><span class="dim"><?= esc($e['bank_mapel'] ?? '') ?></span></td>
            <td class="small"><?= $e['kelas'] === '*' ? '<span class="dim">Semua</span>' : esc($e['kelas']) ?></td>
            <td class="small nowrap"><?= tgl_id($e['mulai_at']) ?><br><span class="dim">s/d <?= tgl_id($e['selesai_at']) ?></span></td>
            <td class="center"><?= (int) $e['jumlah_soal'] === 0 ? 'Semua' : (int) $e['jumlah_soal'] ?></td>
            <td class="center"><?= (int) $e['durasi_menit'] ?>'</td>
            <td><?= badge_status($e['status_waktu']) ?></td>
            <td class="center"><?= (int) $e['jumlah_selesai'] ?>/<?= (int) $e['jumlah_peserta'] ?></td>
            <td class="act">
              <a class="btn btn-sm" href="<?= site_url('admin/ujian/hasil/' . $e['id']) ?>">Hasil</a>
              <button class="btn btn-ghost btn-sm" onclick='formUjian(<?= json_encode($e, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
              <form method="post" action="<?= site_url('admin/ujian/hapus/' . $e['id']) ?>" style="display:inline"
                    data-confirm="Hapus ujian ini beserta <?= (int) $e['jumlah_peserta'] ?> hasil peserta?">
                <?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>

<div class="modal-bg" id="mUjian">
  <div class="modal wide">
    <form method="post" action="<?= site_url('admin/ujian/simpan') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="e_id">
      <div class="modal-head"><h3 id="e_judul">Buat Ujian</h3><button type="button" onclick="closeModal('mUjian')">&times;</button></div>
      <div class="modal-body">
        <div class="field"><label for="e_nama">Nama Ujian</label><input type="text" id="e_nama" name="nama" required placeholder="UAS Matematika Ganjil"></div>

        <div class="grid-2">
          <div class="field">
            <label for="e_bank">Bank Soal</label>
            <select id="e_bank" name="bank_id" required>
              <option value="">— pilih bank soal —</option>
              <?php foreach ($banks as $b): ?>
                <option value="<?= (int) $b['id'] ?>" data-max="<?= (int) $b['jumlah_soal'] ?>">
                  <?= esc($b['mapel']) ?> — <?= esc($b['nama']) ?> (<?= (int) $b['jumlah_soal'] ?> soal)
                </option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="field">
            <label>Kelas Peserta</label>
            <div class="kelas-pick" id="e_kelas_box">
              <label class="check kp-all">
                <input type="checkbox" id="e_semua" name="semua_kelas" value="1" checked>
                <span><b>Semua kelas</b> <span class="dim small">(seluruh siswa aktif)</span></span>
              </label>
              <?php if ($daftarKelas === []): ?>
                <div class="empty small">Belum ada data siswa. Tambahkan siswa lebih dulu.</div>
              <?php else: ?>
                <div class="kp-list">
                  <?php foreach ($daftarKelas as $k): ?>
                    <label class="check kp-item">
                      <input type="checkbox" class="kp-cb" name="kelas[]" value="<?= esc($k['kelas'], 'attr') ?>">
                      <span><?= esc($k['kelas']) ?> <span class="dim small"><?= $k['jumlah'] ?> siswa</span></span>
                    </label>
                  <?php endforeach ?>
                </div>
              <?php endif ?>
            </div>
            <div class="hint" id="e_kelas_ket">Semua kelas dipilih.</div>
          </div>
        </div>

        <div class="grid-3">
          <div class="field">
            <label for="e_jumlah">Jumlah Soal</label>
            <input type="number" id="e_jumlah" name="jumlah_soal" value="0" min="0">
            <div class="hint">0 = pakai semua soal <span id="e_max"></span></div>
          </div>
          <div class="field"><label for="e_durasi">Durasi (menit)</label><input type="number" id="e_durasi" name="durasi_menit" value="60" min="1" required></div>
          <div class="field">
            <label for="e_token">Token Ujian</label>
            <input type="text" id="e_token" name="token" maxlength="10" placeholder="opsional" style="text-transform:uppercase">
            <div class="hint">Kosongkan bila tidak perlu.</div>
          </div>
        </div>

        <div class="grid-2">
          <div class="field"><label for="e_mulai">Waktu Mulai</label><input type="datetime-local" id="e_mulai" name="mulai_at" required></div>
          <div class="field"><label for="e_selesai">Waktu Selesai</label><input type="datetime-local" id="e_selesai" name="selesai_at" required></div>
        </div>

        <div class="card" style="box-shadow:none;margin:0"><div class="card-body" style="display:grid;gap:.5rem">
          <label class="check"><input type="checkbox" name="acak_soal" id="e_acak_soal" value="1" checked> Acak urutan soal per siswa</label>
          <label class="check"><input type="checkbox" name="acak_opsi" id="e_acak_opsi" value="1"> Acak urutan opsi jawaban</label>
          <label class="check"><input type="checkbox" name="tampilkan_hasil" id="e_tampil" value="1" checked> Siswa boleh melihat nilainya sendiri setelah selesai</label>
          <label class="check"><input type="checkbox" name="aktif" id="e_aktif" value="1" checked> Ujian aktif</label>
        </div></div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" onclick="closeModal('mUjian')">Batal</button>
        <button class="btn" type="submit">Simpan Ujian</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
/* "2026-03-01 07:30:00" -> "2026-03-01T07:30" untuk input datetime-local */
const toLocal = s => s ? s.replace(' ', 'T').slice(0, 16) : '';

function formUjian(e) {
  const v = (id, val) => document.getElementById(id).value = val ?? '';
  const c = (id, on) => document.getElementById(id).checked = !!on;

  document.getElementById('e_judul').textContent = e ? 'Edit Ujian' : 'Buat Ujian';
  v('e_id', e ? e.id : '');
  v('e_nama', e ? e.nama : '');
  v('e_bank', e ? e.bank_id : '');
  v('e_jumlah', e ? e.jumlah_soal : 0);
  v('e_durasi', e ? e.durasi_menit : 60);
  v('e_token', e && e.token ? e.token : '');
  v('e_mulai', e ? toLocal(e.mulai_at) : '');
  v('e_selesai', e ? toLocal(e.selesai_at) : '');
  c('e_acak_soal', e ? Number(e.acak_soal) === 1 : true);
  c('e_acak_opsi', e ? Number(e.acak_opsi) === 1 : false);
  c('e_tampil', e ? Number(e.tampilkan_hasil) === 1 : true);
  c('e_aktif', e ? Number(e.aktif) === 1 : true);

  /* isi centang kelas dari nilai tersimpan ("*" atau "XII RPL 1,XII RPL 2") */
  const target = e ? String(e.kelas || '*') : '*';
  const semua = target === '' || target === '*';
  const dipilih = semua ? [] : target.split(',').map(s => s.trim());
  document.getElementById('e_semua').checked = semua;
  kpBoxes().forEach(cb => cb.checked = dipilih.includes(cb.value));
  kpSinkron();

  maxSoal();
  openModal('mUjian');
}

/* --- pilih kelas peserta: centang, bukan ketik --- */
const kpBoxes = () => Array.from(document.querySelectorAll('.kp-cb'));

function kpSinkron() {
  const semua = document.getElementById('e_semua');
  const box = kpBoxes();
  const pilih = box.filter(cb => cb.checked);

  // "Semua kelas" dan kelas spesifik saling meniadakan
  box.forEach(cb => cb.closest('.kp-item').classList.toggle('mati', semua.checked));
  document.getElementById('e_kelas_box').classList.toggle('mode-semua', semua.checked);

  const ket = document.getElementById('e_kelas_ket');
  if (semua.checked) {
    ket.textContent = 'Semua kelas dipilih.';
    ket.className = 'hint';
  } else if (pilih.length === 0) {
    ket.textContent = 'Belum ada kelas dicentang — pilih minimal satu, atau centang "Semua kelas".';
    ket.className = 'hint hint-bad';
  } else {
    ket.textContent = pilih.length + ' kelas dipilih: ' + pilih.map(cb => cb.value).join(', ');
    ket.className = 'hint hint-ok';
  }
}

document.getElementById('e_semua').addEventListener('change', function () {
  if (this.checked) kpBoxes().forEach(cb => cb.checked = false);
  kpSinkron();
});
kpBoxes().forEach(cb => cb.addEventListener('change', function () {
  // mencentang satu kelas otomatis melepas "Semua kelas"
  if (this.checked) document.getElementById('e_semua').checked = false;
  kpSinkron();
}));

/* jangan biarkan form terkirim tanpa satu pun kelas (kalau lolos, server
   akan menyimpannya sebagai "*" dan ujian bocor ke seluruh sekolah) */
document.querySelector('#mUjian form').addEventListener('submit', function (ev) {
  if (!document.getElementById('e_semua').checked && kpBoxes().every(cb => !cb.checked)) {
    ev.preventDefault();
    kpSinkron();
    document.getElementById('e_kelas_box').scrollIntoView({ block: 'center' });
  }
});

function maxSoal() {
  const opt = document.getElementById('e_bank').selectedOptions[0];
  const max = opt ? opt.dataset.max : '';
  document.getElementById('e_max').textContent = max ? '(tersedia ' + max + ')' : '';
  document.getElementById('e_jumlah').max = max || '';
}
document.getElementById('e_bank').addEventListener('change', maxSoal);
</script>
<?= $this->endSection() ?>
