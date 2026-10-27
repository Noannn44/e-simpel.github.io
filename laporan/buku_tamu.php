<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

date_default_timezone_set('Asia/Makassar');

/* =========================================================
   FILTER
========================================================= */

$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int) date('Y');
}

$bulan = (isset($_GET['bulan']) && $_GET['bulan'] !== '') ? (int) $_GET['bulan'] : 0;

if ($bulan < 0 || $bulan > 12) {
    $bulan = 0;
}

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$singkat = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$label_bulan = ($bulan > 0) ? $nama_bulan[$bulan] : 'Semua Bulan';

/* =========================================================
   DAFTAR TAHUN (dari data + tahun berjalan)
========================================================= */

$daftar_tahun = [(int) date('Y'), $tahun];

$rt = mysqli_query($koneksi, "SELECT DISTINCT YEAR(tanggal_kunjungan) AS y FROM tamu WHERE tanggal_kunjungan IS NOT NULL");

if ($rt) {
    while ($r = mysqli_fetch_assoc($rt)) {
        if ((int) $r['y'] >= 2000) {
            $daftar_tahun[] = (int) $r['y'];
        }
    }
}

$daftar_tahun = array_values(array_unique($daftar_tahun));
rsort($daftar_tahun);

/* =========================================================
   KUNJUNGAN UNTUK GRAFIK
   - Semua bulan : jumlah tamu per bulan
   - Satu bulan  : jumlah tamu per hari
   (tidak terpengaruh kolom pencarian)
========================================================= */

if ($bulan > 0) {

    $hari_dalam_bulan = (int) date('t', mktime(0, 0, 0, $bulan, 1, $tahun));
    $seri = array_fill(1, $hari_dalam_bulan, 0);

    $sg = mysqli_prepare($koneksi, "SELECT DAY(tanggal_kunjungan) AS d, COUNT(*) AS c FROM tamu WHERE YEAR(tanggal_kunjungan) = ? AND MONTH(tanggal_kunjungan) = ? GROUP BY DAY(tanggal_kunjungan)");
    mysqli_stmt_bind_param($sg, "ii", $tahun, $bulan);

} else {

    $seri = array_fill(1, 12, 0);

    $sg = mysqli_prepare($koneksi, "SELECT MONTH(tanggal_kunjungan) AS d, COUNT(*) AS c FROM tamu WHERE YEAR(tanggal_kunjungan) = ? GROUP BY MONTH(tanggal_kunjungan)");
    mysqli_stmt_bind_param($sg, "i", $tahun);
}

mysqli_stmt_execute($sg);
$rg = mysqli_stmt_get_result($sg);

while ($r = mysqli_fetch_assoc($rg)) {
    $k = (int) $r['d'];

    if (isset($seri[$k])) {
        $seri[$k] = (int) $r['c'];
    }
}

$total_periode = array_sum($seri);
$maks          = max($seri);
$puncak        = ($maks > 0) ? array_search($maks, $seri) : 0;

/* =========================================================
   INSTANSI TERBANYAK (pada periode yang dipilih)
========================================================= */

$sql_top = "SELECT instansi_asal AS nama, COUNT(*) AS jml FROM tamu WHERE YEAR(tanggal_kunjungan) = ? AND instansi_asal IS NOT NULL AND TRIM(instansi_asal) <> ''";
$tp_types  = "i";
$tp_params = [$tahun];

if ($bulan > 0) {
    $sql_top .= " AND MONTH(tanggal_kunjungan) = ?";
    $tp_types .= "i";
    $tp_params[] = $bulan;
}

$sql_top .= " GROUP BY instansi_asal ORDER BY jml DESC, instansi_asal ASC LIMIT 5";

$st = mysqli_prepare($koneksi, $sql_top);
mysqli_stmt_bind_param($st, $tp_types, ...$tp_params);
mysqli_stmt_execute($st);
$rtop = mysqli_stmt_get_result($st);

$top_instansi = [];

while ($r = mysqli_fetch_assoc($rtop)) {
    $top_instansi[] = $r;
}

$maks_top = $top_instansi ? (int) $top_instansi[0]['jml'] : 0;

/* =========================================================
   QUERY DATA
========================================================= */

$sql    = "SELECT id, nama, instansi_asal, keperluan, yang_melayani, tanggal_kunjungan, jam_kunjungan FROM tamu WHERE YEAR(tanggal_kunjungan) = ?";
$types  = "i";
$params = [$tahun];

