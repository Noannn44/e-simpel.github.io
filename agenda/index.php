<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

adminDanOperator();

require_once "../config/database.php";

/** @var mysqli $koneksi */

date_default_timezone_set('Asia/Makassar');

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";

$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$nama_hari = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

$sekarang  = new DateTime('today', new DateTimeZone('Asia/Makassar'));
$hari_ini  = $sekarang->format('Y-m-d');

function tanggalValidAgenda($v)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $v);

    return ($d && $d->format('Y-m-d') === $v) ? $d : null;
}

$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) $sekarang->format('Y');
$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) $sekarang->format('n');

$dipilih = null;

if (isset($_GET['tanggal'])) {

    $obj = tanggalValidAgenda((string) $_GET['tanggal']);

    if ($obj) {
        $dipilih = $obj->format('Y-m-d');
        $tahun   = (int) $obj->format('Y');
        $bulan   = (int) $obj->format('n');
    }
}

if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int) $sekarang->format('Y');
}

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int) $sekarang->format('n');
}

$awal_bulan  = new DateTime(sprintf('%04d-%02d-01', $tahun, $bulan));
$akhir_bulan = (clone $awal_bulan)->modify('last day of this month');
$jumlah_hari = (int) $akhir_bulan->format('j');

$bulan_lalu = (clone $awal_bulan)->modify('-1 month');
$bulan_depan = (clone $awal_bulan)->modify('+1 month');

/* =========================================================
   DATA: AGENDA BULAN YANG DITAMPILKAN
========================================================= */

$kolom = "id, nomor_surat, pengirim, perihal, tanggal_acara, tempat_acara, agenda_acara";

$sa = $awal_bulan->format('Y-m-d');
$sb = $akhir_bulan->format('Y-m-d');

$q1 = mysqli_prepare($koneksi, "
    SELECT $kolom FROM surat_masuk
    WHERE jenis_surat = 'Undangan'
      AND tanggal_acara IS NOT NULL
      AND tanggal_acara BETWEEN ? AND ?
    ORDER BY tanggal_acara ASC, id ASC
");
mysqli_stmt_bind_param($q1, "ss", $sa, $sb);
mysqli_stmt_execute($q1);
$r1 = mysqli_stmt_get_result($q1);

$agenda_bulan = [];
$per_tanggal  = [];

while ($r = mysqli_fetch_assoc($r1)) {
    $agenda_bulan[] = $r;
    $per_tanggal[$r['tanggal_acara']][] = $r;
}

/* =========================================================
   DATA: AKAN DATANG (mulai hari ini)
========================================================= */

$q2 = mysqli_prepare($koneksi, "
    SELECT $kolom FROM surat_masuk
    WHERE jenis_surat = 'Undangan'
      AND tanggal_acara IS NOT NULL
      AND tanggal_acara >= ?
    ORDER BY tanggal_acara ASC, id ASC
    LIMIT 200
");
mysqli_stmt_bind_param($q2, "s", $hari_ini);
mysqli_stmt_execute($q2);
$r2 = mysqli_stmt_get_result($q2);

$akan_datang = [];

while ($r = mysqli_fetch_assoc($r2)) {
    $akan_datang[] = $r;
}

/* =========================================================
   HELPER
========================================================= */

/* selisih hari dari hari ini (negatif = sudah lewat) */
function selisihHari($tanggal, $hari_ini)
{
    $a = new DateTime($hari_ini);
    $b = new DateTime($tanggal);

    return (int) $a->diff($b)->format('%r%a');
}

function tanggalPanjang($tanggal, $nama_hari, $nama_bulan)
{
    $t = new DateTime($tanggal);

    return $nama_hari[(int) $t->format('N')] . ', ' . $t->format('j') . ' ' . $nama_bulan[(int) $t->format('n')] . ' ' . $t->format('Y');
}

function labelSelisih($d)
{
    if ($d === 0) return ['Hari ini', 'hari'];
    if ($d === 1) return ['Besok', 'besok'];
    if ($d === 2) return ['Lusa', 'lusa'];
    if ($d > 2)   return ['H-' . $d, 'nanti'];

    return ['Selesai', 'lewat'];
}

function potong($teks, $maks)
{
    $teks = trim((string) $teks);

    return mb_strlen($teks) > $maks ? mb_substr($teks, 0, $maks - 1) . '…' : $teks;
}

function urlAgenda($t, $b, $tanggal = null)
{
    $q = ['tahun' => $t, 'bulan' => $b];

    if ($tanggal !== null) {
        $q['tanggal'] = $tanggal;
    }

    return 'index.php?' . http_build_query($q);
}

/* ---------- hitungan ringkas ---------- */

$jml_hari_ini = 0;
$jml_tujuh    = 0;
$jml_sisa_bln = 0;

$bulan_berjalan = $sekarang->format('Y-m');

foreach ($akan_datang as $a) {

    $d = selisihHari($a['tanggal_acara'], $hari_ini);

    if ($d === 0) {
        $jml_hari_ini++;
    }

    if ($d >= 0 && $d <= 6) {
        $jml_tujuh++;
    }

    if (substr($a['tanggal_acara'], 0, 7) === $bulan_berjalan) {
        $jml_sisa_bln++;
    }
}

/* ---------- peringatan: hari ini, besok, lusa ---------- */

$peringatan = [];

foreach ($akan_datang as $a) {

    $d = selisihHari($a['tanggal_acara'], $hari_ini);

    if ($d >= 0 && $d <= 2) {
        $peringatan[] = $a + ['_d' => $d];
    }
}

/* ---------- daftar panel kanan ---------- */

$judul_panel = 'Akan datang';
$panel       = [];

if ($dipilih !== null) {

    $judul_panel = 'Agenda ' . tanggalPanjang($dipilih, $nama_hari, $nama_bulan);
    $panel       = $per_tanggal[$dipilih] ?? [];

} else {

    $panel = array_slice($akan_datang, 0, 12);
}

/* ---------- peta titik pada kalender ---------- */

$total_agenda_bulan = count($agenda_bulan);

$hari_pertama_kolom = (int) $awal_bulan->format('N'); // 1 = Senin

?>

<style>

.ag-wrap {
    --ac: #6366F1;
    --ac-dark: #4338CA;
    --ac-soft: #E0E7FF;
}

/* ===== HERO ===== */

.ag-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #14162b 0%, #1f2150 58%, #3730a3 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.ag-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -80px;
    top: -140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99,102,241,.55), transparent 70%);
    pointer-events: none;
}

