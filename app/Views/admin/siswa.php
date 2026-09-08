<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Siswa &amp; Kartu Login</h1>
    <p class="muted mb0">Kartu berisi NIS + token; token dipakai siswa untuk login.</p>
  </div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/siswa/template') ?>">&#11015; Template Excel</a>
    <button class="btn btn-ghost btn-sm" onclick="openModal('mImport')">&#128228; Import Excel</button>
    <a class="btn btn-ghost btn-sm" target="_blank"
       href="<?= site_url('admin/siswa/kartu') . ($filter['kelas'] !== '' ? '?kelas=' . urlencode($filter['kelas']) : '') ?>">&#128424; Cetak Kartu</a>
    <button class="btn btn-sm" onclick="formSiswa()">+ Tambah Siswa</button>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <form method="get" class="btn-row" style="flex:1">
      <input type="text" name="q" value="<?= esc($filter['q']) ?>" placeholder="Cari nama / NIS" style="max-width:230px">
      <select name="kelas" style="max-width:180px">
        <option value="">Semua kelas</option>
        <?php foreach ($daftarKelas as $k): ?>
          <option value="<?= esc($k, 'attr') ?>" <?= $filter['kelas'] === $k ? 'selected' : '' ?>><?= esc($k) ?></option>
        <?php endforeach ?>
      </select>
      <button class="btn btn-sm" type="submit">Filter</button>
      <?php if ($filter['q'] !== '' || $filter['kelas'] !== ''): ?>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/siswa') ?>">Reset</a>
      <?php endif ?>
    </form>
  </div>

  <?php if ($siswa === []): ?>
    <div class="empty"><span class="big">&#128101;</span>Belum ada data siswa. Import dari Excel atau tambah manual.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr>
          <th>NIS</th><th>Nama</th><th>Kelas</th><th class="center">JK</th><th>Token</th><th class="center">Status</th><th class="act"><span class="sr-only">Aksi</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($siswa as $s): ?>
          <tr>
            <td class="mono"><?= esc($s['nis']) ?></td>
            <td><b><?= esc($s['nama']) ?></b></td>
            <td><?= esc($s['kelas']) ?></td>
            <td class="center"><?= esc($s['jk']) ?></td>
            <td class="mono" style="letter-spacing:.12em"><?= esc($s['token']) ?></td>
            <td class="center">
              <?php if ((int) $s['aktif'] === 1): ?>
                <span class="badge badge-selesai">Aktif</span>
              <?php else: ?>
                <span class="badge badge-nonaktif">Nonaktif</span>
              <?php endif ?>
            </td>
            <td class="act">
              <button class="btn btn-ghost btn-sm" onclick='formSiswa(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
              <form method="post" action="<?= site_url('admin/siswa/reset-token/' . $s['id']) ?>" style="display:inline"
                    data-confirm="Buat token baru? Kartu lama tidak bisa dipakai lagi.">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit">Token</button>
              </form>
              <form method="post" action="<?= site_url('admin/siswa/hapus/' . $s['id']) ?>" style="display:inline"
                    data-confirm="Hapus <?= esc($s['nama'], 'attr') ?> beserta riwayat ujiannya?">
                <?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <div class="card-body"><?= $pager->links('default', 'default_full') ?></div>
  <?php endif ?>
</div>

<!-- modal: form siswa -->
<div class="modal-bg" id="mSiswa">
  <div class="modal">
    <form method="post" action="<?= site_url('admin/siswa/simpan') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="s_id">
      <div class="modal-head"><h3 id="s_judul">Tambah Siswa</h3><button type="button" onclick="closeModal('mSiswa')">&times;</button></div>
      <div class="modal-body">
        <div class="grid-2">
          <div class="field"><label for="s_nis">NIS</label><input type="text" id="s_nis" name="nis" required></div>
          <div class="field"><label for="s_kelas">Kelas</label><input type="text" id="s_kelas" name="kelas" required placeholder="XII RPL 1" list="kelasList">
            <datalist id="kelasList"><?php foreach ($daftarKelas as $k): ?><option value="<?= esc($k, 'attr') ?>"><?php endforeach ?></datalist>
          </div>
        </div>
        <div class="field"><label for="s_nama">Nama Lengkap</label><input type="text" id="s_nama" name="nama" required></div>
        <div class="grid-2">
          <div class="field"><label for="s_jk">Jenis Kelamin</label>
            <select id="s_jk" name="jk"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
          </div>
          <div class="field"><label for="s_token">Token Kartu</label>
            <input type="text" id="s_token" name="token" maxlength="12" placeholder="otomatis bila kosong" style="text-transform:uppercase">
          </div>
        </div>
        <label class="check"><input type="checkbox" name="aktif" id="s_aktif" value="1" checked> Akun aktif (boleh login)</label>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" onclick="closeModal('mSiswa')">Batal</button>
        <button class="btn" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- modal: import -->
<div class="modal-bg" id="mImport">
  <div class="modal">
    <form method="post" action="<?= site_url('admin/siswa/import') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-head"><h3>Import Siswa dari Excel</h3><button type="button" onclick="closeModal('mImport')">&times;</button></div>
      <div class="modal-body">
        <div class="alert alert-info">
          Kolom wajib berurutan: <b>nis, nama, kelas, jk</b> (baris pertama header).
          NIS yang sudah ada akan <b>diperbarui</b> tanpa mengubah tokennya.
        </div>
        <div class="field">
          <label for="berkas">Berkas .xlsx / .xls / .csv</label>
          <input type="file" id="berkas" name="berkas" accept=".xlsx,.xls,.csv" required>
        </div>
        <a class="small" href="<?= site_url('admin/siswa/template') ?>">Unduh template</a>
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
function formSiswa(s) {
  document.getElementById('s_judul').textContent = s ? 'Edit Siswa' : 'Tambah Siswa';
  document.getElementById('s_id').value = s ? s.id : '';
  document.getElementById('s_nis').value = s ? s.nis : '';
  document.getElementById('s_nama').value = s ? s.nama : '';
  document.getElementById('s_kelas').value = s ? s.kelas : '';
  document.getElementById('s_jk').value = s ? s.jk : 'L';
  document.getElementById('s_token').value = s ? s.token : '';
  document.getElementById('s_aktif').checked = s ? Number(s.aktif) === 1 : true;
  openModal('mSiswa');
}
</script>
<?= $this->endSection() ?>
