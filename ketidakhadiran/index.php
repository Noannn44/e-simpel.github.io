<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

adminDanOperator();

include "../config/database.php";
require_once "../config/ketidakhadiran.php";

date_default_timezone_set('Asia/Makassar');

siapkanTabelKetidakhadiran($koneksi);

/* ===== urutan sama persis dengan halaman Data Pegawai ===== */

if (!function_exists('tingkatJabatan')) {

    function tingkatJabatan($jabatan, $unit_kerja = '')
    {
        // pegawai di unit "Pimpinan" selalu tingkat tertinggi
        if (strtolower(trim((string) $unit_kerja)) === 'pimpinan') {
            return 1;
        }

        $j = strtolower(trim((string) $jabatan));

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
}

/* kelompokkan per unit kerja: jabatan tertinggi dulu, lalu nama;
   kelompok Pimpinan paling atas, lalu menurut jabatan tertinggi, lalu nama unit */
function susunStruktur($daftar)
{
    $grup = [];

    foreach ($daftar as $p) {
        $p['pangkat'] = tingkatJabatan($p['jabatan'], $p['unit_kerja']);
        $nama_unit = trim((string) $p['unit_kerja']) !== '' ? $p['unit_kerja'] : 'Tanpa Unit Kerja';
        $p['grup'] = $nama_unit;
        $grup[$nama_unit][] = $p;
    }

    foreach ($grup as $u => &$isi) {
        usort($isi, function ($a, $b) {
            if ($a['pangkat'] !== $b['pangkat']) {
                return $a['pangkat'] <=> $b['pangkat'];
            }
            return strcasecmp($a['nama'], $b['nama']);
        });
    }
    unset($isi);

    uksort($grup, function ($ua, $ub) use ($grup) {

        if (strcasecmp($ua, 'Pimpinan') === 0) return -1;
        if (strcasecmp($ub, 'Pimpinan') === 0) return 1;

        $ta = $grup[$ua][0]['pangkat'];
        $tb = $grup[$ub][0]['pangkat'];

        if ($ta !== $tb) {
            return $ta <=> $tb;
        }

        return strcasecmp($ua, $ub);
    });

    $hasil = [];

    foreach ($grup as $isi) {
        foreach ($isi as $p) {
            $hasil[] = $p;
        }
    }

    return $hasil;
}

$is_admin = (strtolower($_SESSION['role'] ?? 'operator') === 'admin');
$hari_ini = date('Y-m-d');

$tab = (($_GET['tab'] ?? '') === 'riwayat') ? 'riwayat' : 'hari';

/* tanggal yang dilihat (default hari ini) */
$tgl = (string) ($_GET['tanggal'] ?? $hari_ini);
$dt  = DateTime::createFromFormat('Y-m-d', $tgl);

if (!$dt || $dt->format('Y-m-d') !== $tgl) {
    $tgl = $hari_ini;
}

$f_status = in_array($_GET['status'] ?? '', ['hadir', 'tugas', 'cuti'], true) ? $_GET['status'] : '';
$keyword  = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$pesan = $_GET['pesan'] ?? '';
$teks_pesan = ['tambah' => 'Data berhasil ditambahkan.', 'ubah' => 'Data berhasil diperbarui.', 'hapus' => 'Data berhasil dihapus.'][$pesan] ?? '';

/* =========================================================
   TAB 1: SELURUH PEGAWAI PADA TANGGAL TERPILIH
========================================================= */

$roster = [];
$n_hadir = $n_tugas = $n_cuti = 0;

if ($tab === 'hari') {

    $tidak = [];

    $st = mysqli_prepare($koneksi, "SELECT * FROM ketidakhadiran WHERE ? BETWEEN tanggal_mulai AND tanggal_selesai ORDER BY id DESC");
    mysqli_stmt_bind_param($st, "s", $tgl);
    mysqli_stmt_execute($st);
    $rk = mysqli_stmt_get_result($st);

    while ($rk && ($r = mysqli_fetch_assoc($rk))) {
        $tidak[(int) $r['pegawai_id']] = $r; // satu catatan per pegawai
    }

    $rp = mysqli_query($koneksi, "SELECT id, nip, nama, jabatan, unit_kerja FROM pegawai WHERE status = 'Aktif' OR status IS NULL ORDER BY nama ASC");

    while ($rp && ($p = mysqli_fetch_assoc($rp))) {

        $k = $tidak[(int) $p['id']] ?? null;
        $p['kh']     = $k;
        $p['status'] = $k === null ? 'hadir' : ($k['jenis'] === 'Cuti' ? 'cuti' : 'tugas');

        if ($p['status'] === 'hadir') { $n_hadir++; }
        elseif ($p['status'] === 'cuti') { $n_cuti++; }
        else { $n_tugas++; }

        $roster[] = $p;
    }

    $n_total = count($roster);

    /* terapkan filter status dan pencarian */
    $roster = array_values(array_filter($roster, function ($p) use ($f_status, $keyword) {

        if ($f_status !== '' && $p['status'] !== $f_status) {
            return false;
        }

        if ($keyword !== '' && stripos($p['nama'] . ' ' . $p['nip'] . ' ' . $p['jabatan'], $keyword) === false) {
            return false;
        }

        return true;
    }));

    $roster = susunStruktur($roster);
    $total_tampil = count($roster);
}

/* =========================================================
   TAB 2: RIWAYAT SEMUA CATATAN
========================================================= */

$n_akan = 0;
$hasil = null;
$total_tampil_r = 0;
$f_jenis = in_array($_GET['jenis'] ?? '', ['Tugas Luar', 'Cuti'], true) ? $_GET['jenis'] : '';
$f_waktu = in_array($_GET['waktu'] ?? '', ['berlangsung', 'akan', 'selesai'], true) ? $_GET['waktu'] : '';

if ($tab === 'riwayat') {

    $sql = "SELECT k.*, p.nama, p.nip, p.jabatan
            FROM ketidakhadiran k JOIN pegawai p ON p.id = k.pegawai_id WHERE 1=1";
    $types = '';
    $params = [];

    if ($f_jenis !== '') { $sql .= " AND k.jenis = ?"; $types .= 's'; $params[] = $f_jenis; }

    if ($f_waktu === 'berlangsung') { $sql .= " AND ? BETWEEN k.tanggal_mulai AND k.tanggal_selesai"; $types .= 's'; $params[] = $hari_ini; }
    elseif ($f_waktu === 'akan') { $sql .= " AND k.tanggal_mulai > ?"; $types .= 's'; $params[] = $hari_ini; }
    elseif ($f_waktu === 'selesai') { $sql .= " AND k.tanggal_selesai < ?"; $types .= 's'; $params[] = $hari_ini; }

    if ($keyword !== '') {
        $sql .= " AND (p.nama LIKE ? OR k.tujuan LIKE ? OR k.keperluan LIKE ?)";
        $like = '%' . $keyword . '%';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }

    $sql .= " ORDER BY CASE WHEN ? BETWEEN k.tanggal_mulai AND k.tanggal_selesai THEN 0 WHEN k.tanggal_mulai > ? THEN 1 ELSE 2 END ASC,
              k.tanggal_mulai ASC, k.id ASC";
    $types .= 'ss';
    array_push($params, $hari_ini, $hari_ini);

    $st = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $hasil = mysqli_stmt_get_result($st);
    $total_tampil_r = $hasil ? mysqli_num_rows($hasil) : 0;
}

/* jumlah catatan akan datang (untuk pil hero) */
$sa = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM ketidakhadiran WHERE tanggal_mulai > ?");
mysqli_stmt_bind_param($sa, "s", $hari_ini);
mysqli_stmt_execute($sa);
$ra = mysqli_stmt_get_result($sa);
$n_akan = $ra ? (int) (mysqli_fetch_row($ra)[0] ?? 0) : 0;

function urlKh($tambah = [])
{
    $q = array_merge($_GET, $tambah);
    unset($q['pesan']);
    foreach ($q as $k => $v) { if ($v === '' || $v === null) { unset($q[$k]); } }
    return 'index.php?' . http_build_query($q);
}

$tgl_label = khTanggalPanjang($tgl) . ($tgl === $hari_ini ? ' (hari ini)' : '');

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.kh-wrap { --ac:#0EA5E9; --ac-dark:#0369A1; --ac-soft:#E0F2FE; --hadir:#16A34A; --cuti:#F59E0B; }

/* ===== HERO ===== */
.kh-hero {
    position: relative; overflow: hidden; border-radius: 24px; padding: 28px 32px 24px;
    background: linear-gradient(135deg, #071a2b 0%, #0b3354 55%, #0a6aa8 100%);
    margin-bottom: 18px;
}
.kh-hero::before { content:""; position:absolute; width:360px; height:360px; right:-90px; top:-160px; border-radius:50%;
    background: radial-gradient(circle, rgba(56,189,248,.45), transparent 70%); pointer-events:none; }
.kh-hero::after { content:""; position:absolute; inset:0; background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 18px 18px; pointer-events:none; }
.kh-hero > * { position: relative; z-index: 1; }
.kh-hero-top { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap; }
.kh-eyebrow { display:inline-flex; align-items:center; gap:7px; padding:5px 12px; border-radius:999px; background:rgba(255,255,255,.1);
    color:#fff; font-size:11px; font-weight:700; letter-spacing:1.2px; margin-bottom:12px; }
.kh-eyebrow i { width:7px; height:7px; border-radius:50%; background:#38BDF8; box-shadow:0 0 0 4px rgba(56,189,248,.35); }
.kh-hero h1 { margin:0; font-size:28px; font-weight:800; letter-spacing:-.3px; color:#fff !important; }
.kh-hero p { margin:6px 0 0; font-size:13.5px; color:rgba(255,255,255,.68) !important; }
.kh-hero-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.kh-pill { min-width:96px; padding:11px 16px; border-radius:16px; background:rgba(255,255,255,.09); border:1px solid rgba(255,255,255,.1); color:#fff; }
.kh-pill strong { display:block; font-size:22px; font-weight:800; line-height:1.1; }
.kh-pill span { font-size:10.5px; text-transform:uppercase; letter-spacing:.6px; color:rgba(255,255,255,.62); }

.kh-meter { margin-top:22px; }
.kh-meter-bar { display:flex; height:10px; border-radius:999px; overflow:hidden; background:rgba(255,255,255,.12); }
.kh-meter-bar i { display:block; height:100%; transition: width .5s ease; }
.kh-meter-bar .h { background:#4ADE80; }
.kh-meter-bar .t { background:#38BDF8; }
.kh-meter-bar .c { background:#FBBF24; }
.kh-meter-ket { display:flex; gap:18px; flex-wrap:wrap; margin-top:10px; font-size:12px; color:rgba(255,255,255,.75); }
.kh-meter-ket span::before { content:""; display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:6px; vertical-align:-1px; background:var(--d); }

.kh-notif { background:#ECFDF5; border:1px solid #A7F3D0; color:#047857; border-radius:14px; padding:12px 16px; font-size:13.5px; font-weight:600; margin-bottom:16px; }

/* ===== TAB ===== */
.kh-tabs { display:inline-flex; gap:4px; padding:4px; border-radius:999px; background:var(--color-surface); border:1px solid var(--color-border); margin-bottom:16px; box-shadow:var(--shadow-card); }
.kh-tab { padding:9px 20px; border-radius:999px; font-size:13px; font-weight:700; color:var(--color-text-muted); text-decoration:none; transition:all .2s; }
.kh-tab:hover { color:var(--ac-dark); }
.kh-tab.on { background:var(--ac); color:#fff; box-shadow:0 8px 18px rgba(14,165,233,.35); }

/* ===== FILTER ===== */
.kh-filter { display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px; background:var(--color-surface); border:1px solid var(--color-border);
    border-radius:20px; padding:16px 18px; margin-bottom:16px; box-shadow:var(--shadow-card); }
.kh-fg { display:flex; flex-direction:column; gap:6px; }
.kh-fg label { font-size:11.5px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; color:var(--color-text-muted); }
.kh-fg select, .kh-fg input { height:42px; border:1.5px solid var(--color-border); border-radius:12px; padding:0 14px; background:var(--color-surface);
    color:var(--color-text); font-size:13.5px; outline:none; font-family:inherit; transition:border-color .2s, box-shadow .2s; }
.kh-fg select:focus, .kh-fg input:focus { border-color:var(--ac); box-shadow:0 0 0 4px rgba(14,165,233,.14); }
.kh-fg.cari { flex:1; min-width:220px; }
.kh-go { height:42px; padding:0 22px; border:none; border-radius:12px; background:var(--ac); color:#fff; font-weight:700; font-size:13.5px; cursor:pointer;
    box-shadow:0 8px 18px rgba(14,165,233,.3); transition:transform .2s, background .2s; }
.kh-go:hover { background:var(--ac-dark); transform:translateY(-2px); }
.kh-reset { align-self:center; font-size:13px; font-weight:700; color:var(--color-text-muted); text-decoration:none; }
.kh-reset:hover { color:#B91C1C; }

.kh-chips { display:flex; gap:6px; flex-wrap:wrap; }
.kh-chip { display:inline-flex; align-items:center; gap:7px; height:42px; padding:0 14px; border-radius:12px; font-size:13px; font-weight:700; text-decoration:none;
    border:1.5px solid var(--color-border); color:var(--color-text-muted); background:var(--color-surface); white-space:nowrap; transition:all .2s; }
.kh-chip b { min-width:22px; height:22px; padding:0 6px; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; font-size:11.5px; background:var(--color-bg); color:var(--color-text); }
.kh-chip:hover { border-color:var(--c); color:var(--c); }
.kh-chip.on { background:var(--c); border-color:var(--c); color:#fff; box-shadow:0 8px 18px rgba(0,0,0,.12); }
.kh-chip.on b { background:rgba(255,255,255,.25); color:#fff; }

/* ===== KARTU TABEL ===== */
.kh-tabel-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:22px; box-shadow:var(--shadow-card); overflow:hidden; }
.kh-tabel-top { display:flex; justify-content:space-between; align-items:center; padding:18px 22px; border-bottom:1px solid var(--color-border); }
.kh-tabel-top h3 { margin:0; font-size:15.5px; font-weight:800; }
.kh-count { font-size:12px; font-weight:800; padding:5px 13px; border-radius:999px; background:var(--ac-soft); color:var(--ac-dark); }
.kh-scroll { overflow-x:auto; }
.kh-tabel { width:100%; border-collapse:collapse; font-size:13.5px; }
.kh-tabel th { text-align:left; padding:12px 18px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--color-text-muted); background:var(--color-bg); white-space:nowrap; }
.kh-tabel td { padding:14px 18px; border-top:1px solid var(--color-border); vertical-align:middle; }
.kh-tabel tbody tr { transition:background .15s; }
.kh-tabel tbody tr:not(.kh-grup):hover td { background:rgba(14,165,233,.05); }

/* grup */
.kh-tabel tr.kh-grup td { padding:11px 18px !important; background:var(--color-bg); border-top:1px solid var(--color-border);
    font-size:11.5px; font-weight:800; letter-spacing:.8px; text-transform:uppercase; color:var(--ac-dark); box-shadow:inset 4px 0 0 var(--ac); }
.kh-grup td span { margin-left:8px; padding:2px 9px; border-radius:999px; background:var(--color-surface); font-weight:700; letter-spacing:0; text-transform:none; color:var(--color-text-muted); border:1px solid var(--color-border); }
.kh-tabel tr.kh-grup.pimpinan td { background:linear-gradient(90deg, #E0F2FE, var(--color-bg)); box-shadow:inset 4px 0 0 #0369A1; }

/* orang */
.kh-org { display:flex; align-items:center; gap:13px; min-width:230px; }
.kh-ava { flex:0 0 42px; width:42px; height:42px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:15px; color:#fff;
    background:linear-gradient(135deg, #22C55E, #15803D); box-shadow:0 6px 14px rgba(22,163,74,.25); }
.kh-ava.tugas { background:linear-gradient(135deg, #38BDF8, #0369A1); box-shadow:0 6px 14px rgba(14,165,233,.28); }
.kh-ava.cuti { background:linear-gradient(135deg, #FBBF24, #D97706); box-shadow:0 6px 14px rgba(245,158,11,.28); }
.kh-nm { font-weight:700; color:var(--color-text); line-height:1.3; }
.kh-pimpin .kh-nm { font-weight:800; }
.kh-jb { display:block; font-size:12px; font-weight:500; color:var(--color-text-muted); margin-top:2px; }

/* lencana status */
.kh-badge { display:inline-flex; align-items:center; gap:7px; padding:5px 12px 5px 10px; border-radius:999px; font-size:12px; font-weight:700; white-space:nowrap; background:#E0F2FE; color:#0369A1; }
.kh-badge::before { content:""; width:7px; height:7px; border-radius:50%; background:currentColor; }
.kh-badge.cuti { background:#FEF3C7; color:#B45309; }
.kh-badge.hadir { background:#DCFCE7; color:#15803D; }

.kh-ket { min-width:220px; line-height:1.55; color:var(--color-text-muted); font-size:13px; }
.kh-ket b { color:var(--color-text); }
.kh-ket .lok { display:flex; align-items:center; gap:5px; }
.kh-ket .tgl { display:inline-block; margin-top:5px; padding:2px 10px; border-radius:8px; background:var(--color-bg); font-size:11.5px; font-weight:700; color:var(--color-text-muted); }

.kh-st { font-size:12px; font-weight:700; white-space:nowrap; }
.kh-st.berlangsung { color:#15803D; }
.kh-st.akan { color:#B45309; }
.kh-st.selesai { color:#8a8a98; }

.kh-nama { font-weight:700; min-width:170px; }
.kh-nama small { display:block; font-weight:500; color:var(--color-text-muted); font-size:12px; }

/* aksi */
.kh-aksi { display:flex; gap:6px; align-items:center; }
.kh-act, .kh-del { width:36px; height:36px; border-radius:11px; border:none; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
    text-decoration:none; background:var(--color-bg); color:var(--color-text-muted); transition:all .2s; }
.kh-act:hover { background:#E0F2FE; color:#0369A1; transform:translateY(-2px); }
.kh-del:hover { background:#FEE2E2; color:#B91C1C; transform:translateY(-2px); }
.kh-catat { display:inline-flex; align-items:center; gap:7px; height:36px; padding:0 14px; border-radius:11px; font-size:12.5px; font-weight:700; text-decoration:none;
    background:#fff; color:#0369A1; border:1.5px dashed #7DD3FC; white-space:nowrap; transition:all .2s; }
.kh-catat:hover { background:#0EA5E9; border-style:solid; border-color:#0EA5E9; color:#fff; transform:translateY(-2px); box-shadow:0 8px 16px rgba(14,165,233,.3); }

.kh-empty { text-align:center; padding:48px 16px !important; color:var(--color-text-muted); }
.kh-catatan { margin:14px 6px 0; font-size:12px; color:var(--color-text-muted); }

@media (max-width: 720px) {
    .kh-hero { padding:22px 20px; }
    .kh-hero h1 { font-size:23px; }
}

</style>
<div class="content">
<div class="container-fluid kh-wrap">

    <div class="kh-hero">

        <div class="kh-hero-top">
            <div>
                <div class="kh-eyebrow"><i></i> KEPEGAWAIAN</div>
                <h1>Ketidakhadiran Pegawai</h1>
                <p>Status kehadiran seluruh pegawai, berdasarkan catatan tugas luar dan cuti.</p>
            </div>

            <div class="kh-hero-right">
                <?php if ($tab === 'hari') { ?>
                    <div class="kh-pill"><strong><?= $n_hadir; ?></strong><span>Hadir</span></div>
                    <div class="kh-pill"><strong><?= $n_tugas; ?></strong><span>Tugas luar</span></div>
                    <div class="kh-pill"><strong><?= $n_cuti; ?></strong><span>Cuti</span></div>
                <?php } else { ?>
                    <div class="kh-pill"><strong><?= $total_tampil_r; ?></strong><span>Catatan</span></div>
                    <div class="kh-pill"><strong><?= $n_akan; ?></strong><span>Akan datang</span></div>
                <?php } ?>
            </div>
        </div>

        <?php if ($tab === 'hari' && $n_total > 0) {
            $pct_h = round($n_hadir / $n_total * 100);
            $pct_t = round($n_tugas / $n_total * 100);
            $pct_c = max(0, 100 - $pct_h - $pct_t);
        ?>
            <div class="kh-meter">
                <div class="kh-meter-bar" title="Komposisi kehadiran">
                    <i class="h" style="width:<?= $pct_h; ?>%"></i><i class="t" style="width:<?= $pct_t; ?>%"></i><i class="c" style="width:<?= $pct_c; ?>%"></i>
                </div>
                <div class="kh-meter-ket">
                    <span style="--d:#4ADE80">Hadir <?= $pct_h; ?>%</span>
                    <span style="--d:#38BDF8">Tugas luar <?= $pct_t; ?>%</span>
                    <span style="--d:#FBBF24">Cuti <?= $pct_c; ?>%</span>
                    <span style="--d:transparent;margin-left:auto"><?= htmlspecialchars($tgl_label); ?></span>
                </div>
            </div>
        <?php } ?>

    </div>

    <?php if ($teks_pesan !== '') { ?>
        <div class="kh-notif"><?= htmlspecialchars($teks_pesan); ?></div>
    <?php } ?>

    <div class="kh-tabs">
        <a href="index.php" class="kh-tab <?= $tab === 'hari' ? 'on' : ''; ?>">Status Pegawai</a>
        <a href="index.php?tab=riwayat" class="kh-tab <?= $tab === 'riwayat' ? 'on' : ''; ?>">Riwayat Catatan</a>
    </div>

<?php if ($tab === 'hari') { ?>

    <form method="GET" class="kh-filter">

        <div class="kh-fg">
            <label for="tanggal">Tanggal</label>
            <input type="date" name="tanggal" id="tanggal" class="kh-tgl-input" value="<?= htmlspecialchars($tgl); ?>" onchange="this.form.submit()">
        </div>

        <div class="kh-fg">
            <label>Status</label>
            <div class="kh-chips">
                <a class="kh-chip <?= $f_status === '' ? 'on' : ''; ?>" style="--c:#475569" href="<?= htmlspecialchars(urlKh(['status' => ''])); ?>">Semua<b><?= $n_total; ?></b></a>
                <a class="kh-chip <?= $f_status === 'hadir' ? 'on' : ''; ?>" style="--c:#16A34A" href="<?= htmlspecialchars(urlKh(['status' => 'hadir'])); ?>">Hadir<b><?= $n_hadir; ?></b></a>
                <a class="kh-chip <?= $f_status === 'tugas' ? 'on' : ''; ?>" style="--c:#0EA5E9" href="<?= htmlspecialchars(urlKh(['status' => 'tugas'])); ?>">Tugas Luar<b><?= $n_tugas; ?></b></a>
                <a class="kh-chip <?= $f_status === 'cuti' ? 'on' : ''; ?>" style="--c:#F59E0B" href="<?= htmlspecialchars(urlKh(['status' => 'cuti'])); ?>">Cuti<b><?= $n_cuti; ?></b></a>
            </div>
        </div>

        <input type="hidden" name="status" value="<?= htmlspecialchars($f_status); ?>">

        <div class="kh-fg cari">
            <label for="keyword">Pencarian</label>
            <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari nama, NIP, atau jabatan...">
        </div>

        <button type="submit" class="kh-go">Cari</button>

        <?php if ($f_status !== '' || $keyword !== '' || $tgl !== $hari_ini) { ?>
            <a href="index.php" class="kh-reset">Reset</a>
        <?php } ?>

    </form>

    <div class="kh-tabel-card">

        <div class="kh-tabel-top">
            <h3>Pegawai &middot; <?= htmlspecialchars($tgl_label); ?></h3>
            <span class="kh-count"><?= $total_tampil; ?> pegawai</span>
        </div>

        <div class="kh-scroll">
            <table class="kh-tabel">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Pegawai</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total_tampil > 0) {

                    $no = 1;
                    $grup_kini = null;

                    foreach ($roster as $p) {

                        if ($p['grup'] !== $grup_kini) {
                            $grup_kini = $p['grup'];
                            $jml_grup = 0;
                            foreach ($roster as $q) { if ($q['grup'] === $grup_kini) { $jml_grup++; } }
                ?>
                    <tr class="kh-grup <?= strcasecmp($grup_kini, 'Pimpinan') === 0 ? 'pimpinan' : ''; ?>">
                        <td colspan="5"><?= htmlspecialchars($grup_kini); ?> <span><?= $jml_grup; ?> pegawai</span></td>
                    </tr>
                <?php
                        }

                        $k = $p['kh'];
                        $kelas_baris = $p['status'] === 'cuti' ? 'kh-row-cuti' : ($p['status'] === 'tugas' ? 'kh-row-tidak' : '');
                ?>

                    <tr class="<?= $kelas_baris; ?><?= $p['pangkat'] <= 3 ? ' kh-pimpin' : ''; ?>">

                        <td><?= $no++; ?></td>

                        <td>
                            <div class="kh-org">
                                <div class="kh-ava <?= $p['status']; ?>"><?= htmlspecialchars(mb_strtoupper(mb_substr(preg_replace('/^(dr\.?|drs\.?|ir\.?|h\.?|hj\.?)\s+/iu', '', trim($p['nama'])), 0, 1))); ?></div>
                                <div>
                                    <div class="kh-nm"><?= htmlspecialchars($p['nama']); ?></div>
                                    <span class="kh-jb"><?= htmlspecialchars($p['jabatan']); ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <?php if ($p['status'] === 'hadir') { ?>
                                <span class="kh-badge hadir">Hadir</span>
                            <?php } elseif ($p['status'] === 'cuti') { ?>
                                <span class="kh-badge cuti">Cuti</span>
                            <?php } else { ?>
                                <span class="kh-badge">Tugas Luar</span>
                            <?php } ?>
                        </td>

                        <td class="kh-ket">
                            <?php if ($k) { ?>
                                <?php if ($k['jenis'] === 'Tugas Luar') { ?>
                                    <b><?= htmlspecialchars(($k['keperluan'] ?? '') !== '' ? $k['keperluan'] : 'Acara belum dicatat'); ?></b>
                                    <?php if (($k['tujuan'] ?? '') !== '') { ?>
                                        <span class="lok"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><?= htmlspecialchars($k['tujuan']); ?></span>
                                    <?php } ?>
                                <?php } else { ?>
                                    <b>Sedang cuti</b><br>
                                <?php } ?>
                                <span class="tgl"><?= khTanggal($k['tanggal_mulai']); ?><?= $k['tanggal_selesai'] !== $k['tanggal_mulai'] ? ' s.d. ' . khTanggal($k['tanggal_selesai']) : ''; ?></span>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>

                        <td>
                            <div class="kh-aksi">
                                <?php if ($k) { ?>

                                    <a href="edit.php?id=<?= (int) $k['id']; ?>" class="kh-act" title="Ubah">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                    </a>

                                    <?php if ($is_admin) { ?>
                                        <form method="POST" action="hapus.php" onsubmit="return confirm('Hapus catatan ketidakhadiran ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $k['id']; ?>">
                                            <button type="submit" class="kh-del" title="Hapus">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                            </button>
                                        </form>
                                    <?php } ?>

                                <?php } else { ?>

                                    <a href="tambah.php?pegawai_id=<?= (int) $p['id']; ?>&amp;tanggal=<?= urlencode($tgl); ?>" class="kh-catat">+ Catat tidak hadir</a>

                                <?php } ?>
                            </div>
                        </td>

                    </tr>

                <?php }

                } else { ?>

                    <tr>
                        <td colspan="5" class="kh-empty">
                            <?= ($f_status !== '' || $keyword !== '') ? 'Tidak ada pegawai dengan filter tersebut.' : 'Belum ada data pegawai aktif. Tambahkan pegawai di menu Data Pegawai.'; ?>
                        </td>
                    </tr>

                <?php } ?>

                </tbody>

            </table>
        </div>

    </div>

    <p class="kh-catatan">
        Pegawai berstatus <b>Hadir</b> bila tidak ada catatan tugas luar atau cuti pada tanggal tersebut. Catatan ini bukan pengganti absensi resmi.
    </p>

<?php } else { ?>

    <form method="GET" class="kh-filter">

        <input type="hidden" name="tab" value="riwayat">

        <div class="kh-fg">
            <label for="jenis">Jenis</label>
            <select name="jenis" id="jenis" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="Tugas Luar" <?= $f_jenis === 'Tugas Luar' ? 'selected' : ''; ?>>Tugas Luar</option>
                <option value="Cuti" <?= $f_jenis === 'Cuti' ? 'selected' : ''; ?>>Cuti</option>
            </select>
        </div>

        <div class="kh-fg">
            <label for="waktu">Waktu</label>
            <select name="waktu" id="waktu" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="berlangsung" <?= $f_waktu === 'berlangsung' ? 'selected' : ''; ?>>Sedang berlangsung</option>
                <option value="akan" <?= $f_waktu === 'akan' ? 'selected' : ''; ?>>Akan datang</option>
                <option value="selesai" <?= $f_waktu === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
            </select>
        </div>

        <div class="kh-fg cari">
            <label for="keyword">Pencarian</label>
            <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari nama, tujuan, atau acara...">
        </div>

        <button type="submit" class="kh-go">Tampilkan</button>

        <?php if ($f_jenis !== '' || $f_waktu !== '' || $keyword !== '') { ?>
            <a href="index.php?tab=riwayat" class="kh-reset">Reset</a>
        <?php } ?>

    </form>

    <div class="kh-tabel-card">

        <div class="kh-tabel-top">
            <h3>Riwayat Ketidakhadiran</h3>
            <span class="kh-count"><?= $total_tampil_r; ?> data</span>
        </div>

        <div class="kh-scroll">
            <table class="kh-tabel">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Pegawai</th>
                        <th>Jenis</th>
                        <th>Waktu</th>
                        <th>Tujuan / Acara</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total_tampil_r > 0) {

                    $no = 1;

                    while ($d = mysqli_fetch_assoc($hasil)) {

                        $st_k = khStatus($d['tanggal_mulai'], $d['tanggal_selesai'], $hari_ini);
                        $lama = khLamaHari($d['tanggal_mulai'], $d['tanggal_selesai']);
                ?>

                    <tr>
                        <td><?= $no++; ?></td>

                        <td class="kh-nama">
                            <?= htmlspecialchars($d['nama']); ?>
                            <small><?= htmlspecialchars($d['jabatan']); ?></small>
                        </td>

                        <td><span class="kh-badge <?= $d['jenis'] === 'Cuti' ? 'cuti' : ''; ?>"><?= htmlspecialchars($d['jenis']); ?></span></td>

                        <td style="white-space:nowrap">
                            <?= khTanggal($d['tanggal_mulai']); ?><?= $d['tanggal_selesai'] !== $d['tanggal_mulai'] ? ' s.d. ' . khTanggal($d['tanggal_selesai']) : ''; ?>
                            <small style="display:block;color:var(--color-text-muted)"><?= $lama; ?> hari</small>
                        </td>

                        <td class="kh-ket">
                            <?php if ($d['jenis'] === 'Tugas Luar') { ?>
                                <b style="color:var(--color-text)"><?= htmlspecialchars($d['keperluan'] ?? ''); ?></b>
                                <?= ($d['keperluan'] ?? '') !== '' ? '<br>' : ''; ?><?= htmlspecialchars($d['tujuan'] ?? ''); ?>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>

                        <td><span class="kh-st <?= $st_k; ?>"><?= htmlspecialchars(khLabelStatus($st_k)); ?></span></td>

                        <td>
                            <div class="kh-aksi">
                                <a href="edit.php?id=<?= (int) $d['id']; ?>" class="kh-act" title="Ubah">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                </a>

                                <?php if ($is_admin) { ?>
                                    <form method="POST" action="hapus.php" onsubmit="return confirm('Hapus data ketidakhadiran ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="id" value="<?= (int) $d['id']; ?>">
                                        <button type="submit" class="kh-del" title="Hapus">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                                        </button>
                                    </form>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>

                <?php }

                } else { ?>

                    <tr>
                        <td colspan="7" class="kh-empty">Belum ada catatan ketidakhadiran.</td>
                    </tr>

                <?php } ?>

                </tbody>

            </table>
        </div>

    </div>

<?php } ?>

</div>
</div>

<?php
include "../layouts/footer.php";