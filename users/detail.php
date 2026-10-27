<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

hanyaAdmin();

include "../config/database.php";

/* =========================
   AMBIL DATA USER
========================= */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>alert('ID user tidak valid.');window.location='index.php';</script>";
    exit;
}

$stmt = mysqli_prepare($koneksi, "SELECT id, nama, username, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$u) {
    echo "<script>alert('Data user tidak ditemukan.');window.location='index.php';</script>";
    exit;
}

$is_admin  = in_array(strtolower($u['role']), ['administrator', 'admin'], true);
$nama_role = $is_admin ? 'Administrator' : 'Operator';
$akun_saya = ($u['username'] === $_SESSION['username']);

/* jumlah user dengan role yang sama */

$stmt2 = mysqli_prepare($koneksi, "
    SELECT COUNT(*) AS jml FROM users
    WHERE " . ($is_admin
        ? "LOWER(role) IN ('administrator','admin')"
        : "LOWER(role) = 'operator'")
);
mysqli_stmt_execute($stmt2);
$sama_role = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2))['jml'] ?? 0);

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

/* daftar modul & apakah boleh diakses */

$modul = [
    ['Dashboard',      true,      'Ringkasan data dan statistik sistem'],
    ['Surat Masuk',    true,      'Catat, lihat, dan kelola surat masuk'],
    ['Surat Keluar',   true,      'Catat, lihat, dan kelola surat keluar'],
    ['Buku Tamu',      true,      'Catat dan kelola kedatangan tamu'],
    ['Laporan',        true,      'Lihat dan cetak laporan'],
    ['Data Pegawai',   $is_admin, 'Tambah, ubah, dan hapus data pegawai'],
    ['Data User',      $is_admin, 'Kelola akun dan hak akses pengguna'],
];

$jml_akses = 0;
foreach ($modul as $m) {
    if ($m[1]) {
        $jml_akses++;
    }
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.pd-wrap {
    --us: #14B8A6;
    --us-dark: #0F766E;
    --us-soft: #CCFBF1;
}

.pd-shell {
    max-width: 1120px;
    margin: 0 auto;
}

/* ===== BREADCRUMB ===== */

.pd-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.pd-crumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: #8a8a98;
}

.pd-crumb a {
    color: #8a8a98;
    text-decoration: none;
    transition: color .2s;
}

.pd-crumb a:hover { color: var(--us-dark); }
.pd-crumb b { color: #1d1d27; font-weight: 600; }

.pd-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    padding: 0 18px;
    border-radius: 13px;
    background: #fff;
    border: 1px solid var(--color-border, #eee);
    color: #444;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pd-back:hover {
    transform: translateX(-3px);
    border-color: var(--us);
    color: var(--us-dark);
}

/* ===== HERO ===== */

.pd-hero {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 30px 34px;
    background: linear-gradient(135deg, #14181f 0%, #17232b 60%, #123a3d 100%);
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.pd-hero::before {
    content: "";
    position: absolute;
    width: 340px;
    height: 340px;
    right: -90px;
    top: -150px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(20,184,166,.42), transparent 70%);
    pointer-events: none;
}

.pd-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.045) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.pd-hero > * { position: relative; z-index: 1; }

.pd-ring {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    padding: 4px;
    background: conic-gradient(from 200deg, #14B8A6, #5EEAD4, #14B8A6);
    flex-shrink: 0;
}

.pd-ring div {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #1b2a32;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    font-weight: 800;
    color: #5EEAD4;
}

.pd-id { flex: 1; min-width: 220px; }

.pd-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}

.pd-tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
    background: rgba(255,255,255,.1);
    color: #fff;
}

.pd-tag.admin    { background: #FB7C3C; }
.pd-tag.operator { background: var(--us); color: #04201d; }

.pd-tag i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #fff;
    opacity: .85;
}

.pd-wrap .pd-hero h1 {
    margin: 0;
    font-size: 27px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
    word-break: break-word;
}

.pd-wrap .pd-hero p {
    margin: 5px 0 0;
    font-size: 13.5px;
    color: rgba(255,255,255,.65) !important;
}

.pd-hero-stats {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.pd-stat {
    min-width: 96px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.pd-stat:hover {
    transform: translateY(-3px);
    background: rgba(255,255,255,.14);
}

.pd-stat strong {
    display: block;
    font-size: 21px;
    font-weight: 700;
    line-height: 1.1;
    color: #fff;
}

.pd-stat span {
    font-size: 10.5px;
    color: rgba(255,255,255,.6);
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* ===== LAYOUT ===== */

.pd-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 22px;
    align-items: start;
}

.pd-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 22px;
    padding: 26px 28px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
    margin-bottom: 20px;
    animation: pdIn .45s var(--ease-smooth, ease) both;
}

.pd-card:nth-child(2) { animation-delay: .08s; }

@keyframes pdIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.pd-sec {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: .9px;
    text-transform: uppercase;
    color: var(--us-dark);
}

.pd-sec::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--color-border, #eee);
}

/* ===== INFO ===== */

.pd-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.pd-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-radius: 16px;
    background: #fafafd;
    border: 1px solid #f0f0f6;
    transition: all .25s var(--ease-smooth, ease);
}