.ag-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.045) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.ag-hero > * { position: relative; z-index: 1; }

.ag-eyebrow {
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

.ag-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #A5B4FC;
    box-shadow: 0 0 0 4px rgba(165,180,252,.35);
}

.ag-wrap .ag-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.ag-wrap .ag-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.68) !important;
}

.ag-hero-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.ag-pill {
    min-width: 98px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.ag-pill:hover { transform: translateY(-3px); background: rgba(255,255,255,.14); }

.ag-pill strong { display: block; font-size: 21px; font-weight: 700; line-height: 1.1; color: #fff; }

.ag-pill span {
    font-size: 10.5px;
    color: rgba(255,255,255,.62);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.ag-btn-tambah {
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
    box-shadow: 0 10px 24px rgba(99,102,241,.45);
    transition: transform .25s var(--ease-smooth, ease), background .25s;
}

.ag-btn-tambah:hover { background: #818CF8; color: #fff; transform: translateY(-3px); }

/* ===== PERINGATAN ===== */

.ag-alert-wrap {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.ag-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid transparent;
    text-decoration: none;
    transition: transform .25s var(--ease-smooth, ease), box-shadow .25s var(--ease-smooth, ease);
}

.ag-alert:hover { transform: translateY(-3px); box-shadow: 0 10px 22px rgba(20,20,40,.08); }

.ag-alert .ic {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ag-alert small {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
}

.ag-alert b { display: block; margin-top: 2px; font-size: 13px; line-height: 1.35; }
.ag-alert span.t { display: block; margin-top: 3px; font-size: 11.5px; opacity: .8; }

.ag-alert.hari  { background: #FEE2E2; border-color: #FECACA; color: #991B1B; }
.ag-alert.hari .ic { background: #FCA5A5; }
.ag-alert.besok { background: #FEF3C7; border-color: #FDE68A; color: #92400E; }
.ag-alert.besok .ic { background: #FCD34D; }
.ag-alert.lusa  { background: #E0E7FF; border-color: #C7D2FE; color: #3730A3; }
.ag-alert.lusa .ic { background: #A5B4FC; }

/* ===== GRID ===== */

.ag-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    gap: 18px;
    margin-bottom: 18px;
    align-items: start;
}

.ag-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    padding: 20px 22px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

.ag-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.ag-card-head h3 { margin: 0; font-size: 14px; font-weight: 700; color: #1d1d27; }
.ag-card-head small { font-size: 11.5px; color: #9a9aa8; }

/* ===== KALENDER ===== */

.ag-nav { display: flex; align-items: center; gap: 8px; }

.ag-nav a.panah {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #f4f4f7;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #555;
    text-decoration: none;
    transition: all .2s var(--ease-smooth, ease);
}

.ag-nav a.panah:hover { background: var(--ac-soft); color: var(--ac-dark); }

.ag-nav a.hari-ini {
    height: 34px;
    padding: 0 13px;
    border-radius: 10px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: all .2s var(--ease-smooth, ease);
}

.ag-nav a.hari-ini:hover { background: var(--ac); color: #fff; }

.ag-cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }

.ag-cal .nm {
    padding: 6px 0 8px;
    text-align: center;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
    color: #9a9aa8;
}

.ag-cal .nm.mgg { color: #ef4444; }

.ag-cal .kosong { min-height: 56px; }

.ag-hr {
    position: relative;
    min-height: 56px;
    padding: 7px 4px 6px;
    border-radius: 13px;
    border: 1.5px solid transparent;
    background: #fafafc;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    color: #2a2a35;
    transition: background .2s var(--ease-smooth, ease), transform .2s var(--ease-smooth, ease), border-color .2s;
}

.ag-hr:hover { background: var(--ac-soft); transform: translateY(-2px); }

.ag-hr .tgl { font-size: 13px; font-weight: 600; }
.ag-hr.mgg .tgl { color: #ef4444; }

.ag-hr.sekarang { border-color: var(--ac); background: #fff; }
.ag-hr.sekarang .tgl { color: var(--ac-dark); font-weight: 800; }

.ag-hr.dipilih { background: var(--ac); border-color: var(--ac); color: #fff; }
.ag-hr.dipilih .tgl { color: #fff; }

.ag-dots { display: flex; gap: 3px; min-height: 7px; }

.ag-dots i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #EF4444;
}

.ag-hr.ada {
    background: #FEF2F2;
    border-color: #FECACA;
}

.ag-hr.ada .tgl { color: #B91C1C; font-weight: 800; }

.ag-hr.ada:hover { background: #FEE2E2; }
.ag-hr.ada.sekarang { border-color: var(--ac); }

.ag-hr.ada.lewat { background: #f4f4f7; border-color: #e5e7eb; }
.ag-hr.ada.lewat .tgl { color: #8a8f98; font-weight: 600; }

.ag-hr.lewat .ag-dots i { background: #c4c4d0; }
.ag-hr.dipilih .ag-dots i { background: #fff; }
.ag-hr.ada.dipilih { background: var(--ac); border-color: var(--ac); }
.ag-hr.ada.dipilih .tgl { color: #fff; }

.ag-dots em {
    font-style: normal;
    font-size: 9.5px;
    font-weight: 800;
    line-height: 6px;
    color: #B91C1C;
}

.ag-hr.ada.lewat .ag-dots em { color: #8a8f98; }

.ag-hr.dipilih .ag-dots em { color: #fff; }

.ag-legend {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 14px;
    font-size: 11.5px;
    color: #8a8a98;
}

.ag-legend span { display: inline-flex; align-items: center; gap: 6px; }
.ag-legend i { width: 8px; height: 8px; border-radius: 50%; background: #EF4444; display: inline-block; }
.ag-legend i.abu { background: #c4c4d0; }
.ag-legend i.cincin { background: #fff; border: 2px solid var(--ac); width: 10px; height: 10px; }

/* ===== DAFTAR AGENDA ===== */

.ag-group-label {
    margin: 18px 0 10px;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .7px;
    text-transform: uppercase;
    color: #9a9aa8;
}

.ag-group-label:first-of-type { margin-top: 0; }

.ag-item {
    display: flex;
    gap: 14px;
    padding: 13px;
    margin-bottom: 10px;
    border-radius: 16px;
    background: #fafafc;
    border: 1px solid transparent;
    text-decoration: none;
    color: inherit;
    transition: background .22s var(--ease-smooth, ease), transform .22s var(--ease-smooth, ease), border-color .22s;
}

.ag-item:last-child { margin-bottom: 0; }

.ag-item:hover { background: #fff; border-color: var(--ac-soft); transform: translateY(-3px); box-shadow: 0 10px 22px rgba(20,20,40,.07); color: inherit; }

.ag-item.lewat { opacity: .62; }

.ag-date {
    width: 52px;
    flex-shrink: 0;
    padding: 8px 0;
    border-radius: 13px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    text-align: center;
    line-height: 1.1;
}

.ag-date b { display: block; font-size: 20px; font-weight: 800; }
.ag-date span { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

.ag-item.lewat .ag-date { background: #eceef2; color: #8a8f98; }

.ag-body { min-width: 0; flex: 1; }

.ag-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.ag-body h4 {
    margin: 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1d1d27;
    line-height: 1.35;
}

.ag-chip {
    flex-shrink: 0;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    white-space: nowrap;
}

.ag-chip.hari  { background: #FEE2E2; color: #B91C1C; }
.ag-chip.besok { background: #FEF3C7; color: #B45309; }
.ag-chip.lusa  { background: #E0E7FF; color: #3730A3; }
.ag-chip.nanti { background: #f1f1f5; color: #666; }
.ag-chip.lewat { background: #eceef2; color: #8a8f98; }

.ag-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    margin-top: 6px;
    font-size: 12px;
    color: #777;
}

.ag-meta span { display: inline-flex; align-items: center; gap: 5px; }

.ag-desc {
    margin: 7px 0 0;
    font-size: 12px;
    color: #666;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.ag-empty {
    text-align: center;
    padding: 38px 16px;
    color: #9a9aa8;
}

.ag-empty .ic {
    width: 62px;
    height: 62px;
    margin: 0 auto 12px;
    border-radius: 20px;
    background: var(--ac-soft);
    display: flex;
    align-items: center;
    justify-content: center;
}

.ag-empty h4 { margin: 0 0 4px; font-size: 14px; color: #333; }
.ag-empty p { margin: 0; font-size: 12.5px; }

.ag-link-semua {
    display: inline-flex;
    align-items: center;
    height: 30px;
    padding: 0 12px;
    border-radius: 9px;
    background: var(--ac-soft);
    color: var(--ac-dark);
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none;
}

.ag-link-semua:hover { background: var(--ac); color: #fff; }

/* ===== RESPONSIVE ===== */

@media (max-width: 991px) {
    .ag-grid { grid-template-columns: 1fr; }
}

@media (max-width: 700px) {
    .ag-hero { padding: 24px 20px; }
    .ag-wrap .ag-hero h1 { font-size: 23px; }
    .ag-card { padding: 18px 14px; }
    .ag-cal { gap: 4px; }
    .ag-hr { min-height: 48px; padding: 6px 2px 5px; }
    .ag-hr .tgl { font-size: 12px; }
    .ag-top { flex-direction: column; gap: 6px; }
}

</style>

<div class="content">
<div class="container-fluid ag-wrap">

    <!-- ===== HERO ===== -->

    <div class="ag-hero">

        <div>
            <div class="ag-eyebrow"><i></i> ADMINISTRASI</div>
            <h1>Agenda Kegiatan</h1>
            <p>Jadwal undangan yang diterima instansi, dikumpulkan dari surat masuk.</p>
        </div>

        <div class="ag-hero-right">

            <div class="ag-pill"><strong><?= $jml_hari_ini; ?></strong><span>Hari ini</span></div>
            <div class="ag-pill"><strong><?= $jml_tujuh; ?></strong><span>7 hari ke depan</span></div>
            <div class="ag-pill"><strong><?= $jml_sisa_bln; ?></strong><span>Sisa bulan ini</span></div>

            <a href="../surat_masuk/tambah.php" class="ag-btn-tambah">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Catat Undangan
            </a>

        </div>

    </div>

    <!-- ===== PERINGATAN: HARI INI, BESOK, LUSA ===== -->

    <?php if ($peringatan) { ?>

        <div class="ag-alert-wrap">

            <?php foreach ($peringatan as $p) {

                [$lbl, $kls] = labelSelisih($p['_d']);
            ?>

                <a href="../surat_masuk/detail.php?id=<?= (int) $p['id']; ?>" class="ag-alert <?= $kls; ?>">
                    <span class="ic">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    </span>
                    <span>
                        <small><?= $lbl; ?></small>
                        <b><?= htmlspecialchars(potong($p['perihal'], 90)); ?></b>
                        <span class="t"><?= htmlspecialchars(($p['tempat_acara'] ?? '') !== '' ? $p['tempat_acara'] : 'Tempat belum dicatat'); ?></span>
                    </span>
                </a>

            <?php } ?>

        </div>

    <?php } ?>

    <!-- ===== KALENDER + DAFTAR ===== -->

    <div class="ag-grid">

        <!-- KALENDER -->

        <div class="ag-card">

            <div class="ag-card-head">

                <h3><?= $nama_bulan[$bulan]; ?> <?= $tahun; ?></h3>

                <div class="ag-nav">

                    <a class="panah" href="<?= htmlspecialchars(urlAgenda((int) $bulan_lalu->format('Y'), (int) $bulan_lalu->format('n'))); ?>" title="Bulan sebelumnya">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>

                    <a class="hari-ini" href="index.php">Hari ini</a>

                    <a class="panah" href="<?= htmlspecialchars(urlAgenda((int) $bulan_depan->format('Y'), (int) $bulan_depan->format('n'))); ?>" title="Bulan berikutnya">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    </a>

                </div>

            </div>

            <div class="ag-cal">

                <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $i => $h) { ?>
                    <div class="nm<?= $i === 6 ? ' mgg' : ''; ?>"><?= $h; ?></div>
                <?php } ?>

                <?php for ($k = 1; $k < $hari_pertama_kolom; $k++) { ?>
                    <div class="kosong"></div>
                <?php } ?>

                <?php for ($tgl = 1; $tgl <= $jumlah_hari; $tgl++) {

                    $kunci  = sprintf('%04d-%02d-%02d', $tahun, $bulan, $tgl);
                    $acara  = $per_tanggal[$kunci] ?? [];
                    $jml    = count($acara);
                    $nomor_hari = ($hari_pertama_kolom - 1 + $tgl - 1) % 7; // 0 = Senin

                    $kelas  = 'ag-hr';
                    $kelas .= ($nomor_hari === 6) ? ' mgg' : '';
                    $kelas .= ($jml > 0) ? ' ada' : '';
                    $kelas .= ($kunci === $hari_ini) ? ' sekarang' : '';
                    $kelas .= ($kunci === $dipilih) ? ' dipilih' : '';
                    $kelas .= ($kunci < $hari_ini) ? ' lewat' : '';
                ?>

                    <a href="<?= htmlspecialchars(urlAgenda($tahun, $bulan, $kunci)); ?>"
                       class="<?= $kelas; ?>"
                       title="<?= $jml > 0 ? $jml . ' agenda' : 'Tidak ada agenda'; ?>">
                        <span class="tgl"><?= $tgl; ?></span>
                        <span class="ag-dots">
                            <?php if ($jml > 0 && $jml <= 3) {
                                for ($d = 0; $d < $jml; $d++) { echo '<i></i>'; }
                            } elseif ($jml > 3) {
                                echo '<i></i><em>' . $jml . '</em>';
                            } ?>
                        </span>
                    </a>

                <?php } ?>

            </div>

            <div class="ag-legend">
                <span><i class="cincin"></i> Hari ini</span>
                <span><i></i> Ada agenda (tanggal diberi warna merah)</span>
                <span><i class="abu"></i> Sudah lewat</span>
            </div>

        </div>

        <!-- DAFTAR KANAN -->

        <div class="ag-card">

            <div class="ag-card-head">

                <h3><?= htmlspecialchars($judul_panel); ?></h3>

                <?php if ($dipilih !== null) { ?>
                    <a class="ag-link-semua" href="index.php">Lihat akan datang</a>
                <?php } else { ?>
                    <small><?= count($akan_datang); ?> agenda mendatang</small>
                <?php } ?>

            </div>

            <?php if ($panel) {

                $label_lama = null;

                foreach ($panel as $a) {

                    $d = selisihHari($a['tanggal_acara'], $hari_ini);
                    [$lbl, $kls] = labelSelisih($d);

                    if ($dipilih === null) {

                        if ($d === 0) { $grup = 'Hari ini'; }
                        elseif ($d === 1) { $grup = 'Besok'; }
                        elseif ($d <= 6) { $grup = '7 hari ke depan'; }
                        elseif ($d <= 29) { $grup = '30 hari ke depan'; }
                        else { $grup = 'Selanjutnya'; }

                        if ($grup !== $label_lama) {
                            echo '<div class="ag-group-label">' . htmlspecialchars($grup) . '</div>';
                            $label_lama = $grup;
                        }
                    }

                    $t = new DateTime($a['tanggal_acara']);
            ?>

                <a href="../surat_masuk/detail.php?id=<?= (int) $a['id']; ?>" class="ag-item<?= $d < 0 ? ' lewat' : ''; ?>">

                    <div class="ag-date">
                        <b><?= $t->format('j'); ?></b>
                        <span><?= substr($nama_bulan[(int) $t->format('n')], 0, 3); ?></span>
                    </div>

                    <div class="ag-body">

                        <div class="ag-top">
                            <h4><?= htmlspecialchars($a['perihal']); ?></h4>
                            <span class="ag-chip <?= $kls; ?>"><?= $lbl; ?></span>
                        </div>

                        <div class="ag-meta">
                            <span>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?= htmlspecialchars(($a['tempat_acara'] ?? '') !== '' ? $a['tempat_acara'] : '-'); ?>
                            </span>
                            <span>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9 6 9-6"/><rect x="3" y="5" width="18" height="14" rx="2"/></svg>
                                <?= htmlspecialchars(potong($a['pengirim'], 40)); ?>
                            </span>
                        </div>

                        <?php if (!empty($a['agenda_acara'])) { ?>
                            <p class="ag-desc"><?= htmlspecialchars($a['agenda_acara']); ?></p>
                        <?php } ?>

                    </div>

                </a>

            <?php }

            } else { ?>

                <div class="ag-empty">
                    <div class="ic">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4338CA" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </div>
                    <h4><?= $dipilih !== null ? 'Tidak ada agenda pada tanggal ini' : 'Belum ada agenda mendatang'; ?></h4>
                    <p>Agenda muncul otomatis dari surat masuk berjenis Undangan.</p>
                </div>

            <?php } ?>

        </div>

    </div>

    <!-- ===== SEMUA AGENDA PADA BULAN INI ===== -->

    <div class="ag-card">

        <div class="ag-card-head">
            <h3>Seluruh agenda &middot; <?= $nama_bulan[$bulan]; ?> <?= $tahun; ?></h3>
            <small><?= $total_agenda_bulan; ?> agenda</small>
        </div>

        <?php if ($agenda_bulan) {

            foreach ($agenda_bulan as $a) {

                $d = selisihHari($a['tanggal_acara'], $hari_ini);
                [$lbl, $kls] = labelSelisih($d);
                $t = new DateTime($a['tanggal_acara']);
        ?>

            <a href="../surat_masuk/detail.php?id=<?= (int) $a['id']; ?>" class="ag-item<?= $d < 0 ? ' lewat' : ''; ?>">

                <div class="ag-date">
                    <b><?= $t->format('j'); ?></b>
                    <span><?= substr($nama_bulan[(int) $t->format('n')], 0, 3); ?></span>
                </div>

                <div class="ag-body">

                    <div class="ag-top">
                        <h4><?= htmlspecialchars($a['perihal']); ?></h4>
                        <span class="ag-chip <?= $kls; ?>"><?= $lbl; ?></span>
                    </div>

                    <div class="ag-meta">
                        <span><?= htmlspecialchars($nama_hari[(int) $t->format('N')]); ?></span>
                        <span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= htmlspecialchars(($a['tempat_acara'] ?? '') !== '' ? $a['tempat_acara'] : '-'); ?>
                        </span>
                        <span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9 6 9-6"/><rect x="3" y="5" width="18" height="14" rx="2"/></svg>
                            <?= htmlspecialchars(potong($a['pengirim'], 50)); ?>
                        </span>
                        <span>No. <?= htmlspecialchars($a['nomor_surat']); ?></span>
                    </div>

                    <?php if (!empty($a['agenda_acara'])) { ?>
                        <p class="ag-desc"><?= htmlspecialchars($a['agenda_acara']); ?></p>
                    <?php } ?>

                </div>

            </a>

        <?php }

        } else { ?>

            <div class="ag-empty">
                <div class="ic">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4338CA" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </div>
                <h4>Tidak ada agenda pada <?= $nama_bulan[$bulan]; ?> <?= $tahun; ?></h4>
                <p>Catat surat masuk berjenis Undangan, lalu agendanya muncul di sini.</p>
            </div>

        <?php } ?>

    </div>

</div>
</div>

<?php include "../layouts/footer.php"; ?>