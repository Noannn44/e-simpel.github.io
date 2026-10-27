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
   CEK ID & AMBIL DATA
========================= */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($koneksi, "SELECT * FROM pegawai WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    echo "<script>
            alert('Data pegawai tidak ditemukan.');
            window.location='index.php';
          </script>";
    exit;
}

/* =========================
   HELPER
========================= */

function tingkatJabatan($jabatan)
{
    $j = strtolower(trim($jabatan));

    $aturan = [
        [1, '/kepala\s+(badan|dinas|bkpsdm)|^kaban|^kadis/'],
        [2, '/sekretaris/'],
        [3, '/kepala\s+bidang|^kabid/'],
        [4, '/kepala\s+(sub|seksi|unit|upt)|kasubag|kasubbid|kasi\b|subbagian|subbidang/'],
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

$aktif = strtolower($data['status']) === 'aktif';
$tingkat = tingkatJabatan($data['jabatan']);
[$label_tier, $kelas_tier] = labelTingkat($tingkat);
[$av_bg, $av_fg] = warnaAvatar($data['nama']);
$pimpinan = ($tingkat === 1);

/* =========================
   REKAN SATU UNIT KERJA
========================= */

$rekan = [];

$sr = mysqli_prepare($koneksi, "SELECT id, nama, jabatan, status FROM pegawai WHERE unit_kerja = ? AND id <> ?");
mysqli_stmt_bind_param($sr, "si", $data['unit_kerja'], $id);
mysqli_stmt_execute($sr);
$hr = mysqli_stmt_get_result($sr);

while ($r = mysqli_fetch_assoc($hr)) {
    $r['_tingkat'] = tingkatJabatan($r['jabatan']);
    $rekan[] = $r;
}

usort($rekan, function ($a, $b) {
    if ($a['_tingkat'] !== $b['_tingkat']) {
        return $a['_tingkat'] <=> $b['_tingkat'];
    }
    return strcasecmp($a['nama'], $b['nama']);
});

$jumlah_rekan = count($rekan);
$rekan_tampil = array_slice($rekan, 0, 6);

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.pd-wrap {
    --pg: #8B5CF6;
    --pg-dark: #6D28D9;
    --pg-soft: #EDE9FE;
}

.pd-shell {
    max-width: 1120px;
    margin: 0 auto;
}

/* ===== HEADER HALAMAN ===== */

.pd-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}

.pd-head h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #1d1d27;
}

.pd-head p {
    margin: 5px 0 0;
    font-size: 13px;
    color: #8a8a98;
}

.pd-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 20px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid var(--color-border, #e8e8ef);
    color: #555;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(0,0,0,.04);
    transition: all .25s var(--ease-smooth, ease);
    white-space: nowrap;
}

.pd-back:hover {
    color: var(--pg-dark);
    border-color: rgba(139,92,246,.4);
    transform: translateY(-2px);
}

/* ===== LAYOUT ===== */

.pd-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 18px;
    align-items: start;
}

.pd-main { display: flex; flex-direction: column; gap: 18px; min-width: 0; }

/* ===== HERO ===== */

.pd-hero {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 30px 32px;
    background: linear-gradient(135deg, #1b1a26 0%, #272238 60%, #33284f 100%);
    display: flex;
    align-items: center;
    gap: 24px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.06));
}

.pd-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -90px;
    top: -150px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139,92,246,.45), transparent 70%);
    pointer-events: none;
}

.pd-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.pd-avatar {
    position: relative;
    z-index: 1;
    width: 96px;
    height: 96px;
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 0 0 7px rgba(255,255,255,.08), 0 18px 34px rgba(0,0,0,.3);
    transition: transform .35s var(--ease-smooth, ease);
}

.pd-hero:hover .pd-avatar { transform: rotate(-4deg) scale(1.04); }

.pd-hero-info { position: relative; z-index: 1; min-width: 0; }

.pd-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,.1);
    color: #fff;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .5px;
    margin-bottom: 10px;
}

.pd-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--pg);
    box-shadow: 0 0 0 4px rgba(139,92,246,.3);
}

.pd-wrap .pd-hero h1 {
    margin: 0;
    font-size: 26px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #fff !important;
    word-break: break-word;
}

.pd-nip {
    margin-top: 6px;
    font-size: 13.5px;
    color: rgba(255,255,255,.62);
    letter-spacing: .4px;
}

.pd-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}

.pd-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 13px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
}

