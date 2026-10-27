<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/jabatan.php";

/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

/* =========================================================
   SARINGAN (sama dengan halaman laporan)
========================================================= */

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

/* =========================================================
   DATA PEGAWAI
========================================================= */

$sql    = "SELECT nip, nama, jabatan, unit_kerja, status FROM pegawai WHERE 1=1";
$types  = "";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (nip LIKE ? OR nama LIKE ? OR jabatan LIKE ? OR unit_kerja LIKE ?)";
    $like = "%" . $keyword . "%";
    $types .= "ssss";
    array_push($params, $like, $like, $like, $like);
}

$sql .= " ORDER BY nama ASC";

$stmt = mysqli_prepare($koneksi, $sql);

if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$rows = [];

while ($r = mysqli_fetch_assoc($hasil)) {
    $rows[] = $r;
}

// pimpinan paling atas, lalu sekretaris, dst. menurut jabatan
urutkanPegawai($rows);

$total = count($rows);
$aktif = 0;
$units = [];

foreach ($rows as $r) {

    if (strtolower(trim((string) ($r['status'] ?? ''))) === 'aktif') {
        $aktif++;
    }

    if (!empty($r['unit_kerja'])) {
        $units[$r['unit_kerja']] = true;
    }
}

$nonaktif   = $total - $aktif;
$jumlahUnit = count($units);

/* =========================================================
   HELPER
========================================================= */

