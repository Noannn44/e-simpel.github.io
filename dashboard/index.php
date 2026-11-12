<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

date_default_timezone_set('Asia/Makassar');

require_once "../config/ketidakhadiran.php";
siapkanTabelKetidakhadiran($koneksi);

/* pegawai yang tidak hadir hari ini (tugas luar / cuti) */
$kh_hari_ini = [];
try {
    $kh_tgl = date('Y-m-d');
    $kh_st = mysqli_prepare($koneksi, "SELECT k.jenis, k.tanggal_selesai, k.tujuan, k.keperluan, p.nama
        FROM ketidakhadiran k JOIN pegawai p ON p.id = k.pegawai_id
        WHERE ? BETWEEN k.tanggal_mulai AND k.tanggal_selesai
        ORDER BY k.jenis ASC, p.nama ASC");
    mysqli_stmt_bind_param($kh_st, "s", $kh_tgl);
    mysqli_stmt_execute($kh_st);
    $kh_res = mysqli_stmt_get_result($kh_st);
    while ($kh_res && ($kh_r = mysqli_fetch_assoc($kh_res))) { $kh_hari_ini[] = $kh_r; }
} catch (Throwable $e) { $kh_hari_ini = []; }

$total_pegawai = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM pegawai"));
$total_masuk   = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM surat_masuk"));
$total_keluar  = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM surat_keluar"));
$total_disposisi = mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM disposisi_surat"));

$tabel_tamu_ada = mysqli_query($koneksi, "SHOW TABLES LIKE 'tamu'");
$tamu_ada = $tabel_tamu_ada && mysqli_num_rows($tabel_tamu_ada) > 0;
$total_tamu = $tamu_ada ? mysqli_num_rows(mysqli_query($koneksi, "SELECT * FROM tamu")) : 0;


/* =========================================================
   PERSENTASE PERUBAHAN BULAN INI VS BULAN LALU
========================================================= */

function hitungPerubahan($koneksi, $tabel, $kolom_tanggal = 'tanggal_surat')
{
    $bulan_ini  = (int) date('n');
    $tahun_ini  = (int) date('Y');

    $bulan_lalu = $bulan_ini - 1;
    $tahun_lalu = $tahun_ini;

    if ($bulan_lalu < 1) {
        $bulan_lalu = 12;
        $tahun_lalu = $tahun_ini - 1;
    }

    $q1 = mysqli_query($koneksi, "SELECT COUNT(*) AS jumlah FROM $tabel
        WHERE MONTH($kolom_tanggal) = $bulan_ini AND YEAR($kolom_tanggal) = $tahun_ini");
    $bulan_ini_total = $q1 ? (int) mysqli_fetch_assoc($q1)['jumlah'] : 0;

    $q2 = mysqli_query($koneksi, "SELECT COUNT(*) AS jumlah FROM $tabel
        WHERE MONTH($kolom_tanggal) = $bulan_lalu AND YEAR($kolom_tanggal) = $tahun_lalu");
    $bulan_lalu_total = $q2 ? (int) mysqli_fetch_assoc($q2)['jumlah'] : 0;

    if ($bulan_lalu_total > 0) {
        $persen = round((($bulan_ini_total - $bulan_lalu_total) / $bulan_lalu_total) * 100);
    } elseif ($bulan_ini_total > 0) {
        $persen = 100;
    } else {
        $persen = 0;
    }

    return ['total' => $bulan_ini_total, 'persen' => $persen, 'naik' => $bulan_ini_total >= $bulan_lalu_total];
}

$perubahan_masuk  = hitungPerubahan($koneksi, 'surat_masuk');
$perubahan_keluar = hitungPerubahan($koneksi, 'surat_keluar');
$perubahan_disposisi = ['total' => $total_disposisi, 'persen' => 0, 'naik' => true];
$perubahan_tamu = $tamu_ada
    ? hitungPerubahan($koneksi, 'tamu', 'tanggal_kunjungan')
    : ['total' => 0, 'persen' => 0, 'naik' => true];


/* =========================================================
   GRAFIK BULANAN (TAHUN BERJALAN): TAMU, SURAT MASUK, SURAT KELUAR
========================================================= */

$tahun_berjalan = (int) date('Y');

function grafikBulanan($koneksi, $tabel, $kolom, $tahun)
{
    $hasil = array_fill(1, 12, 0);

    $q = mysqli_query($koneksi, "SELECT MONTH($kolom) AS bln, COUNT(*) AS jumlah
        FROM $tabel WHERE YEAR($kolom) = $tahun GROUP BY bln");

    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $hasil[(int) $row['bln']] = (int) $row['jumlah'];
        }
    }

    return array_values($hasil);
}

$grafik_semua = [
    'tamu'      => $tamu_ada ? grafikBulanan($koneksi, 'tamu', 'tanggal_kunjungan', $tahun_berjalan) : array_fill(0, 12, 0),
    'masuk'     => grafikBulanan($koneksi, 'surat_masuk', 'tanggal_diterima', $tahun_berjalan),
    'keluar'    => grafikBulanan($koneksi, 'surat_keluar', 'tanggal_surat', $tahun_berjalan),
];


/* =========================================================
   SURAT TERBARU (5 TERAKHIR)
========================================================= */

$surat_terbaru = mysqli_query($koneksi, "SELECT nomor_surat, perihal, pengirim, tanggal_diterima
    FROM surat_masuk ORDER BY tanggal_diterima DESC, id DESC LIMIT 5");


/* =========================================================
   TANGGAL & WAKTU INDONESIA
========================================================= */

$hari_map = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$bulan_map = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$tanggal_lengkap = $hari_map[date('l')] . ', ' . date('j') . ' ' . $bulan_map[(int) date('n')] . ' ' . date('Y');

/* =========================================================
   AGENDA 7 HARI KE DEPAN (dari surat masuk jenis Undangan)
========================================================= */

$agenda_minggu = [];

try {

    $zona_agenda = new DateTimeZone('Asia/Makassar');
    $ag_mulai    = (new DateTime('today', $zona_agenda));
    $ag_akhir    = (clone $ag_mulai)->modify('+6 day');

    $ag_a = $ag_mulai->format('Y-m-d');
    $ag_b = $ag_akhir->format('Y-m-d');

    $ag_stmt = mysqli_prepare($koneksi, "SELECT id, perihal, tanggal_acara, tempat_acara
        FROM surat_masuk
        WHERE jenis_surat = 'Undangan' AND tanggal_acara IS NOT NULL
          AND tanggal_acara BETWEEN ? AND ?
        ORDER BY tanggal_acara ASC, id ASC LIMIT 8");

    mysqli_stmt_bind_param($ag_stmt, "ss", $ag_a, $ag_b);
    mysqli_stmt_execute($ag_stmt);
    $ag_res = mysqli_stmt_get_result($ag_stmt);

    while ($ag_row = mysqli_fetch_assoc($ag_res)) {
        $agenda_minggu[] = $ag_row;
    }

} catch (Throwable $e) {
    $agenda_minggu = [];
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.dash-wrap { padding: 26px 28px 40px; }

.dash-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 26px;
    flex-wrap: wrap;
    gap: 14px;
}

.dash-title h1 {
    font-size: 26px;
    font-weight: 800;
    color: var(--color-text);
    margin-bottom: 6px;
}

.dash-title .underline {
    width: 46px;
    height: 4px;
    border-radius: 4px;
    background: var(--color-primary);
    margin-bottom: 10px;
}

.dash-title p {
    font-size: 13.5px;
    color: var(--color-text-muted);
}

.dash-title p strong { color: var(--color-text); }

.dash-date {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    padding: 9px 16px;
    border-radius: 999px;
    font-size: 12.5px;
    color: var(--color-text-muted);
    box-shadow: var(--shadow-card);
    white-space: nowrap;
}

/* ===== STAT CARDS ===== */

.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}

@media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 600px) { .stat-grid { grid-template-columns: minmax(0, 1fr); } }

.stat-card {
    min-width: 0;
    background: var(--soft);
    border-radius: var(--radius-lg);
    padding: 20px 22px;
    box-shadow: var(--shadow-card);
    transition: transform .25s var(--ease-smooth), box-shadow .25s var(--ease-smooth);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 30px rgba(20,20,40,.10);
}

.stat-card.c-blue   { --soft: var(--card-blue-soft);   --main: var(--card-blue); }
.stat-card.c-orange { --soft: var(--card-orange-soft); --main: var(--card-orange); }
.stat-card.c-purple { --soft: var(--card-purple-soft); --main: var(--card-purple); }
.stat-card.c-green  { --soft: var(--card-green-soft);  --main: var(--card-green); }

.stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: var(--main);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    box-shadow: 0 8px 16px rgba(0,0,0,.12);
}

.stat-icon svg { width: 22px; height: 22px; stroke: #fff; stroke-width: 2; fill: none; }

.stat-label {
    font-size: 13px;
    color: var(--color-text-muted);
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-number {
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text);
    margin-bottom: 8px;
}

.stat-change {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 14px;
}

.stat-change.up { color: #16a34a; }
.stat-change.down { color: var(--color-danger); }
.stat-change span { color: var(--color-text-muted); font-weight: 400; }

.stat-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 12px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--main);
    text-decoration: none;
    transition: gap .2s var(--ease-smooth);
}

.stat-link:hover { gap: 9px; }

/* ===== MAIN GRID (chart + promo) ===== */

.dash-row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 18px;
    margin-bottom: 18px;
}

