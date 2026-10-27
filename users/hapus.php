<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";
require_once "../config/role.php";

hanyaAdmin();

include "../config/database.php";

function kembali($pesan, $tujuan = "index.php")
{
    echo "<script>alert(" . json_encode($pesan) . ");window.location=" . json_encode($tujuan) . ";</script>";
    exit;
}

/* hanya boleh lewat form (POST) + token CSRF */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    kembali("Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.");
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    kembali("ID user tidak valid.");
}

/* user harus ada */

$stmt = mysqli_prepare($koneksi, "SELECT id, username, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$u) {
    kembali("Data user tidak ditemukan.");
}

/* tidak boleh menghapus akun sendiri */

if ($u['username'] === $_SESSION['username']) {
    kembali("Akun yang sedang Anda gunakan tidak dapat dihapus.");
}

/* tidak boleh menghapus administrator terakhir */

if (adalahAdminRole($u['role'])) {

    $q = mysqli_prepare($koneksi, "SELECT COUNT(*) AS jml FROM users WHERE LOWER(role) IN ('administrator','admin') AND id <> ?");
    mysqli_stmt_bind_param($q, "i", $id);
    mysqli_stmt_execute($q);
    $sisa = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($q))['jml'] ?? 0);

    if ($sisa < 1) {
        kembali("Tidak dapat menghapus Administrator terakhir.");
    }
}

/* hapus */

$del = mysqli_prepare($koneksi, "DELETE FROM users WHERE id = ?");
mysqli_stmt_bind_param($del, "i", $id);

if (mysqli_stmt_execute($del)) {
    kembali("User berhasil dihapus.");
}

kembali("User gagal dihapus.");