<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/ketidakhadiran.php";

adminDanOperator();
siapkanTabelKetidakhadiran($koneksi);
date_default_timezone_set('Asia/Makassar');

$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
if ($tahun < 2000 || $tahun > 2100) { $tahun = (int) date('Y'); }

$bulan = (isset($_GET['bulan']) && $_GET['bulan'] !== '') ? (int) $_GET['bulan'] : 0;
if ($bulan < 0 || $bulan > 12) { $bulan = 0; }

$jenis   = in_array($_GET['jenis'] ?? '', ['Tugas Luar', 'Cuti'], true) ? $_GET['jenis'] : '';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$singkat = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$label_bulan = ($bulan > 0) ? $nama_bulan[$bulan] : 'Semua Bulan';

$daftar_tahun = [(int) date('Y'), $tahun];
$rt = mysqli_query($koneksi, "SELECT DISTINCT YEAR(tanggal_mulai) AS y FROM ketidakhadiran");
if ($rt) {
    while ($r = mysqli_fetch_assoc($rt)) {
        if ((int) $r['y'] >= 2000) { $daftar_tahun[] = (int) $r['y']; }
    }
}
$daftar_tahun = array_values(array_unique($daftar_tahun));
rsort($daftar_tahun);

/* grafik per bulan: dua seri (tugas luar, cuti), tidak terpengaruh pencarian */
$per_bulan = array_fill(1, 12, 0);
$sb = mysqli_prepare($koneksi, "SELECT MONTH(tanggal_mulai) AS m, COUNT(*) AS c FROM ketidakhadiran WHERE YEAR(tanggal_mulai) = ?" . ($jenis !== '' ? " AND jenis = ?" : "") . " GROUP BY MONTH(tanggal_mulai)");
if ($jenis !== '') { mysqli_stmt_bind_param($sb, "is", $tahun, $jenis); } else { mysqli_stmt_bind_param($sb, "i", $tahun); }
mysqli_stmt_execute($sb);
$rb = mysqli_stmt_get_result($sb);
while ($r = mysqli_fetch_assoc($rb)) { $per_bulan[(int) $r['m']] = (int) $r['c']; }

$total_tahun = array_sum($per_bulan);
$maks        = max($per_bulan);
$bulan_ramai = ($maks > 0) ? array_search($maks, $per_bulan) : 0;

$sql = "SELECT k.*, p.nama, p.nip, p.jabatan
        FROM ketidakhadiran k JOIN pegawai p ON p.id = k.pegawai_id
        WHERE YEAR(k.tanggal_mulai) = ?";
$types  = "i";
$params = [$tahun];

if ($bulan > 0) { $sql .= " AND MONTH(k.tanggal_mulai) = ?"; $types .= "i"; $params[] = $bulan; }
if ($jenis !== '') { $sql .= " AND k.jenis = ?"; $types .= "s"; $params[] = $jenis; }
if ($keyword !== '') {
    $sql .= " AND (p.nama LIKE ? OR p.nip LIKE ? OR k.tujuan LIKE ? OR k.keperluan LIKE ?)";
    $like = "%" . $keyword . "%";
    $types .= "ssss";
    array_push($params, $like, $like, $like, $like);
}
$sql .= " ORDER BY k.tanggal_mulai ASC, k.id ASC";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$total_tampil = mysqli_num_rows($hasil);

function tgl($v) { return khTanggal($v); }

function urlLaporan($t, $b, $k, $j)
{
    $q = ['tahun' => $t];
    if ($b > 0) { $q['bulan'] = $b; }
    if ($j !== '') { $q['jenis'] = $j; }
    if ($k !== '') { $q['keyword'] = $k; }
    return 'ketidakhadiran.php?' . http_build_query($q);
}

$ada_filter = ($bulan > 0 || $keyword !== '' || $jenis !== '' || $tahun !== (int) date('Y'));

/* ===== UNDUH EXCEL ===== */

