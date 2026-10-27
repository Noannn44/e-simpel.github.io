<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

hanyaAdmin();

include "../config/database.php";

$saya = $_SESSION['username'];

/* =========================
   FILTER & PENCARIAN
========================= */

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$filter  = isset($_GET['role']) ? trim($_GET['role']) : 'semua';

if (!in_array($filter, ['semua', 'administrator', 'operator'], true)) {
    $filter = 'semua';
}

/* =========================
   STATISTIK
========================= */

$stat = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(role) IN ('administrator','admin') THEN 1 ELSE 0 END) AS admin,
        SUM(CASE WHEN LOWER(role) = 'operator' THEN 1 ELSE 0 END) AS operator
    FROM users
"));

$total_user     = (int) ($stat['total'] ?? 0);
$total_admin    = (int) ($stat['admin'] ?? 0);
$total_operator = (int) ($stat['operator'] ?? 0);

/* =========================
   QUERY DATA
========================= */

$sql    = "SELECT id, nama, username, role FROM users WHERE 1=1";
$types  = "";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (nama LIKE ? OR username LIKE ? OR role LIKE ?)";
    $like = "%" . $keyword . "%";
    $types .= "sss";
    array_push($params, $like, $like, $like);
}

if ($filter === 'administrator') {
    $sql .= " AND LOWER(role) IN ('administrator','admin')";
} elseif ($filter === 'operator') {
    $sql .= " AND LOWER(role) = 'operator'";
}

$sql .= " ORDER BY nama ASC";

$stmt = mysqli_prepare($koneksi, $sql);

if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

/* =========================
   KELOMPOKKAN PER ROLE
========================= */

function namaRole($role)
{
    $r = strtolower(trim($role));

    if ($r === 'administrator' || $r === 'admin') {
        return 'Administrator';
    }

    if ($r === 'operator') {
        return 'Operator';
    }

    return ucfirst($role);
}

$grup = [];
$jumlah_tampil = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $grup[namaRole($row['role'])][] = $row;
    $jumlah_tampil++;
}

// Administrator paling atas, lalu Operator, lalu role lain
uksort($grup, function ($a, $b) {
    $urut = ['Administrator' => 1, 'Operator' => 2];
    $x = $urut[$a] ?? 3;
    $y = $urut[$b] ?? 3;
    return $x === $y ? strcasecmp($a, $b) : $x <=> $y;
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
        ['#CCFBF1', '#0F766E'],
        ['#DBEAFE', '#1D4ED8'],
        ['#FFEDD5', '#C2410C'],
        ['#EDE9FE', '#6D28D9'],
        ['#FCE7F3', '#BE185D'],
        ['#DCFCE7', '#15803D'],
    ];

    return $palet[crc32($nama) % count($palet)];
}

function urlFilter($role, $keyword)
{
    $q = [];
    if ($role !== 'semua') {
        $q['role'] = $role;
    }
    if ($keyword !== '') {
        $q['keyword'] = $keyword;
    }
    return 'index.php' . (count($q) ? '?' . http_build_query($q) : '');
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.us-wrap {
    --us: #14B8A6;
    --us-dark: #0F766E;
    --us-soft: #CCFBF1;
}

/* ===== HERO ===== */

.us-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #14181f 0%, #17232b 60%, #123a3d 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.us-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -80px;
    top: -140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(20,184,166,.42), transparent 70%);
    pointer-events: none;
}

.us-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.045) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.us-hero-left { position: relative; z-index: 1; }

.us-eyebrow {
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

.us-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--us);
    box-shadow: 0 0 0 4px rgba(20,184,166,.3);
}

.us-wrap .us-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.us-wrap .us-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.65) !important;
}

.us-hero-right {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.us-pill {
    min-width: 92px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.us-pill:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,.14);
}

.us-pill strong {
    display: block;
    font-size: 21px;
    font-weight: 700;
    line-height: 1.1;
    color: #fff;
}

