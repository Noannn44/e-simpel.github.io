<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

hanyaAdmin();

include "../config/database.php";
require_once "../config/log.php";
require_once "../config/ketidakhadiran.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo "<script>alert('Permintaan tidak valid.'); window.location='index.php';</script>";
    exit;
}

siapkanTabelKetidakhadiran($koneksi);

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {

    $st = mysqli_prepare($koneksi, "DELETE FROM ketidakhadiran WHERE id = ?");
    mysqli_stmt_bind_param($st, "i", $id);
    mysqli_stmt_execute($st);

    catatAktivitas($koneksi, 'hapus', 'ketidakhadiran', 'Menghapus data ketidakhadiran ID ' . $id);
}

header("Location: index.php?pesan=hapus");
exit;