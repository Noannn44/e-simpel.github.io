<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

adminDanOperator();

/* Hanya admin yang boleh tambah, edit, dan hapus */
$adalahAdmin = (roleUser() === 'admin');

include "../config/database.php";

/* =========================
   FILTER & PENCARIAN
========================= */

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$filter  = isset($_GET['status']) ? trim($_GET['status']) : 'semua';
$unit    = isset($_GET['unit']) ? trim($_GET['unit']) : '';

if (!in_array($filter, ['semua', 'aktif', 'nonaktif'], true)) {
    $filter = 'semua';
}

/* =========================
   TINGKAT JABATAN
   (angka kecil = lebih tinggi)
   Ubah / tambah kata kunci di sini
   bila penamaan jabatan berbeda.
========================= */

function tingkatJabatan($jabatan, $unit_kerja = '')
{
    // pegawai di unit "Pimpinan" selalu tingkat tertinggi
    if (strtolower(trim($unit_kerja)) === 'pimpinan') {
        return 1;
    }

    $j = strtolower(trim($jabatan));

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

function labelTingkat($t)
{
    if ($t <= 1) return ['Pimpinan', 'lead'];
    if ($t <= 4) return ['Struktural', 'struk'];
    if ($t <= 8) return ['Fungsional', 'fung'];
    return ['Pelaksana', 'pel'];
}

/* =========================
   STATISTIK
========================= */

$stat = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(status) = 'aktif' THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN LOWER(status) <> 'aktif' THEN 1 ELSE 0 END) AS nonaktif,
        COUNT(DISTINCT unit_kerja) AS unit
    FROM pegawai
"));

$total_pegawai = (int) ($stat['total'] ?? 0);
$total_aktif   = (int) ($stat['aktif'] ?? 0);
$total_non     = (int) ($stat['nonaktif'] ?? 0);
$total_unit    = (int) ($stat['unit'] ?? 0);

/* =========================
   DAFTAR UNIT KERJA (DROPDOWN)
========================= */

$daftar_unit = [];
$qu = mysqli_query($koneksi, "SELECT DISTINCT unit_kerja FROM pegawai WHERE unit_kerja <> '' ORDER BY unit_kerja ASC");
while ($u = mysqli_fetch_assoc($qu)) {
    $daftar_unit[] = $u['unit_kerja'];
}

/* =========================
   QUERY DATA
========================= */

$sql    = "SELECT * FROM pegawai WHERE 1=1";
$types  = "";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (nip LIKE ? OR nama LIKE ? OR jabatan LIKE ? OR unit_kerja LIKE ?)";
    $like = "%" . $keyword . "%";
    $types .= "ssss";
    array_push($params, $like, $like, $like, $like);
}

if ($unit !== '') {
    $sql .= " AND unit_kerja = ?";
    $types .= "s";
    $params[] = $unit;
}

if ($filter === 'aktif') {
    $sql .= " AND LOWER(status) = 'aktif'";
} elseif ($filter === 'nonaktif') {
    $sql .= " AND LOWER(status) <> 'aktif'";
}

$stmt = mysqli_prepare($koneksi, $sql);

if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

/* =========================
   KELOMPOKKAN + URUTKAN
========================= */

$grup = [];
$jumlah_tampil = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $row['_tingkat'] = tingkatJabatan($row['jabatan'], $row['unit_kerja']);
    $nama_unit = trim($row['unit_kerja']) !== '' ? $row['unit_kerja'] : 'Tanpa Unit Kerja';
    $grup[$nama_unit][] = $row;
    $jumlah_tampil++;
}

// urutkan pegawai dalam kelompok: jabatan tertinggi dulu, lalu nama
foreach ($grup as $nama_unit => &$daftar) {
    usort($daftar, function ($a, $b) {
        if ($a['_tingkat'] !== $b['_tingkat']) {
            return $a['_tingkat'] <=> $b['_tingkat'];
        }
        return strcasecmp($a['nama'], $b['nama']);
    });
}
unset($daftar);

