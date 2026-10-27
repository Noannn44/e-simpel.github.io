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
   PENCARIAN
========================================================= */

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

/* =========================================================
   STATISTIK (seluruh pegawai, tidak terpengaruh pencarian)
========================================================= */

$st = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(status) = 'aktif' THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN LOWER(status) <> 'aktif' OR status IS NULL THEN 1 ELSE 0 END) AS nonaktif
    FROM pegawai
"));

$total_pegawai = (int) ($st['total'] ?? 0);
$total_aktif   = (int) ($st['aktif'] ?? 0);
$total_non     = (int) ($st['nonaktif'] ?? 0);

/* =========================================================
   SEBARAN PER UNIT KERJA
========================================================= */

$unit_list = [];

$ru = mysqli_query($koneksi, "
    SELECT unit_kerja, COUNT(*) AS jml
    FROM pegawai
    WHERE unit_kerja IS NOT NULL AND unit_kerja <> ''
    GROUP BY unit_kerja
    ORDER BY (unit_kerja = 'Pimpinan') DESC, jml DESC, unit_kerja ASC
");

if ($ru) {
    while ($r = mysqli_fetch_assoc($ru)) {
        $unit_list[] = $r;
    }
}

$total_unit = count($unit_list);
$maks_unit  = 0;

foreach ($unit_list as $u) {
    $maks_unit = max($maks_unit, (int) $u['jml']);
}

/* =========================================================
   QUERY DATA
========================================================= */

$sql    = "SELECT id, nip, nama, jabatan, unit_kerja, status FROM pegawai WHERE 1=1";
$types  = "";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (nip LIKE ? OR nama LIKE ? OR jabatan LIKE ? OR unit_kerja LIKE ?)";
    $like = "%" . $keyword . "%";
    $types .= "ssss";
    array_push($params, $like, $like, $like, $like);
}

$sql .= " ORDER BY nama ASC"; // diurutkan ulang menurut jabatan di bawah

$stmt = mysqli_prepare($koneksi, $sql);

if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$daftar_pegawai = [];

while ($baris = mysqli_fetch_assoc($hasil)) {
    $daftar_pegawai[] = $baris;
}

// pimpinan paling atas, lalu sekretaris, dst. menurut jabatan
urutkanPegawai($daftar_pegawai);

$total_tampil = count($daftar_pegawai);

/* =========================================================
   HELPER
========================================================= */

function inisial($nama)
{
    $kata = preg_split('/\s+/', trim((string) $nama));
    $hasil = '';

    foreach ($kata as $k) {
        if ($k !== '' && preg_match('/^[A-Za-z]/', $k)) {
            $hasil .= strtoupper($k[0]);
        }
        if (strlen($hasil) >= 2) {
            break;
        }
    }

    return $hasil !== '' ? $hasil : '?';
}

function warnaAvatar($nama)
{
    $palet = [
        ['#EDE9FE', '#6D28D9'],
        ['#DBEAFE', '#1D4ED8'],
        ['#FFEDD5', '#C2410C'],
        ['#CCFBF1', '#0F766E'],
        ['#FCE7F3', '#BE185D'],
        ['#DCFCE7', '#15803D'],
    ];

    return $palet[crc32((string) $nama) % count($palet)];
}

/* =========================================================
   UNDUH EXCEL (.xlsx)
========================================================= */