@media (max-width: 1000px) { .dash-row { grid-template-columns: minmax(0, 1fr); } }

.panel {
    min-width: 0;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: 22px;
    box-shadow: var(--shadow-card);
    border: 1px solid var(--color-border);
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    gap: 12px;
    flex-wrap: wrap;
}

.panel-head h3 {
    font-size: 15.5px;
    font-weight: 700;
    color: var(--color-text);
    display: flex;
    align-items: center;
    gap: 9px;
}

.panel-head h3 .dot-icon {
    width: 30px; height: 30px;
    border-radius: 9px;
    background: var(--color-primary-soft);
    color: var(--color-primary-dark);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.panel-head h3 .dot-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }

.panel-head a {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--color-primary-dark);
    text-decoration: none;
    white-space: nowrap;
}

.chart-toggle {
    display: flex;
    background: var(--color-bg);
    border-radius: 999px;
    padding: 3px;
    gap: 3px;
    flex-shrink: 0;
}

.chart-toggle button {
    border: none;
    background: transparent;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-muted);
    cursor: pointer;
    transition: all .2s var(--ease-smooth);
}

.chart-toggle button.active {
    background: var(--color-primary);
    color: #fff;
    box-shadow: 0 4px 10px rgba(251,124,60,.35);
}

.chart-tahun {
    margin-left: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-muted);
}

