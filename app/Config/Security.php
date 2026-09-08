<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * CSRF Protection Method
     * --------------------------------------------------------------------------
     *
     * Protection Method for Cross Site Request Forgery protection.
     *
     * @var string 'cookie' or 'session'
     */
    /**
     * Simpan token CSRF di SESI, bukan cookie.
     *
     * Mode 'cookie' (bawaan) menyetel cookie sekali dengan masa berlaku tetap
     * dan TIDAK memperpanjangnya pada request berikutnya. Akibatnya admin yang
     * membuka halaman kelola soal, menyiapkan berkas Excel selama beberapa jam,
     * lalu menekan Import akan ditolak "The action you requested is not allowed"
     * padahal ia masih terlihat login — token di form sudah tidak punya
     * pasangan cookie. Dengan mode sesi, token hidup selama sesi login masih
     * hidup, dan sesi diperpanjang setiap aktivitas.
     */
    public string $csrfProtection = 'session';

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Randomization
     * --------------------------------------------------------------------------
     *
     * Randomize the CSRF Token for added security.
     */
    public bool $tokenRandomize = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Name
     * --------------------------------------------------------------------------
     *
     * Token name for Cross Site Request Forgery protection.
     */
    public string $tokenName = 'csrf_test_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Header Name
     * --------------------------------------------------------------------------
     *
     * Header name for Cross Site Request Forgery protection.
     */
    public string $headerName = 'X-CSRF-TOKEN';

    /**
     * --------------------------------------------------------------------------
     * CSRF Cookie Name
     * --------------------------------------------------------------------------
     *
     * Cookie name for Cross Site Request Forgery protection.
     */
    public string $cookieName = 'csrf_cookie_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Expires
     * --------------------------------------------------------------------------
     *
     * Expiration time for Cross Site Request Forgery protection cookie.
     *
     * Defaults to two hours (in seconds).
     */
    /**
     * Hanya berpengaruh pada mode 'cookie'. Kita memakai mode 'session', jadi
     * masa berlaku token mengikuti masa sesi (lihat Config\Session::$expiration).
     */
    public int $expires = 14400;

    /**
     * --------------------------------------------------------------------------
     * CSRF Regenerate
     * --------------------------------------------------------------------------
     *
     * Regenerate CSRF Token on every submission.
     */
    public bool $regenerate = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Redirect
     * --------------------------------------------------------------------------
     *
     * Redirect to previous page with error on failure.
     *
     * @see https://codeigniter4.github.io/userguide/libraries/security.html#redirection-on-failure
     */
    /**
     * Selalu alihkan kembali ke halaman sebelumnya dengan pesan, bukan
     * menampilkan halaman error 403. Bawaan CI4 hanya melakukannya di
     * production; di development guru melihat layar exception yang menakutkan.
     * Dengan ini, gagal CSRF cukup memunculkan alert merah + instruksi.
     */
    public bool $redirect = true;
}
