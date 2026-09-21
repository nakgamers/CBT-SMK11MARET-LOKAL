<?= $this->extend('layout/auth') ?>

<?= $this->section('form') ?>
<form method="post" action="<?= site_url('login') ?>" autocomplete="off">
  <?= csrf_field() ?>
  <div class="field">
    <label for="nis">NIS</label>
    <input type="text" id="nis" name="nis" value="<?= esc(old('nis')) ?>" placeholder="Nomor Induk Siswa" required autofocus>
  </div>
  <div class="field">
    <label for="token">Password</label>
    <input type="text" id="token" name="token" placeholder="Sama dengan NIS" required maxlength="30" autocomplete="off">
    <div class="hint">Password login sama dengan NIS kamu.</div>
  </div>
  <button class="btn btn-block" type="submit">Masuk Ujian</button>
</form>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<a href="<?= site_url('admin/login') ?>">Login admin</a>
<?= $this->endSection() ?>