.us-pill span {
    font-size: 10.5px;
    color: rgba(255,255,255,.6);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.us-add {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 46px;
    padding: 0 20px;
    border-radius: 14px;
    background: var(--us);
    color: #04201d;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 10px 24px rgba(20,184,166,.35);
    transition: transform .25s var(--ease-smooth, ease), box-shadow .25s, background .25s;
}

.us-add:hover {
    background: #2DD4BF;
    color: #04201d;
    transform: translateY(-3px);
}

/* ===== TOOLBAR ===== */

.us-toolbar {
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

.us-tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    background: #f4f4f7;
    border-radius: 13px;
}

.us-tab {
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

.us-tab em {
    font-style: normal;
    font-size: 10.5px;
    padding: 1px 7px;
    border-radius: 999px;
    background: rgba(0,0,0,.06);
}

.us-tab:hover { color: #222; }

.us-tab.active {
    background: #fff;
    color: var(--us-dark);
    box-shadow: 0 3px 10px rgba(0,0,0,.08);
}

.us-tab.active em {
    background: var(--us-soft);
    color: var(--us-dark);
}

.us-search {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    justify-content: flex-end;
    min-width: 260px;
}

.us-search-box {
    position: relative;
    flex: 1;
    max-width: 380px;
}

.us-search-box svg {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
}

.us-search-box input {
    width: 100%;
    height: 42px;
    padding: 0 14px 0 38px;
    border: 1px solid transparent;
    background: #f4f4f7;
    border-radius: 13px;
    font-size: 12.5px;
    outline: none;
    transition: all .25s var(--ease-smooth, ease);
}

.us-search-box input:focus {
    background: #fff;
    border-color: var(--us);
    box-shadow: 0 0 0 4px rgba(20,184,166,.14);
}

.us-btn-cari {
    height: 42px;
    padding: 0 18px;
    border: none;
    border-radius: 13px;
    background: #14181f;
    color: #fff;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.us-btn-cari:hover {
    background: var(--us-dark);
    transform: translateY(-2px);
}

.us-reset {
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

.us-reset:hover { background: #e9e9ef; color: #222; }

/* ===== GROUP ===== */

.us-group { margin-bottom: 26px; }

.us-group-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.us-group-head .bar {
    width: 5px;
    height: 22px;
    border-radius: 4px;
    background: var(--us);
}

.us-group.admin .us-group-head .bar { background: #FB7C3C; }

.us-group-head h3 {
    margin: 0;
    font-size: 14.5px;
    font-weight: 700;
    color: #1d1d27;
}

.us-group-head .count {
    padding: 3px 10px;
    border-radius: 999px;
    background: var(--us-soft);
    color: var(--us-dark);
    font-size: 11px;
    font-weight: 700;
}

.us-group.admin .us-group-head .count {
    background: #FFEDD5;
    color: #C2410C;
}

.us-group-head .line {
    flex: 1;
    height: 1px;
    background: var(--color-border, #eee);
}

/* ===== LIST ===== */

.us-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.us-row {
    position: relative;
    display: grid;
    grid-template-columns: minmax(260px, 1.6fr) 150px 1fr auto;
    align-items: center;
    gap: 18px;
    padding: 14px 18px 14px 22px;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 18px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
    transition: transform .28s var(--ease-smooth, ease), box-shadow .28s, border-color .28s;
    animation: usIn .4s var(--ease-smooth, ease) both;
}

.us-row::before {
    content: "";
    position: absolute;
    left: 0;
    top: 16px;
    bottom: 16px;
    width: 4px;
    border-radius: 0 4px 4px 0;
    background: var(--us);
}

.us-row.admin::before { background: #FB7C3C; }

.us-row:hover {
    transform: translateX(4px);
    border-color: rgba(20,184,166,.4);
    box-shadow: 0 14px 30px rgba(15,118,110,.1);
}

@keyframes usIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.us-person {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.us-avatar {
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

.us-row:hover .us-avatar { transform: scale(1.08) rotate(-4deg); }

.us-person h4 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #1d1d27;
}

.us-person h4 span.nm {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.us-me {
    flex-shrink: 0;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--us-soft);
    color: var(--us-dark);
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.us-person small {
    display: block;
    margin-top: 2px;
    font-size: 11.5px;
    color: #9a9aa8;
}

.us-role {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    width: fit-content;
}

.us-role.admin    { background: #FFEDD5; color: #C2410C; }
.us-role.operator { background: #CCFBF1; color: #0F766E; }
.us-role.lain     { background: #EEEEF3; color: #6b6b78; }

.us-role svg { flex-shrink: 0; }

.us-akses {
    font-size: 12px;
    color: #8a8a98;
    line-height: 1.5;
}

.us-actions { display: flex; gap: 6px; justify-content: flex-end; }

.us-act {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    text-decoration: none;
    transition: all .25s var(--ease-smooth, ease);
}

.us-act.det { background: #DBEAFE; color: #1D4ED8; }
.us-act.edt { background: #FFEDD5; color: #C2410C; }
.us-act.del { background: #FEE2E2; color: #B91C1C; }
.us-act.lock { background: #F1F1F6; color: #b3b3c0; cursor: not-allowed; }

.us-act:not(.lock):hover { transform: translateY(-3px); color: #fff; }
.us-act.det:hover { background: #2F6FED; box-shadow: 0 8px 16px rgba(47,111,237,.3); }
.us-act.edt:hover { background: #FB7C3C; box-shadow: 0 8px 16px rgba(251,124,60,.3); }
.us-act.del:hover { background: #EF4444; box-shadow: 0 8px 16px rgba(239,68,68,.3); }

/* ===== EMPTY ===== */

.us-empty {
    text-align: center;
    padding: 60px 20px;
    background: var(--color-surface, #fff);
    border: 1px dashed #d9d9e3;
    border-radius: 22px;
    color: #9a9aa8;
}

.us-empty .ic {
    width: 68px;
    height: 68px;
    margin: 0 auto 14px;
    border-radius: 22px;
    background: var(--us-soft);
    display: flex;
    align-items: center;
    justify-content: center;
}

.us-empty h4 { margin: 0 0 4px; color: #333; font-size: 15px; }
.us-empty p { margin: 0; font-size: 12.5px; }

/* ===== RESPONSIVE ===== */

@media (max-width: 1000px) {
    .us-row {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px 14px;
    }
    .us-row .us-person { grid-column: 1 / 2; }
    .us-row .us-rolecell { grid-column: 2 / 3; grid-row: 1; justify-self: end; }
    .us-row .us-akses { grid-column: 1 / 2; }
    .us-row .us-actions { grid-column: 2 / 3; grid-row: 2; }
}

@media (max-width: 700px) {
    .us-hero { padding: 24px 20px; }
    .us-wrap .us-hero h1 { font-size: 23px; }
    .us-search { min-width: 100%; justify-content: stretch; }
    .us-search-box { max-width: none; }
    .us-tabs { width: 100%; overflow-x: auto; }
}

</style>

<div class="content">
<div class="container-fluid us-wrap">

    <!-- ===== HERO ===== -->

    <div class="us-hero">

        <div class="us-hero-left">
            <div class="us-eyebrow"><i></i> PENGATURAN</div>
            <h1>Data User</h1>
            <p>Kelola pengguna yang memiliki akses ke e-SIMPEL.</p>
        </div>

        <div class="us-hero-right">
            <div class="us-pill"><strong><?= $total_user; ?></strong><span>User</span></div>
            <div class="us-pill"><strong><?= $total_admin; ?></strong><span>Administrator</span></div>
            <div class="us-pill"><strong><?= $total_operator; ?></strong><span>Operator</span></div>

            <a href="tambah.php" class="us-add">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#04201d" stroke-width="2.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Tambah User
            </a>
        </div>

    </div>

    <!-- ===== TOOLBAR ===== -->

    <div class="us-toolbar">

        <div class="us-tabs">
            <a href="<?= urlFilter('semua', $keyword); ?>" class="us-tab <?= $filter === 'semua' ? 'active' : ''; ?>">Semua <em><?= $total_user; ?></em></a>
            <a href="<?= urlFilter('administrator', $keyword); ?>" class="us-tab <?= $filter === 'administrator' ? 'active' : ''; ?>">Administrator <em><?= $total_admin; ?></em></a>
            <a href="<?= urlFilter('operator', $keyword); ?>" class="us-tab <?= $filter === 'operator' ? 'active' : ''; ?>">Operator <em><?= $total_operator; ?></em></a>
        </div>

        <form method="GET" class="us-search">

            <?php if ($filter !== 'semua') { ?>
                <input type="hidden" name="role" value="<?= htmlspecialchars($filter); ?>">
            <?php } ?>

            <div class="us-search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari nama atau username...">
            </div>

            <button type="submit" class="us-btn-cari">Cari</button>

            <?php if ($keyword !== '' || $filter !== 'semua') { ?>
                <a href="index.php" class="us-reset">Reset</a>
            <?php } ?>

        </form>

    </div>

    <!-- ===== DAFTAR ===== -->

    <?php if ($jumlah_tampil === 0) { ?>

        <div class="us-empty">
            <div class="ic">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <h4><?= ($keyword !== '' || $filter !== 'semua') ? 'User tidak ditemukan' : 'Belum ada data user'; ?></h4>
            <p>
                <?= ($keyword !== '' || $filter !== 'semua')
                    ? 'Coba ubah kata kunci atau filter yang dipakai.'
                    : 'Klik tombol Tambah User untuk membuat akun pertama.'; ?>
            </p>
        </div>

    <?php } else { ?>

        <?php $delay = 0; foreach ($grup as $nama_role => $daftar) {
            $is_admin = ($nama_role === 'Administrator');
            $kelas_role = $is_admin ? 'admin' : (($nama_role === 'Operator') ? 'operator' : 'lain');
        ?>

            <div class="us-group <?= $is_admin ? 'admin' : ''; ?>">

                <div class="us-group-head">
                    <span class="bar"></span>
                    <h3><?= htmlspecialchars($nama_role); ?></h3>
                    <span class="count"><?= count($daftar); ?> user</span>
                    <span class="line"></span>
                </div>

                <div class="us-list">

                    <?php foreach ($daftar as $u) {
                        [$bg, $fg] = warnaAvatar($u['nama']);
                        $akun_saya = ($u['username'] === $saya);
                        $delay += 0.04;
                    ?>

                        <div class="us-row <?= $is_admin ? 'admin' : ''; ?>" style="animation-delay: <?= min($delay, 0.5); ?>s">

                            <div class="us-person">
                                <div class="us-avatar" style="background:<?= $bg; ?>;color:<?= $fg; ?>">
                                    <?= htmlspecialchars(inisial($u['nama'])); ?>
                                </div>
                                <div style="min-width:0">
                                    <h4>
                                        <span class="nm" title="<?= htmlspecialchars($u['nama']); ?>"><?= htmlspecialchars($u['nama']); ?></span>
                                        <?php if ($akun_saya) { ?><span class="us-me">Anda</span><?php } ?>
                                    </h4>
                                    <small>@<?= htmlspecialchars($u['username']); ?></small>
                                </div>
                            </div>

                            <div class="us-rolecell">
                                <span class="us-role <?= $kelas_role; ?>">
                                    <?php if ($is_admin) { ?>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg>
                                    <?php } else { ?>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                                    <?php } ?>
                                    <?= htmlspecialchars(namaRole($u['role'])); ?>
                                </span>
                            </div>

                            <div class="us-akses">
                                <?= $is_admin
                                    ? 'Akses penuh: surat, buku tamu, pegawai, user'
                                    : 'Akses: surat masuk, surat keluar, buku tamu'; ?>
                            </div>

                            <div class="us-actions">

                                <a href="detail.php?id=<?= (int) $u['id']; ?>" class="us-act det" title="Detail">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>

                                <a href="edit.php?id=<?= (int) $u['id']; ?>" class="us-act edt" title="Edit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                </a>

                                <?php if ($akun_saya) { ?>

                                    <span class="us-act lock" title="Akun yang sedang Anda gunakan tidak dapat dihapus">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                    </span>

                                <?php } else { ?>

                                    <form action="hapus.php" method="POST" style="margin:0;display:flex" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                        <input type="hidden" name="id" value="<?= (int) $u['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
                                        <button type="submit" class="us-act del" title="Hapus" style="border:none;cursor:pointer;padding:0">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                        </button>
                                    </form>

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