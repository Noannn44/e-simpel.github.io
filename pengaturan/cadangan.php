<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

hanyaAdmin();

require_once "../config/database.php";

/** @var mysqli $koneksi */

date_default_timezone_set('Asia/Makassar');

/* =========================================================
   NAMA DATABASE AKTIF
========================================================= */

$r_db = mysqli_query($koneksi, "SELECT DATABASE() AS nama");
$nama_db = ($r_db && ($row_db = mysqli_fetch_assoc($r_db))) ? (string) $row_db['nama'] : '';

/* =========================================================
   FUNGSI PEMBUAT CADANGAN (.sql)
========================================================= */

function nilaiSql($koneksi, $v)
{
    if ($v === null) {
        return 'NULL';
    }

    return "'" . mysqli_real_escape_string($koneksi, (string) $v) . "'";
}

function daftarTabel($koneksi)
{
    $tabel = [];

    $r = mysqli_query($koneksi, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

    while ($r && ($row = mysqli_fetch_row($r))) {
        $tabel[] = $row[0];
    }

    return $tabel;
}

function tulisCadangan($koneksi, $nama_db)
{
    mysqli_set_charset($koneksi, 'utf8mb4');

    echo "-- ======================================================\n";
    echo "-- Cadangan database : {$nama_db}\n";
    echo "-- Dibuat pada       : " . date('d-m-Y H:i:s') . " (WITA)\n";
    echo "-- Aplikasi          : e-SIMPEL BKPSDM Kabupaten Tapin\n";
    echo "-- ======================================================\n\n";

    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    echo "SET time_zone = '+00:00';\n\n";

    echo "CREATE DATABASE IF NOT EXISTS `{$nama_db}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
    echo "USE `{$nama_db}`;\n\n";

    foreach (daftarTabel($koneksi) as $tabel) {

        $t = '`' . str_replace('`', '``', $tabel) . '`';

        echo "-- ------------------------------------------------------\n";
        echo "-- Tabel {$t}\n";
        echo "-- ------------------------------------------------------\n\n";

        echo "DROP TABLE IF EXISTS {$t};\n";

        $rc = mysqli_query($koneksi, "SHOW CREATE TABLE {$t}");
        $rowc = $rc ? mysqli_fetch_row($rc) : null;

        if ($rowc) {
            echo $rowc[1] . ";\n\n";
        }

        $rd = mysqli_query($koneksi, "SELECT * FROM {$t}", MYSQLI_USE_RESULT);

        if (!$rd) {
            continue;
        }

        $kolom   = null;
        $batch   = [];
        $jumlah  = 0;

        while ($row = mysqli_fetch_assoc($rd)) {

            if ($kolom === null) {
                $kolom = '`' . implode('`, `', array_map(function ($k) {
                    return str_replace('`', '``', $k);
                }, array_keys($row))) . '`';
            }

            $nilai = [];

            foreach ($row as $v) {
                $nilai[] = nilaiSql($koneksi, $v);
            }

            $batch[] = '(' . implode(', ', $nilai) . ')';
            $jumlah++;

            if (count($batch) >= 100) {
                echo "INSERT INTO {$t} ({$kolom}) VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }

        mysqli_free_result($rd);

        if ($batch) {
            echo "INSERT INTO {$t} ({$kolom}) VALUES\n" . implode(",\n", $batch) . ";\n";
        }

        echo "\n";

        flush();
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo "-- Selesai\n";
}

/* =========================================================
   PROSES UNDUH (POST + CSRF)
========================================================= */

$pesan_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {

        $pesan_error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';

    } elseif ($nama_db === '') {

        $pesan_error = 'Nama database tidak terbaca.';

    } else {

        @set_time_limit(0);

        $nama_file = 'cadangan_' . preg_replace('/[^A-Za-z0-9_-]/', '', $nama_db) . '_' . date('Ymd_His') . '.sql';

        /* catat dulu (setelah unduhan dimulai tidak bisa lagi) */
        catatAktivitas($koneksi, 'Cadangan', 'Database', 'Mengunduh cadangan: ' . $nama_file);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nama_file . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        tulisCadangan($koneksi, $nama_db);

        exit;
    }
}

/* =========================================================
   INFORMASI TABEL
========================================================= */

$info_tabel  = [];
$total_baris = 0;
$total_ukuran = 0;

$rs = mysqli_query($koneksi, "SHOW TABLE STATUS");

while ($rs && ($row = mysqli_fetch_assoc($rs))) {

    if (!empty($row['Engine']) || $row['Rows'] !== null) {

        $ukuran = (int) $row['Data_length'] + (int) $row['Index_length'];

        $info_tabel[] = [
            'nama'   => $row['Name'],
            'baris'  => (int) $row['Rows'],
            'ukuran' => $ukuran,
        ];

        $total_baris  += (int) $row['Rows'];
        $total_ukuran += $ukuran;
    }
}

function formatUkuran($b)
{
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1, ',', '.') . ' MB';
    }

    return number_format(max($b, 0) / 1024, 1, ',', '.') . ' KB';
}

/* ---------- cadangan terakhir (dari riwayat aktivitas, jika ada) ---------- */

$terakhir = null;

try {
    $rt = mysqli_query($koneksi, "SELECT waktu, username FROM riwayat_aktivitas WHERE aksi = 'Cadangan' ORDER BY id DESC LIMIT 1");
    $terakhir = $rt ? mysqli_fetch_assoc($rt) : null;
} catch (Throwable $e) {
    $terakhir = null;
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.cd-wrap {
    --ac: #8B5CF6;
    --ac-dark: #6D28D9;
    --ac-soft: #EDE9FE;
}

.cd-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #1a1530 0%, #2b1f5c 58%, #5b21b6 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.cd-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -80px;
    top: -140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139,92,246,.55), transparent 70%);
    pointer-events: none;
}

