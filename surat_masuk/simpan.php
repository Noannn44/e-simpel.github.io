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
   HANYA LEWAT FORM (POST)
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    kembali("Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.", "tambah.php");
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
   CEK DUPLIKAT (nomor + pengirim yang sama)
========================================================= */

$dup = mysqli_prepare($koneksi, "SELECT id FROM surat_masuk WHERE nomor_surat = ? AND pengirim = ? LIMIT 1");
mysqli_stmt_bind_param($dup, "ss", $nomor_surat, $pengirim);
mysqli_stmt_execute($dup);
mysqli_stmt_store_result($dup);

if (mysqli_stmt_num_rows($dup) > 0) {
    kembali("Surat dengan nomor dan pengirim yang sama sudah pernah dicatat.");
}

/* =========================================================
   BERKAS PDF (opsional)
========================================================= */

$nama_file   = null;
$lokasi_file = null;

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

    $folder = __DIR__ . '/uploads/';

    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        kembali("Folder unggahan tidak dapat dibuat.");
    }

    // larang eksekusi skrip di folder unggahan
    $ht = $folder . '.htaccess';

    if (!file_exists($ht)) {
        @file_put_contents($ht, "<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
    }

    $nama_file   = 'surat_masuk_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $lokasi_file = $folder . $nama_file;

    if (!move_uploaded_file($file['tmp_name'], $lokasi_file)) {
        kembali("File PDF gagal disimpan ke server.");
    }
}

/* =========================================================
   SIMPAN
========================================================= */

try {

    $stmt = mysqli_prepare($koneksi, "
        INSERT INTO surat_masuk
            (nomor_surat, tanggal_surat, tanggal_diterima, pengirim, perihal,
             sifat, jenis_surat, tanggal_acara, tempat_acara, agenda_acara, file_surat)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssssss",
        $nomor_surat, $tanggal_surat, $tanggal_diterima, $pengirim, $perihal,
        $sifat, $jenis_surat, $tanggal_acara, $tempat_acara, $agenda_acara, $nama_file
    );

    mysqli_stmt_execute($stmt);

    $id_baru = mysqli_insert_id($koneksi);

} catch (Throwable $e) {

    // batalkan berkas yang sudah terunggah agar tidak menjadi sampah
    if ($lokasi_file !== null && is_file($lokasi_file)) {
        @unlink($lokasi_file);
    }

    error_log("surat_masuk/simpan.php: " . $e->getMessage());

    kembali("Surat gagal disimpan. Periksa kembali data yang diisi.");
}

if ($id_baru > 0) {
    kembali("Surat masuk berhasil disimpan.", "detail.php?id=" . $id_baru);
}

kembali("Surat berhasil disimpan.", "index.php");