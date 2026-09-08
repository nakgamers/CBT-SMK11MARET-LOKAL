<?= $this->extend('layout/admin') ?>

<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Analisis Butir Soal</h1>
    <p class="muted mb0">
      <?= esc($exam['nama']) ?> &middot; <?= esc($exam['bank_mapel'] ?? '-') ?> &middot;
      <?= $peserta ?> peserta selesai
    </p>
  </div>
  <div class="btn-row">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/ujian/hasil/' . $exam['id']) ?>">&larr; Daftar Nilai</a>
  </div>
</div>

<?php if ($butir === []): ?>
  <div class="card"><div class="empty">Belum ada peserta yang menyelesaikan ujian ini, jadi belum ada butir yang bisa dianalisis.</div></div>
<?php else: ?>

  <?php
      $mudah = $sedang = $sulit = 0;
      foreach ($butir as $b) {
          if ($b['persen'] === null) {
              continue;
          }
          if ($b['persen'] >= 70) {
              $mudah++;
          } elseif ($b['persen'] >= 30) {
              $sedang++;
          } else {
              $sulit++;
          }
      }
  ?>
  <div class="stats">
    <div class="stat"><div class="ic">&#128218;</div><div><b><?= count($butir) ?></b><span>Butir diujikan</span></div></div>
    <div class="stat"><div class="ic">&#128994;</div><div><b><?= $mudah ?></b><span>Mudah <span class="nowrap">(&ge;70% benar)</span></span></div></div>
    <div class="stat"><div class="ic">&#128993;</div><div><b><?= $sedang ?></b><span>Sedang <span class="nowrap">(30&ndash;69%)</span></span></div></div>
    <div class="stat"><div class="ic">&#128308;</div><div><b><?= $sulit ?></b><span>Sulit <span class="nowrap">(&lt;30% benar)</span></span></div></div>
  </div>

  <?php if ($peserta < 10): ?>
    <div class="alert alert-warn">
      Baru <b><?= $peserta ?> peserta</b> yang selesai. Angka tingkat kesukaran di
      bawah masih terlalu tipis untuk dijadikan dasar merevisi soal &mdash;
      tunggu sampai satu kelas penuh mengerjakan.
    </div>
  <?php endif ?>

  <div class="card">
    <div class="card-head">
      <h2>Per Butir</h2>
      <span class="small dim">diurutkan dari tersulit</span>
    </div>
    <div class="card-body">
      <p class="muted small mb0">
        Persentase dihitung dari peserta yang benar-benar menerima soal itu &mdash;
        pada ujian dengan soal diacak/dibatasi jumlahnya, tidak semua siswa
        mendapat soal yang sama. Kolom sebaran menunjukkan berapa siswa memilih
        tiap opsi; opsi bertanda hijau adalah kuncinya.
      </p>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr>
          <th>Soal</th>
          <th class="center">Kunci</th>
          <th class="center">Peserta</th>
          <th class="center">Benar</th>
          <th class="center">Salah</th>
          <th class="center">Kosong</th>
          <th>Tingkat Kesukaran</th>
          <th>Sebaran Pilihan</th>
        </tr></thead>
        <tbody>
        <?php foreach ($butir as $b): ?>
          <tr>
            <td style="min-width:280px">
              <div class="small"><?= esc(mb_strimwidth(trim(preg_replace('/\s+/', ' ', $b['teks'])), 0, 110, '…')) ?></div>
              <span class="dim small">bobot <?= (int) $b['bobot'] ?></span>
            </td>
            <td class="center"><span class="badge badge-selesai"><?= esc($b['kunci']) ?></span></td>
            <td class="center"><?= (int) $b['diterima'] ?></td>
            <td class="center" style="color:#065f46"><b><?= (int) $b['benar'] ?></b></td>
            <td class="center" style="color:#991b1b"><?= (int) $b['salah'] ?></td>
            <td class="center dim"><?= (int) $b['kosong'] ?></td>
            <td style="min-width:170px">
              <?php
                  $p = $b['persen'];
                  // samakan warna dengan kartu ringkasan: hijau/kuning/merah
                  $kls = $p === null ? 'nonaktif' : ($p >= 70 ? 'berlangsung' : ($p >= 30 ? 'sedang' : 'lewat'));
                  $lbl = $p === null ? 'belum ada data' : ($p >= 70 ? 'Mudah' : ($p >= 30 ? 'Sedang' : 'Sulit'));
              ?>
              <div class="bar-wrap" style="margin-bottom:.35rem" role="progressbar"
                   aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $p === null ? 0 : $p ?>">
                <div class="bar" style="width:<?= $p === null ? 0 : $p ?>%"></div>
              </div>
              <span class="badge badge-<?= $kls ?>"><?= $p === null ? $lbl : $lbl . ' · ' . number_format((float) $p, 1, ',', '.') . '%' ?></span>
            </td>
            <td style="min-width:270px">
              <div class="sebaran">
                <?php foreach (\App\Models\QuestionModel::OPSI as $k): ?>
                  <?php $n = $b['sebaran'][$k] ?? 0; ?>
                  <span class="pilih<?= $k === $b['kunci'] ? ' kunci' : '' ?><?= $n === 0 ? ' nol' : '' ?>"
                        title="Opsi <?= $k ?><?= $k === $b['kunci'] ? ' (kunci)' : '' ?>: <?= $n ?> siswa">
                    <b><?= $k ?><?= $k === $b['kunci'] ? '&#10003;' : '' ?></b> <i><?= $n ?></i>
                  </span>
                <?php endforeach ?>
              </div>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
