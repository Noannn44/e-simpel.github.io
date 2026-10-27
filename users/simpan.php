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

function kembali($pesan, $tujuan)
{
    echo "<script>alert(" . json_encode($pesan) . ");window.location=" . json_encode($tujuan) . ";</script>";
    exit;
}

/* hanya boleh lewat form (POST) */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

/* perlindungan CSRF */
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    kembali("Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.", "index.php");
}

$nama     = trim($_POST['nama'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role_in  = $_POST['role'] ?? '';

/* validasi */

if ($nama === '' || $username === '' || $password === '' || $role_in === '') {
    kembali("Semua field wajib diisi.", "tambah.php");
}

if (mb_strlen($nama) > 100) {
    kembali("Nama maksimal 100 karakter.", "tambah.php");
}

if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    kembali("Username 3-50 karakter, hanya huruf, angka, titik, garis bawah, atau strip.", "tambah.php");
}

if (strlen($password) < 6) {
    kembali("Password minimal 6 karakter.", "tambah.php");
}

$role = roleKeDb($koneksi, $role_in);

if ($role === null) {
    kembali("Role tidak valid.", "tambah.php");
}

/* username tidak boleh kembar */

$cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($cek, "s", $username);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);

if (mysqli_stmt_num_rows($cek) > 0) {
    kembali("Username sudah digunakan, silakan pilih username lain.", "tambah.php");
}

/* simpan */

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($koneksi, "INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssss", $nama, $username, $hash, $role);

if (mysqli_stmt_execute($stmt)) {
    kembali("User berhasil ditambahkan.", "index.php");
}

kembali("Gagal menyimpan user.", "tambah.php");