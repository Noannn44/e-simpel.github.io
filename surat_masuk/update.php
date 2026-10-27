<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

adminDanOperator();

include "../config/database.php";

date_default_timezone_set('Asia/Makassar');

function kembali($pesan, $tujuan = null)
{
    $aksi = $tujuan === null
        ? "window.history.back();"
        : "window.location=" . json_encode($tujuan) . ";";

    echo "<script>alert(" . json_encode($pesan) . ");" . $aksi . "</script>";
    exit;
}

function tanggalValid($tgl)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $tgl);

    return $d && $d->format('Y-m-d') === $tgl && (int) $d->format('Y') >= 2000 && (int) $d->format('Y') <= 2100;
}

/* =========================================================
   HANYA LEWAT FORM (POST) + TOKEN CSRF
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    kembali("Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.", "index.php");
}

/* =========================================================
   DATA LAMA
========================================================= */

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    kembali("ID surat tidak valid.", "index.php");
}

$cek = mysqli_prepare($koneksi, "SELECT file_surat FROM surat_masuk WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$hasil_cek = mysqli_stmt_get_result($cek);
$data_lama = $hasil_cek ? mysqli_fetch_assoc($hasil_cek) : null;

if (!$data_lama) {
    kembali("Data surat tidak ditemukan.", "index.php");
}

/* =========================================================
   AMBIL DATA
========================================================= */

$nomor_surat      = trim($_POST['nomor_surat'] ?? '');
$tanggal_surat    = trim($_POST['tanggal_surat'] ?? '');
$tanggal_diterima = trim($_POST['tanggal_diterima'] ?? '');
$pengirim         = trim($_POST['pengirim'] ?? '');
$perihal          = trim($_POST['perihal'] ?? '');
$sifat            = trim($_POST['sifat'] ?? 'Biasa');
$jenis_surat      = trim($_POST['jenis_surat'] ?? 'Biasa');

$tanggal_acara = trim($_POST['tanggal_acara'] ?? '');
$tempat_acara  = trim($_POST['tempat_acara'] ?? '');
$agenda_acara  = trim($_POST['agenda_acara'] ?? '');

/* =========================================================
   VALIDASI
========================================================= */

if ($nomor_surat === '' || $tanggal_surat === '' || $tanggal_diterima === '' || $pengirim === '' || $perihal === '') {
    kembali("Nomor surat, tanggal, pengirim, dan perihal wajib diisi.");
}

if (mb_strlen($nomor_surat) > 100) {
    kembali("Nomor surat maksimal 100 karakter.");
}

if (mb_strlen($pengirim) > 150) {
    kembali("Pengirim maksimal 150 karakter.");
}

if (!tanggalValid($tanggal_surat) || !tanggalValid($tanggal_diterima)) {
    kembali("Format tanggal tidak valid.");
}

if (!in_array($sifat, ['Biasa', 'Penting', 'Segera', 'Sangat Segera'], true)) {
    $sifat = 'Biasa';
}

if (!in_array($jenis_surat, ['Biasa', 'Undangan'], true)) {
    $jenis_surat = 'Biasa';
}

if ($jenis_surat === 'Undangan') {

    if ($tanggal_acara === '' || $tempat_acara === '' || $agenda_acara === '') {
        kembali("Surat undangan wajib mengisi tanggal, tempat, dan agenda acara.");
    }

    if (!tanggalValid($tanggal_acara)) {
        kembali("Format tanggal acara tidak valid.");
    }

    if (mb_strlen($tempat_acara) > 200) {
        kembali("Tempat acara maksimal 200 karakter.");
    }

} else {

    // surat biasa tidak menyimpan data kegiatan
    $tanggal_acara = null;
    $tempat_acara  = null;
    $agenda_acara  = null;
}

/* =========================================================
   CEK DUPLIKAT (selain surat ini sendiri)
========================================================= */

$dup = mysqli_prepare($koneksi, "SELECT id FROM surat_masuk WHERE nomor_surat = ? AND pengirim = ? AND id <> ? LIMIT 1");
mysqli_stmt_bind_param($dup, "ssi", $nomor_surat, $pengirim, $id);
mysqli_stmt_execute($dup);
mysqli_stmt_store_result($dup);

if (mysqli_stmt_num_rows($dup) > 0) {
    kembali("Surat lain dengan nomor dan pengirim yang sama sudah pernah dicatat.");
}

/* =========================================================
   BERKAS PDF (opsional, mengganti berkas lama)
========================================================= */

$folder_upload = __DIR__ . '/uploads/';
$file_lama     = (string) ($data_lama['file_surat'] ?? '');
$file_simpan   = $file_lama !== '' ? $file_lama : null;
$file_baru     = null;
$lokasi_baru   = null;

if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] !== UPLOAD_ERR_NO_FILE) {

    $file = $_FILES['file_surat'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        kembali("File gagal diunggah. Silakan coba lagi.");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        kembali("Ukuran file terlalu besar. Maksimal 5 MB.");
    }

    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        kembali("File harus berformat PDF.");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';

    if ($finfo) {
        finfo_close($finfo);
    }

    // isi berkas harus benar-benar diawali tanda PDF
    $awal = file_get_contents($file['tmp_name'], false, null, 0, 5);

    if ($mime !== 'application/pdf' || $awal !== '%PDF-') {
        kembali("File yang diunggah bukan PDF yang valid.");
    }

    if (!is_dir($folder_upload) && !mkdir($folder_upload, 0755, true)) {
        kembali("Folder unggahan tidak dapat dibuat.");
    }

    // larang eksekusi skrip di folder unggahan
    $ht = $folder_upload . '.htaccess';

    if (!file_exists($ht)) {
        @file_put_contents($ht, "<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
    }

    $file_baru   = 'surat_masuk_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $lokasi_baru = $folder_upload . $file_baru;

    if (!move_uploaded_file($file['tmp_name'], $lokasi_baru)) {
        kembali("File PDF gagal disimpan ke server.");
    }

    $file_simpan = $file_baru;
}

/* =========================================================
   SIMPAN PERUBAHAN
========================================================= */

try {

    $stmt = mysqli_prepare($koneksi, "
        UPDATE surat_masuk SET
            nomor_surat      = ?,
            tanggal_surat    = ?,
            tanggal_diterima = ?,
            pengirim         = ?,
            perihal          = ?,
            sifat            = ?,
            jenis_surat      = ?,
            tanggal_acara    = ?,
            tempat_acara     = ?,
            agenda_acara     = ?,
            file_surat       = ?
        WHERE id = ?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssssssi",
        $nomor_surat, $tanggal_surat, $tanggal_diterima, $pengirim, $perihal,
        $sifat, $jenis_surat, $tanggal_acara, $tempat_acara, $agenda_acara,
        $file_simpan, $id
    );

    mysqli_stmt_execute($stmt);

} catch (Throwable $e) {

    // batalkan berkas baru agar tidak menjadi sampah; berkas lama tetap aman
    if ($lokasi_baru !== null && is_file($lokasi_baru)) {
        @unlink($lokasi_baru);
    }

    error_log("surat_masuk/update.php: " . $e->getMessage());

    kembali("Surat gagal diperbarui. Periksa kembali data yang diisi.");
}

/* =========================================================
   HAPUS BERKAS LAMA (hanya setelah database berhasil)
========================================================= */

if ($file_baru !== null && $file_lama !== '') {

    $lokasi_lama = $folder_upload . basename($file_lama);

    if (is_file($lokasi_lama)) {
        @unlink($lokasi_lama);
    }
}

kembali("Surat berhasil diperbarui.", "detail.php?id=" . $id);