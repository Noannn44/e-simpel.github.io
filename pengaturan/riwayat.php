<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

hanyaAdmin();

require_once "../config/database.php";

/** @var mysqli $koneksi */

date_default_timezone_set('Asia/Makassar');

/* =========================================================
   RIWAYAT AKTIVITAS (hanya admin)
========================================================= */

$sql_tabel = "CREATE TABLE IF NOT EXISTS riwayat_aktivitas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    waktu DATETIME NOT NULL,
    username VARCHAR(100) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT '',
    aksi VARCHAR(30) NOT NULL,
    modul VARCHAR(40) NOT NULL,
    keterangan VARCHAR(255) NOT NULL DEFAULT '',
    alamat_ip VARCHAR(45) NOT NULL DEFAULT '',
    INDEX idx_waktu (waktu),
    INDEX idx_username (username),
    INDEX idx_aksi (aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

/* ---------- membuat tabel (tombol) ---------- */

$pesan_tabel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_tabel'])) {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {

        $pesan_tabel = 'Sesi tidak valid. Muat ulang halaman.';

    } else {

        try {
            mysqli_query($koneksi, $sql_tabel);
            header("Location: riwayat.php");
            exit;
        } catch (Throwable $e) {
            $pesan_tabel = 'Gagal membuat tabel: ' . $e->getMessage()
                . ' - jalankan SQL di bawah lewat phpMyAdmin.';
        }
    }
}

/* ---------- ada tabelnya? ---------- */

$tabel_ada = false;

try {
    $cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'riwayat_aktivitas'");
    $tabel_ada = $cek && mysqli_num_rows($cek) > 0;
} catch (Throwable $e) {
    $tabel_ada = false;
}

/* ---------- filter ---------- */

$sekarang = new DateTime('now', new DateTimeZone('Asia/Makassar'));

$bulan   = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : 0;
$tahun   = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) $sekarang->format('Y');
$pengguna = trim((string) ($_GET['pengguna'] ?? ''));
$aksi    = trim((string) ($_GET['aksi'] ?? ''));
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$hal     = max(1, (int) ($_GET['hal'] ?? 1));

if ($bulan < 0 || $bulan > 12) { $bulan = 0; }
if ($tahun < 2000 || $tahun > 2100) { $tahun = (int) $sekarang->format('Y'); }

$daftar_aksi = ['Menambah', 'Mengubah', 'Menghapus', 'Login', 'Logout', 'Cadangan'];

if ($aksi !== '' && !in_array($aksi, $daftar_aksi, true)) { $aksi = ''; }

$per_halaman = 25;

$baris       = [];
$total       = 0;
$daftar_user = [];
$daftar_tahun = [(int) $sekarang->format('Y')];
$hitung = ['Menambah' => 0, 'Mengubah' => 0, 'Menghapus' => 0, 'Login' => 0];