// urutkan kelompok: Pimpinan selalu paling atas,
// lalu yang memuat jabatan tertinggi, lalu nama unit
uksort($grup, function ($ua, $ub) use ($grup) {

    if (strcasecmp($ua, 'Pimpinan') === 0) return -1;
    if (strcasecmp($ub, 'Pimpinan') === 0) return 1;

    $ta = $grup[$ua][0]['_tingkat'];
    $tb = $grup[$ub][0]['_tingkat'];

    if ($ta !== $tb) {
        return $ta <=> $tb;
    }

    return strcasecmp($ua, $ub);
});

/* =========================
   HELPER
========================= */

function inisial($nama)
{
    $kata = preg_split('/\s+/', trim($nama));
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
        ['#DCFCE7', '#15803D'],
        ['#FCE7F3', '#BE185D'],
        ['#CCFBF1', '#0F766E'],
    ];

    return $palet[crc32($nama) % count($palet)];
}

function urlFilter($status, $keyword, $unit)
{
    $q = [];
    if ($status !== 'semua') {
        $q['status'] = $status;
    }
    if ($keyword !== '') {
        $q['keyword'] = $keyword;
    }
    if ($unit !== '') {
        $q['unit'] = $unit;
    }
    return 'index.php' . (count($q) ? '?' . http_build_query($q) : '');
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.pg-wrap {
    --pg: #8B5CF6;
    --pg-dark: #6D28D9;
    --pg-soft: #EDE9FE;
}

/* ===== HERO ===== */

.pg-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #1b1a26 0%, #272238 60%, #33284f 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.pg-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -80px;
    top: -140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139,92,246,.45), transparent 70%);
    pointer-events: none;
}

.pg-hero-left { position: relative; z-index: 1; }

.pg-eyebrow {
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

.pg-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--pg);
    box-shadow: 0 0 0 4px rgba(139,92,246,.3);
}

.pg-wrap .pg-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.pg-wrap .pg-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.65) !important;
}

.pg-hero-right {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.pg-pill {
    min-width: 92px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.pg-pill:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,.14);
}

.pg-pill strong {
    display: block;
    font-size: 21px;
    font-weight: 700;
    line-height: 1.1;
    color: #fff;
}

.pg-pill span {
    font-size: 10.5px;
    color: rgba(255,255,255,.6);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.pg-add {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 46px;
    padding: 0 20px;
    border-radius: 14px;
    background: var(--pg);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 10px 24px rgba(139,92,246,.38);
    transition: transform .25s var(--ease-smooth, ease), box-shadow .25s, background .25s;
}

.pg-add:hover {
    background: var(--pg-dark);
    color: #fff;
    transform: translateY(-3px);
}

/* ===== TOOLBAR ===== */

.pg-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 18px;
    padding: 10px 12px;
    margin-bottom: 22px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.pg-tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    background: #f4f4f7;
    border-radius: 13px;
}

.pg-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    color: #6b6b78;
    text-decoration: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pg-tab em {
    font-style: normal;
    font-size: 10.5px;
    padding: 1px 7px;
    border-radius: 999px;
    background: rgba(0,0,0,.06);
}