.chart-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 2px 0 14px;
}

.chart-chip {
    --chip: #2F6FED;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px 7px 12px;
    border: 1px solid var(--color-border);
    border-radius: 999px;
    background: var(--color-surface);
    color: var(--color-text-muted);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s var(--ease-smooth);
}

.chart-chip i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--chip);
    opacity: .55;
    transition: all .2s var(--ease-smooth);
}

.chart-chip:hover {
    color: var(--color-text);
    border-color: var(--chip);
    transform: translateY(-1px);
}

.chart-chip.active {
    color: var(--color-text);
    border-color: var(--chip);
    background: color-mix(in srgb, var(--chip) 12%, transparent);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--chip) 22%, transparent);
}

.chart-chip.active i {
    opacity: 1;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--chip) 22%, transparent);
}

.chart-box { height: 280px; position: relative; }
.chart-box canvas { max-width: 100%; }

/* ===== PROMO CARD ===== */

.promo-card {
    min-width: 0;
    border-radius: var(--radius-lg);
    padding: 26px 22px;
    background:
        linear-gradient(160deg, rgba(22,22,29,.75), rgba(22,22,29,.92)),
        radial-gradient(circle at 30% 20%, rgba(251,124,60,.35), transparent 55%),
        linear-gradient(135deg, #22242d, #16161d);
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 320px;
    position: relative;
    overflow: hidden;
}

.promo-badge {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 18px;
}

.promo-badge svg { width: 26px; height: 26px; stroke: #fff; fill: none; stroke-width: 1.8; }

.promo-card h4 {
    font-size: 19px;
    font-weight: 800;
    line-height: 1.3;
    margin-bottom: 10px;
}

.promo-card p {
    font-size: 12.5px;
    color: rgba(255,255,255,.72);
    line-height: 1.6;
    margin-bottom: 20px;
}

.btn-promo {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    align-self: flex-start;
    background: var(--color-primary);
    color: #fff;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 700;
    transition: transform .2s var(--ease-smooth), box-shadow .2s var(--ease-smooth);
    white-space: nowrap;
}

.btn-promo:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(251,124,60,.4);
}

/* ===== TABLE + QUICK ACTIONS ===== */

.table-wrap { overflow-x: auto; }

.dash-table { width: 100%; border-collapse: collapse; }

.dash-table th {
    text-align: left;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--color-text-muted);
    padding: 10px 12px;
    border-bottom: 1px solid var(--color-border);
    white-space: nowrap;
}

