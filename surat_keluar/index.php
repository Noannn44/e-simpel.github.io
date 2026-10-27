<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

$role = roleUser() ?? '';
$is_admin = ($role === 'admin');

$tahun_sekarang = (int) date('Y');
$bulan_sekarang = (int) date('n');
$hari_ini = date('Y-m-d');

$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : $tahun_sekarang;

if ($tahun < 2000 || $tahun > 2100) {
    $tahun = $tahun_sekarang;
}

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$keyword_safe = mysqli_real_escape_string($koneksi, $keyword);

$where = "YEAR(tanggal_surat) = '$tahun'";

if ($keyword !== '') {
    $where .= "
        AND (
            nomor_surat LIKE '%$keyword_safe%'
            OR tujuan LIKE '%$keyword_safe%'
            OR perihal LIKE '%$keyword_safe%'
        )
    ";
}

$query = mysqli_query($koneksi, "SELECT * FROM surat_keluar WHERE $where ORDER BY tanggal_surat DESC, id DESC");

if (!$query) {
    die("Terjadi kesalahan database: " . mysqli_error($koneksi));
}

$surat_list = [];
while ($row = mysqli_fetch_assoc($query)) {
    $surat_list[] = $row;
}

$total_query = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM surat_keluar WHERE YEAR(tanggal_surat) = '$tahun'");
$total_surat = $total_query ? (int) mysqli_fetch_assoc($total_query)['total'] : 0;

$bulan_ini_query = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM surat_keluar
    WHERE YEAR(tanggal_surat) = '$tahun_sekarang' AND MONTH(tanggal_surat) = '$bulan_sekarang'");
$total_bulan_ini = $bulan_ini_query ? (int) mysqli_fetch_assoc($bulan_ini_query)['total'] : 0;

$hari_ini_query = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM surat_keluar WHERE tanggal_surat = '$hari_ini'");
$total_hari_ini = $hari_ini_query ? (int) mysqli_fetch_assoc($hari_ini_query)['total'] : 0;

$tahun_query = mysqli_query($koneksi, "SELECT DISTINCT YEAR(tanggal_surat) AS tahun FROM surat_keluar
    WHERE tanggal_surat IS NOT NULL ORDER BY tahun DESC");

$tahun_list = [];

if ($tahun_query) {
    while ($row_tahun = mysqli_fetch_assoc($tahun_query)) {
        $tahun_database = (int) $row_tahun['tahun'];
        if ($tahun_database >= 2000) {
            $tahun_list[] = $tahun_database;
        }
    }
}

if (!in_array($tahun_sekarang, $tahun_list)) { $tahun_list[] = $tahun_sekarang; }
if (!in_array($tahun, $tahun_list)) { $tahun_list[] = $tahun; }

rsort($tahun_list);

$nama_bulan_singkat = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
$nama_hari = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];

$url_index = basename($_SERVER['PHP_SELF']);