if ($bulan > 0) {
    $sql .= " AND MONTH(tanggal_kunjungan) = ?";
    $types .= "i";
    $params[] = $bulan;
}

if ($keyword !== '') {
    $sql .= " AND (nama LIKE ? OR instansi_asal LIKE ? OR keperluan LIKE ? OR yang_melayani LIKE ?)";
    $like = "%" . $keyword . "%";
    $types .= "ssss";
    array_push($params, $like, $like, $like, $like);
}

// terlama di atas: nomor urut sama dengan nomor pada halaman cetak
$sql .= " ORDER BY tanggal_kunjungan ASC, jam_kunjungan ASC, id ASC";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$total_tampil = mysqli_num_rows($hasil);

/* =========================================================
   HELPER
========================================================= */

function tglTamu($v)
{
    return (!empty($v) && $v !== '0000-00-00') ? date('d-m-Y', strtotime($v)) : '-';
}

function jamTamu($v)
{
    return !empty($v) ? date('H:i', strtotime($v)) : '-';
}

function inisialTamu($nama)
{
    $kata = preg_split('/\s+/', trim((string) $nama));
    $hasil = '';

    foreach ($kata as $k) {
        if ($k !== '') {
            $hasil .= strtoupper(mb_substr($k, 0, 1));
        }

        if (strlen($hasil) >= 2) {
            break;
        }
    }

    return $hasil !== '' ? $hasil : '?';
}

function warnaTamu($nama)
{
    $palet = [
        ['#DCFCE7', '#15803D'],
        ['#DBEAFE', '#1D4ED8'],
        ['#FFEDD5', '#C2410C'],
        ['#EDE9FE', '#6D28D9'],
        ['#CCFBF1', '#0F766E'],
        ['#FCE7F3', '#BE185D'],
    ];

    return $palet[crc32((string) $nama) % count($palet)];
}

function urlLaporanTamu($t, $b, $k)
{
    $q = ['tahun' => $t];

    if ($b > 0) {
        $q['bulan'] = $b;
    }

    if ($k !== '') {
        $q['keyword'] = $k;
    }

    return 'buku_tamu.php?' . http_build_query($q);
}

$ada_filter = ($bulan > 0 || $keyword !== '' || $tahun !== (int) date('Y'));

$judul_grafik = ($bulan > 0)
    ? 'Kunjungan harian, ' . $nama_bulan[$bulan] . ' ' . $tahun
    : 'Kunjungan per bulan, ' . $tahun;

$label_puncak = '-';

if ($puncak > 0) {
    $label_puncak = ($bulan > 0) ? 'Tgl ' . $puncak : $singkat[$puncak];
}

/* =========================================================
   UNDUH EXCEL (.xlsx)
========================================================= */

if (isset($_GET['unduh']) && $_GET['unduh'] === 'xlsx') {

    require_once "../config/xlsx.php";

    $xl_baris = [];
    $xl_no    = 0;

    while ($xr = mysqli_fetch_assoc($hasil)) {
        $xl_no++;
        $xl_baris[] = [
            $xl_no,
            (!empty($xr['tanggal_kunjungan']) && $xr['tanggal_kunjungan'] !== '0000-00-00') ? date('d-m-Y', strtotime($xr['tanggal_kunjungan'])) : '-',
            !empty($xr['jam_kunjungan']) ? substr((string) $xr['jam_kunjungan'], 0, 5) : '-',
            (string) $xr['nama'],
            (string) $xr['instansi_asal'],
            (string) $xr['keperluan'],
            (string) $xr['yang_melayani'],
        ];
    }

    kirimXlsx("buku_tamu_" . $tahun . ($bulan > 0 ? "_" . $nama_bulan[$bulan] : "") . ".xlsx",
        "Buku Tamu",
        [
            "LAPORAN BUKU TAMU",
            "BKPSDM Kabupaten Tapin",
            "Periode: " . $label_bulan . " " . $tahun . ($keyword !== "" ? " | Kata kunci: " . $keyword : "") . " | Jumlah: " . $xl_no . " kunjungan",
        ],
        ["No", "Tanggal", "Jam", "Nama Tamu", "Instansi Asal", "Keperluan", "Dilayani Oleh"],
        $xl_baris,
        [6, 14, 8, 28, 32, 44, 24]);
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.bt-wrap {
    --ac: #22C55E;
    --ac-dark: #15803D;
    --ac-soft: #DCFCE7;
}

/* ===== HERO ===== */

.bt-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #10201a 0%, #123524 58%, #166534 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.bt-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -80px;
    top: -140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(34,197,94,.5), transparent 70%);
    pointer-events: none;
}

