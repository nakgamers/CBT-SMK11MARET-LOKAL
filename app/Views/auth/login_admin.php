<?= $this->extend('layout/auth') ?>

<?= $this->section('form') ?>
<form method="post" action="<?= site_url('admin/login') ?>">
  <?= csrf_field() ?>
  <div class="field">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= esc(old('username')) ?>" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
  </div>
  <button class="btn btn-block" type="submit">Masuk</button>
</form>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<a href="<?= site_url('login') ?>">Login siswa</a>
<?= $this->endSection() ?>
