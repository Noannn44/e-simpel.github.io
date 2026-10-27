<?php

/* =========================================================
   CEK LOGIN
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CEK SUDAH LOGIN
========================================================= */

function cekLogin()
{
    if (!isset($_SESSION['username'])) {
        header("Location: ../login.php");
        exit;
    }
}


/* =========================================================
   NORMALISASI ROLE
========================================================= */

function roleUser()
{
    if (!isset($_SESSION['role'])) {
        return null;
    }

    $role = strtolower(trim($_SESSION['role']));

    /*
       Menyesuaikan data lama:
       Administrator -> admin
       Operator      -> operator
    */

    if ($role === 'administrator') {
        return 'admin';
    }

    if ($role === 'operator') {
        return 'operator';
    }

    if ($role === 'admin') {
        return 'admin';
    }

    return $role;
}


/* =========================================================
   CEK ROLE
========================================================= */

function cekRole($role_yang_diizinkan)
{
    cekLogin();

    $role = roleUser();

    if ($role === null) {
        header("Location: ../login.php");
        exit;
    }

    /*
       Normalisasi role yang diizinkan
    */

    $role_yang_diizinkan = array_map(function ($item) {

        $item = strtolower(trim($item));

        if ($item === 'administrator') {
            return 'admin';
        }

        return $item;

    }, $role_yang_diizinkan);


    /*
       Cek apakah role pengguna diizinkan
    */

    if (!in_array($role, $role_yang_diizinkan, true)) {

        header(
            "Location: ../dashboard/index.php?error=akses_ditolak"
        );

        exit;
    }
}


/* =========================================================
   CEK ADMIN
========================================================= */

function hanyaAdmin()
{
    cekRole(['admin']);
}


/* =========================================================
   CEK ADMIN / OPERATOR
========================================================= */

function adminDanOperator()
{
    cekRole(['admin', 'operator']);
}


/* =========================================================
   CSRF PROTECTION
========================================================= */

/*
   Membuat token CSRF
*/

function csrf_token()
{
    if (
        empty($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/* =========================================================
   VALIDASI TOKEN CSRF
========================================================= */

function verify_csrf_token($token)
{
    if (
        empty($token) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/* =========================================================
   RIWAYAT AKTIVITAS (pencatatan otomatis)
========================================================= */

require_once __DIR__ . '/log.php';