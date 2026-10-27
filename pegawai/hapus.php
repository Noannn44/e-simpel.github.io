<?php

session_start();

require_once "../config/auth.php";

hanyaAdmin();

include "../config/database.php";

function kembali($pesan, $tujuan = "index.php")
{
    echo "<script>alert(" . json_encode($pesan) . ");window.location=" . json_encode($tujuan) . ";</script>";
    exit;
}

/* hanya boleh lewat POST + token CSRF */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    kembali("Permintaan tidak valid. Silakan muat ulang halaman lalu coba lagi.");
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    kembali("ID pegawai tidak valid.");
}

/* pegawai harus ada */

$cek = mysqli_prepare($koneksi, "SELECT id, nama FROM pegawai WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$pegawai = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

if (!$pegawai) {
    kembali("Data pegawai tidak ditemukan.");
}

/* hapus */

$del = mysqli_prepare($koneksi, "DELETE FROM pegawai WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($del, "i", $id);

if (mysqli_stmt_execute($del)) {
    kembali("Data pegawai " . $pegawai['nama'] . " berhasil dihapus.");
}

kembali("Data pegawai gagal dihapus.");