/* Warna avatar bervariasi, konsisten berdasarkan nama tujuan */
function warnaAvatar($nama) {
    $palet = [
        ['bg' => '#FDE8DD', 'fg' => '#c9660f'],
        ['bg' => '#DBEAFE', 'fg' => '#1d4fa8'],
        ['bg' => '#DCFCE7', 'fg' => '#158a45'],
        ['bg' => '#EDE4FB', 'fg' => '#6d3fc9'],
        ['bg' => '#FCE7F3', 'fg' => '#be185d'],
        ['bg' => '#FEF3C7', 'fg' => '#b45309'],
    ];
    $index = array_sum(array_map('ord', str_split(substr($nama, 0, 5)))) % count($palet);
    return $palet[$index];
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";

?>

<style>

.sk2-page { padding-bottom: 40px; }

/* ===== HERO ===== */

.sk2-hero {
    position: relative;
    border-radius: var(--radius-lg);
    padding: 30px 28px;
    margin-bottom: 22px;
    background:
        radial-gradient(circle at 85% -10%, rgba(251,140,60,.34), transparent 50%),
        radial-gradient(circle at 10% 110%, rgba(47,111,237,.16), transparent 45%),
        linear-gradient(160deg, rgba(22,22,29,.85), rgba(22,22,29,.97)),
        linear-gradient(135deg, #22242d, #16161d);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    overflow: hidden;
}

.sk2-hero::before {
    content: "";
    position: absolute;
    top: -40%;
    right: -6%;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(251,140,60,.2), transparent 70%);
    pointer-events: none;
}

.sk2-hero-text { position: relative; z-index: 1; }

.sk2-hero-text h2 {
    margin: 0 0 7px;
    font-size: 24px;
    font-weight: 800;
    background: linear-gradient(135deg, #ffffff, #ffd9b8);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.sk2-hero-text p { margin: 0; font-size: 13px; color: rgba(255,255,255,.62); max-width: 420px; line-height: 1.6; }

.sk2-hero-stats { display: flex; gap: 12px; flex-wrap: wrap; position: relative; z-index: 1; }

.sk2-stat-pill {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: var(--radius-md);
    padding: 12px 20px;
    text-align: center;
    min-width: 92px;
    backdrop-filter: blur(6px);
    transition: transform .25s var(--ease-smooth), background .25s var(--ease-smooth);
}

.sk2-stat-pill:hover { transform: translateY(-3px); background: rgba(255,255,255,.1); }

.sk2-stat-pill strong { display: block; font-size: 23px; font-weight: 800; color: #fff; letter-spacing: -.3px; }
.sk2-stat-pill small { display: block; font-size: 9.5px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; }

.sk2-stat-pill.active {
    background: linear-gradient(135deg, rgba(251,140,60,.32), rgba(251,140,60,.16));
    border-color: rgba(251,140,60,.45);
    box-shadow: 0 8px 20px rgba(251,140,60,.2);
}

.sk2-stat-pill.active strong { color: #ffc899; }

.btn-add-fab {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: linear-gradient(135deg, var(--card-orange), #c9660f);
    color: white;
    border: none;
    padding: 14px 24px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(251,140,60,.42);
    transition: transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s var(--ease-smooth);
    position: relative;
    z-index: 1;
}

.btn-add-fab:hover { color: white; transform: translateY(-3px) scale(1.02); box-shadow: 0 14px 28px rgba(251,140,60,.5); }
.btn-add-fab svg { transition: transform .25s var(--ease-smooth); }
.btn-add-fab:hover svg { transform: rotate(90deg); }

/* ===== FILTER + SEARCH ===== */

.sk2-filter-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 26px;
    flex-wrap: wrap;
    align-items: center;
}

.sk2-select {
    height: 48px;
    border: 1.5px solid var(--color-border);
    border-radius: 999px;
    padding: 0 38px 0 20px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    color: var(--color-text);
    background: var(--color-surface);
    box-shadow: var(--shadow-card);
    outline: none;
    cursor: pointer;
    transition: border-color .25s var(--ease-smooth), box-shadow .25s var(--ease-smooth);
}

.sk2-select:focus { border-color: var(--card-orange); box-shadow: 0 0 0 4px var(--card-orange-soft); }

.sk2-filter-bar input[type="text"] {
    flex: 1;
    min-width: 200px;
    height: 48px;
    border: 1.5px solid var(--color-border);
    border-radius: 999px;
    padding: 0 22px;
    font-size: 13.5px;
    outline: none;
    background: var(--color-surface);
    box-shadow: var(--shadow-card);
    font-family: inherit;
    transition: border-color .25s var(--ease-smooth), box-shadow .25s var(--ease-smooth);
}

.sk2-filter-bar input[type="text"]:focus { border-color: var(--card-orange); box-shadow: 0 0 0 4px var(--card-orange-soft); }
.sk2-filter-bar input[type="text"]::placeholder { color: #a8adb8; }

.sk2-filter-bar button {
    height: 48px;
    width: 48px;
    border-radius: 50%;
    border: none;
    background: var(--color-text);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform .2s var(--ease-smooth), background .2s var(--ease-smooth);
}

.sk2-filter-bar button:hover { background: var(--card-orange); transform: scale(1.06); }

.sk2-reset-chip {
    display: inline-flex;
    align-items: center;
    height: 48px;
    padding: 0 18px;
    border-radius: 999px;
    background: var(--color-bg);
    color: var(--color-text-muted);
    text-decoration: none;
    font-size: 12.5px;
    font-weight: 600;
    flex-shrink: 0;
    transition: background .2s var(--ease-smooth), color .2s var(--ease-smooth);
}

.sk2-reset-chip:hover { background: var(--card-orange-soft); color: #c9660f; }

/* ===== TIMELINE ===== */

.day-group { margin-bottom: 28px; opacity: 0; animation: fadeInUp .5s var(--ease-smooth) forwards; }
.day-group:nth-of-type(1) { animation-delay: .02s; }
.day-group:nth-of-type(2) { animation-delay: .08s; }
.day-group:nth-of-type(3) { animation-delay: .14s; }
.day-group:nth-of-type(4) { animation-delay: .2s; }
.day-group:last-child { margin-bottom: 0; }

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.day-label {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: var(--color-text);
    color: #fff;
    padding: 8px 18px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
    margin-bottom: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}

.day-label.today { background: linear-gradient(135deg, var(--card-orange), #c9660f); }

.day-label .day-count {
    background: rgba(255,255,255,.22);
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 10.5px;
}

.timeline { position: relative; padding-left: 28px; }

.timeline::before {
    content: "";
    position: absolute;
    left: 8px;
    top: 4px;
    bottom: 4px;
    width: 2px;
    background: linear-gradient(to bottom, var(--color-border), transparent);
}

.t-item { position: relative; margin-bottom: 12px; }
.t-item:last-child { margin-bottom: 0; }

.t-dot {
    position: absolute;
    left: -28px;
    top: 22px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: var(--card-orange);
    border: 3px solid var(--color-bg);
    box-shadow: 0 0 0 2px var(--card-orange);
}

.t-dot.today-dot { animation: pulseDot 2s infinite; }

@keyframes pulseDot {
    0%   { box-shadow: 0 0 0 2px var(--card-orange), 0 0 0 0 rgba(251,140,60,.5); }
    70%  { box-shadow: 0 0 0 2px var(--card-orange), 0 0 0 8px rgba(251,140,60,0); }
    100% { box-shadow: 0 0 0 2px var(--card-orange), 0 0 0 0 rgba(251,140,60,0); }
}

.t-card {
    position: relative;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: box-shadow .25s var(--ease-smooth), border-color .25s var(--ease-smooth), transform .25s var(--ease-smooth);
    overflow: hidden;
}

.t-card::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: var(--card-orange);
    transform: scaleY(0);
    transition: transform .25s var(--ease-smooth);
}

.t-card:hover { box-shadow: 0 10px 24px rgba(20,20,40,.09); border-color: transparent; transform: translateX(4px); }
.t-card:hover::before { transform: scaleY(1); }

.t-main {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    color: inherit;
}

.t-main:hover { color: inherit; }

.t-date {
    width: 52px;
    flex-shrink: 0;
    text-align: center;
    padding: 7px 4px;
    border-radius: 11px;
    background: var(--card-orange-soft);
}

.t-date .d-num { font-size: 18px; font-weight: 800; color: #c9660f; line-height: 1.1; }
.t-date .d-mon { font-size: 9.5px; font-weight: 700; color: #c9660f; text-transform: uppercase; letter-spacing: .3px; }

.t-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 3px 8px rgba(0,0,0,.08);
}

.t-body { flex: 1; min-width: 0; }
.t-body strong { display: block; font-size: 14px; font-weight: 700; color: var(--color-text); word-break: break-word; font-family: 'Courier New', monospace; letter-spacing: -.1px; }
.t-body .t-meta { font-size: 12px; color: var(--color-text-muted); margin-top: 3px; }
.t-body .t-perihal { font-size: 12.5px; color: var(--color-text); margin-top: 4px; }

.t-actions { display: flex; gap: 6px; flex-shrink: 0; }

.t-actions form { margin: 0; }

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    flex-shrink: 0;
    transition: transform .2s var(--ease-smooth), background .2s var(--ease-smooth), color .2s var(--ease-smooth);
}

.btn-detail { background: var(--card-blue-soft); color: var(--card-blue); }
.btn-edit   { background: var(--card-orange-soft); color: #c9660f; }
.btn-delete { background: #FCE9E9; color: var(--color-danger); }

.btn-detail:hover { background: var(--card-blue); color: white; transform: translateY(-2px); }
.btn-edit:hover   { background: var(--card-orange); color: white; transform: translateY(-2px); }
.btn-delete:hover { background: var(--color-danger); color: white; transform: translateY(-2px); }

/* ===== EMPTY STATE ===== */

.sk2-empty {
    text-align: center;
    padding: 70px 20px;
    color: var(--color-text-muted);
    background: var(--color-surface);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-lg);
}

.sk2-empty .e-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--card-orange-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.sk2-empty strong { display: block; font-size: 14px; color: var(--color-text); margin-bottom: 4px; }
.sk2-empty p { margin: 0; font-size: 12.5px; }

@media (max-width: 700px) {
    .sk2-hero { flex-direction: column; align-items: flex-start; }
    .sk2-hero-stats { width: 100%; }
    .sk2-stat-pill { flex: 1; }
    .btn-add-fab { width: 100%; justify-content: center; }
    .t-card { flex-wrap: wrap; }
    .t-main { flex-basis: 100%; }
    .t-actions { width: 100%; justify-content: flex-end; }
    .sk2-select { flex: 1; }
}

</style>

<div class="content">

    <div class="container-fluid sk2-page">

        <!-- HERO -->

        <div class="sk2-hero">

            <div class="sk2-hero-text">
                <h2>Surat Keluar</h2>
                <p>Linimasa surat yang dikeluarkan instansi sepanjang tahun <?= $tahun; ?>.</p>
            </div>

            <div class="sk2-hero-stats">
                <div class="sk2-stat-pill active">
                    <strong><?= $total_surat; ?></strong>
                    <small>Total <?= $tahun; ?></small>
                </div>
                <div class="sk2-stat-pill">
                    <strong><?= $total_bulan_ini; ?></strong>
                    <small>Bulan Ini</small>
                </div>
                <div class="sk2-stat-pill">
                    <strong><?= $total_hari_ini; ?></strong>
                    <small>Hari Ini</small>
                </div>
            </div>

            <?php if ($role === 'admin' || $role === 'operator') { ?>
            <a href="tambah.php" class="btn-add-fab">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>
                Tambah Surat
            </a>
            <?php } ?>

        </div>

        <!-- FILTER + SEARCH -->

        <form method="GET" action="<?= htmlspecialchars($url_index); ?>" class="sk2-filter-bar">

            <select name="tahun" class="sk2-select">
                <?php foreach ($tahun_list as $tahun_item) { ?>
                    <option value="<?= $tahun_item; ?>" <?= ($tahun === (int) $tahun_item) ? 'selected' : ''; ?>><?= $tahun_item; ?></option>
                <?php } ?>
            </select>

            <input
                type="text"
                name="keyword"
                value="<?= htmlspecialchars($keyword); ?>"
                placeholder="Cari nomor, tujuan, atau perihal..."
            >

            <button type="submit" title="Tampilkan">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
            </button>

            <a href="<?= htmlspecialchars($url_index); ?>" class="sk2-reset-chip">Reset</a>

        </form>

        <!-- TIMELINE -->

        <?php if (count($surat_list) > 0) {

            $tanggal_terakhir = null;
            $sedang_buka = false;

            foreach ($surat_list as $data) {

                $tgl = $data['tanggal_surat'];

                if ($tgl !== $tanggal_terakhir) {

                    if ($sedang_buka) {
                        echo '</div></div>';
                    }

                    $jumlah_hari = 0;
                    foreach ($surat_list as $cek) {
                        if ($cek['tanggal_surat'] === $tgl) {
                            $jumlah_hari++;
                        }
                    }

                    if (!empty($tgl) && $tgl !== '0000-00-00') {
                        $ts = strtotime($tgl);
                        $label_tanggal = $nama_hari[date('l', $ts)] . ', ' . date('d', $ts) . ' ' . $nama_bulan_singkat[(int) date('n', $ts)] . ' ' . date('Y', $ts);
                    } else {
                        $label_tanggal = 'Tanggal tidak diketahui';
                    }

                    $is_today = ($tgl === $hari_ini);

                    if ($is_today) {
                        $label_tanggal = '✨ Hari Ini — ' . $label_tanggal;
                    }

                    echo '<div class="day-group">';
                    echo '<div class="day-label' . ($is_today ? ' today' : '') . '">' . htmlspecialchars($label_tanggal) . ' <span class="day-count">' . $jumlah_hari . ' surat</span></div>';
                    echo '<div class="timeline">';

                    $tanggal_terakhir = $tgl;
                    $sedang_buka = true;
                }

                $ts_item = (!empty($tgl) && $tgl !== '0000-00-00') ? strtotime($tgl) : false;
                $tgl_num = $ts_item ? date('d', $ts_item) : '--';
                $tgl_mon = $ts_item ? strtoupper($nama_bulan_singkat[(int) date('n', $ts_item)]) : '---';

                $inisial = strtoupper(substr($data['tujuan'], 0, 1));
                $warna = warnaAvatar($data['tujuan']);

                $dot_class = ($tgl === $hari_ini) ? ' today-dot' : '';

        ?>
            <div class="t-item">
                <div class="t-dot<?= $dot_class; ?>"></div>

                <div class="t-card">

                    <a href="detail.php?id=<?= (int) $data['id']; ?>" class="t-main">

                        <div class="t-date">
                            <div class="d-num"><?= $tgl_num; ?></div>
                            <div class="d-mon"><?= $tgl_mon; ?></div>
                        </div>

                        <div class="t-avatar" style="background:<?= $warna['bg']; ?>; color:<?= $warna['fg']; ?>;"><?= htmlspecialchars($inisial); ?></div>

                        <div class="t-body">
                            <strong><?= htmlspecialchars($data['nomor_surat']); ?></strong>
                            <div class="t-meta">Kepada: <?= htmlspecialchars($data['tujuan']); ?></div>
                            <div class="t-perihal"><?= htmlspecialchars($data['perihal']); ?></div>
                        </div>

                    </a>

                    <div class="t-actions">

                        <a href="detail.php?id=<?= (int) $data['id']; ?>" class="action-btn btn-detail" title="Detail"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>

                        <?php if ($is_admin) { ?>

                        <a href="edit.php?id=<?= (int) $data['id']; ?>" class="action-btn btn-edit" title="Edit"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"></path></svg></a>

                        <form method="POST" action="hapus.php" onsubmit="return confirm('Yakin ingin menghapus surat ini?')">
                            <input type="hidden" name="id" value="<?= (int) $data['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                            <button type="submit" class="action-btn btn-delete" title="Hapus"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6M14 11v6"></path></svg></button>
                        </form>

                        <?php } ?>

                    </div>

                </div>
            </div>
        <?php
            }

            if ($sedang_buka) {
                echo '</div></div>';
            }

        } else {
        ?>
            <div class="sk2-empty">
                <div class="e-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#FB8C3C" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9 6 9-6"></path><rect x="3" y="5" width="18" height="14" rx="2"></rect></svg></div>
                <strong>Belum ada surat keluar</strong>
                <p>Tidak ada surat keluar pada tahun <?= $tahun; ?>.<?= ($role === 'admin' || $role === 'operator') ? ' Klik "Tambah Surat" untuk mencatat surat baru.' : ''; ?></p>
            </div>
        <?php } ?>

    </div>

</div>

<?php include "../layouts/footer.php"; ?>