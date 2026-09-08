<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Bank Soal</h1>
    <p class="muted mb0">Kelompokkan soal per mata pelajaran, lalu pakai di jadwal ujian.</p>
  </div>
  <button class="btn btn-sm" onclick="formBank()">+ Bank Soal Baru</button>
</div>

<div class="card">
  <?php if ($banks === []): ?>
    <div class="empty"><span class="big">&#128218;</span>Belum ada bank soal. Buat satu untuk mulai mengisi soal.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Nama</th><th>Mapel</th><th>Keterangan</th><th class="center">Jumlah Soal</th><th class="act"><span class="sr-only">Aksi</span></th></tr></thead>
        <tbody>
        <?php foreach ($banks as $b): ?>
          <tr>
            <td><b><?= esc($b['nama']) ?></b></td>
            <td><?= esc($b['mapel']) ?></td>
            <td class="small muted"><?= esc($b['keterangan'] ?? '-') ?></td>
            <td class="center"><b><?= (int) $b['jumlah_soal'] ?></b></td>
            <td class="act">
              <a class="btn btn-sm" href="<?= site_url('admin/soal/' . $b['id']) ?>">Kelola Soal</a>
              <button class="btn btn-ghost btn-sm" onclick='formBank(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
              <form method="post" action="<?= site_url('admin/bank/hapus/' . $b['id']) ?>" style="display:inline"
                    data-confirm="Hapus bank ini beserta <?= (int) $b['jumlah_soal'] ?> soal di dalamnya?">
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

<div class="modal-bg" id="mBank">
  <div class="modal">
    <form method="post" action="<?= site_url('admin/bank/simpan') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="b_id">
      <div class="modal-head"><h3 id="b_judul">Bank Soal Baru</h3><button type="button" onclick="closeModal('mBank')">&times;</button></div>
      <div class="modal-body">
        <div class="field"><label for="b_nama">Nama Bank</label><input type="text" id="b_nama" name="nama" required placeholder="UAS Semester 1"></div>
        <div class="field"><label for="b_mapel">Mata Pelajaran</label><input type="text" id="b_mapel" name="mapel" required placeholder="Matematika"></div>
        <div class="field"><label for="b_ket">Keterangan</label><input type="text" id="b_ket" name="keterangan" placeholder="opsional"></div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-ghost" type="button" onclick="closeModal('mBank')">Batal</button>
        <button class="btn" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
function formBank(b) {
  document.getElementById('b_judul').textContent = b ? 'Edit Bank Soal' : 'Bank Soal Baru';
  document.getElementById('b_id').value = b ? b.id : '';
  document.getElementById('b_nama').value = b ? b.nama : '';
  document.getElementById('b_mapel').value = b ? b.mapel : '';
  document.getElementById('b_ket').value = b && b.keterangan ? b.keterangan : '';
  openModal('mBank');
}
</script>
<?= $this->endSection() ?>