.pg-tab:hover { color: #222; }

.pg-tab.active {
    background: #fff;
    color: var(--pg-dark);
    box-shadow: 0 3px 10px rgba(0,0,0,.08);
}

.pg-tab.active em {
    background: var(--pg-soft);
    color: var(--pg-dark);
}

.pg-search {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    justify-content: flex-end;
    min-width: 260px;
}

.pg-search-box {
    position: relative;
    flex: 1;
    max-width: 340px;
}

.pg-search-box svg {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
}

.pg-search-box input,
.pg-select {
    height: 42px;
    border: 1px solid transparent;
    background: #f4f4f7;
    border-radius: 13px;
    font-size: 12.5px;
    outline: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pg-search-box input {
    width: 100%;
    padding: 0 14px 0 38px;
}

.pg-select {
    padding: 0 12px;
    max-width: 200px;
    cursor: pointer;
    color: #444;
}

.pg-search-box input:focus,
.pg-select:focus {
    background: #fff;
    border-color: var(--pg);
    box-shadow: 0 0 0 4px rgba(139,92,246,.12);
}

.pg-btn-cari {
    height: 42px;
    padding: 0 18px;
    border: none;
    border-radius: 13px;
    background: #1b1a26;
    color: #fff;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.pg-btn-cari:hover {
    background: var(--pg-dark);
    transform: translateY(-2px);
}

.pg-reset {
    height: 42px;
    display: inline-flex;
    align-items: center;
    padding: 0 14px;
    border-radius: 13px;
    background: #f4f4f7;
    color: #666;
    font-size: 12px;
    text-decoration: none;
    transition: all .25s;
}

.pg-reset:hover { background: #e9e9ef; color: #222; }

/* ===== GROUP ===== */

.pg-group { margin-bottom: 26px; }

.pg-group-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.pg-group-head .bar {
    width: 5px;
    height: 22px;
    border-radius: 4px;
    background: var(--pg);
}

.pg-group.pimpinan .pg-group-head .bar { background: #FB7C3C; }

.pg-group-head h3 {
    margin: 0;
    font-size: 14.5px;
    font-weight: 700;
    color: #1d1d27;
}

.pg-group-head .count {
    padding: 3px 10px;
    border-radius: 999px;
    background: var(--pg-soft);
    color: var(--pg-dark);
    font-size: 11px;
    font-weight: 700;
}

.pg-group.pimpinan .pg-group-head .count {
    background: #FFEDD5;
    color: #C2410C;
}

.pg-group-head .line {
    flex: 1;
    height: 1px;
    background: var(--color-border, #eee);
}

/* ===== LIST ===== */

.pg-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pg-row {
    position: relative;
    display: grid;
    grid-template-columns: minmax(240px, 1.3fr) minmax(200px, 1.2fr) 110px auto;
    align-items: center;
    gap: 18px;
    padding: 14px 18px 14px 22px;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 18px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
    transition: transform .28s var(--ease-smooth, ease), box-shadow .28s, border-color .28s;
    animation: pgIn .4s var(--ease-smooth, ease) both;
}

.pg-row::before {
    content: "";
    position: absolute;
    left: 0;
    top: 16px;
    bottom: 16px;
    width: 4px;
    border-radius: 0 4px 4px 0;
    background: #d9d9e3;
}

.pg-row.t-lead::before  { background: #FB7C3C; }
.pg-row.t-struk::before { background: #8B5CF6; }
.pg-row.t-fung::before  { background: #2F6FED; }
.pg-row.t-pel::before   { background: #cfcfda; }

.pg-row:hover {
    transform: translateX(4px);
    border-color: rgba(139,92,246,.35);
    box-shadow: 0 14px 30px rgba(109,40,217,.1);
}

@keyframes pgIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.pg-person {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.pg-avatar {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 800;
    flex-shrink: 0;
    transition: transform .28s var(--ease-smooth, ease);
}

.pg-row:hover .pg-avatar { transform: scale(1.08) rotate(-4deg); }

.pg-person h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1d1d27;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pg-person span {
    display: block;
    margin-top: 2px;
    font-size: 11.5px;
    color: #9a9aa8;
    letter-spacing: .3px;
}

.pg-cell b {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: #2a2a36;
    line-height: 1.35;
    margin-top: 5px;
}

.pg-tier {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.pg-tier.lead  { background: #FFEDD5; color: #C2410C; }
.pg-tier.struk { background: #EDE9FE; color: #6D28D9; }
.pg-tier.fung  { background: #DBEAFE; color: #1D4ED8; }
.pg-tier.pel   { background: #EEEEF3; color: #6b6b78; }

.pg-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    width: fit-content;
}

.pg-status::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.pg-status.on  { background: #DCFCE7; color: #15803D; }
.pg-status.on::before  { background: #22C55E; box-shadow: 0 0 0 3px rgba(34,197,94,.22); }
.pg-status.off { background: #FEE2E2; color: #B91C1C; }
.pg-status.off::before { background: #EF4444; }

.pg-actions { display: flex; gap: 6px; justify-content: flex-end; }

.pg-act {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    text-decoration: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pg-act.det { background: #DBEAFE; color: #1D4ED8; }
.pg-act.edt { background: #FFEDD5; color: #C2410C; }
.pg-act.del { background: #FEE2E2; color: #B91C1C; }

.pg-act:hover { transform: translateY(-3px); color: #fff; }
.pg-act.det:hover { background: #2F6FED; box-shadow: 0 8px 16px rgba(47,111,237,.3); }
.pg-act.edt:hover { background: #FB7C3C; box-shadow: 0 8px 16px rgba(251,124,60,.3); }
.pg-act.del:hover { background: #EF4444; box-shadow: 0 8px 16px rgba(239,68,68,.3); }

/* ===== EMPTY ===== */

.pg-empty {
    text-align: center;
    padding: 60px 20px;
    background: var(--color-surface, #fff);
    border: 1px dashed #d9d9e3;
    border-radius: 22px;
    color: #9a9aa8;
}

.pg-empty .ic {
    width: 68px;
    height: 68px;
    margin: 0 auto 14px;
    border-radius: 22px;
    background: var(--pg-soft);
    display: flex;
    align-items: center;
    justify-content: center;
}

.pg-empty h4 { margin: 0 0 4px; color: #333; font-size: 15px; }
.pg-empty p { margin: 0; font-size: 12.5px; }

/* ===== RESPONSIVE ===== */

@media (max-width: 1000px) {
    .pg-row {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px 14px;
    }
    .pg-row .pg-person { grid-column: 1 / 2; }
    .pg-row .pg-stat { grid-column: 2 / 3; grid-row: 1; justify-self: end; }
    .pg-row .pg-cell { grid-column: 1 / 2; }
    .pg-row .pg-actions { grid-column: 2 / 3; grid-row: 2; }
}

@media (max-width: 700px) {
    .pg-hero { padding: 24px 20px; }
    .pg-wrap .pg-hero h1 { font-size: 23px; }
    .pg-search { min-width: 100%; justify-content: stretch; }
    .pg-search-box { max-width: none; }
    .pg-tabs { width: 100%; overflow-x: auto; }
}

</style>

<div class="content">
<div class="container-fluid pg-wrap">

    <!-- ===== HERO ===== -->

    <div class="pg-hero">

        <div class="pg-hero-left">
            <div class="pg-eyebrow"><i></i> KEPEGAWAIAN</div>
            <h1>Data Pegawai</h1>
            <p>Kelola seluruh data pegawai BKPSDM Kabupaten Tapin dalam satu tempat.</p>
        </div>

        <div class="pg-hero-right">
            <div class="pg-pill"><strong><?= $total_pegawai; ?></strong><span>Pegawai</span></div>
            <div class="pg-pill"><strong><?= $total_aktif; ?></strong><span>Aktif</span></div>
            <div class="pg-pill"><strong><?= $total_unit; ?></strong><span>Unit Kerja</span></div>

            <?php if ($adalahAdmin) { ?>
            <a href="tambah.php" class="pg-add">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Tambah Pegawai
            </a>
            <?php } ?>
        </div>

    </div>

    <!-- ===== TOOLBAR ===== -->

    <div class="pg-toolbar">

        <div class="pg-tabs">
            <a href="<?= urlFilter('semua', $keyword, $unit); ?>" class="pg-tab <?= $filter === 'semua' ? 'active' : ''; ?>">Semua <em><?= $total_pegawai; ?></em></a>
            <a href="<?= urlFilter('aktif', $keyword, $unit); ?>" class="pg-tab <?= $filter === 'aktif' ? 'active' : ''; ?>">Aktif <em><?= $total_aktif; ?></em></a>
            <a href="<?= urlFilter('nonaktif', $keyword, $unit); ?>" class="pg-tab <?= $filter === 'nonaktif' ? 'active' : ''; ?>">Nonaktif <em><?= $total_non; ?></em></a>
        </div>

        <form method="GET" class="pg-search">

            <?php if ($filter !== 'semua') { ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter); ?>">
            <?php } ?>

            <select name="unit" class="pg-select" onchange="this.form.submit()">
                <option value="">Semua Unit Kerja</option>
                <?php foreach ($daftar_unit as $u) { ?>
                    <option value="<?= htmlspecialchars($u); ?>" <?= $unit === $u ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($u); ?>
                    </option>
                <?php } ?>
            </select>

            <div class="pg-search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari NIP, nama, jabatan...">
            </div>

            <button type="submit" class="pg-btn-cari">Cari</button>

            <?php if ($keyword !== '' || $filter !== 'semua' || $unit !== '') { ?>
                <a href="index.php" class="pg-reset">Reset</a>
            <?php } ?>

        </form>

    </div>

    <!-- ===== DAFTAR ===== -->

    <?php if ($jumlah_tampil === 0) { ?>

        <div class="pg-empty">
            <div class="ic">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 0 0-3-3.87M17 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h4><?= ($keyword !== '' || $unit !== '' || $filter !== 'semua') ? 'Pegawai tidak ditemukan' : 'Belum ada data pegawai'; ?></h4>
            <p>
                <?= ($keyword !== '' || $unit !== '' || $filter !== 'semua')
                    ? 'Coba ubah kata kunci atau filter yang dipakai.'
                    : 'Klik tombol Tambah Pegawai untuk mulai mengisi data.'; ?>
            </p>
        </div>

    <?php } else { ?>

        <?php $delay = 0; foreach ($grup as $nama_unit => $daftar) { ?>

            <div class="pg-group <?= strcasecmp($nama_unit, 'Pimpinan') === 0 ? 'pimpinan' : ''; ?>">

                <div class="pg-group-head">
                    <span class="bar"></span>
                    <h3><?= htmlspecialchars($nama_unit); ?></h3>
                    <span class="count"><?= count($daftar); ?> pegawai</span>
                    <span class="line"></span>
                </div>

                <div class="pg-list">

                    <?php foreach ($daftar as $p) {
                        $aktif = strtolower($p['status']) === 'aktif';
                        [$bg, $fg] = warnaAvatar($p['nama']);
                        [$label_tier, $kelas_tier] = labelTingkat($p['_tingkat']);
                        $delay += 0.04;
                    ?>

                        <div class="pg-row t-<?= $kelas_tier; ?>" style="animation-delay: <?= min($delay, 0.5); ?>s">

                            <div class="pg-person">
                                <div class="pg-avatar" style="background:<?= $bg; ?>;color:<?= $fg; ?>">
                                    <?= htmlspecialchars(inisial($p['nama'])); ?>
                                </div>
                                <div style="min-width:0">
                                    <h4 title="<?= htmlspecialchars($p['nama']); ?>"><?= htmlspecialchars($p['nama']); ?></h4>
                                    <span>NIP <?= htmlspecialchars($p['nip']); ?></span>
                                </div>
                            </div>

                            <div class="pg-cell">
                                <span class="pg-tier <?= $kelas_tier; ?>"><?= $label_tier; ?></span>
                                <b><?= htmlspecialchars($p['jabatan']); ?></b>
                            </div>

                            <div class="pg-stat">
                                <span class="pg-status <?= $aktif ? 'on' : 'off'; ?>">
                                    <?= $aktif ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </div>

                            <div class="pg-actions">

                                <a href="detail.php?id=<?= (int) $p['id']; ?>" class="pg-act det" title="Detail">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>

                                <?php if ($adalahAdmin) { ?>

                                <a href="edit.php?id=<?= (int) $p['id']; ?>" class="pg-act edt" title="Edit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                </a>

                                <a href="hapus.php?id=<?= (int) $p['id']; ?>" class="pg-act del" title="Hapus" onclick="return confirm('Yakin ingin menghapus data pegawai ini?')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                </a>

                                <?php } ?>

                            </div>

                        </div>

                    <?php } ?>

                </div>

            </div>

        <?php } ?>

    <?php } ?>

</div>
</div>

<?php
include "../layouts/footer.php";
?>