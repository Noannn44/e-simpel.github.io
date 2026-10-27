<?php
session_start();

/* catat dulu sebelum sesi dihapus */
if (!empty($_SESSION['username'])) {

    require_once "config/log.php";
    include "config/database.php";

    if (isset($koneksi)) {
        catatAktivitas($koneksi, 'Logout', 'Akun', 'Keluar dari aplikasi');
    }
}

session_unset();     // Menghapus semua session
session_destroy();   // Menghancurkan session

header("Location: login.php");
exit;
?>