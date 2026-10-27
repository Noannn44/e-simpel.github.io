<?php
require_once "../config/auth.php";

hanyaAdmin();

include "../config/database.php";

/* =========================
   HANYA METODE POST
========================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

/* perlindungan CSRF */
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo "<script>alert('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');window.location='index.php';</script>";
    exit;
}

/* =========================
   AMBIL DATA
========================= */

$nip        = trim($_POST['nip'] ?? '');
$nama       = trim($_POST['nama'] ?? '');
$jabatan    = trim($_POST['jabatan'] ?? '');
$unit_kerja = trim($_POST['unit_kerja'] ?? '');
$status     = trim($_POST['status'] ?? '');

/* =========================
   PIMPINAN TIDAK PUNYA UNIT KERJA
   (Kepala Badan / Kepala Dinas)
========================= */

$jabatan_kecil = strtolower($jabatan);

if (preg_match('/kepala\s+(badan|dinas|bkpsdm)|^kaban|^kadis/', $jabatan_kecil)) {
    $unit_kerja = 'Pimpinan';
}

/* =========================
   NORMALISASI STATUS
========================= */

$status = (strtolower($status) === 'nonaktif') ? 'Nonaktif' : 'Aktif';

/* =========================
   VALIDASI WAJIB
========================= */

if ($nip === '' || $nama === '' || $jabatan === '' || $unit_kerja === '') {

    echo "<script>
            alert('Semua data pegawai wajib diisi.');
            window.history.back();
          </script>";

    exit;
}

/* =========================
   CEK NIP DUPLIKAT
========================= */

$cek = mysqli_prepare($koneksi, "SELECT id FROM pegawai WHERE nip = ? LIMIT 1");
mysqli_stmt_bind_param($cek, "s", $nip);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);

if (mysqli_stmt_num_rows($cek) > 0) {

    echo "<script>
            alert('NIP sudah terdaftar. Gunakan NIP yang berbeda.');
            window.history.back();
          </script>";

    exit;
}

mysqli_stmt_close($cek);

/* =========================
   SIMPAN DATA
========================= */

$stmt = mysqli_prepare(
    $koneksi,
    "INSERT INTO pegawai (nip, nama, jabatan, unit_kerja, status) VALUES (?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param($stmt, "sssss", $nip, $nama, $jabatan, $unit_kerja, $status);

$berhasil = mysqli_stmt_execute($stmt);

/* =========================
   HASIL
========================= */

if ($berhasil) {

    echo "<script>
            alert('Data pegawai berhasil ditambahkan.');
            window.location='index.php';
          </script>";

} else {

    echo "<script>
            alert('Data pegawai gagal disimpan.');
            window.history.back();
          </script>";

}

?>