function tanggalIndo($ts = null)
{
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $ts = $ts ?: time();

    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

$dicetak_oleh = $_SESSION['nama'] ?? $_SESSION['username'];

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Data Pegawai - BKPSDM Kabupaten Tapin</title>

    <style>

        * { box-sizing: border-box; }

        :root { --ac: #6D28D9; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #e9e9ef;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ===== BILAH ALAT (hanya di layar) ===== */

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 24px;
            background: #14181f;
            color: #fff;
        }

        .toolbar .info {
            font-size: 12.5px;
            color: rgba(255,255,255,.7);
        }

        .toolbar .info b { color: #fff; }

        .toolbar .aksi { display: flex; gap: 10px; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 40px;
            padding: 0 18px;
            border: none;
            border-radius: 11px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: transform .2s, background .2s;
        }

        .btn:hover { transform: translateY(-2px); }

        .btn-back { background: rgba(255,255,255,.12); color: #fff; }
        .btn-back:hover { background: rgba(255,255,255,.2); color: #fff; }

        .btn-print { background: #8B5CF6; color: #fff; }
        .btn-print:hover { background: #A78BFA; }

        /* ===== LEMBAR KERTAS ===== */

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 24px auto;
            padding: 18mm 16mm 20mm;
            background: #fff;
            box-shadow: 0 10px 40px rgba(0,0,0,.15);
        }

        /* ===== KOP ===== */

        .kop {
            text-align: center;
            line-height: 1.35;
        }

        .kop { display: grid; grid-template-columns: 96px 1fr 96px; align-items: center; column-gap: 6px; }
        .kop-logo { height: 96px; width: auto; justify-self: start; display: block; }
        .kop-text { grid-column: 2; }
        .kop .l1 { font-size: 13px; font-weight: 700; letter-spacing: .5px; }
        .kop .l2 { font-size: 16px; font-weight: 800; letter-spacing: .6px; }
        .kop .l3 { font-size: 13px; font-weight: 700; letter-spacing: .5px; }

        .garis {
            margin: 10px 0 16px;
            border-top: 3px solid #111;
            border-bottom: 1px solid #111;
            height: 6px;
        }

        .saringan {
            margin: -6px 0 12px;
            font-size: 11px;
            color: #333;
        }

        .saringan b { font-weight: 700; }

        /* ===== TABEL ===== */

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        thead { display: table-header-group; }

        th, td {
            border: 1px solid #111;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #e4e4ea;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            text-align: center;
            vertical-align: middle;
        }

        tbody tr { page-break-inside: avoid; }
        tbody tr:nth-child(even) td { background: #f6f6f9; }

        .center { text-align: center; }
        .nip { white-space: nowrap; }

        /* ===== TANDA TANGAN ===== */

        .ttd-wrap {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            page-break-inside: avoid;
        }

        .ttd {
            width: 240px;
            font-size: 12px;
            line-height: 1.5;
            text-align: center;
        }

        .ttd .space { height: 68px; }
        .ttd .nama { font-weight: 700; }

        .cetak-info {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #bbb;
            font-size: 9.5px;
            color: #666;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        /* ===== CETAK ===== */

        @page {
            size: A4 portrait;
            margin: 14mm 12mm;
        }

        @media print {

            body { background: #fff; }

            .toolbar { display: none !important; }

            .sheet {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }

        @media (max-width: 800px) {
            .sheet {
                width: auto;
                margin: 12px;
                padding: 20px 14px;
                overflow-x: auto;
            }
        }

    </style>

</head>

<body>

<!-- ===== BILAH ALAT ===== -->

<div class="toolbar">

    <div class="info">
        Pratinjau cetak &middot; <b><?= $total; ?></b> pegawai
        <?= $keyword !== '' ? '&middot; saringan: <b>' . htmlspecialchars($keyword) . '</b>' : ''; ?>
    </div>

    <div class="aksi">

        <a href="pegawai.php?keyword=<?= urlencode($keyword); ?>" class="btn btn-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>

        <button type="button" class="btn btn-print" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Cetak
        </button>

    </div>

</div>

<!-- ===== LEMBAR ===== -->

<div class="sheet">

    <!-- KOP -->

    <div class="kop">
        <img class="kop-logo" src="../assets/img/lambang_tapin.png" alt="Lambang Kabupaten Tapin">
        <div class="kop-text">
        <div class="l1">PEMERINTAH KABUPATEN TAPIN</div>
        <div class="l2">BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA</div>
        <div class="l3">KABUPATEN TAPIN</div>
        </div>
    </div>

    <div class="garis"></div>

    <?php if ($keyword !== '') { ?>
        <div class="saringan">Saringan pencarian: <b>"<?= htmlspecialchars($keyword); ?>"</b></div>
    <?php } ?>

    <!-- TABEL -->

    <table>

        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:19%">NIP</th>
                <th style="width:22%">Nama Pegawai</th>
                <th style="width:26%">Jabatan</th>
                <th style="width:17%">Unit Kerja</th>
                <th style="width:11%">Status</th>
            </tr>
        </thead>

        <tbody>

        <?php if ($total > 0) {

            $no = 1;

            foreach ($rows as $p) { ?>

                <tr>
                    <td class="center"><?= $no++; ?></td>
                    <td class="nip"><?= htmlspecialchars(($p['nip'] ?? '') !== '' ? $p['nip'] : '-'); ?></td>
                    <td><?= htmlspecialchars($p['nama'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars(($p['jabatan'] ?? '') !== '' ? $p['jabatan'] : '-'); ?></td>
                    <td><?= htmlspecialchars(($p['unit_kerja'] ?? '') !== '' ? $p['unit_kerja'] : '-'); ?></td>
                    <td class="center"><?= htmlspecialchars(($p['status'] ?? '') !== '' ? $p['status'] : '-'); ?></td>
                </tr>

            <?php }

        } else { ?>

            <tr>
                <td colspan="6" class="center" style="padding:22px">Tidak ada data pegawai<?= $keyword !== '' ? ' untuk saringan tersebut' : ''; ?>.</td>
            </tr>

        <?php } ?>

        </tbody>

    </table>

    <!-- TANDA TANGAN -->

    <div class="ttd-wrap">

        <div class="ttd">
            <div>Tapin, <?= tanggalIndo(); ?></div>
            <div>Mengetahui,</div>
            <div class="space"></div>
            <div class="nama">______________________________</div>
            <div>NIP. _________________________</div>
        </div>

    </div>

    <div class="cetak-info">
        <span>Dicetak oleh: <?= htmlspecialchars($dicetak_oleh); ?></span>
        <span>e-SIMPEL &middot; BKPSDM Kabupaten Tapin</span>
    </div>

</div>

</body>

</html>