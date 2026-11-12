<?php

/* =========================================================
   KETIDAKHADIRAN PEGAWAI (TUGAS LUAR DAN CUTI)
   - membuat tabel bila belum ada (aman dipanggil berulang)
   - fungsi bantu tanggal dan status
========================================================= */

function siapkanTabelKetidakhadiran($koneksi)
{
    static $sudah = false;

    if ($sudah) {
        return;
    }

    $sudah = true;

    try {

        mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS `ketidakhadiran` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `pegawai_id` int(11) NOT NULL,
            `jenis` enum('Tugas Luar','Cuti') NOT NULL DEFAULT 'Tugas Luar',
            `tanggal_mulai` date NOT NULL,
            `tanggal_selesai` date NOT NULL,
            `tujuan` varchar(255) DEFAULT NULL,
            `keperluan` varchar(255) DEFAULT NULL,
            `no_surat_tugas` varchar(100) DEFAULT NULL,
            `keterangan` text DEFAULT NULL,
            `dicatat_oleh` varchar(100) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_pegawai` (`pegawai_id`),
            KEY `idx_tanggal` (`tanggal_mulai`, `tanggal_selesai`),
            CONSTRAINT `fk_ketidakhadiran_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    } catch (Throwable $e) {
        // diabaikan; halaman menampilkan data kosong bila tabel gagal dibuat
    }
}

function khTanggal($v)
{
    return (!empty($v) && $v !== '0000-00-00') ? date('d-m-Y', strtotime($v)) : '-';
}

function khTanggalPanjang($v)
{
    if (empty($v) || $v === '0000-00-00') {
        return '-';
    }

    $hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t     = strtotime($v);

    return $hari[(int) date('w', $t)] . ', ' . date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function khLamaHari($mulai, $selesai)
{
    $a = strtotime($mulai);
    $b = strtotime($selesai);

    if (!$a || !$b || $b < $a) {
        return 1;
    }

    return (int) round(($b - $a) / 86400) + 1;
}

/* status terhadap tanggal hari ini */
function khStatus($mulai, $selesai, $hari_ini)
{
    if ($mulai <= $hari_ini && $selesai >= $hari_ini) {
        return 'berlangsung';
    }

    if ($mulai > $hari_ini) {
        return 'akan';
    }

    return 'selesai';
}

function khLabelStatus($status)
{
    $peta = ['berlangsung' => 'Sedang berlangsung', 'akan' => 'Akan datang', 'selesai' => 'Selesai'];

    return $peta[$status] ?? '-';
}