if (isset($_GET['unduh']) && $_GET['unduh'] === 'xlsx') {

    require_once "../config/xlsx.php";

    $xl_baris = [];
    $xl_no = 0;

    while ($xr = mysqli_fetch_assoc($hasil)) {
        $xl_no++;
        $xl_baris[] = [
            $xl_no,
            (string) $xr['nama'],
            (string) $xr['nip'],
            (string) $xr['jabatan'],
            (string) $xr['jenis'],
            khTanggal($xr['tanggal_mulai']),
            khTanggal($xr['tanggal_selesai']),
            khLamaHari($xr['tanggal_mulai'], $xr['tanggal_selesai']),
            (string) $xr['tujuan'],
            (string) $xr['keperluan'],
            (string) $xr['no_surat_tugas'],
        ];
    }

    kirimXlsx("ketidakhadiran_" . $tahun . ($bulan > 0 ? "_" . $nama_bulan[$bulan] : "") . ".xlsx",
        "Ketidakhadiran",
        [
            "LAPORAN KETIDAKHADIRAN PEGAWAI",
            "BKPSDM Kabupaten Tapin",
            "Periode: " . $label_bulan . " " . $tahun . ($jenis !== "" ? " | Jenis: " . $jenis : "") . ($keyword !== "" ? " | Kata kunci: " . $keyword : "") . " | Jumlah: " . $xl_no . " data",
        ],
        ["No", "Nama", "NIP", "Jabatan", "Jenis", "Mulai", "Selesai", "Lama (hari)", "Tujuan", "Acara / Keperluan", "No. Surat Tugas"],
        $xl_baris,
        [6, 30, 22, 30, 14, 14, 14, 12, 32, 36, 24]);
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.lp-wrap {
    --ac: #0EA5E9;
    --ac-dark: #0369A1;
    --ac-soft: #E0F2FE;
}

/* ===== HERO ===== */

.lp-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #14181f 0%, #0c2a44 60%, #0369A1 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.lp-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -80px;
    top: -140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(14,165,233,.5), transparent 70%);
    pointer-events: none;
}

.lp-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.045) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.lp-hero > * { position: relative; z-index: 1; }

.lp-eyebrow {
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

.lp-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #0EA5E9;
    box-shadow: 0 0 0 4px rgba(14,165,233,.35);
}

.lp-wrap .lp-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.lp-wrap .lp-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.65) !important;
}

.lp-hero-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.lp-pill {
    min-width: 98px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.lp-pill:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,.14);
}

.lp-pill strong {
    display: block;
    font-size: 21px;
    font-weight: 700;
    line-height: 1.1;
    color: #fff;
}