if ($tabel_ada) {

    /* daftar tahun & pengguna untuk pilihan */

    $rt = mysqli_query($koneksi, "SELECT DISTINCT YEAR(waktu) AS t FROM riwayat_aktivitas ORDER BY t DESC");

    while ($rt && ($x = mysqli_fetch_assoc($rt))) {
        if ((int) $x['t'] > 0 && !in_array((int) $x['t'], $daftar_tahun, true)) {
            $daftar_tahun[] = (int) $x['t'];
        }
    }

    rsort($daftar_tahun);

    $ru = mysqli_query($koneksi, "SELECT DISTINCT username FROM riwayat_aktivitas ORDER BY username ASC");

    while ($ru && ($x = mysqli_fetch_assoc($ru))) {
        $daftar_user[] = $x['username'];
    }

    /* kondisi */

    $where  = ["YEAR(waktu) = ?"];
    $tipe   = "i";
    $param  = [$tahun];

    if ($bulan > 0) {
        $where[] = "MONTH(waktu) = ?";
        $tipe   .= "i";
        $param[] = $bulan;
    }

    if ($pengguna !== '') {
        $where[] = "username = ?";
        $tipe   .= "s";
        $param[] = $pengguna;
    }

    if ($aksi !== '') {
        $where[] = "aksi = ?";
        $tipe   .= "s";
        $param[] = $aksi;
    }

    if ($keyword !== '') {
        $where[] = "(modul LIKE ? OR keterangan LIKE ? OR username LIKE ?)";
        $tipe   .= "sss";
        $like    = '%' . $keyword . '%';
        $param[] = $like;
        $param[] = $like;
        $param[] = $like;
    }

    $kondisi = implode(' AND ', $where);

    /* total */

    $s1 = mysqli_prepare($koneksi, "SELECT COUNT(*) AS n FROM riwayat_aktivitas WHERE $kondisi");
    mysqli_stmt_bind_param($s1, $tipe, ...$param);
    mysqli_stmt_execute($s1);
    $total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($s1))['n'] ?? 0);

    /* ringkasan per aksi (untuk filter yang sama) */

    $s2 = mysqli_prepare($koneksi, "SELECT aksi, COUNT(*) AS n FROM riwayat_aktivitas WHERE $kondisi GROUP BY aksi");
    mysqli_stmt_bind_param($s2, $tipe, ...$param);
    mysqli_stmt_execute($s2);
    $r2 = mysqli_stmt_get_result($s2);

    while ($x = mysqli_fetch_assoc($r2)) {
        if (isset($hitung[$x['aksi']])) {
            $hitung[$x['aksi']] = (int) $x['n'];
        }
    }

    /* halaman */

    $jumlah_hal = max(1, (int) ceil($total / $per_halaman));

    if ($hal > $jumlah_hal) { $hal = $jumlah_hal; }

    $offset = ($hal - 1) * $per_halaman;

    $s3 = mysqli_prepare(
        $koneksi,
        "SELECT waktu, username, role, aksi, modul, keterangan, alamat_ip
         FROM riwayat_aktivitas WHERE $kondisi
         ORDER BY id DESC LIMIT ? OFFSET ?"
    );

    $tipe3  = $tipe . "ii";
    $param3 = array_merge($param, [$per_halaman, $offset]);

    mysqli_stmt_bind_param($s3, $tipe3, ...$param3);
    mysqli_stmt_execute($s3);
    $r3 = mysqli_stmt_get_result($s3);

    while ($x = mysqli_fetch_assoc($r3)) {
        $baris[] = $x;
    }

} else {

    $jumlah_hal = 1;
}

function urlRiwayat($ubah = [])
{
    $q = array_merge($_GET, $ubah);

    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        }
    }

    return 'riwayat.php' . ($q ? '?' . http_build_query($q) : '');
}

function kelasAksi($a)
{
    switch ($a) {
        case 'Menambah':  return 'tambah';
        case 'Mengubah':  return 'ubah';
        case 'Menghapus': return 'hapus';
        case 'Cadangan':  return 'cadangan';
        default:          return 'akun';
    }
}

$ada_filter = ($bulan > 0 || $pengguna !== '' || $aksi !== '' || $keyword !== '');

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.rw-wrap {
    --ac: #0EA5E9;
    --ac-dark: #0369A1;
    --ac-soft: #E0F2FE;
}

.rw-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #0b1b2b 0%, #0f2f4d 58%, #0369a1 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.rw-hero::before {
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

.rw-hero > * { position: relative; z-index: 1; }

.rw-eyebrow {
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

.rw-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #7DD3FC;
    box-shadow: 0 0 0 4px rgba(125,211,252,.35);
}

.rw-wrap .rw-hero h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.rw-wrap .rw-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.7) !important;
}

.rw-pills { display: flex; gap: 12px; flex-wrap: wrap; }

.rw-pill {
    min-width: 92px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
}

.rw-pill strong { display: block; font-size: 21px; font-weight: 700; line-height: 1.1; color: #fff; }
.rw-pill span { font-size: 10.5px; color: rgba(255,255,255,.62); text-transform: uppercase; letter-spacing: .5px; }

.rw-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    padding: 20px 22px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
    margin-bottom: 18px;
}

.rw-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.rw-filter select,
.rw-filter input[type="text"] {
    height: 42px;
    border: 1px solid #e6e6ee;
    border-radius: 12px;
    background: #fafafc;
    padding: 0 14px;
    font-size: 13px;
    color: #2a2a35;
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
}

.rw-filter select:focus,
.rw-filter input[type="text"]:focus {
    border-color: var(--ac);
    background: #fff;
    box-shadow: 0 0 0 4px rgba(14,165,233,.14);
}

.rw-filter input[type="text"] { flex: 1; min-width: 200px; }