.pd-item:hover {
    transform: translateY(-3px);
    background: #fff;
    border-color: rgba(20,184,166,.35);
    box-shadow: 0 10px 22px rgba(15,118,110,.08);
}

.pd-item.full { grid-column: 1 / -1; }

.pd-ic {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: var(--us-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pd-item small {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #9a9aa8;
}

.pd-item strong {
    display: block;
    margin-top: 2px;
    font-size: 14.5px;
    font-weight: 700;
    color: #1d1d27;
    word-break: break-word;
}

.pd-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 3px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
}

.pd-badge.admin    { background: #FFEDD5; color: #C2410C; }
.pd-badge.operator { background: var(--us-soft); color: var(--us-dark); }

/* ===== HAK AKSES ===== */

.pd-akses {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.pd-mod {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid #f0f0f6;
    background: #fafafd;
    transition: all .25s var(--ease-smooth, ease);
}

.pd-mod:hover { transform: translateY(-2px); background: #fff; }

.pd-mod .ck {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pd-mod.ya .ck { background: var(--us-soft); }
.pd-mod.tdk .ck { background: #F1F1F6; }

.pd-mod strong {
    display: block;
    font-size: 12.8px;
    color: #1d1d27;
}

.pd-mod small {
    display: block;
    margin-top: 1px;
    font-size: 11px;
    line-height: 1.4;
    color: #9a9aa8;
}

.pd-mod.tdk strong { color: #a3a3b1; text-decoration: line-through; text-decoration-color: #d4d4de; }

/* ===== SIDEBAR ===== */

.pd-side {
    position: sticky;
    top: 90px;
}

.pd-sum {
    background: linear-gradient(160deg, #14181f 0%, #17232b 60%, #123a3d 100%);
    color: #fff;
    border-radius: 22px;
    padding: 24px;
    margin-bottom: 16px;
    position: relative;
    overflow: hidden;
}

.pd-sum::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.pd-sum > * { position: relative; z-index: 1; }

.pd-sum h4 {
    margin: 0 0 14px;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: .9px;
    text-transform: uppercase;
    color: #5EEAD4;
}

.pd-sum .row2 {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,.09);
    font-size: 12.3px;
}

.pd-sum .row2:last-child { border-bottom: none; }
.pd-sum .row2 span { color: rgba(255,255,255,.55); }
.pd-sum .row2 b { color: #fff; font-weight: 600; text-align: right; word-break: break-all; }

.pd-me {
    display: inline-block;
    margin-top: 14px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(20,184,166,.2);
    color: #5EEAD4;
    font-size: 11px;
    font-weight: 700;
}

.pd-actions {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 22px;
    padding: 12px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.pd-act {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 12px 14px;
    border: none;
    border-radius: 14px;
    background: transparent;
    text-decoration: none;
    text-align: left;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #2a2a36;
    transition: all .25s var(--ease-smooth, ease);
}

.pd-act + .pd-act { margin-top: 4px; }

.pd-act .ai {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all .25s;
}

.pd-act small {
    display: block;
    font-size: 11px;
    font-weight: 400;
    color: #9a9aa8;
    margin-top: 1px;
}

.pd-act:hover { transform: translateX(4px); }

.pd-act.edit .ai { background: #FFEDD5; }
.pd-act.edit:hover { background: #FFF7F0; color: #C2410C; }

.pd-act.list .ai { background: #DBEAFE; }
.pd-act.list:hover { background: #F2F7FF; color: #1D4ED8; }

.pd-act.del .ai { background: #FEE2E2; }
.pd-act.del:hover { background: #FFF5F5; color: #B91C1C; }

.pd-act.lock {
    cursor: not-allowed;
    opacity: .6;
}

.pd-act.lock:hover { transform: none; background: transparent; }
.pd-act.lock .ai { background: #F1F1F6; }

/* ===== RESPONSIVE ===== */

@media (max-width: 960px) {
    .pd-grid { grid-template-columns: 1fr; }
    .pd-side { position: static; }
}

@media (max-width: 640px) {
    .pd-hero { padding: 24px 20px; }
    .pd-wrap .pd-hero h1 { font-size: 22px; }
    .pd-info, .pd-akses { grid-template-columns: 1fr; }
    .pd-card { padding: 22px 20px; }
}

</style>

<div class="content">
<div class="container-fluid pd-wrap">
<div class="pd-shell">

    <div class="pd-top">

        <div class="pd-crumb">
            <a href="index.php">Data User</a>
            <span>/</span>
            <b>Detail</b>
        </div>

        <a href="index.php" class="pd-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>

    </div>

    <!-- ===== HERO ===== -->

    <div class="pd-hero">

        <div class="pd-ring"><div><?= htmlspecialchars(inisial($u['nama'])); ?></div></div>

        <div class="pd-id">

            <div class="pd-tags">
                <span class="pd-tag <?= $is_admin ? 'admin' : 'operator'; ?>"><i></i> <?= $nama_role; ?></span>
                <?php if ($akun_saya) { ?>
                    <span class="pd-tag"><i></i> Akun Anda</span>
                <?php } ?>
            </div>

            <h1><?= htmlspecialchars($u['nama']); ?></h1>
            <p>@<?= htmlspecialchars($u['username']); ?></p>

        </div>

        <div class="pd-hero-stats">
            <div class="pd-stat"><strong><?= $jml_akses; ?></strong><span>Modul</span></div>
            <div class="pd-stat"><strong>#<?= (int) $u['id']; ?></strong><span>ID User</span></div>
        </div>

    </div>

    <!-- ===== ISI ===== -->

    <div class="pd-grid">

        <div>

            <!-- INFORMASI AKUN -->

            <div class="pd-card">

                <div class="pd-sec">Informasi Akun</div>

                <div class="pd-info">

                    <div class="pd-item full">
                        <div class="pd-ic">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div>
                            <small>Nama Lengkap</small>
                            <strong><?= htmlspecialchars($u['nama']); ?></strong>
                        </div>
                    </div>

                    <div class="pd-item">
                        <div class="pd-ic">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                        </div>
                        <div>
                            <small>Username</small>
                            <strong><?= htmlspecialchars($u['username']); ?></strong>
                        </div>
                    </div>

                    <div class="pd-item">
                        <div class="pd-ic">
                            <?php if ($is_admin) { ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg>
                            <?php } else { ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                            <?php } ?>
                        </div>
                        <div>
                            <small>Role</small>
                            <span class="pd-badge <?= $is_admin ? 'admin' : 'operator'; ?>"><?= $nama_role; ?></span>
                        </div>
                    </div>

                    <div class="pd-item full">
                        <div class="pd-ic">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </div>
                        <div>
                            <small>Password</small>
                            <strong>••••••••&nbsp;<span style="font-weight:500;color:#9a9aa8;font-size:12px">tersimpan terenkripsi</span></strong>
                        </div>
                    </div>

                </div>

            </div>

            <!-- HAK AKSES -->

            <div class="pd-card">

                <div class="pd-sec">Hak Akses Modul</div>

                <div class="pd-akses">

                    <?php foreach ($modul as $m) { ?>

                        <div class="pd-mod <?= $m[1] ? 'ya' : 'tdk'; ?>">

                            <span class="ck">
                                <?php if ($m[1]) { ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                <?php } else { ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="3" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                <?php } ?>
                            </span>

                            <span>
                                <strong><?= htmlspecialchars($m[0]); ?></strong>
                                <small><?= htmlspecialchars($m[2]); ?></small>
                            </span>

                        </div>

                    <?php } ?>

                </div>

            </div>

        </div>

        <!-- ===== SIDEBAR KANAN ===== -->

        <aside class="pd-side">

            <div class="pd-sum">

                <h4>Ringkasan</h4>

                <div class="row2"><span>ID User</span><b>#<?= (int) $u['id']; ?></b></div>
                <div class="row2"><span>Username</span><b><?= htmlspecialchars($u['username']); ?></b></div>
                <div class="row2"><span>Role</span><b><?= $nama_role; ?></b></div>
                <div class="row2"><span>Modul diakses</span><b><?= $jml_akses; ?> dari <?= count($modul); ?></b></div>
                <div class="row2"><span>User se-role</span><b><?= $sama_role; ?> orang</b></div>

                <?php if ($akun_saya) { ?>
                    <span class="pd-me">Ini akun yang sedang Anda pakai</span>
                <?php } ?>

            </div>

            <div class="pd-actions">

                <a href="edit.php?id=<?= (int) $u['id']; ?>" class="pd-act edit">
                    <span class="ai">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#C2410C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    </span>
                    <span>Edit User<small>Ubah data atau password</small></span>
                </a>

                <a href="index.php" class="pd-act list">
                    <span class="ai">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    </span>
                    <span>Daftar User<small>Kembali ke semua user</small></span>
                </a>

                <?php if ($akun_saya) { ?>

                    <div class="pd-act del lock" title="Akun yang sedang Anda gunakan tidak dapat dihapus">
                        <span class="ai">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </span>
                        <span>Hapus User<small>Tidak bisa menghapus akun sendiri</small></span>
                    </div>

                <?php } else { ?>

                    <form action="hapus.php" method="POST" style="margin:4px 0 0" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                        <input type="hidden" name="id" value="<?= (int) $u['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
                        <button type="submit" class="pd-act del">
                        <span class="ai">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                        </span>
                        <span>Hapus User<small>Hapus akun secara permanen</small></span>
                        </button>
                    </form>

                <?php } ?>

            </div>

        </aside>

    </div>

</div>
</div>
</div>

<?php
include "../layouts/footer.php";
?>