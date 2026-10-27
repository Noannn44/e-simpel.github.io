<?php

/* =========================================================
   RIWAYAT AKTIVITAS
   - Mencatat siapa melakukan apa (tambah, ubah, hapus, login, dll)
   - Pencatatan otomatis untuk halaman simpan/update/hapus
   - Kegagalan mencatat TIDAK pernah mengganggu halaman utama
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* pengguna saat halaman mulai dimuat (dibutuhkan untuk logout) */
$GLOBALS['_log_user_awal'] = $_SESSION['username'] ?? null;
$GLOBALS['_log_role_awal'] = $_SESSION['role'] ?? null;

/*
   Mencatat satu aktivitas.
   $aksi : Menambah | Mengubah | Menghapus | Login | Logout | Cadangan
*/
function catatAktivitas($koneksi, $aksi, $modul, $keterangan = '', $username = null, $role = null)
{
    try {

        if (!$koneksi) {
            return false;
        }

        $username = $username ?? ($_SESSION['username'] ?? ($GLOBALS['_log_user_awal'] ?? ''));
        $role     = $role     ?? ($_SESSION['role']     ?? ($GLOBALS['_log_role_awal'] ?? ''));

        if ($username === '' || $username === null) {
            return false;
        }

        $zona  = new DateTimeZone('Asia/Makassar');
        $waktu = (new DateTime('now', $zona))->format('Y-m-d H:i:s');
        $ip    = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $keterangan = mb_substr((string) $keterangan, 0, 250);

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO riwayat_aktivitas (waktu, username, role, aksi, modul, keterangan, alamat_ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $username = (string) $username;
        $role     = (string) $role;

        mysqli_stmt_bind_param($stmt, "sssssss", $waktu, $username, $role, $aksi, $modul, $keterangan, $ip);
        mysqli_stmt_execute($stmt);

        return true;

    } catch (Throwable $e) {

        return false;
    }
}

/* =========================================================
   PENCATATAN OTOMATIS (dijalankan di akhir permintaan)
========================================================= */

function _logPetaHalaman()
{
    return [
        'surat_masuk/simpan.php'   => ['Menambah', 'Surat Masuk'],
        'surat_masuk/update.php'   => ['Mengubah', 'Surat Masuk'],
        'surat_masuk/hapus.php'    => ['Menghapus', 'Surat Masuk'],
        'surat_keluar/tambah.php'  => ['Menambah', 'Surat Keluar'],
        'surat_keluar/edit.php'    => ['Mengubah', 'Surat Keluar'],
        'surat_keluar/hapus.php'   => ['Menghapus', 'Surat Keluar'],
        'buku_tamu/tambah.php'     => ['Menambah', 'Buku Tamu'],
        'buku_tamu/edit.php'       => ['Mengubah', 'Buku Tamu'],
        'buku_tamu/hapus.php'      => ['Menghapus', 'Buku Tamu'],
        'pegawai/simpan.php'       => ['Menambah', 'Data Pegawai'],
        'pegawai/update.php'       => ['Mengubah', 'Data Pegawai'],
        'pegawai/hapus.php'        => ['Menghapus', 'Data Pegawai'],
        'users/simpan.php'         => ['Menambah', 'Data User'],
        'users/update.php'         => ['Mengubah', 'Data User'],
        'users/hapus.php'          => ['Menghapus', 'Data User'],
        'surat_masuk/disposisi.php' => ['Mengubah', 'Disposisi'],
    ];
}

function _logKeterangan()
{
    $bagian = [];

    foreach (['nomor_surat', 'nama', 'nama_tamu_manual', 'username', 'tujuan', 'perihal', 'keperluan'] as $k) {

        if (!empty($_POST[$k]) && is_string($_POST[$k])) {
            $bagian[] = trim($_POST[$k]);
        }

        if (count($bagian) >= 2) {
            break;
        }
    }

    $teks = implode(' - ', $bagian);

    if (!empty($_POST['id'])) {
        $teks .= ($teks !== '' ? ' ' : '') . '(ID ' . (int) $_POST['id'] . ')';
    } elseif (!empty($_GET['id'])) {
        $teks .= ($teks !== '' ? ' ' : '') . '(ID ' . (int) $_GET['id'] . ')';
    }

    return $teks;
}

/*
   Untuk permintaan POST ke halaman simpan/update/hapus, keluaran halaman
   ditampung dulu supaya hasilnya (berhasil/gagal) bisa dibaca di akhir.
*/
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    $_log_skrip = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $_log_tail2 = implode('/', array_slice(explode('/', $_log_skrip), -2));

    if (isset(_logPetaHalaman()[$_log_tail2])) {
        ob_start();
    }
}

register_shutdown_function(function () {

    try {

        global $koneksi;

        if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
            return;
        }

        $skrip = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $parts = explode('/', $skrip);
        $tail2 = implode('/', array_slice($parts, -2));

        $metode = $_SERVER['REQUEST_METHOD'] ?? '';

        /* ---- login: dicatat sekali, saat halaman pertama setelah masuk ---- */

        if (!empty($_SESSION['username']) && empty($_SESSION['_login_tercatat'])) {

            $_SESSION['_login_tercatat'] = 1;

            catatAktivitas($koneksi, 'Login', 'Akun', 'Masuk ke aplikasi');
        }

        /* ---- tambah / ubah / hapus ---- */

        $peta = _logPetaHalaman();

        if ($metode !== 'POST' || !isset($peta[$tail2])) {
            return;
        }

        /* hanya jika berhasil:
           - halaman mengalihkan (Location) bukan ke login / error, atau
           - halaman menampilkan pesan "berhasil" (alert JavaScript) */

        $berhasil = false;

        foreach (headers_list() as $h) {

            if (stripos($h, 'Location:') === 0
                && stripos($h, 'login.php') === false
                && stripos($h, 'error=') === false) {
                $berhasil = true;
            }
        }

        if (!$berhasil && ob_get_level() > 0) {

            $isi = (string) ob_get_contents();

            $gagal = (bool) preg_match('/gagal|tidak valid|tidak ditemukan|tidak diizinkan|tidak dapat|tidak boleh/i', $isi);

            if (!$gagal && preg_match('/berhasil/i', $isi)) {
                $berhasil = true;
            }
        }

        if (!$berhasil) {
            return;
        }

        [$aksi, $modul] = $peta[$tail2];

        catatAktivitas($koneksi, $aksi, $modul, _logKeterangan());

    } catch (Throwable $e) {
        /* diabaikan */
    }
});