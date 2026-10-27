<?php
/* =========================================================
   URUTAN JABATAN PEGAWAI
   Dipakai oleh halaman laporan agar pegawai tersusun
   dari jabatan tertinggi (pimpinan) ke yang terendah.

   Angka kecil = jabatan lebih tinggi.
   Ubah / tambah kata kunci di bawah bila penamaan jabatan
   di data Anda berbeda.
========================================================= */

if (!function_exists('tingkatJabatan')) {

    function tingkatJabatan($jabatan, $unit_kerja = '')
    {
        // pegawai di unit "Pimpinan" selalu tingkat tertinggi
        if (strtolower(trim((string) $unit_kerja)) === 'pimpinan') {
            return 1;
        }

        $j = strtolower(trim((string) $jabatan));

        $aturan = [
            [1, '/kepala\s+(badan|dinas|bkpsdm)|^kaban|^kadis/'],
            [2, '/sekretaris/'],
            [3, '/kepala\s+bidang|^kabid/'],
            [4, '/kepala\s+(sub|seksi|unit|upt)|kasubag|kasubbag|kasubbid|kasi\b|subbagian|subbidang/'],
            [5, '/madya/'],
            [6, '/muda/'],
            [7, '/pertama|analis|pranata|pengadministrasi|arsiparis|auditor/'],
            [8, '/penyelia|terampil|mahir/'],
            [9, '/pengelola|pelaksana|staf|operator|pramu|pengemudi|petugas|resepsionis/'],
        ];

        foreach ($aturan as $a) {
            if (preg_match($a[1], $j)) {
                return $a[0];
            }
        }

        return 10;
    }
}

if (!function_exists('urutkanPegawai')) {

    /* Urutkan daftar pegawai: jabatan tertinggi dulu, lalu nama A-Z */
    function urutkanPegawai(array &$daftar)
    {
        foreach ($daftar as &$p) {
            $p['_tingkat'] = tingkatJabatan($p['jabatan'] ?? '', $p['unit_kerja'] ?? '');
        }
        unset($p);

        usort($daftar, function ($a, $b) {

            if ($a['_tingkat'] !== $b['_tingkat']) {
                return $a['_tingkat'] <=> $b['_tingkat'];
            }

            return strcasecmp($a['nama'] ?? '', $b['nama'] ?? '');
        });
    }
}