.pd-chip.unit { background: rgba(255,255,255,.1); color: #fff; }

.pd-chip.on  { background: rgba(34,197,94,.18); color: #4ADE80; }
.pd-chip.off { background: rgba(239,68,68,.2); color: #FCA5A5; }

.pd-chip.on::before,
.pd-chip.off::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

.pd-chip.lead  { background: #FFEDD5; color: #C2410C; }
.pd-chip.struk { background: #EDE9FE; color: #6D28D9; }
.pd-chip.fung  { background: #DBEAFE; color: #1D4ED8; }
.pd-chip.pel   { background: #EEEEF3; color: #6b6b78; }

/* ===== KARTU ===== */

.pd-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 22px;
    padding: 26px 28px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.pd-sec {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 0 18px;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: var(--pg-dark);
}

.pd-sec::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--color-border, #e8e8ef);
}

.pd-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.pd-item {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 16px;
    border-radius: 16px;
    background: #f8f8fb;
    border: 1px solid transparent;
    transition: all .28s var(--ease-smooth, ease);
}

.pd-item:hover {
    background: #fff;
    border-color: rgba(139,92,246,.3);
    transform: translateY(-3px);
    box-shadow: 0 14px 28px rgba(109,40,217,.08);
}

.pd-item.full { grid-column: 1 / -1; }

.pd-ic {
    width: 40px;
    height: 40px;
    border-radius: 13px;
    background: var(--pg-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pd-item small {
    display: block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #a3a3b1;
    margin-bottom: 4px;
}

.pd-item b {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #1d1d27;
    line-height: 1.4;
    word-break: break-word;
}

.pd-item .sub {
    margin-top: 3px;
    font-size: 11.5px;
    color: #8a8a98;
}

/* ===== REKAN ===== */

.pd-rekan { display: flex; flex-direction: column; gap: 8px; }

.pd-rekan a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 11px 14px;
    border-radius: 14px;
    background: #f8f8fb;
    text-decoration: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pd-rekan a:hover {
    background: #fff;
    box-shadow: 0 10px 22px rgba(109,40,217,.1);
    transform: translateX(4px);
}

.pd-mini {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
}

.pd-rekan .nm {
    flex: 1;
    min-width: 0;
}

.pd-rekan .nm b {
    display: block;
    font-size: 13px;
    color: #1d1d27;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pd-rekan .nm span {
    font-size: 11.5px;
    color: #8a8a98;
}

.pd-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}

.pd-dot.on  { background: #22C55E; box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
.pd-dot.off { background: #EF4444; }

.pd-more {
    margin-top: 4px;
    text-align: center;
    font-size: 12px;
    color: #8a8a98;
}

.pd-more a {
    color: var(--pg-dark);
    font-weight: 700;
    text-decoration: none;
}

.pd-kosong {
    padding: 22px;
    text-align: center;
    border-radius: 16px;
    background: #f8f8fb;
    color: #8a8a98;
    font-size: 12.5px;
}

/* ===== SIDEBAR ===== */

.pd-side {
    position: sticky;
    top: 100px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pd-sum {
    background: linear-gradient(165deg, #17171f 0%, #221d33 55%, #2f2350 100%);
    border-radius: 22px;
    padding: 24px;
    color: #fff;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.06));
}

.pd-sum h3 {
    margin: 0 0 14px;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #C4B5FD !important;
}

.pd-sum-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
    padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,.08);
}

.pd-sum-row:last-child { border-bottom: none; }

.pd-sum-row span {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: rgba(255,255,255,.45);
}

.pd-sum-row b {
    font-size: 12.5px;
    font-weight: 600;
    color: #fff;
    text-align: right;
    word-break: break-word;
}

.pd-sum-row b.on  { color: #4ADE80; }
.pd-sum-row b.off { color: #F87171; }

.pd-actions {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 22px;
    padding: 12px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.pd-act {
    display: flex;
    align-items: center;
    gap: 13px;
    width: 100%;
    padding: 12px 14px;
    border: none;
    border-radius: 14px;
    background: transparent;
    font-family: inherit;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.pd-act .ic {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all .25s;
}

.pd-act b {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #1d1d27;
}

.pd-act small {
    display: block;
    margin-top: 1px;
    font-size: 11px;
    color: #9a9aa8;
}

.pd-act:hover { background: #f6f6fa; transform: translateX(4px); }

.pd-act.edt .ic { background: #FFEDD5; color: #C2410C; }
.pd-act.cpy .ic { background: #DBEAFE; color: #1D4ED8; }
.pd-act.lst .ic { background: var(--pg-soft); color: var(--pg-dark); }
.pd-act.del .ic { background: #FEE2E2; color: #B91C1C; }

.pd-act.edt:hover .ic { background: #FB7C3C; color: #fff; }
.pd-act.cpy:hover .ic { background: #2F6FED; color: #fff; }
.pd-act.lst:hover .ic { background: var(--pg); color: #fff; }
.pd-act.del:hover .ic { background: #EF4444; color: #fff; }
.pd-act.del:hover { background: #FEF2F2; }
.pd-act.del:hover b { color: #B91C1C; }

.pd-sep {
    height: 1px;
    background: var(--color-border, #eee);
    margin: 4px 8px;
}

/* ===== RESPONSIVE ===== */

@media (max-width: 960px) {
    .pd-layout { grid-template-columns: 1fr; }
    .pd-side { position: static; }
}

@media (max-width: 620px) {
    .pd-head { flex-direction: column; }
    .pd-hero { flex-direction: column; align-items: flex-start; padding: 26px 22px; }
    .pd-grid { grid-template-columns: 1fr; }
    .pd-card { padding: 22px 18px; }
}

</style>

<div class="content">
<div class="container-fluid pd-wrap">
<div class="pd-shell">

    <div class="pd-head">
        <div>
            <h2>Detail Pegawai</h2>
            <p>Informasi lengkap data pegawai.</p>
        </div>
        <a href="index.php" class="pd-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <div class="pd-layout">

        <!-- ===== KOLOM UTAMA ===== -->

        <div class="pd-main">

            <!-- HERO -->

            <div class="pd-hero">

                <div class="pd-avatar" style="background:<?= $av_bg; ?>;color:<?= $av_fg; ?>">
                    <?= htmlspecialchars(inisial($data['nama'])); ?>
                </div>

                <div class="pd-hero-info">
                    <div class="pd-eyebrow"><i></i> DATA PEGAWAI</div>
                    <h1><?= htmlspecialchars($data['nama']); ?></h1>
                    <div class="pd-nip">NIP <?= htmlspecialchars($data['nip']); ?></div>

                    <div class="pd-chips">
                        <span class="pd-chip <?= $kelas_tier; ?>"><?= $label_tier; ?></span>
                        <span class="pd-chip unit"><?= $pimpinan ? 'Pimpinan' : htmlspecialchars($data['unit_kerja']); ?></span>
                        <span class="pd-chip <?= $aktif ? 'on' : 'off'; ?>"><?= $aktif ? 'Aktif' : 'Nonaktif'; ?></span>
                    </div>
                </div>

            </div>

            <!-- INFORMASI -->

            <div class="pd-card">

                <div class="pd-sec">Informasi Pegawai</div>

                <div class="pd-grid">

                    <div class="pd-item">
                        <div class="pd-ic">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"/></svg>
                        </div>
                        <div>
                            <small>NIP</small>
                            <b><?= htmlspecialchars($data['nip']); ?></b>
                        </div>
                    </div>

                    <div class="pd-item">
                        <div class="pd-ic">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div>
                            <small>Nama Lengkap</small>
                            <b><?= htmlspecialchars($data['nama']); ?></b>
                        </div>
                    </div>

                    <div class="pd-item">
                        <div class="pd-ic">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                        </div>
                        <div>
                            <small>Jabatan</small>
                            <b><?= htmlspecialchars($data['jabatan']); ?></b>
                            <div class="sub">Tingkat: <?= $label_tier; ?></div>
                        </div>
                    </div>

                    <div class="pd-item">
                        <div class="pd-ic">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                        </div>
                        <div>
                            <small>Unit Kerja</small>
                            <b><?= $pimpinan ? 'Pimpinan' : htmlspecialchars($data['unit_kerja']); ?></b>
                            <div class="sub"><?= $pimpinan ? 'Tidak memiliki unit kerja' : ($jumlah_rekan . ' rekan satu unit'); ?></div>
                        </div>
                    </div>

                    <div class="pd-item full">
                        <div class="pd-ic" style="background:<?= $aktif ? '#DCFCE7' : '#FEE2E2'; ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $aktif ? '#15803D' : '#B91C1C'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="<?= $aktif ? 'M8 12l3 3 5-6' : 'M9 9l6 6M15 9l-6 6'; ?>"/></svg>
                        </div>
                        <div>
                            <small>Status Kepegawaian</small>
                            <b style="color:<?= $aktif ? '#15803D' : '#B91C1C'; ?>"><?= $aktif ? 'Aktif' : 'Nonaktif'; ?></b>
                        </div>
                    </div>

                </div>

            </div>

            <!-- REKAN SATU UNIT -->

            <?php if (!$pimpinan) { ?>

                <div class="pd-card">

                    <div class="pd-sec">Rekan Satu Unit Kerja</div>

                    <?php if ($jumlah_rekan === 0) { ?>

                        <div class="pd-kosong">Belum ada pegawai lain di unit kerja ini.</div>

                    <?php } else { ?>

                        <div class="pd-rekan">

                            <?php foreach ($rekan_tampil as $r) {
                                [$bg, $fg] = warnaAvatar($r['nama']);
                                $r_aktif = strtolower($r['status']) === 'aktif';
                            ?>

                                <a href="detail.php?id=<?= (int) $r['id']; ?>">
                                    <div class="pd-mini" style="background:<?= $bg; ?>;color:<?= $fg; ?>"><?= htmlspecialchars(inisial($r['nama'])); ?></div>
                                    <div class="nm">
                                        <b><?= htmlspecialchars($r['nama']); ?></b>
                                        <span><?= htmlspecialchars($r['jabatan']); ?></span>
                                    </div>
                                    <span class="pd-dot <?= $r_aktif ? 'on' : 'off'; ?>" title="<?= $r_aktif ? 'Aktif' : 'Nonaktif'; ?>"></span>
                                </a>

                            <?php } ?>

                            <?php if ($jumlah_rekan > count($rekan_tampil)) { ?>
                                <div class="pd-more">
                                    dan <?= $jumlah_rekan - count($rekan_tampil); ?> lainnya &middot;
                                    <a href="index.php?unit=<?= urlencode($data['unit_kerja']); ?>">Lihat semua</a>
                                </div>
                            <?php } ?>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>

        </div>

        <!-- ===== SIDEBAR ===== -->

        <aside class="pd-side">

            <div class="pd-sum">
                <h3>Ringkasan</h3>
                <div class="pd-sum-row"><span>Status</span><b class="<?= $aktif ? 'on' : 'off'; ?>"><?= $aktif ? 'Aktif' : 'Nonaktif'; ?></b></div>
                <div class="pd-sum-row"><span>Tingkat</span><b><?= $label_tier; ?></b></div>
                <div class="pd-sum-row"><span>Unit Kerja</span><b><?= $pimpinan ? 'Pimpinan' : htmlspecialchars($data['unit_kerja']); ?></b></div>
                <div class="pd-sum-row"><span>Rekan Unit</span><b><?= $pimpinan ? '-' : $jumlah_rekan; ?></b></div>
            </div>

            <div class="pd-actions">

                <?php if ($adalahAdmin) { ?>
                <a href="edit.php?id=<?= (int) $data['id']; ?>" class="pd-act edt">
                    <span class="ic">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    </span>
                    <span><b>Edit Pegawai</b><small>Ubah data pegawai</small></span>
                </a>
                <?php } ?>

                <button type="button" class="pd-act cpy" id="btnSalin" data-nip="<?= htmlspecialchars($data['nip']); ?>">
                    <span class="ic">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </span>
                    <span><b id="salinJudul">Salin NIP</b><small>Salin ke clipboard</small></span>
                </button>

                <a href="index.php" class="pd-act lst">
                    <span class="ic">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 0 0-3-3.87M17 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <span><b>Daftar Pegawai</b><small>Kembali ke semua pegawai</small></span>
                </a>

                <?php if ($adalahAdmin) { ?>
                <div class="pd-sep"></div>

                <a href="hapus.php?id=<?= (int) $data['id']; ?>" class="pd-act del"
                   onclick="return confirm('Yakin ingin menghapus data pegawai ini? Tindakan ini tidak dapat dibatalkan.')">
                    <span class="ic">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    </span>
                    <span><b>Hapus Pegawai</b><small>Hapus permanen</small></span>
                </a>
                <?php } ?>

            </div>

        </aside>

    </div>

</div>
</div>
</div>

<script>
(function () {

    var btn = document.getElementById('btnSalin');
    if (!btn) return;

    btn.addEventListener('click', function () {

        var nip = btn.getAttribute('data-nip');
        var judul = document.getElementById('salinJudul');

        function sukses() {
            judul.textContent = 'NIP tersalin!';
            setTimeout(function () { judul.textContent = 'Salin NIP'; }, 1800);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(nip).then(sukses);
        } else {
            var t = document.createElement('textarea');
            t.value = nip;
            document.body.appendChild(t);
            t.select();
            document.execCommand('copy');
            document.body.removeChild(t);
            sukses();
        }
    });

})();
</script>

<?php
include "../layouts/footer.php";
?>