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

$id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$nama     = trim($_POST['nama'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role_in  = $_POST['role'] ?? '';

if ($id <= 0) {
    kembali("ID user tidak valid.", "index.php");
}

$balik = "edit.php?id=" . $id;

/* user harus ada */

$stmt = mysqli_prepare($koneksi, "SELECT id, nama, username, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$lama) {
    kembali("Data user tidak ditemukan.", "index.php");
}

$akun_saya  = ($lama['username'] === $_SESSION['username']);
$lama_admin = adalahAdminRole($lama['role']);

/* validasi */

if ($nama === '' || $username === '' || $role_in === '') {
    kembali("Nama, username, dan role wajib diisi.", $balik);
}

if (mb_strlen($nama) > 100) {
    kembali("Nama maksimal 100 karakter.", $balik);
}

if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    kembali("Username 3-50 karakter, hanya huruf, angka, titik, garis bawah, atau strip.", $balik);
}

if ($password !== '' && strlen($password) < 6) {
    kembali("Password baru minimal 6 karakter.", $balik);
}

/* role */

if ($akun_saya && $lama_admin) {

    // akun sendiri yang administrator: role tidak boleh diubah
    $role = $lama['role'];

} else {

    $role = roleKeDb($koneksi, $role_in);

    if ($role === null) {
        kembali("Role tidak valid.", $balik);
    }
}

/* jangan sampai administrator terakhir diturunkan */

if ($lama_admin && !adalahAdminRole($role)) {

    $q = mysqli_prepare($koneksi, "SELECT COUNT(*) AS jml FROM users WHERE LOWER(role) IN ('administrator','admin') AND id <> ?");
    mysqli_stmt_bind_param($q, "i", $id);
    mysqli_stmt_execute($q);
    $sisa = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($q))['jml'] ?? 0);

    if ($sisa < 1) {
        kembali("Tidak dapat mengubah role. Sistem harus memiliki minimal satu Administrator.", $balik);
    }
}

/* username tidak boleh kembar (selain milik user ini) */

$cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
mysqli_stmt_bind_param($cek, "si", $username, $id);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);

if (mysqli_stmt_num_rows($cek) > 0) {
    kembali("Username sudah digunakan user lain.", $balik);
}

/* simpan */

if ($password !== '') {

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $upd = mysqli_prepare($koneksi, "UPDATE users SET nama = ?, username = ?, password = ?, role = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "ssssi", $nama, $username, $hash, $role, $id);

} else {

    $upd = mysqli_prepare($koneksi, "UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "sssi", $nama, $username, $role, $id);
}

if (!mysqli_stmt_execute($upd)) {
    kembali("Gagal memperbarui data user.", $balik);
}

/* jika yang diedit akun sendiri, perbarui sesi agar tidak terlempar keluar */

if ($akun_saya) {
    $_SESSION['username'] = $username;
    $_SESSION['nama']     = $nama;
    $_SESSION['role']     = $role;
}

kembali("Data user berhasil diperbarui.", "detail.php?id=" . $id);