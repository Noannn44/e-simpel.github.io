<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

hanyaAdmin();

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
    kembali("ID surat tidak valid.");
}

/* surat harus ada */

$cek = mysqli_prepare($koneksi, "SELECT id, nomor_surat, file_surat FROM surat_masuk WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$surat = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

if (!$surat) {
    kembali("Data surat masuk tidak ditemukan.");
}

/* hapus surat + disposisinya dalam satu transaksi */

mysqli_begin_transaction($koneksi);

try {

    $d1 = mysqli_prepare($koneksi, "DELETE FROM disposisi_surat WHERE surat_id = ?");
    mysqli_stmt_bind_param($d1, "i", $id);
    mysqli_stmt_execute($d1);

    $d2 = mysqli_prepare($koneksi, "DELETE FROM surat_masuk WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($d2, "i", $id);
    mysqli_stmt_execute($d2);

    mysqli_commit($koneksi);

} catch (Throwable $e) {

    mysqli_rollback($koneksi);
    kembali("Gagal menghapus surat masuk. Silakan coba lagi.");
}

/* hapus berkas PDF (basename mencegah keluar dari folder uploads) */

if (!empty($surat['file_surat'])) {

    $path = __DIR__ . '/uploads/' . basename($surat['file_surat']);

    if (is_file($path)) {
        @unlink($path);
    }
}

kembali("Surat masuk nomor " . $surat['nomor_surat'] . " berhasil dihapus.");