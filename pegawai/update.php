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
   CEK ID
========================= */

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {

    echo "<script>
            alert('ID pegawai tidak valid.');
            window.location='index.php';
          </script>";

    exit;
}

/* =========================
   PASTIKAN DATA ADA
========================= */

$ada = mysqli_prepare($koneksi, "SELECT id FROM pegawai WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($ada, "i", $id);
mysqli_stmt_execute($ada);
mysqli_stmt_store_result($ada);

if (mysqli_stmt_num_rows($ada) === 0) {

    echo "<script>
            alert('Data pegawai tidak ditemukan.');
            window.location='index.php';
          </script>";

    exit;
}

mysqli_stmt_close($ada);

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
   VALIDASI
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

$cek = mysqli_prepare($koneksi, "SELECT id FROM pegawai WHERE nip = ? AND id <> ? LIMIT 1");
mysqli_stmt_bind_param($cek, "si", $nip, $id);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);

if (mysqli_stmt_num_rows($cek) > 0) {

    echo "<script>
            alert('NIP sudah digunakan oleh pegawai lain.');
            window.history.back();
          </script>";

    exit;
}

mysqli_stmt_close($cek);

/* =========================
   UPDATE
========================= */

$stmt = mysqli_prepare(
    $koneksi,
    "UPDATE pegawai
     SET nip = ?, nama = ?, jabatan = ?, unit_kerja = ?, status = ?
     WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, "sssssi", $nip, $nama, $jabatan, $unit_kerja, $status, $id);

$berhasil = mysqli_stmt_execute($stmt);

/* =========================
   HASIL
========================= */

if ($berhasil) {

    echo "<script>
            alert('Data pegawai berhasil diperbarui.');
            window.location='detail.php?id=$id';
          </script>";

} else {

    echo "<script>
            alert('Data pegawai gagal diperbarui.');
            window.history.back();
          </script>";

}

?>