<?php

/**
 * Pesan keamanan berbahasa Indonesia.
 *
 * Menimpa Language/en/Security.php milik framework. Alasannya, pesan bawaan
 * "The action you requested is not allowed." tidak memberi tahu guru apa yang
 * harus dilakukan; padahal 99% kejadiannya adalah halaman yang dibiarkan
 * terbuka lama sehingga token CSRF-nya kedaluwarsa.
 */

return [
    'disallowedAction' => 'Sesi formulir sudah kedaluwarsa karena halaman terlalu lama dibiarkan terbuka. '
        . 'Muat ulang halaman ini (F5), lalu ulangi unggah berkasnya. Data yang sudah tersimpan tidak hilang.',
];