.bt-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.045) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.bt-hero > * { position: relative; z-index: 1; }

.bt-eyebrow {
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

.bt-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #4ADE80;
    box-shadow: 0 0 0 4px rgba(74,222,128,.35);
}

.bt-wrap .bt-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.bt-wrap .bt-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.68) !important;
}

.bt-hero-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.bt-pill {
    min-width: 98px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.bt-pill:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,.14);
}

.bt-pill strong {
    display: block;
    font-size: 21px;
    font-weight: 700;
    line-height: 1.1;
    color: #fff;
}

.bt-pill span {
    font-size: 10.5px;
    color: rgba(255,255,255,.62);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.bt-btn-print {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 46px;
    padding: 0 20px;
    border-radius: 14px;
    background: var(--ac);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 10px 24px rgba(34,197,94,.4);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.bt-btn-print:hover {
    background: #4ADE80;
    color: #fff;
    transform: translateY(-3px);
}

.bt-btn-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 46px;
    padding: 0 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.1);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all .25s;
}

.bt-btn-back:hover {
    background: rgba(255,255,255,.18);
    color: #fff;
    transform: translateX(-3px);
}

/* ===== FILTER ===== */

.bt-filter {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 18px;
    padding: 16px 18px;
    margin-bottom: 18px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.bt-fg { display: flex; flex-direction: column; gap: 6px; }

.bt-fg label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #9a9aa8;
}

.bt-fg select,
.bt-search input {
    height: 44px;
    padding: 0 14px;
    border: 1.5px solid transparent;
    background: #f4f4f7;
    border-radius: 13px;
    font-size: 13px;
    color: #1d1d27;
    outline: none;
    transition: all .25s var(--ease-smooth, ease);
}

.bt-fg select { min-width: 150px; cursor: pointer; }

.bt-fg select:focus,
.bt-search input:focus {
    background: #fff;
    border-color: var(--ac);
    box-shadow: 0 0 0 4px rgba(34,197,94,.15);
}

.bt-search { flex: 1; min-width: 240px; position: relative; }

.bt-search svg {
    position: absolute;
    left: 14px;
    bottom: 14px;
    pointer-events: none;
}

.bt-search input { width: 100%; padding-left: 40px; }