if (isset($_GET['unduh']) && $_GET['unduh'] === 'xlsx') {

    require_once "../config/xlsx.php";

    $xl_baris = [];
    $xl_no    = 0;

    foreach ($daftar_pegawai as $xr) {
        $xl_no++;
        $xl_baris[] = [
            $xl_no,
            (string) $xr['nip'],
            (string) $xr['nama'],
            (string) $xr['jabatan'],
            (string) $xr['unit_kerja'],
            (string) $xr['status'],
        ];
    }

    kirimXlsx("data_pegawai.xlsx",
        "Data Pegawai",
        [
            "LAPORAN DATA PEGAWAI",
            "BKPSDM Kabupaten Tapin",
            ($keyword !== "" ? "Kata kunci: " . $keyword . " | " : "") . "Jumlah: " . $xl_no . " pegawai (urut menurut jabatan)",
        ],
        ["No", "NIP", "Nama", "Jabatan", "Unit Kerja", "Status"],
        $xl_baris,
        [6, 24, 34, 40, 34, 14]);
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.lp-wrap {
    --ac: #8B5CF6;
    --ac-dark: #6D28D9;
    --ac-soft: #EDE9FE;
}

/* ===== HERO ===== */

.lp-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #14181f 0%, #1d1a35 60%, #3b2a7a 100%);
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
    background: radial-gradient(circle, rgba(139,92,246,.5), transparent 70%);
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
    background: #A78BFA;
    box-shadow: 0 0 0 4px rgba(139,92,246,.4);
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
    min-width: 92px;
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
    box-shadow: 0 10px 24px rgba(139,92,246,.4);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.lp-btn-print:hover {
    background: #A78BFA;
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
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 18px;
    padding: 12px 14px;
    margin-bottom: 18px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.lp-search { flex: 1; min-width: 240px; position: relative; }

.lp-search svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

.lp-search input {
    width: 100%;
    height: 44px;
    padding: 0 14px 0 40px;
    border: 1.5px solid transparent;
    background: #f4f4f7;
    border-radius: 13px;
    font-size: 13px;
    color: #1d1d27;
    outline: none;
    transition: all .25s var(--ease-smooth, ease);
}

.lp-search input:focus {
    background: #fff;
    border-color: var(--ac);
    box-shadow: 0 0 0 4px rgba(139,92,246,.14);
}

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

/* ===== SEBARAN UNIT ===== */

.lp-chart {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    padding: 20px 24px 18px;
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

.lp-units {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 22px;
}

.lp-unit {
    display: grid;
    grid-template-columns: minmax(120px, 1.1fr) 1.4fr 30px;
    align-items: center;
    gap: 12px;
    padding: 7px 10px;
    border-radius: 11px;
    text-decoration: none;
    transition: background .2s;
}

.lp-unit:hover { background: #f7f5ff; }

.lp-unit.on { background: var(--ac-soft); }

.lp-unit .lp-unm {
    font-size: 12.3px;
    font-weight: 600;
    color: #3a3a48;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lp-unit .lp-utrack {
    display: block;
    height: 8px;
    border-radius: 6px;
    background: #f0eefa;
    overflow: hidden;
}

.lp-unit .lp-ufill {
    display: block;
    height: 100%;
    border-radius: 6px;
    background: linear-gradient(90deg, #A78BFA, var(--ac-dark));
    transition: width .5s var(--ease-smooth, ease);
}

.lp-unit .lp-ujml {
    text-align: right;
    font-size: 12px;
    font-weight: 700;
    color: var(--ac-dark);
}

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
    min-width: 880px;
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
    padding: 14px 16px;
    border-top: 1px solid #f2f2f7;
    font-size: 13px;
    color: #2a2a36;
    vertical-align: middle;
}

.lp-table tbody tr {
    transition: background .2s;
    animation: lpIn .4s var(--ease-smooth, ease) both;
}

.lp-table tbody tr:hover { background: #faf8ff; }

@keyframes lpIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.lp-no { color: #a3a3b1; font-weight: 600; width: 52px; }

.lp-nip {
    display: inline-block;
    padding: 5px 11px;
    border-radius: 9px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
}

.lp-person {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 200px;
}

.lp-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
}

.lp-person b {
    font-weight: 700;
    color: #1d1d27;
}

.lp-jabatan { min-width: 200px; max-width: 300px; line-height: 1.5; color: #55556a; }

.lp-unitcell { min-width: 140px; color: #55556a; }

.lp-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
}

.lp-status i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.lp-status.aktif { background: #DCFCE7; color: #15803D; }
.lp-status.aktif i { background: #22C55E; }
.lp-status.non { background: #F1F1F6; color: #6b6b78; }
.lp-status.non i { background: #a3a3b1; }

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
    box-shadow: 0 8px 16px rgba(139,92,246,.3);
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

@media (max-width: 900px) {
    .lp-units { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
    .lp-hero { padding: 24px 20px; }
    .lp-wrap .lp-hero h1 { font-size: 23px; }
    .lp-chart { padding: 18px 14px; }
    .lp-unit { grid-template-columns: minmax(90px, 1fr) 1fr 26px; gap: 8px; }
}

</style>

<div class="content">
<div class="container-fluid lp-wrap">

    <!-- ===== HERO ===== -->

    <div class="lp-hero">

        <div>
            <div class="lp-eyebrow"><i></i> LAPORAN</div>
            <h1>Laporan Data Pegawai</h1>
            <p>Rekap seluruh pegawai beserta jabatan, unit kerja, dan status.</p>
        </div>

        <div class="lp-hero-right">

            <div class="lp-pill"><strong><?= $total_pegawai; ?></strong><span>Pegawai</span></div>
            <div class="lp-pill"><strong><?= $total_aktif; ?></strong><span>Aktif</span></div>
            <div class="lp-pill"><strong><?= $total_non; ?></strong><span>Nonaktif</span></div>
            <div class="lp-pill"><strong><?= $total_unit; ?></strong><span>Unit Kerja</span></div>

            <a href="cetak_pegawai.php?keyword=<?= urlencode($keyword); ?>" class="lp-btn-print">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Lihat Laporan
            </a>

            <style>
.xl-btn { display:inline-flex; align-items:center; gap:8px; height:46px; padding:0 18px; border-radius:14px; background:#16A34A; color:#fff; font-size:13px; font-weight:700; text-decoration:none; box-shadow:0 10px 24px rgba(22,163,74,.35); transition:transform .25s var(--ease-smooth, ease), background .25s; }
.xl-btn:hover { background:#22C55E; color:#fff; transform:translateY(-3px); }
</style>
            <a href="pegawai.php?keyword=<?= urlencode($keyword); ?>&unduh=xlsx" class="xl-btn" title="Unduh sebagai file Excel (.xlsx)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                Unduh Excel
            </a>

            <a href="../dashboard/index.php" class="lp-btn-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Kembali
            </a>

        </div>

    </div>

    <!-- ===== PENCARIAN ===== -->

    <form method="GET" class="lp-filter">

        <div class="lp-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="text" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari NIP, nama, jabatan, atau unit kerja...">
        </div>

        <button type="submit" class="lp-go">Cari</button>

        <?php if ($keyword !== '') { ?>
            <a href="pegawai.php" class="lp-reset">Reset</a>
        <?php } ?>

    </form>

    <!-- ===== SEBARAN UNIT KERJA ===== -->

    <?php if ($total_unit > 0) { ?>

        <div class="lp-chart">

            <div class="lp-chart-head">
                <h3>Sebaran pegawai per unit kerja</h3>
                <small>Klik unit untuk menyaring tabel<?= $keyword !== '' ? ' · klik lagi untuk menghapus saringan' : ''; ?></small>
            </div>

            <div class="lp-units">

                <?php foreach ($unit_list as $u) {

                    $lebar  = ($maks_unit > 0) ? max(round($u['jml'] / $maks_unit * 100), 6) : 0;
                    $aktif  = (strcasecmp($keyword, $u['unit_kerja']) === 0);
                    $tujuan = $aktif ? 'pegawai.php' : 'pegawai.php?keyword=' . urlencode($u['unit_kerja']);
                ?>

                    <a href="<?= htmlspecialchars($tujuan); ?>" class="lp-unit <?= $aktif ? 'on' : ''; ?>" title="<?= htmlspecialchars($u['unit_kerja']); ?>">
                        <span class="lp-unm"><?= htmlspecialchars($u['unit_kerja']); ?></span>
                        <span class="lp-utrack"><span class="lp-ufill" style="width:<?= $lebar; ?>%"></span></span>
                        <span class="lp-ujml"><?= (int) $u['jml']; ?></span>
                    </a>

                <?php } ?>

            </div>

        </div>

    <?php } ?>

    <!-- ===== TABEL ===== -->

    <div class="lp-table-card">

        <div class="lp-table-top">
            <h3>Daftar Pegawai<?= $keyword !== '' ? ' &middot; "' . htmlspecialchars($keyword) . '"' : ''; ?></h3>
            <span class="lp-count"><?= $total_tampil; ?> pegawai</span>
        </div>

        <div class="lp-scroll">

            <table class="lp-table">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIP</th>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>Unit Kerja</th>
                        <th>Status</th>
                        <th style="text-align:center">Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total_tampil > 0) {

                    $no = 1;

                    foreach ($daftar_pegawai as $d) {

                        [$bg, $fg] = warnaAvatar($d['nama'] ?? '');
                        $aktif_p   = (strtolower(trim((string) ($d['status'] ?? ''))) === 'aktif');
                ?>

                        <tr style="animation-delay: <?= min($no * 0.03, 0.4); ?>s">

                            <td class="lp-no"><?= $no++; ?></td>

                            <td><span class="lp-nip"><?= htmlspecialchars(($d['nip'] ?? '') !== '' ? $d['nip'] : '-'); ?></span></td>

                            <td>
                                <div class="lp-person">
                                    <div class="lp-avatar" style="background:<?= $bg; ?>;color:<?= $fg; ?>"><?= htmlspecialchars(inisial($d['nama'] ?? '')); ?></div>
                                    <b><?= htmlspecialchars($d['nama'] ?? '-'); ?></b>
                                </div>
                            </td>

                            <td class="lp-jabatan"><?= htmlspecialchars(($d['jabatan'] ?? '') !== '' ? $d['jabatan'] : '-'); ?></td>

                            <td class="lp-unitcell"><?= htmlspecialchars(($d['unit_kerja'] ?? '') !== '' ? $d['unit_kerja'] : '-'); ?></td>

                            <td>
                                <span class="lp-status <?= $aktif_p ? 'aktif' : 'non'; ?>">
                                    <i></i><?= $aktif_p ? 'Aktif' : htmlspecialchars(($d['status'] ?? '') !== '' ? $d['status'] : 'Nonaktif'); ?>
                                </span>
                            </td>

                            <td style="text-align:center">
                                <a href="../pegawai/detail.php?id=<?= (int) $d['id']; ?>" class="lp-act" title="Detail Pegawai">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                            </td>

                        </tr>

                    <?php }

                } else { ?>

                    <tr>
                        <td colspan="7" class="lp-empty">
                            <div class="ic">
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <h4><?= $keyword !== '' ? 'Pegawai tidak ditemukan' : 'Belum ada data pegawai'; ?></h4>
                            <p><?= $keyword !== '' ? 'Coba ubah kata kunci pencarian.' : 'Data pegawai akan tampil di sini setelah ditambahkan.'; ?></p>
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