.cd-hero > * { position: relative; z-index: 1; }

.cd-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,.1);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .4px;
    margin-bottom: 12px;
}

.cd-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #C4B5FD;
    box-shadow: 0 0 0 4px rgba(196,181,253,.35);
}

.cd-wrap .cd-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.cd-wrap .cd-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.7) !important;
}

.cd-pills { display: flex; gap: 12px; flex-wrap: wrap; }

.cd-pill {
    min-width: 98px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
}

.cd-pill strong { display: block; font-size: 21px; font-weight: 700; line-height: 1.1; color: #fff; }
.cd-pill span { font-size: 10.5px; color: rgba(255,255,255,.62); text-transform: uppercase; letter-spacing: .5px; }

.cd-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
    gap: 18px;
    align-items: start;
}

.cd-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    padding: 22px 24px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.cd-card h3 { margin: 0 0 4px; font-size: 14px; font-weight: 700; color: #1d1d27; }
.cd-card .sub { margin: 0 0 16px; font-size: 12.5px; color: #8a8a98; }

.cd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    width: 100%;
    height: 50px;
    border: none;
    border-radius: 14px;
    background: var(--ac);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(139,92,246,.35);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.cd-btn:hover { background: #7C3AED; transform: translateY(-3px); }

.cd-error {
    margin-bottom: 14px;
    padding: 11px 14px;
    border-radius: 12px;
    background: #FEE2E2;
    color: #B91C1C;
    font-size: 12.5px;
    font-weight: 600;
}

.cd-terakhir {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 12.5px;
}

.cd-terakhir.kosong { background: #FEF3C7; color: #92400E; }

.cd-tips { margin: 18px 0 0; padding: 0; list-style: none; }

.cd-tips li {
    display: flex;
    gap: 10px;
    padding: 9px 0;
    border-top: 1px solid #f0f0f4;
    font-size: 12.5px;
    color: #55556a;
    line-height: 1.5;
}

.cd-tips b {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cd-table { width: 100%; border-collapse: collapse; }

.cd-table th {
    padding: 8px 10px;
    text-align: left;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #9a9aa8;
    border-bottom: 1px solid #eee;
}

.cd-table td {
    padding: 11px 10px;
    font-size: 13px;
    color: #2a2a35;
    border-bottom: 1px solid #f4f4f7;
}

.cd-table td.num, .cd-table th.num { text-align: right; }

@media (max-width: 991px) {
    .cd-grid { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
    .cd-hero { padding: 24px 20px; }
    .cd-wrap .cd-hero h1 { font-size: 23px; }
    .cd-card { padding: 18px 16px; }
}

</style>

<div class="content">
<div class="container-fluid cd-wrap">

    <div class="cd-hero">

        <div>
            <div class="cd-eyebrow"><i></i> PENGATURAN</div>
            <h1>Cadangan Database</h1>
            <p>Unduh salinan seluruh data aplikasi dalam satu file .sql.</p>
        </div>

        <div class="cd-pills">
            <div class="cd-pill"><strong><?= count($info_tabel); ?></strong><span>Tabel</span></div>
            <div class="cd-pill"><strong><?= number_format($total_baris, 0, ',', '.'); ?></strong><span>Baris data</span></div>
            <div class="cd-pill"><strong><?= formatUkuran($total_ukuran); ?></strong><span>Ukuran</span></div>
        </div>

    </div>

    <div class="cd-grid">

        <div class="cd-card">

            <h3>Unduh cadangan sekarang</h3>
            <p class="sub">Database: <b><?= htmlspecialchars($nama_db); ?></b></p>

            <?php if ($pesan_error !== '') { ?>
                <div class="cd-error"><?= htmlspecialchars($pesan_error); ?></div>
            <?php } ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                <button type="submit" class="cd-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                    Unduh Cadangan (.sql)
                </button>
            </form>

            <?php if ($terakhir) {

                $w = new DateTime($terakhir['waktu']);
            ?>
                <div class="cd-terakhir">
                    Cadangan terakhir: <b><?= $w->format('d-m-Y H:i'); ?></b>
                    oleh <?= htmlspecialchars($terakhir['username']); ?>
                </div>
            <?php } else { ?>
                <div class="cd-terakhir kosong">Belum ada cadangan yang tercatat.</div>
            <?php } ?>

            <ul class="cd-tips">
                <li><b>1</b><span>Simpan file hasil unduhan di <strong>dua tempat</strong>, misalnya flashdisk dan Google Drive.</span></li>
                <li><b>2</b><span>Lakukan <strong>seminggu sekali</strong>, dan selalu sebelum mengubah aplikasi.</span></li>
                <li><b>3</b><span>Untuk memulihkan: buka phpMyAdmin, tab <strong>Impor</strong>, pilih file ini. Database dibuat otomatis bila belum ada.</span></li>
            </ul>

        </div>

        <div class="cd-card">

            <h3>Isi database</h3>
            <p class="sub">Tabel yang akan ikut tersimpan dalam cadangan.</p>

            <table class="cd-table">
                <thead>
                    <tr>
                        <th>Tabel</th>
                        <th class="num">Baris</th>
                        <th class="num">Ukuran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($info_tabel as $t) { ?>
                        <tr>
                            <td><?= htmlspecialchars($t['nama']); ?></td>
                            <td class="num"><?= number_format($t['baris'], 0, ',', '.'); ?></td>
                            <td class="num"><?= formatUkuran($t['ukuran']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <p class="sub" style="margin-top:14px">
                Catatan: foto/berkas yang diunggah pada surat masuk (folder <code>surat_masuk/uploads</code>)
                tidak ikut dalam file .sql. Salin folder tersebut secara terpisah.
            </p>

        </div>

    </div>

</div>
</div>

<?php include "../layouts/footer.php"; ?>