.lp-pill span {
    font-size: 10.5px;
    color: rgba(255,255,255,.6);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.lp-btn-print {
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
    box-shadow: 0 10px 24px rgba(14,165,233,.4);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.lp-btn-print:hover {
    background: #38BDF8;
    color: #fff;
    transform: translateY(-3px);
}

.lp-btn-back {
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

.lp-btn-back:hover {
    background: rgba(255,255,255,.18);
    color: #fff;
    transform: translateX(-3px);
}

/* ===== FILTER ===== */

.lp-filter {
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

.lp-fg { display: flex; flex-direction: column; gap: 6px; }

.lp-fg label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #9a9aa8;
}

.lp-fg select,
.lp-search input {
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

.lp-fg select { min-width: 150px; cursor: pointer; }

.lp-fg select:focus,
.lp-search input:focus {
    background: #fff;
    border-color: var(--ac);
    box-shadow: 0 0 0 4px rgba(14,165,233,.14);
}

.lp-search { flex: 1; min-width: 240px; position: relative; }

.lp-search svg {
    position: absolute;
    left: 14px;
    bottom: 14px;
    pointer-events: none;
}

.lp-search input { width: 100%; padding-left: 40px; }

.lp-go {
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

.lp-go:hover { background: var(--ac-dark); transform: translateY(-2px); }

.lp-reset {
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

.lp-reset:hover { background: #e9e9ef; color: #222; }

/* ===== GRAFIK ===== */

.lp-chart {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    padding: 20px 24px 16px;
    margin-bottom: 18px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.lp-chart-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.lp-chart-head h3 {
    margin: 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1d1d27;
}

.lp-chart-head small { font-size: 11.5px; color: #9a9aa8; }

.lp-bars {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 8px;
    padding-top: 6px;
}

.lp-bar {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    cursor: pointer;
}

.lp-bar .n {
    height: 16px;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 16px;
    color: #8a8a98;
    transition: color .2s;
}

.lp-bar .plot {
    width: 100%;
    height: 100px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    border-bottom: 1.5px solid #eceef4;
}

.lp-bar .lp-col {
    display: block;
    width: 100%;
    max-width: 34px;
    border-radius: 9px 9px 3px 3px;
    background: #eceef4;
    transition: all .3s var(--ease-smooth, ease);
}

/* bulan yang ada suratnya */
.lp-bar.ada .lp-col {
    background: linear-gradient(180deg, var(--ac), var(--ac-dark));
    opacity: .5;
}

.lp-bar.ada:hover .lp-col { opacity: .85; transform: translateY(-3px); }

/* bulan terbanyak */
.lp-bar.top .lp-col { opacity: 1; }
.lp-bar.top .n { color: var(--ac-dark); }

/* bulan yang sedang dipilih */
.lp-bar.on .lp-col {
    opacity: 1;
    box-shadow: 0 8px 18px rgba(0,0,0,.18);
}

.lp-bar.on .n { color: var(--ac-dark); }

.lp-bar .b {
    font-size: 10.5px;
    font-weight: 600;
    color: #9a9aa8;
}

.lp-bar.on .b {
    color: var(--ac-dark);
    font-weight: 800;
}

.lp-bar:hover .b { color: #333; }

/* ===== TABEL ===== */

.lp-table-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.lp-table-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 18px 24px;
    border-bottom: 1px solid var(--color-border, #eee);
}

.lp-table-top h3 {
    margin: 0;
    font-size: 14.5px;
    font-weight: 700;
    color: #1d1d27;
}

.lp-count {
    padding: 4px 12px;
    border-radius: 999px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 11.5px;
    font-weight: 700;
}

.lp-scroll { overflow-x: auto; }

.lp-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.lp-table thead th {
    padding: 13px 16px;
    background: #fafafd;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .7px;
    text-transform: uppercase;
    color: #8a8a98;
    text-align: left;
    white-space: nowrap;
}

.lp-table tbody td {
    padding: 15px 16px;
    border-top: 1px solid #f2f2f7;
    font-size: 13px;
    color: #2a2a36;
    vertical-align: middle;
}

.lp-table tbody tr {
    transition: background .2s;
    animation: lpIn .4s var(--ease-smooth, ease) both;
}

.lp-table tbody tr:hover { background: #fffaf6; }

@keyframes lpIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.lp-no { color: #a3a3b1; font-weight: 600; width: 52px; }

.lp-nomor {
    display: inline-block;
    padding: 5px 11px;
    border-radius: 9px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.lp-tgl { white-space: nowrap; color: #55556a; }

.lp-tujuan { font-weight: 600; color: #1d1d27; min-width: 160px; }

.lp-perihal {
    min-width: 220px;
    max-width: 340px;
    line-height: 1.55;
    color: #55556a;
}

.lp-ket {
    min-width: 140px;
    max-width: 240px;
    line-height: 1.55;
    color: #8a8a98;
    font-size: 12.5px;
}

.lp-sifat { display:inline-block; padding:3px 10px; border-radius:999px; background:#E0F2FE; color:#0369A1; font-size:12px; font-weight:700; white-space:nowrap; }

.lp-tl { display:inline-block; padding:3px 10px; border-radius:999px; background:#DBEAFE; color:#1D4ED8; font-size:12px; font-weight:700; white-space:nowrap; }
.lp-tl.arsip { background:#DCFCE7; color:#15803D; }

.lp-act {
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

.lp-act:hover {
    background: var(--ac);
    color: #fff;
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(14,165,233,.3);
}

.lp-empty {
    text-align: center;
    padding: 60px 20px !important;
    color: #9a9aa8;
}

.lp-empty .ic {
    width: 68px;
    height: 68px;
    margin: 0 auto 14px;
    border-radius: 22px;
    background: var(--ac-soft);
    display: flex;
    align-items: center;
    justify-content: center;
}

.lp-empty h4 { margin: 0 0 4px; color: #333; font-size: 15px; }
.lp-empty p { margin: 0; font-size: 12.5px; }

/* ===== RESPONSIVE ===== */

@media (max-width: 700px) {
    .lp-hero { padding: 24px 20px; }
    .lp-wrap .lp-hero h1 { font-size: 23px; }
    .lp-fg, .lp-fg select { width: 100%; }
    .lp-chart { padding: 18px 14px 14px; }
    .lp-bars { gap: 4px; }
    .lp-bar .b { font-size: 9px; }
    .lp-bar .n { font-size: 10px; }
}

.lp-tl.cuti { background:#FEF3C7; color:#B45309; }
.lp-tl.tl { background:#E0F2FE; color:#0369A1; }
.lp-lama { display:block; font-size:11.5px; color:#8a8a98; font-weight:500; }
.lp-nip { display:block; font-size:11.5px; color:#8a8a98; font-weight:500; }
</style>

<div class="content">
<div class="container-fluid lp-wrap">

    <!-- ===== HERO ===== -->

    <div class="lp-hero">

        <div>
            <div class="lp-eyebrow"><i></i> LAPORAN</div>
            <h1>Laporan Ketidakhadiran Pegawai</h1>
            <p>Rekap tugas luar dan cuti pegawai, per periode.</p>
        </div>

        <div class="lp-hero-right">

            <div class="lp-pill"><strong><?= $total_tahun; ?></strong><span>Tahun <?= $tahun; ?></span></div>
            <div class="lp-pill"><strong><?= $total_tampil; ?></strong><span>Ditampilkan</span></div>
            <div class="lp-pill"><strong><?= $bulan_ramai > 0 ? $singkat[$bulan_ramai] : '-'; ?></strong><span>Terbanyak</span></div>

            <a href="cetak_ketidakhadiran.php?tahun=<?= urlencode($tahun); ?>&bulan=<?= urlencode($bulan); ?>&jenis=<?= urlencode($jenis); ?>&keyword=<?= urlencode($keyword); ?>" class="lp-btn-print">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Lihat Laporan
            </a>

            <style>
.xl-btn { display:inline-flex; align-items:center; gap:8px; height:46px; padding:0 18px; border-radius:14px; background:#16A34A; color:#fff; font-size:13px; font-weight:700; text-decoration:none; box-shadow:0 10px 24px rgba(22,163,74,.35); transition:transform .25s var(--ease-smooth, ease), background .25s; }
.xl-btn:hover { background:#22C55E; color:#fff; transform:translateY(-3px); }
</style>
            <a href="ketidakhadiran.php?tahun=<?= urlencode($tahun); ?>&bulan=<?= urlencode($bulan); ?>&jenis=<?= urlencode($jenis); ?>&keyword=<?= urlencode($keyword); ?>&unduh=xlsx" class="xl-btn" title="Unduh sebagai file Excel (.xlsx)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                Unduh Excel
            </a>

            <a href="../dashboard/index.php" class="lp-btn-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Kembali
            </a>

        </div>

    </div>

    <!-- ===== FILTER ===== -->

    <form method="GET" class="lp-filter" id="formFilter">

        <div class="lp-fg">
            <label for="jenis">Jenis</label>
            <select name="jenis" id="jenis" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="Tugas Luar" <?= $jenis === 'Tugas Luar' ? 'selected' : ''; ?>>Tugas Luar</option>
                <option value="Cuti" <?= $jenis === 'Cuti' ? 'selected' : ''; ?>>Cuti</option>
            </select>
        </div>

        <div class="lp-fg">
            <label for="bulan">Bulan</label>
            <select name="bulan" id="bulan" onchange="this.form.submit()">
                <option value="">Semua Bulan</option>
                <?php foreach ($nama_bulan as $n => $nm) { ?>
                    <option value="<?= $n; ?>" <?= ($bulan === $n) ? 'selected' : ''; ?>><?= $nm; ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="lp-fg">
            <label for="tahun">Tahun</label>
            <select name="tahun" id="tahun" onchange="this.form.submit()">
                <?php foreach ($daftar_tahun as $t) { ?>
                    <option value="<?= $t; ?>" <?= ($tahun === $t) ? 'selected' : ''; ?>><?= $t; ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="lp-fg lp-search">
            <label for="keyword">Pencarian</label>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari nama, NIP, tujuan, acara...">
        </div>

        <button type="submit" class="lp-go">Tampilkan</button>

        <?php if ($ada_filter) { ?>
            <a href="ketidakhadiran.php" class="lp-reset">Reset</a>
        <?php } ?>

    </form>

    <!-- ===== GRAFIK PER BULAN ===== -->

    <div class="lp-chart">

        <div class="lp-chart-head">
            <h3>Ketidakhadiran per bulan, <?= $tahun; ?><?= $jenis !== '' ? ' &middot; ' . htmlspecialchars($jenis) : ''; ?></h3>
            <small>Klik batang untuk melihat satu bulan<?= $bulan > 0 ? ' · klik lagi untuk semua bulan' : ''; ?></small>
        </div>

        <div class="lp-bars">

            <?php for ($m = 1; $m <= 12; $m++) {

                $jml    = $per_bulan[$m];
                $px     = ($jml > 0 && $maks > 0) ? max(round($jml / $maks * 100), 10) : 4;
                $tujuan = urlLaporan($tahun, ($bulan === $m ? 0 : $m), $keyword, $jenis);

                $kelas  = 'lp-bar';
                $kelas .= ($jml > 0) ? ' ada' : '';
                $kelas .= ($bulan === $m) ? ' on' : '';
                $kelas .= ($m === $bulan_ramai && $maks > 0) ? ' top' : '';
            ?>

                <a href="<?= htmlspecialchars($tujuan); ?>" class="<?= $kelas; ?>" title="<?= $nama_bulan[$m]; ?>: <?= $jml; ?> data">
                    <span class="n"><?= $jml > 0 ? $jml : ''; ?></span>
                    <span class="plot"><span class="lp-col" style="height:<?= $px; ?>px"></span></span>
                    <span class="b"><?= $singkat[$m]; ?></span>
                </a>

            <?php } ?>

        </div>

    </div>

    <!-- ===== TABEL ===== -->

    <div class="lp-table-card">

        <div class="lp-table-top">
            <h3>Daftar Ketidakhadiran &middot; <?= htmlspecialchars($label_bulan); ?> <?= $tahun; ?></h3>
            <span class="lp-count"><?= $total_tampil; ?> data</span>
        </div>

        <div class="lp-scroll">

            <table class="lp-table">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Pegawai</th>
                        <th>Jenis</th>
                        <th>Waktu</th>
                        <th>Tujuan</th>
                        <th>Acara / Keperluan</th>
                        <th>No. Surat Tugas</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total_tampil > 0) {

                    $no = 1;

                    while ($d = mysqli_fetch_assoc($hasil)) { ?>

                        <tr style="animation-delay: <?= min($no * 0.03, 0.4); ?>s">

                            <td class="lp-no"><?= $no++; ?></td>

                            <td class="lp-tujuan">
                                <?= htmlspecialchars($d['nama'] ?? ''); ?>
                                <span class="lp-nip"><?= htmlspecialchars(($d['nip'] ?? '') !== '' ? $d['nip'] : ''); ?></span>
                            </td>

                            <td><span class="lp-tl <?= ($d['jenis'] ?? '') === 'Cuti' ? 'cuti' : 'tl'; ?>"><?= htmlspecialchars($d['jenis'] ?? ''); ?></span></td>

                            <td class="lp-tgl">
                                <?= tgl($d['tanggal_mulai']); ?><?= $d['tanggal_selesai'] !== $d['tanggal_mulai'] ? ' s.d. ' . tgl($d['tanggal_selesai']) : ''; ?>
                                <span class="lp-lama"><?= khLamaHari($d['tanggal_mulai'], $d['tanggal_selesai']); ?> hari</span>
                            </td>

                            <td class="lp-perihal"><?= htmlspecialchars(($d['tujuan'] ?? '') !== '' ? $d['tujuan'] : '-'); ?></td>

                            <td class="lp-ket"><?= htmlspecialchars(($d['keperluan'] ?? '') !== '' ? $d['keperluan'] : '-'); ?></td>

                            <td class="lp-ket"><?= htmlspecialchars(($d['no_surat_tugas'] ?? '') !== '' ? $d['no_surat_tugas'] : '-'); ?></td>

                        </tr>

                    <?php }

                } else { ?>

                    <tr>
                        <td colspan="7" class="lp-empty">
                            <div class="ic">
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#C2410C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
                            </div>
                            <h4>Tidak ada data ketidakhadiran</h4>
                            <p>
                                Belum ada data pada <strong><?= htmlspecialchars($label_bulan); ?> <?= $tahun; ?></strong><?= $keyword !== '' ? ' dengan kata kunci tersebut' : ''; ?>.
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