.bt-go {
    height: 44px;
    padding: 0 22px;
    border: none;
    border-radius: 13px;
    background: #14181f;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.bt-go:hover { background: var(--ac-dark); transform: translateY(-2px); }

.bt-reset {
    height: 44px;
    display: inline-flex;
    align-items: center;
    padding: 0 16px;
    border-radius: 13px;
    background: #f4f4f7;
    color: #666;
    font-size: 12.5px;
    text-decoration: none;
    transition: all .25s;
}

.bt-reset:hover { background: #e9e9ef; color: #222; }

/* ===== GRAFIK + INSTANSI ===== */

.bt-insight {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 18px;
    margin-bottom: 18px;
}

.bt-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    padding: 20px 24px 18px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.bt-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.bt-card-head h3 {
    margin: 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1d1d27;
}

.bt-card-head small { font-size: 11.5px; color: #9a9aa8; }

/* batang */

.bt-bars {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    min-height: 150px;
}

.bt-bar {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    color: #8a8a98;
}

.bt-bar .n {
    height: 14px;
    font-size: 10.5px;
    font-weight: 700;
    color: #1d1d27;
}

.bt-bar .plot {
    width: 100%;
    height: 100px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.bt-bar .tiang {
    display: block;
    width: 100%;
    max-width: 34px;
    border-radius: 8px 8px 3px 3px;
    background: #eceef2;
    transition: background .25s var(--ease-smooth, ease), transform .25s var(--ease-smooth, ease);
}

.bt-bar.ada .tiang { background: #BBF7D0; }
.bt-bar.puncak .tiang { background: #4ADE80; }
.bt-bar.on .tiang { background: var(--ac-dark); }

.bt-bar:hover .tiang { background: var(--ac); transform: translateY(-3px); }

.bt-bar .b {
    font-size: 10.5px;
    font-weight: 600;
}

.bt-bar.on .b { color: var(--ac-dark); }

/* instansi */

.bt-top { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 13px; }

.bt-top li { display: block; }

.bt-top .baris-atas {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 12.5px;
    margin-bottom: 6px;
}

.bt-top .baris-atas b {
    font-weight: 600;
    color: #1d1d27;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bt-top .baris-atas span { font-weight: 700; color: var(--ac-dark); flex-shrink: 0; }

.bt-top .jalur {
    display: block;
    height: 8px;
    border-radius: 999px;
    background: #f0f1f5;
    overflow: hidden;
}

.bt-top .isi {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #4ADE80, #16A34A);
}

.bt-kosong-kecil {
    padding: 26px 8px;
    text-align: center;
    font-size: 12.5px;
    color: #9a9aa8;
}

/* ===== TABEL ===== */

.bt-table-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
    overflow: hidden;
}

.bt-table-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--color-border, #eee);
}

.bt-table-top h3 { margin: 0; font-size: 14px; font-weight: 700; color: #1d1d27; }

.bt-count {
    padding: 5px 12px;
    border-radius: 999px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 11.5px;
    font-weight: 700;
}

.bt-scroll { overflow-x: auto; }

.bt-table { width: 100%; border-collapse: collapse; min-width: 860px; }

.bt-table th {
    padding: 13px 16px;
    background: #fafafc;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #9a9aa8;
    text-align: left;
    white-space: nowrap;
}

.bt-table td {
    padding: 14px 16px;
    border-top: 1px solid #f1f1f5;
    font-size: 13px;
    color: #2a2a35;
    vertical-align: middle;
}

.bt-table tbody tr {
    opacity: 0;
    animation: btMasuk .4s var(--ease-smooth, ease) forwards;
    transition: background .2s;
}

.bt-table tbody tr:hover { background: #f7fdf9; }

@keyframes btMasuk {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}

.bt-no { width: 52px; font-weight: 700; color: #9a9aa8; }

.bt-waktu b { display: block; font-size: 13px; color: #1d1d27; white-space: nowrap; }
.bt-waktu small { font-size: 11.5px; color: #9a9aa8; }

.bt-tamu { display: flex; align-items: center; gap: 11px; min-width: 190px; }

.bt-avatar {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
}

.bt-tamu b { font-weight: 600; color: #1d1d27; }

.bt-instansi { color: #555; min-width: 150px; }

.bt-keperluan {
    max-width: 280px;
    color: #555;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.bt-layani {
    display: inline-block;
    padding: 4px 11px;
    border-radius: 999px;
    background: #f1f1f5;
    font-size: 11.5px;
    font-weight: 600;
    color: #555;
    white-space: nowrap;
}

.bt-act {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    text-decoration: none;
    transition: all .25s var(--ease-smooth, ease);
}

.bt-act:hover {
    background: var(--ac);
    color: #fff;
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(34,197,94,.3);
}

.bt-empty {
    text-align: center;
    padding: 60px 20px !important;
    color: #9a9aa8;
}

.bt-empty .ic {
    width: 68px;
    height: 68px;
    margin: 0 auto 14px;
    border-radius: 22px;
    background: var(--ac-soft);
    display: flex;
    align-items: center;
    justify-content: center;
}

.bt-empty h4 { margin: 0 0 4px; color: #333; font-size: 15px; }
.bt-empty p { margin: 0; font-size: 12.5px; }

/* ===== RESPONSIVE ===== */

@media (max-width: 991px) {
    .bt-insight { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
    .bt-hero { padding: 24px 20px; }
    .bt-wrap .bt-hero h1 { font-size: 23px; }
    .bt-fg, .bt-fg select { width: 100%; }
    .bt-card { padding: 18px 14px 14px; }
    .bt-bars { gap: 3px; }
    .bt-bar .b { font-size: 8.5px; }
    .bt-bar .n { font-size: 9px; }
}

</style>

<div class="content">
<div class="container-fluid bt-wrap">

    <!-- ===== HERO ===== -->

    <div class="bt-hero">

        <div>
            <div class="bt-eyebrow"><i></i> LAPORAN</div>
            <h1>Laporan Buku Tamu</h1>
            <p>Rekap kunjungan tamu instansi, per periode.</p>
        </div>

        <div class="bt-hero-right">

            <div class="bt-pill"><strong><?= $total_periode; ?></strong><span><?= $bulan > 0 ? htmlspecialchars($singkat[$bulan]) . ' ' . $tahun : 'Tahun ' . $tahun; ?></span></div>
            <div class="bt-pill"><strong><?= $total_tampil; ?></strong><span>Ditampilkan</span></div>
            <div class="bt-pill"><strong><?= htmlspecialchars($label_puncak); ?></strong><span>Teramai</span></div>

            <a href="cetak_buku_tamu.php?tahun=<?= urlencode($tahun); ?>&bulan=<?= urlencode($bulan); ?>&keyword=<?= urlencode($keyword); ?>" class="bt-btn-print">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Lihat Laporan
            </a>

            <style>
.xl-btn { display:inline-flex; align-items:center; gap:8px; height:46px; padding:0 18px; border-radius:14px; background:#16A34A; color:#fff; font-size:13px; font-weight:700; text-decoration:none; box-shadow:0 10px 24px rgba(22,163,74,.35); transition:transform .25s var(--ease-smooth, ease), background .25s; }
.xl-btn:hover { background:#22C55E; color:#fff; transform:translateY(-3px); }
</style>
            <a href="buku_tamu.php?tahun=<?= urlencode($tahun); ?>&bulan=<?= urlencode($bulan); ?>&keyword=<?= urlencode($keyword); ?>&unduh=xlsx" class="xl-btn" title="Unduh sebagai file Excel (.xlsx)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                Unduh Excel
            </a>

            <a href="../dashboard/index.php" class="bt-btn-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Kembali
            </a>

        </div>

    </div>

    <!-- ===== FILTER ===== -->

    <form method="GET" class="bt-filter" id="formFilter">

        <div class="bt-fg">
            <label for="bulan">Bulan</label>
            <select name="bulan" id="bulan" onchange="this.form.submit()">
                <option value="">Semua Bulan</option>
                <?php foreach ($nama_bulan as $n => $nm) { ?>
                    <option value="<?= $n; ?>" <?= ($bulan === $n) ? 'selected' : ''; ?>><?= $nm; ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="bt-fg">
            <label for="tahun">Tahun</label>
            <select name="tahun" id="tahun" onchange="this.form.submit()">
                <?php foreach ($daftar_tahun as $t) { ?>
                    <option value="<?= $t; ?>" <?= ($tahun === $t) ? 'selected' : ''; ?>><?= $t; ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="bt-fg bt-search">
            <label for="keyword">Pencarian</label>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari nama, instansi, keperluan...">
        </div>

        <button type="submit" class="bt-go">Tampilkan</button>

        <?php if ($ada_filter) { ?>
            <a href="buku_tamu.php" class="bt-reset">Reset</a>
        <?php } ?>

    </form>

    <!-- ===== GRAFIK + INSTANSI TERBANYAK ===== -->

    <div class="bt-insight">

        <div class="bt-card">

            <div class="bt-card-head">
                <h3><?= htmlspecialchars($judul_grafik); ?></h3>
                <?php if ($bulan === 0) { ?>
                    <small>Klik batang untuk melihat satu bulan</small>
                <?php } else { ?>
                    <small><a href="<?= htmlspecialchars(urlLaporanTamu($tahun, 0, $keyword)); ?>" style="color:#15803D;text-decoration:none;font-weight:600">Kembali ke semua bulan</a></small>
                <?php } ?>
            </div>

            <div class="bt-bars">

                <?php foreach ($seri as $k => $jml) {

                    $px = ($jml > 0 && $maks > 0) ? max((int) round($jml / $maks * 100), 10) : 4;

                    $kelas  = 'bt-bar';
                    $kelas .= ($jml > 0) ? ' ada' : '';
                    $kelas .= ($k === $puncak && $maks > 0) ? ' puncak' : '';

                    if ($bulan === 0) {

                        $tujuan  = htmlspecialchars(urlLaporanTamu($tahun, $k, $keyword));
                        $label   = $singkat[$k];
                        $judul   = $nama_bulan[$k] . ': ' . $jml . ' tamu';
                        $tag_a   = 'a href="' . $tujuan . '"';
                        $tag_e   = 'a';

                    } else {

                        $label = (string) $k;
                        $judul = $k . ' ' . $nama_bulan[$bulan] . ': ' . $jml . ' tamu';
                        $tag_a = 'div';
                        $tag_e = 'div';
                    }
                ?>

                    <<?= $tag_a; ?> class="<?= $kelas; ?>" title="<?= htmlspecialchars($judul); ?>">
                        <span class="n"><?= $jml > 0 ? $jml : ''; ?></span>
                        <span class="plot"><span class="tiang" style="height:<?= $px; ?>px"></span></span>
                        <span class="b"><?= htmlspecialchars($label); ?></span>
                    </<?= $tag_e; ?>>

                <?php } ?>

            </div>

        </div>

        <div class="bt-card">

            <div class="bt-card-head">
                <h3>Instansi terbanyak</h3>
                <small><?= $bulan > 0 ? htmlspecialchars($nama_bulan[$bulan]) . ' ' . $tahun : 'Tahun ' . $tahun; ?></small>
            </div>

            <?php if ($top_instansi) { ?>

                <ul class="bt-top">
                    <?php foreach ($top_instansi as $ti) {

                        $lebar = $maks_top > 0 ? max((int) round($ti['jml'] / $maks_top * 100), 8) : 0;
                    ?>
                        <li>
                            <div class="baris-atas">
                                <b title="<?= htmlspecialchars($ti['nama']); ?>"><?= htmlspecialchars($ti['nama']); ?></b>
                                <span><?= (int) $ti['jml']; ?> tamu</span>
                            </div>
                            <span class="jalur"><span class="isi" style="width:<?= $lebar; ?>%"></span></span>
                        </li>
                    <?php } ?>
                </ul>

            <?php } else { ?>

                <div class="bt-kosong-kecil">Belum ada data instansi pada periode ini.</div>

            <?php } ?>

        </div>

    </div>

    <!-- ===== TABEL ===== -->

    <div class="bt-table-card">

        <div class="bt-table-top">
            <h3>Daftar Tamu &middot; <?= htmlspecialchars($label_bulan); ?> <?= $tahun; ?></h3>
            <span class="bt-count"><?= $total_tampil; ?> tamu</span>
        </div>

        <div class="bt-scroll">

            <table class="bt-table">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Waktu Kunjungan</th>
                        <th>Nama Tamu</th>
                        <th>Instansi Asal</th>
                        <th>Keperluan</th>
                        <th>Dilayani Oleh</th>
                        <th style="text-align:center">Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total_tampil > 0) {

                    $no = 1;

                    while ($d = mysqli_fetch_assoc($hasil)) {

                        [$bg, $fg] = warnaTamu($d['nama'] ?? '');
                ?>

                        <tr style="animation-delay: <?= min($no * 0.03, 0.4); ?>s">

                            <td class="bt-no"><?= $no++; ?></td>

                            <td class="bt-waktu">
                                <b><?= tglTamu($d['tanggal_kunjungan'] ?? ''); ?></b>
                                <small><?= jamTamu($d['jam_kunjungan'] ?? ''); ?> WITA</small>
                            </td>

                            <td>
                                <div class="bt-tamu">
                                    <span class="bt-avatar" style="background:<?= $bg; ?>;color:<?= $fg; ?>"><?= htmlspecialchars(inisialTamu($d['nama'] ?? '')); ?></span>
                                    <b><?= htmlspecialchars($d['nama'] ?? ''); ?></b>
                                </div>
                            </td>

                            <td class="bt-instansi"><?= (isset($d['instansi_asal']) && $d['instansi_asal'] !== '') ? htmlspecialchars($d['instansi_asal']) : '-'; ?></td>

                            <td><div class="bt-keperluan"><?= (isset($d['keperluan']) && $d['keperluan'] !== '') ? htmlspecialchars($d['keperluan']) : '-'; ?></div></td>

                            <td>
                                <?php if (!empty($d['yang_melayani'])) { ?>
                                    <span class="bt-layani"><?= htmlspecialchars($d['yang_melayani']); ?></span>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>

                            <td style="text-align:center">
                                <a href="../buku_tamu/detail.php?id=<?= (int) $d['id']; ?>" class="bt-act" title="Detail Tamu">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                            </td>

                        </tr>

                    <?php }

                } else { ?>

                    <tr>
                        <td colspan="7" class="bt-empty">
                            <div class="ic">
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21c0-4 3-7 7-7s7 3 7 7"/></svg>
                            </div>
                            <h4>Tidak ada data tamu</h4>
                            <p>
                                Belum ada kunjungan pada <strong><?= htmlspecialchars($label_bulan); ?> <?= $tahun; ?></strong><?= $keyword !== '' ? ' dengan kata kunci tersebut' : ''; ?>.
                            </p>
                        </td>
                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
</div>

<?php
include "../layouts/footer.php";
?>