.dash-table td {
    padding: 13px 12px;
    font-size: 13px;
    color: var(--color-text);
    border-bottom: 1px solid var(--color-border);
    white-space: nowrap;
}

.dash-table tr:last-child td { border-bottom: none; }

.dash-table tr { transition: background .2s var(--ease-smooth); }
.dash-table tbody tr:hover { background: var(--color-bg); }

.status-dot {
    display: inline-block;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--card-blue);
    margin-right: 8px;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.quick-btn {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 14px;
    border-radius: var(--radius-md);
    background: var(--color-bg);
    text-decoration: none;
    color: var(--color-text);
    font-size: 12.5px;
    font-weight: 600;
    transition: background .2s var(--ease-smooth), transform .2s var(--ease-smooth);
}

.quick-btn:hover { transform: translateY(-2px); }

.quick-btn .qicon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    flex-shrink: 0;
}

.quick-btn .qicon svg { width: 17px; height: 17px; stroke: #fff; fill: none; stroke-width: 2; }

.qicon.blue   { background: var(--card-blue); }
.qicon.orange { background: var(--card-orange); }
.qicon.purple { background: var(--card-purple); }
.qicon.green  { background: var(--card-green); }

</style>

<div class="content">
<div class="dash-wrap">

    <!-- HEADER -->
    <div class="dash-header">
        <div class="dash-title">
            <h1>Dashboard</h1>
            <div class="underline"></div>
            <p>Selamat datang kembali, <strong><?= htmlspecialchars($_SESSION['username']); ?>!</strong><br>
               Berikut adalah ringkasan data e-SIMPEL BKPSDM Kabupaten Tapin.</p>
        </div>
    
    </div>

    <!-- STAT CARDS -->
    <div class="stat-grid">

        <div class="stat-card c-blue">
            <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M3 7l9 6 9-6"></path><rect x="3" y="5" width="18" height="14" rx="2"></rect></svg></div>
            <div class="stat-label">Surat Masuk</div>
            <div class="stat-number"><?= $total_masuk; ?></div>
            <div class="stat-change <?= $perubahan_masuk['naik'] ? 'up' : 'down'; ?>">
                <?= $perubahan_masuk['naik'] ? '↑' : '↓'; ?> <?= abs($perubahan_masuk['persen']); ?>% <span>dari bulan lalu</span>
            </div>
            <a href="../surat_masuk/index.php" class="stat-link">Lihat Detail →</a>
        </div>

        <div class="stat-card c-orange">
            <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M3 7l9 6 9-6"></path><rect x="3" y="5" width="18" height="14" rx="2"></rect></svg></div>
            <div class="stat-label">Surat Keluar</div>
            <div class="stat-number"><?= $total_keluar; ?></div>
            <div class="stat-change <?= $perubahan_keluar['naik'] ? 'up' : 'down'; ?>">
                <?= $perubahan_keluar['naik'] ? '↑' : '↓'; ?> <?= abs($perubahan_keluar['persen']); ?>% <span>dari bulan lalu</span>
            </div>
            <a href="../surat_keluar/index.php" class="stat-link">Lihat Detail →</a>
        </div>

        <div class="stat-card c-purple">
            <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h6"></path><path d="M6 3h9l3 3v15H6z"></path></svg></div>
            <div class="stat-label">Disposisi</div>
            <div class="stat-number"><?= $total_disposisi; ?></div>
            <div class="stat-change up">↑ Total tercatat</div>
            <a href="#" class="stat-link">Lihat Detail →</a>
        </div>

        <div class="stat-card c-green">
            <div class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-6 6-6"></path><circle cx="17" cy="8" r="3"></circle><path d="M15 14c3.3 0 6 2.7 6 6"></path></svg></div>
            <div class="stat-label">Tamu</div>
            <div class="stat-number"><?= $total_tamu; ?></div>
            <div class="stat-change <?= $perubahan_tamu['naik'] ? 'up' : 'down'; ?>">
                <?= $perubahan_tamu['naik'] ? '↑' : '↓'; ?> <?= abs($perubahan_tamu['persen']); ?>% <span>dari bulan lalu</span>
            </div>
            <a href="../buku_tamu/index.php" class="stat-link">Lihat Detail →</a>
        </div>

    </div>

<style>
.ag-dash { margin-bottom: 22px; }
.ag-dash-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
.ag-dash-head h3 { margin:0; font-size:15px; font-weight:800; color:var(--color-text); display:flex; align-items:center; gap:9px; }
.ag-dash-head h3 .ic { width:30px; height:30px; border-radius:10px; background:#E0E7FF; display:flex; align-items:center; justify-content:center; }
.ag-dash-head a { font-size:12.5px; font-weight:700; color:#4338CA; text-decoration:none; }
.ag-dash-head a:hover { text-decoration:underline; }
.ag-dash-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:12px; }
.ag-dash-item { display:flex; gap:12px; padding:13px; border-radius:16px; background:var(--color-surface); border:1px solid var(--color-border); box-shadow:var(--shadow-card); text-decoration:none; color:inherit; transition:transform .22s var(--ease-smooth, ease), box-shadow .22s var(--ease-smooth, ease); }
.ag-dash-item:hover { transform:translateY(-3px); box-shadow:0 12px 24px rgba(20,20,40,.09); color:inherit; }
.ag-dash-item.hari  { border-color:#FECACA; background:#FEF2F2; }
.ag-dash-item.besok { border-color:#FDE68A; background:#FFFBEB; }
.ag-dash-tgl { width:50px; flex-shrink:0; padding:7px 0; border-radius:12px; background:#E0E7FF; color:#4338CA; text-align:center; line-height:1.1; }
.ag-dash-item.hari .ag-dash-tgl { background:#FEE2E2; color:#B91C1C; }
.ag-dash-item.besok .ag-dash-tgl { background:#FEF3C7; color:#B45309; }
.ag-dash-tgl b { display:block; font-size:19px; font-weight:800; }
.ag-dash-tgl span { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
.ag-dash-info { min-width:0; flex:1; }
.ag-dash-info h4 { margin:0; font-size:13px; font-weight:700; color:var(--color-text); line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.ag-dash-info p { margin:5px 0 0; font-size:11.5px; color:var(--color-text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ag-dash-tag { display:inline-block; margin-top:6px; padding:2px 9px; border-radius:999px; font-size:10px; font-weight:800; background:#E0E7FF; color:#3730A3; }
.ag-dash-item.hari .ag-dash-tag { background:#FEE2E2; color:#B91C1C; }
.ag-dash-item.besok .ag-dash-tag { background:#FEF3C7; color:#B45309; }
.ag-dash-kosong { padding:18px; border-radius:16px; background:var(--color-surface); border:1px dashed var(--color-border); color:var(--color-text-muted); font-size:13px; text-align:center; }
</style>

<div class="ag-dash">

    <div class="ag-dash-head">
        <h3>
            <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4338CA" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
            Agenda 7 hari ke depan
        </h3>
        <a href="../agenda/index.php">Buka kalender &rarr;</a>
    </div>

    <?php if ($agenda_minggu) { ?>

        <div class="ag-dash-list">

            <?php foreach ($agenda_minggu as $ag) {

                $ag_tgl  = new DateTime($ag['tanggal_acara'], new DateTimeZone('Asia/Makassar'));
                $ag_hari = (int) (new DateTime('today', new DateTimeZone('Asia/Makassar')))->diff($ag_tgl)->format('%r%a');

                $ag_kelas = $ag_hari === 0 ? 'hari' : ($ag_hari === 1 ? 'besok' : '');
                $ag_label = $ag_hari === 0 ? 'Hari ini' : ($ag_hari === 1 ? 'Besok' : ($ag_hari === 2 ? 'Lusa' : 'H-' . $ag_hari));
            ?>

                <a href="../surat_masuk/detail.php?id=<?= (int) $ag['id']; ?>" class="ag-dash-item <?= $ag_kelas; ?>">
                    <div class="ag-dash-tgl">
                        <b><?= $ag_tgl->format('j'); ?></b>
                        <span><?= substr($bulan_map[(int) $ag_tgl->format('n')], 0, 3); ?></span>
                    </div>
                    <div class="ag-dash-info">
                        <h4><?= htmlspecialchars($ag['perihal']); ?></h4>
                        <p><?= htmlspecialchars(($ag['tempat_acara'] ?? '') !== '' ? $ag['tempat_acara'] : 'Tempat belum dicatat'); ?></p>
                        <span class="ag-dash-tag"><?= $ag_label; ?></span>
                    </div>
                </a>

            <?php } ?>

        </div>

    <?php } else { ?>

        <div class="ag-dash-kosong">Tidak ada agenda undangan dalam 7 hari ke depan.</div>

    <?php } ?>

</div>

<style>
.kh-dash { margin-bottom: 22px; }
.kh-dash-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
.kh-dash-head h3 { margin:0; font-size:15px; font-weight:800; color:var(--color-text); display:flex; align-items:center; gap:9px; }
.kh-dash-head h3 .ic { width:30px; height:30px; border-radius:10px; background:#E0F2FE; display:flex; align-items:center; justify-content:center; }
.kh-dash-head a { font-size:12.5px; font-weight:700; color:#0369A1; text-decoration:none; }
.kh-dash-head a:hover { text-decoration:underline; }
.kh-dash-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:12px; }
.kh-dash-item { padding:13px 15px; border-radius:16px; background:var(--color-surface); border:1px solid var(--color-border); border-left:5px solid #0EA5E9; box-shadow:var(--shadow-card); }
.kh-dash-item.cuti { border-left-color:#F59E0B; }
.kh-dash-item h4 { margin:0; font-size:13.5px; font-weight:800; color:var(--color-text); }
.kh-dash-item p { margin:5px 0 0; font-size:12px; color:var(--color-text-muted); line-height:1.45; }
.kh-dash-tag { display:inline-block; margin-top:7px; padding:2px 10px; border-radius:999px; font-size:10.5px; font-weight:800; background:#E0F2FE; color:#0369A1; }
.kh-dash-item.cuti .kh-dash-tag { background:#FEF3C7; color:#B45309; }
.kh-dash-kosong { padding:18px; border-radius:16px; background:var(--color-surface); border:1px dashed var(--color-border); color:var(--color-text-muted); font-size:13px; text-align:center; }
</style>

<div class="kh-dash">

    <div class="kh-dash-head">
        <h3>
            <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0369A1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            Tidak hadir hari ini
        </h3>
        <a href="../ketidakhadiran/index.php">Kelola &rarr;</a>
    </div>

    <?php if ($kh_hari_ini) { ?>

        <div class="kh-dash-list">
            <?php foreach ($kh_hari_ini as $kh) { ?>
                <div class="kh-dash-item <?= $kh['jenis'] === 'Cuti' ? 'cuti' : ''; ?>">
                    <h4><?= htmlspecialchars($kh['nama']); ?></h4>
                    <p>
                        <?php if ($kh['jenis'] === 'Tugas Luar') { ?>
                            <?= htmlspecialchars(($kh['keperluan'] ?? '') !== '' ? $kh['keperluan'] : 'Acara belum dicatat'); ?><?= ($kh['tujuan'] ?? '') !== '' ? ' &middot; ' . htmlspecialchars($kh['tujuan']) : ''; ?>
                        <?php } else { ?>
                            Sedang cuti
                        <?php } ?>
                        <br>Sampai <?= htmlspecialchars(khTanggalPanjang($kh['tanggal_selesai'])); ?>
                    </p>
                    <span class="kh-dash-tag"><?= htmlspecialchars($kh['jenis']); ?></span>
                </div>
            <?php } ?>
        </div>

    <?php } else { ?>

        <div class="kh-dash-kosong">Semua pegawai hadir. Tidak ada tugas luar atau cuti hari ini.</div>

    <?php } ?>

</div>

    <!-- CHART + PROMO -->
    <div class="dash-row">

        <div class="panel">
            <div class="panel-head">
                <h3>
                    <span class="dot-icon"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"></path><path d="M7 15l4-4 3 3 5-6"></path></svg></span>
                    <span id="judulGrafik">Statistik Kunjungan Tamu</span>
                    <small class="chart-tahun"><?= $tahun_berjalan; ?></small>
                </h3>
                <div class="chart-toggle">
                    <button type="button" class="active" data-type="line">Line</button>
                    <button type="button" data-type="bar">Bar</button>
                </div>
            </div>

            <div class="chart-chips" id="chipGrafik" role="tablist" aria-label="Pilih statistik">
                <button type="button" class="chart-chip active" data-jenis="tamu" style="--chip: #2F6FED;" role="tab" aria-selected="true">
                    <i></i>Kunjungan Tamu
                </button>
                <button type="button" class="chart-chip" data-jenis="masuk" style="--chip: #FB7C3C;" role="tab" aria-selected="false">
                    <i></i>Surat Masuk
                </button>
                <button type="button" class="chart-chip" data-jenis="keluar" style="--chip: #16A34A;" role="tab" aria-selected="false">
                    <i></i>Surat Keluar
                </button>
            </div>

            <div class="chart-box"><canvas id="grafikTamu"></canvas></div>
        </div>

        <div class="promo-card">
            <div>
                <div class="promo-badge"><svg viewBox="0 0 24 24"><path d="M12 2l9 5v6c0 5-3.8 8.5-9 9-5.2-.5-9-4-9-9V7z"></path></svg></div>
                <h4>Melayani dengan Profesional dan Transparan</h4>
                <p>BKPSDM Kabupaten Tapin berkomitmen memberikan pelayanan terbaik dalam pengelolaan administrasi kepegawaian dan keperluan surat menyurat.</p>
            </div>
            <a href="../pegawai/index.php" class="btn-promo">Lihat Detail →</a>
        </div>

    </div>

    <!-- TABLE + QUICK ACTIONS -->
    <div class="dash-row">

        <div class="panel">
            <div class="panel-head">
                <h3>✉️ Surat Terbaru</h3>
                <a href="../surat_masuk/index.php">Lihat Semua →</a>
            </div>
            <div class="table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr><th>No</th><th>Nomor Surat</th><th>Pengirim</th><th>Perihal</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; if (mysqli_num_rows($surat_terbaru) > 0):
                            while ($s = mysqli_fetch_assoc($surat_terbaru)): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><span class="status-dot"></span><?= htmlspecialchars($s['nomor_surat']); ?></td>
                            <td><?= htmlspecialchars($s['pengirim']); ?></td>
                            <td><?= htmlspecialchars($s['perihal']); ?></td>
                            <td><?= !empty($s['tanggal_diterima']) ? date('d-m-Y', strtotime($s['tanggal_diterima'])) : '-'; ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align:center; color:#999;">Belum ada surat masuk</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>⚡ Aksi Cepat</h3></div>
            <div class="quick-grid">
                <a href="../surat_masuk/tambah.php" class="quick-btn">
                    <span class="qicon blue"><svg viewBox="0 0 24 24"><path d="M3 7l9 6 9-6"></path><rect x="3" y="5" width="18" height="14" rx="2"></rect></svg></span>
                    Surat Masuk
                </a>
                <a href="../surat_keluar/tambah.php" class="quick-btn">
                    <span class="qicon orange"><svg viewBox="0 0 24 24"><path d="M3 7l9 6 9-6"></path><rect x="3" y="5" width="18" height="14" rx="2"></rect></svg></span>
                    Surat Keluar
                </a>
                <a href="#" class="quick-btn">
                    <span class="qicon purple"><svg viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h6"></path><path d="M6 3h9l3 3v15H6z"></path></svg></span>
                    Disposisi
                </a>
                <a href="../buku_tamu/tambah.php" class="quick-btn">
                    <span class="qicon green"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-6 6-6"></path><circle cx="17" cy="8" r="3"></circle><path d="M15 14c3.3 0 6 2.7 6 6"></path></svg></span>
                    Buku Tamu
                </a>
                <a href="../pegawai/index.php" class="quick-btn">
                    <span class="qicon blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M5 21c0-4 3-7 7-7s7 3 7 7"></path></svg></span>
                    Data Pegawai
                </a>
                <a href="../laporan/index.php" class="quick-btn">
                    <span class="qicon orange"><svg viewBox="0 0 24 24"><path d="M4 19V5"></path><path d="M4 19h17"></path><polyline points="7 15 11 11 14 13 20 7"></polyline></svg></span>
                    Laporan
                </a>
            </div>
        </div>

    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    var namaBulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    var semuaData = <?= json_encode($grafik_semua); ?>;

    var konfig = {
        tamu:      { judul: 'Statistik Kunjungan Tamu',  label: 'Tamu',          warna: [47, 111, 237] },
        masuk:     { judul: 'Statistik Surat Masuk',     label: 'Surat Masuk',   warna: [251, 124, 60] },
        keluar:    { judul: 'Statistik Surat Keluar',    label: 'Surat Keluar',  warna: [22, 163, 74] }
    };

    var jenisAktif = 'tamu';
    var tipeAktif = 'line';

    var ctx = document.getElementById('grafikTamu');

    function rgba(w, a) { return 'rgba(' + w[0] + ',' + w[1] + ',' + w[2] + ',' + a + ')'; }

    function isiDataset(ds) {
        var k = konfig[jenisAktif];
        var solid = rgba(k.warna, 1);

        ds.label = k.label;
        ds.data = semuaData[jenisAktif];
        ds.borderColor = solid;
        ds.pointBackgroundColor = solid;

        if (tipeAktif === 'line') {
            var g = ctx.getContext('2d').createLinearGradient(0, 0, 0, 260);
            g.addColorStop(0, rgba(k.warna, .25));
            g.addColorStop(1, rgba(k.warna, 0));
            ds.backgroundColor = g;
            ds.fill = true;
        } else {
            ds.backgroundColor = rgba(k.warna, .85);
            ds.fill = false;
        }
    }

    var dataset = {
        tension: 0.4,
        pointRadius: 3,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        borderWidth: 2.5,
        borderRadius: 6
    };
    isiDataset(dataset);

    var chart = new Chart(ctx, {
        type: 'line',
        data: { labels: namaBulan, datasets: [dataset] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#16161d',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    });

    function perbaruiGrafik() {
        isiDataset(chart.data.datasets[0]);
        chart.config.type = tipeAktif;
        document.getElementById('judulGrafik').textContent = konfig[jenisAktif].judul;
        chart.update();
    }

    document.querySelectorAll('.chart-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.chart-chip').forEach(function (c) {
                c.classList.remove('active');
                c.setAttribute('aria-selected', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            jenisAktif = this.dataset.jenis;
            perbaruiGrafik();
        });
    });

    document.querySelectorAll('.chart-toggle button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelector('.chart-toggle button.active').classList.remove('active');
            this.classList.add('active');
            tipeAktif = this.dataset.type;
            perbaruiGrafik();
        });
    });

    var jamEl = document.getElementById('jamLive');
    setInterval(function () {
        var now = new Date();
        var h = String(now.getHours()).padStart(2,'0');
        var m = String(now.getMinutes()).padStart(2,'0');
        if (jamEl) jamEl.textContent = h + ':' + m;
    }, 1000 * 30);

});
</script>

<?php include "../layouts/footer.php"; ?>