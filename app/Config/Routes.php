<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ---------------------------------------------------------------- siswa (publik + terproteksi)
$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->get('logout', 'Auth::logout');

$routes->get('debug-db', 'Debug::db'); // TEMPORER — hapus setelah diagnosa

$routes->group('siswa', ['filter' => 'studentAuth'], static function ($routes): void {
    $routes->get('/', 'Student::index');
    $routes->get('ujian/(:num)', 'Exam::mulai/$1');
    $routes->get('kerjakan/(:num)', 'Exam::kerjakan/$1');
    $routes->post('jawab/(:num)', 'Exam::jawab/$1');       // AJAX autosave
    $routes->post('selesai/(:num)', 'Exam::selesai/$1');
    $routes->get('hasil/(:num)', 'Exam::hasil/$1');

    // absen selfie
    $routes->get('absen/(:num)', 'Selfie::index/$1');
    $routes->post('absen/(:num)/upload', 'Selfie::upload/$1');   // AJAX
    $routes->get('absen/(:num)/lihat/(:segment)', 'Selfie::lihat/$1/$2');
});

// ---------------------------------------------------------------- admin
$routes->get('admin/login', 'Admin\Auth::login');
$routes->post('admin/login', 'Admin\Auth::attempt');
$routes->get('admin/logout', 'Admin\Auth::logout');

$routes->group('admin', ['filter' => 'adminAuth'], static function ($routes): void {
    $routes->get('/', 'Admin\Dashboard::index');

    // siswa
    $routes->get('siswa', 'Admin\Students::index');
    $routes->post('siswa/simpan', 'Admin\Students::simpan');
    $routes->post('siswa/hapus/(:num)', 'Admin\Students::hapus/$1');
    $routes->post('siswa/import', 'Admin\Students::import');
    $routes->get('siswa/template', 'Admin\Students::template');
    $routes->post('siswa/reset-token/(:num)', 'Admin\Students::resetToken/$1');
    $routes->get('siswa/kartu', 'Admin\Students::kartu');

    // bank soal
    $routes->get('bank', 'Admin\Banks::index');
    $routes->post('bank/simpan', 'Admin\Banks::simpan');
    $routes->post('bank/hapus/(:num)', 'Admin\Banks::hapus/$1');

    // soal
    $routes->get('soal/(:num)', 'Admin\Questions::index/$1');
    $routes->post('soal/(:num)/simpan', 'Admin\Questions::simpan/$1');
    $routes->post('soal/(:num)/hapus/(:num)', 'Admin\Questions::hapus/$1/$2');
    $routes->post('soal/(:num)/import', 'Admin\Questions::import/$1');
    $routes->get('soal/template', 'Admin\Questions::template');

    // ujian
    $routes->get('ujian', 'Admin\Exams::index');
    $routes->post('ujian/simpan', 'Admin\Exams::simpan');
    $routes->post('ujian/hapus/(:num)', 'Admin\Exams::hapus/$1');
    $routes->get('ujian/hasil/(:num)', 'Admin\Exams::hasil/$1');
    $routes->get('ujian/analisis/(:num)', 'Admin\Exams::analisis/$1');
    $routes->get('ujian/jawaban/(:num)/(:num)', 'Admin\Exams::jawaban/$1/$2');
    $routes->get('ujian/export/(:num)', 'Admin\Exams::export/$1');
    $routes->post('ujian/reset/(:num)', 'Admin\Exams::resetAttempt/$1');

    // absen selfie
    $routes->get('absen', 'Admin\Selfie::index');
    $routes->get('absen/lihat/(:num)/full', 'Admin\Selfie::lihat/$1/full');
    $routes->get('absen/lihat/(:num)', 'Admin\Selfie::lihat/$1');
    $routes->post('absen/hapus/(:num)', 'Admin\Selfie::hapus/$1');
    $routes->post('absen/hapus-ujian/(:num)', 'Admin\Selfie::hapusUjian/$1');

    // akun admin sendiri
    $routes->get('akun', 'Admin\Akun::index');
    $routes->post('akun/password', 'Admin\Akun::password');
});