.rw-go {
    height: 42px;
    padding: 0 18px;
    border: none;
    border-radius: 12px;
    background: var(--ac);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.rw-reset {
    height: 42px;
    display: inline-flex;
    align-items: center;
    padding: 0 14px;
    border-radius: 12px;
    background: #f1f1f5;
    color: #555;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
}

.rw-reset:hover { background: #e6e6ee; color: #333; }

.rw-table-wrap { overflow-x: auto; }

.rw-table { width: 100%; border-collapse: collapse; min-width: 760px; }

.rw-table th {
    padding: 10px 12px;
    text-align: left;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #9a9aa8;
    border-bottom: 1px solid #eee;
    white-space: nowrap;
}

.rw-table td {
    padding: 12px;
    font-size: 13px;
    color: #2a2a35;
    border-bottom: 1px solid #f4f4f7;
    vertical-align: top;
}

.rw-table tr:hover td { background: #fafcff; }

.rw-waktu b { display: block; font-weight: 700; }
.rw-waktu small { color: #9a9aa8; font-size: 11.5px; }

.rw-user b { display: block; font-weight: 700; }
.rw-user small { color: #9a9aa8; font-size: 11.5px; text-transform: capitalize; }

.rw-chip {
    display: inline-block;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.rw-chip.tambah   { background: #DCFCE7; color: #15803D; }
.rw-chip.ubah     { background: #DBEAFE; color: #1D4ED8; }
.rw-chip.hapus    { background: #FEE2E2; color: #B91C1C; }
.rw-chip.cadangan { background: #EDE9FE; color: #6D28D9; }
.rw-chip.akun     { background: #F1F5F9; color: #475569; }

.rw-ket { color: #55556a; word-break: break-word; }

.rw-empty {
    text-align: center;
    padding: 40px 16px;
    color: #9a9aa8;
    font-size: 13px;
}

.rw-pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 16px;
    font-size: 12.5px;
    color: #8a8a98;
}

.rw-pager .btns { display: flex; gap: 8px; }

.rw-pager a, .rw-pager span.off {
    height: 34px;
    padding: 0 14px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
}

.rw-pager a { background: var(--ac-soft); color: var(--ac-dark); }
.rw-pager a:hover { background: var(--ac); color: #fff; }
.rw-pager span.off { background: #f4f4f7; color: #b5b5c0; }

.rw-setup h3 { margin: 0 0 6px; font-size: 15px; font-weight: 700; color: #1d1d27; }
.rw-setup p { margin: 0 0 12px; font-size: 13px; color: #6b6b7a; }

.rw-setup pre {
    margin: 0 0 14px;
    padding: 14px;
    border-radius: 12px;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 12px;
    line-height: 1.5;
    overflow-x: auto;
    white-space: pre-wrap;
}

.rw-setup .err {
    margin-bottom: 12px;
    padding: 10px 14px;
    border-radius: 12px;
    background: #FEE2E2;
    color: #B91C1C;
    font-size: 12.5px;
    font-weight: 600;
}

@media (max-width: 700px) {
    .rw-hero { padding: 24px 20px; }
    .rw-wrap .rw-hero h1 { font-size: 23px; }
    .rw-card { padding: 16px 14px; }
}

</style>

<div class="content">
<div class="container-fluid rw-wrap">

    <div class="rw-hero">

        <div>
            <div class="rw-eyebrow"><i></i> PENGATURAN</div>
            <h1>Riwayat Aktivitas</h1>
            <p>Catatan siapa menambah, mengubah, atau menghapus data.</p>
        </div>

        <?php if ($tabel_ada) { ?>
            <div class="rw-pills">
                <div class="rw-pill"><strong><?= $hitung['Menambah']; ?></strong><span>Tambah</span></div>
                <div class="rw-pill"><strong><?= $hitung['Mengubah']; ?></strong><span>Ubah</span></div>
                <div class="rw-pill"><strong><?= $hitung['Menghapus']; ?></strong><span>Hapus</span></div>
                <div class="rw-pill"><strong><?= $hitung['Login']; ?></strong><span>Login</span></div>
            </div>
        <?php } ?>

    </div>

    <?php if (!$tabel_ada) { ?>

        <div class="rw-card rw-setup">

            <h3>Tabel riwayat belum dibuat</h3>
            <p>Klik tombol di bawah, atau jalankan SQL ini sekali di phpMyAdmin (tab SQL) pada database aplikasi.</p>

            <?php if ($pesan_tabel !== '') { ?>
                <div class="err"><?= htmlspecialchars($pesan_tabel); ?></div>
            <?php } ?>

            <pre><?= htmlspecialchars($sql_tabel); ?>;</pre>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                <button type="submit" name="buat_tabel" value="1" class="rw-go">Buat tabel otomatis</button>
            </form>

        </div>

    <?php } else { ?>

        <div class="rw-card">

            <form method="GET" class="rw-filter">

                <select name="bulan">
                    <option value="">Semua bulan</option>
                    <?php foreach ($nama_bulan as $n => $nm) { ?>
                        <option value="<?= $n; ?>" <?= $bulan === $n ? 'selected' : ''; ?>><?= $nm; ?></option>
                    <?php } ?>
                </select>

                <select name="tahun">
                    <?php foreach ($daftar_tahun as $t) { ?>
                        <option value="<?= $t; ?>" <?= $tahun === $t ? 'selected' : ''; ?>><?= $t; ?></option>
                    <?php } ?>
                </select>

                <select name="pengguna">
                    <option value="">Semua pengguna</option>
                    <?php foreach ($daftar_user as $u) { ?>
                        <option value="<?= htmlspecialchars($u); ?>" <?= $pengguna === $u ? 'selected' : ''; ?>><?= htmlspecialchars($u); ?></option>
                    <?php } ?>
                </select>

                <select name="aksi">
                    <option value="">Semua aksi</option>
                    <?php foreach ($daftar_aksi as $a) { ?>
                        <option value="<?= $a; ?>" <?= $aksi === $a ? 'selected' : ''; ?>><?= $a; ?></option>
                    <?php } ?>
                </select>

                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari modul atau keterangan...">

                <button type="submit" class="rw-go">Tampilkan</button>

                <?php if ($ada_filter) { ?>
                    <a href="riwayat.php" class="rw-reset">Reset</a>
                <?php } ?>

            </form>

        </div>

        <div class="rw-card">

            <div class="rw-table-wrap">

                <table class="rw-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aksi</th>
                            <th>Modul</th>
                            <th>Keterangan</th>
                            <th>Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php if ($baris) {

                            foreach ($baris as $b) {

                                $w = new DateTime($b['waktu']);
                        ?>
                            <tr>
                                <td class="rw-waktu">
                                    <b><?= $w->format('d-m-Y'); ?></b>
                                    <small><?= $w->format('H:i:s'); ?></small>
                                </td>
                                <td class="rw-user">
                                    <b><?= htmlspecialchars($b['username']); ?></b>
                                    <small><?= htmlspecialchars($b['role']); ?></small>
                                </td>
                                <td><span class="rw-chip <?= kelasAksi($b['aksi']); ?>"><?= htmlspecialchars($b['aksi']); ?></span></td>
                                <td><?= htmlspecialchars($b['modul']); ?></td>
                                <td class="rw-ket"><?= htmlspecialchars($b['keterangan']); ?></td>
                                <td><?= htmlspecialchars($b['alamat_ip']); ?></td>
                            </tr>
                        <?php }

                        } else { ?>
                            <tr><td colspan="6"><div class="rw-empty">Belum ada aktivitas yang cocok dengan filter ini.</div></td></tr>
                        <?php } ?>

                    </tbody>
                </table>

            </div>

            <div class="rw-pager">

                <div><?= number_format($total, 0, ',', '.'); ?> aktivitas &middot; halaman <?= $hal; ?> dari <?= $jumlah_hal; ?></div>

                <div class="btns">
                    <?php if ($hal > 1) { ?>
                        <a href="<?= htmlspecialchars(urlRiwayat(['hal' => $hal - 1])); ?>">&laquo; Sebelumnya</a>
                    <?php } else { ?>
                        <span class="off">&laquo; Sebelumnya</span>
                    <?php } ?>

                    <?php if ($hal < $jumlah_hal) { ?>
                        <a href="<?= htmlspecialchars(urlRiwayat(['hal' => $hal + 1])); ?>">Berikutnya &raquo;</a>
                    <?php } else { ?>
                        <span class="off">Berikutnya &raquo;</span>
                    <?php } ?>
                </div>

            </div>

        </div>

    <?php } ?>

</div>
</div>

<?php include "../layouts/footer.php"; ?>