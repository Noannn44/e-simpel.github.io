<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

/* =========================================================
   HANYA ADMIN YANG BOLEH MENGHAPUS DATA TAMU
========================================================= */

hanyaAdmin();

/* =========================================================
   HANYA LEWAT FORM (POST) + TOKEN CSRF
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo "<script>
        alert('Permintaan tidak valid. Silakan muat ulang halaman.');
        window.location='index.php';
    </script>";
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    echo "<script>
        alert('ID tamu tidak valid.');
        window.location='index.php';
    </script>";
    exit;
}

/* =========================================================
   CEK DATA ADA
========================================================= */

$cek = mysqli_prepare($koneksi, "SELECT id FROM tamu WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);

if (mysqli_stmt_num_rows($cek) === 0) {
    echo "<script>
        alert('Data tamu tidak ditemukan.');
        window.location='index.php';
    </script>";
    exit;
}

/* =========================================================
   HAPUS
========================================================= */

try {

    $stmt = mysqli_prepare($koneksi, "DELETE FROM tamu WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

} catch (Throwable $e) {

    error_log("buku_tamu/hapus.php: " . $e->getMessage());

    echo "<script>
        alert('Data tamu gagal dihapus.');
        window.location='index.php';
    </script>";
    exit;
}

echo "<script>
    alert('Data tamu berhasil dihapus.');
    window.location='index.php';
</script>";
exit;