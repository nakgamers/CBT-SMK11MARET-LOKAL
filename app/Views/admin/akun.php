<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Ganti Password</h1>
    <p class="muted mb0">Akun <b><?= esc($admin['username']) ?></b> &middot; <?= esc($admin['nama']) ?></p>
  </div>
</div>

<div class="card" style="max-width:520px">
  <div class="card-body">
    <form method="post" action="<?= site_url('admin/akun/password') ?>" autocomplete="off">
      <?= csrf_field() ?>

      <div class="field">
        <label for="p_lama">Password Sekarang</label>
        <input type="password" id="p_lama" name="password_lama" required autocomplete="current-password">
      </div>

      <div class="field">
        <label for="p_baru">Password Baru</label>
        <input type="password" id="p_baru" name="password_baru" required minlength="8" autocomplete="new-password">
        <div class="hint">Minimal 8 karakter.</div>
      </div>

      <div class="field">
        <label for="p_ulang">Ulangi Password Baru</label>
        <input type="password" id="p_ulang" name="password_ulang" required minlength="8" autocomplete="new-password">
        <div class="hint" id="p_cocok"></div>
      </div>

      <div class="btn-row">
        <button class="btn" type="submit">Simpan Password</button>
        <a class="btn btn-ghost" href="<?= site_url('admin') ?>">Batal</a>
      </div>
    </form>
  </div>
</div>

<div class="alert alert-info" style="max-width:520px">
  Lupa password dan tidak bisa masuk? Reset dari terminal server:
  <br><span class="mono">php spark cbt:admin <?= esc($admin['username']) ?> &lt;password-baru&gt;</span>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
/* cocokkan konfirmasi sambil mengetik: lebih baik daripada gagal setelah submit */
const pb = document.getElementById('p_baru');
const pu = document.getElementById('p_ulang');
const ket = document.getElementById('p_cocok');
function cek() {
  if (!pu.value) { ket.textContent = ''; ket.className = 'hint'; return; }
  const sama = pb.value === pu.value;
  ket.textContent = sama ? 'Cocok.' : 'Belum sama dengan password baru.';
  ket.className = 'hint ' + (sama ? 'hint-ok' : 'hint-bad');
}
pb.addEventListener('input', cek);
pu.addEventListener('input', cek);
</script>
<?= $this->endSection() ?>
