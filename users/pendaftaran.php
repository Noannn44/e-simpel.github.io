<?php
 
session_start();
 
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}
 
require_once "../config/auth.php";
require_once "../config/role.php";
 
hanyaAdmin();
 
include "../config/database.php";
 
/** @var mysqli $koneksi */
 
date_default_timezone_set('Asia/Makassar');
 
/* =========================================================
   PENDAFTARAN AKUN (persetujuan admin)
========================================================= */
 
$sql_tabel = "CREATE TABLE IF NOT EXISTS pendaftaran_akun (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nip VARCHAR(25) NOT NULL DEFAULT '',
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
    waktu DATETIME NOT NULL,
    alamat_ip VARCHAR(45) NOT NULL DEFAULT '',
    diproses_oleh VARCHAR(50) NOT NULL DEFAULT '',
    diproses_waktu DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
 
$pesan  = '';
$galat  = '';
 
/* ---------- tabel ada? ---------- */
 
function tabelAda($koneksi)
{
    try {
        $c = mysqli_query($koneksi, "SHOW TABLES LIKE 'pendaftaran_akun'");
        return $c && mysqli_num_rows($c) > 0;
    } catch (Throwable $e) {
        return false;
    }
}
 
/* ---------- buat tabel (tombol) ---------- */
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_tabel'])) {
 
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
 
        $galat = 'Sesi tidak valid. Muat ulang halaman.';
 
    } else {
 
        try {
            mysqli_query($koneksi, $sql_tabel);
            header("Location: pendaftaran.php");
            exit;
        } catch (Throwable $e) {
            $galat = 'Gagal membuat tabel: ' . $e->getMessage()
                . ' - jalankan SQL di bawah lewat phpMyAdmin sebagai root.';
        }
    }
}
 
$tabel_ada = tabelAda($koneksi);
 
/* ---------- setujui / tolak ---------- */
 
if ($tabel_ada && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
 
    $id   = (int) ($_POST['id'] ?? 0);
    $aksi = (string) $_POST['aksi'];
 
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
 
        $galat = 'Sesi tidak valid. Muat ulang halaman.';
 
    } elseif ($id <= 0 || !in_array($aksi, ['setujui', 'tolak'], true)) {
 
        $galat = 'Permintaan tidak valid.';
 
    } else {
 
        $st = mysqli_prepare($koneksi, "SELECT id, nama, username, password, status FROM pendaftaran_akun WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($st, "i", $id);
        mysqli_stmt_execute($st);
        $p = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
 
        if (!$p || $p['status'] !== 'menunggu') {
 
            $galat = 'Pendaftaran tidak ditemukan atau sudah diproses.';
 
        } else {
 
            $admin = (string) $_SESSION['username'];
            $waktu = date('Y-m-d H:i:s');
 
            if ($aksi === 'tolak') {
 
                $up = mysqli_prepare($koneksi, "UPDATE pendaftaran_akun SET status = 'ditolak', diproses_oleh = ?, diproses_waktu = ? WHERE id = ?");
                mysqli_stmt_bind_param($up, "ssi", $admin, $waktu, $id);
                mysqli_stmt_execute($up);
 
                catatAktivitas($koneksi, 'Mengubah', 'Pendaftaran Akun', 'Menolak pendaftaran: ' . $p['username']);
 
                $pesan = 'Pendaftaran ' . $p['username'] . ' ditolak.';
 
            } else {
 
                $role = roleKeDb($koneksi, $_POST['role'] ?? 'operator');
 
                if ($role === null) {
 
                    $galat = 'Role tidak valid.';
 
                } else {
 
                    /* username masih bebas? */
 
                    $c = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? LIMIT 1");
                    mysqli_stmt_bind_param($c, "s", $p['username']);
                    mysqli_stmt_execute($c);
                    mysqli_stmt_store_result($c);
 
                    if (mysqli_stmt_num_rows($c) > 0) {
 
                        $galat = 'Username ' . $p['username'] . ' sudah dipakai akun lain. Tolak pendaftaran ini.';
 
                    } else {
 
                        try {
 
                            $ins = mysqli_prepare($koneksi, "INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
                            mysqli_stmt_bind_param($ins, "ssss", $p['nama'], $p['username'], $p['password'], $role);
                            mysqli_stmt_execute($ins);
 
                            $up = mysqli_prepare($koneksi, "UPDATE pendaftaran_akun SET status = 'disetujui', diproses_oleh = ?, diproses_waktu = ? WHERE id = ?");
                            mysqli_stmt_bind_param($up, "ssi", $admin, $waktu, $id);
                            mysqli_stmt_execute($up);
 
                            catatAktivitas($koneksi, 'Menambah', 'Data User', 'Menyetujui pendaftaran: ' . $p['username'] . ' (' . $role . ')');
 
                            $pesan = 'Akun ' . $p['username'] . ' disetujui sebagai ' . $role . '. Beri tahu pemiliknya agar bisa masuk.';
 
                        } catch (Throwable $e) {
 
                            error_log('pendaftaran.php: ' . $e->getMessage());
                            $galat = 'Akun gagal dibuat.';
                        }
                    }
                }
            }
        }
    }
}
 
/* ---------- data ---------- */
 
$menunggu = [];
$selesai  = [];
 
if ($tabel_ada) {
 
    $r = mysqli_query($koneksi, "SELECT id, nama, nip, username, waktu FROM pendaftaran_akun WHERE status = 'menunggu' ORDER BY id ASC");
 
    while ($r && ($x = mysqli_fetch_assoc($r))) {
 
        /* cocokkan dengan data pegawai (opsional) */
 
        $x['pegawai'] = null;
 
        try {
 
            if ($x['nip'] !== '') {
                $q = mysqli_prepare($koneksi, "SELECT nama, jabatan FROM pegawai WHERE nip = ? LIMIT 1");
                mysqli_stmt_bind_param($q, "s", $x['nip']);
            } else {
                $q = mysqli_prepare($koneksi, "SELECT nama, jabatan FROM pegawai WHERE nama = ? LIMIT 1");
                mysqli_stmt_bind_param($q, "s", $x['nama']);
            }
 
            mysqli_stmt_execute($q);
            $x['pegawai'] = mysqli_fetch_assoc(mysqli_stmt_get_result($q)) ?: null;
 
        } catch (Throwable $e) {
            $x['pegawai'] = null;
        }
 
        $menunggu[] = $x;
    }
 
    $r2 = mysqli_query($koneksi, "SELECT nama, username, status, diproses_oleh, diproses_waktu FROM pendaftaran_akun WHERE status <> 'menunggu' ORDER BY diproses_waktu DESC, id DESC LIMIT 15");
 
    while ($r2 && ($x = mysqli_fetch_assoc($r2))) {
        $selesai[] = $x;
    }
}
 
include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.pa-wrap {
    --ac: #14B8A6;
    --ac-dark: #0F766E;
    --ac-soft: #CCFBF1;
}

.pa-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #0b1f24 0%, #0f3a3f 58%, #0f766e 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.pa-hero::before {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    right: -80px;
    top: -140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(20,184,166,.5), transparent 70%);
    pointer-events: none;
}

.pa-hero > * { position: relative; z-index: 1; }

.pa-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 999px;
    background: rgba(255,255,255.1);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .4px;
    margin-bottom: 12px;
}

.pa-eyebrow i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #5EEAD4;
    box-shadow: 0 0 0 4px rgba(944,234,212,.35);
}

.pa-wrap .pa-hero h1{
    margin: 0;
    border-radius: 28px;
    font-weight: 700;
    letter-spacing: -.3px;
    color: #ffffff !important;
}

.pa-wrap .pa-hero p {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(255,255,255,.7) !important;
}

.pa-pill {
    min-width: 100px;
    padding: 11px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.08);
}

.pa-pill strong { display: block; font-size: 21px; font-weight: 700; line-height: 1.1; color: #fff; }
.pa-pill span { font-size: 10.5px; color: rgba(255,255,255,.62); text-transform: uppercase; letter-spacing: .5px; }

.pa-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 20px;
    padding: 20px 22px;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
    margin-bottom: 18px;
}

.pa-card h3 { margin: 0 0 14px; font-size: 14px; font-weight: 700; color: #1d1d27;}

.pa-msg, .pa-err {
    margin-bottom: 14px;
    padding: 11px 14px;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding: 16px;
    margin-bottom: 12px;
    border-radius: 16px;
    background: #fafafc;
    border: 1px solid #f0f0f5;
}

.pa-item:last-child { margin-bottom: 0; }

.pa-info { min-width: 0; flex: 1; min-width: 240px; }

.pa-info h4 { margin: 0; font-size: 14.5px; font-weight: 700; color: #1d1d27; }

.pa-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 16px;
    margin-top: 6px;
    font-size: 12px;
    color: #777;
}

.pa-match {
    display: inline-block;
    margin-top: 8px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.pa-match.ya { background: #DCFCE7; color: #166534; }
.pa-match.tidak { background: #FEF3C7; color: #92400E; }

.pa-aksi { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.pa-aksi select {
    height: 40px;
    border: 1px solid #e6e6ee;
    border-radius: 11px;
    background: #fff;
    padding: 0 12px;
    font-size: 12.5px;
    color: #2a2a35;
}

.pa-btn {
    height: 40px;
    padding: 0 16px;
    border: none;
    border-radius: 11px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    transition: transform .2s var(--ease-smooth, ease), background .2s;
}

.pa-btn:hover { transform: translateY(-2px); }

.pa-btn.ok { background: var(--ac); color: #fff; }
.pa-btn.ok:hover { background: #0D9488; }
.pa-btn.no { background: #FEE2E2; color: #B91C1C; }
.pa-btn.no:hover { background: #FECACA; }

.pa-table { width: 100%; border-collapse: collapse; min-width: 520px; }

.pa-tabble th {
    padding: 8px 10px;
    text-align: left;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: #9a9aa8;
    border-bottom: 1px solid #eee;
}

.pa-table td { padding: 11px 10px; font-size: 13px; border-bottom: 1px solid #f4f4f7; color: #2a2a35; }

.pa-chip { padding: 3px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 800; }
.pa-chip.disetujui { background: #DCFCE7; color: #15803D; }
.pa-chip.ditolak { background: #FEE2E1; color: #B91C1C; }

.pa-scroll { overflow-x: auto; }
.pa-empty { text-align: center; padding: 30px 12px; color: #9a9aa8; font-size: 13px; }

.pa-setup pre {
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

@media (max-width: 700px) {
    .pa-hero { padding: 24px 20px;}
    .pa-wrap .pahero h1 { font-size: 23px; }
    .pa-card { padding: 16px 14px; }
}

</style>

<div class="content">
<div class="container-fluid pa-wrap">
 
    <div class="pa-hero">
 
        <div>
            <div class="pa-eyebrow"><i></i> PENGATURAN</div>
            <h1>Pendaftaran Akun</h1>
            <p>Periksa pendaftar baru, lalu setujui atau tolak.</p>
        </div>
 
        <?php if ($tabel_ada) { ?>
            <div class="pa-pill"><strong><?= count($menunggu); ?></strong><span>Menunggu</span></div>
        <?php } ?>
 
    </div>
 
    <?php if ($pesan !== '') { ?><div class="pa-msg"><?= htmlspecialchars($pesan); ?></div><?php } ?>
    <?php if ($galat !== '') { ?><div class="pa-err"><?= htmlspecialchars($galat); ?></div><?php } ?>
 
    <?php if (!$tabel_ada) { ?>
 
        <div class="pa-card pa-setup">
 
            <h3>Tabel pendaftaran belum dibuat</h3>
            <p style="font-size:13px;color:#6b6b7a">Klik tombol di bawah, atau jalankan SQL ini sekali di phpMyAdmin (tab SQL) sebagai root pada database aplikasi.</p>
 
            <pre><?= htmlspecialchars($sql_tabel); ?>;</pre>
 
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                <button type="submit" name="buat_tabel" value="1" class="pa-btn ok">Buat tabel otomatis</button>
            </form>
 
        </div>
 
    <?php } else { ?>
 
        <div class="pa-card">
 
            <h3>Menunggu persetujuan</h3>
 
            <?php if ($menunggu) {
 
                foreach ($menunggu as $m) {
 
                    $w = new DateTime($m['waktu']);
            ?>
 
                <div class="pa-item">
 
                    <div class="pa-info">
                        <h4><?= htmlspecialchars($m['nama']); ?></h4>
                        <div class="pa-meta">
                            <span>Username: <b><?= htmlspecialchars($m['username']); ?></b></span>
                            <span>NIP: <?= $m['nip'] !== '' ? htmlspecialchars($m['nip']) : '-'; ?></span>
                            <span>Mendaftar: <?= $w->format('d-m-Y H:i'); ?></span>
                        </div>
 
                        <?php if ($m['pegawai']) { ?>
                            <span class="pa-match ya">Ada di Data Pegawai: <?= htmlspecialchars($m['pegawai']['nama']); ?> &middot; <?= htmlspecialchars($m['pegawai']['jabatan']); ?></span>
                        <?php } else { ?>
                            <span class="pa-match tidak">Tidak ditemukan di Data Pegawai, periksa dulu</span>
                        <?php } ?>
                    </div>
 
                    <form method="POST" class="pa-aksi" data-manual>
 
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?= (int) $m['id']; ?>">
 
                        <select name="role" title="Hak akses">
                            <option value="operator">Operator</option>
                            <option value="administrator">Administrator</option>
                        </select>
 
                        <button type="submit" name="aksi" value="setujui" class="pa-btn ok">Setujui</button>
                        <button type="submit" name="aksi" value="tolak" class="pa-btn no" onclick="return confirm('Tolak pendaftaran ini?');">Tolak</button>
 
                    </form>
 
                </div>
 
            <?php }
 
            } else { ?>
 
                <div class="pa-empty">Tidak ada pendaftaran yang menunggu.</div>
 
            <?php } ?>
 
        </div>
 
        <div class="pa-card">
 
            <h3>Sudah diproses (15 terakhir)</h3>
 
            <?php if ($selesai) { ?>
 
                <div class="pa-scroll">
                    <table class="pa-table">
                        <thead>
                            <tr><th>Nama</th><th>Username</th><th>Status</th><th>Diproses oleh</th><th>Waktu</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selesai as $s) {
 
                                $ws = $s['diproses_waktu'] ? (new DateTime($s['diproses_waktu']))->format('d-m-Y H:i') : '-';
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['nama']); ?></td>
                                    <td><?= htmlspecialchars($s['username']); ?></td>
                                    <td><span class="pa-chip <?= htmlspecialchars($s['status']); ?>"><?= $s['status'] === 'disetujui' ? 'Disetujui' : 'Ditolak'; ?></span></td>
                                    <td><?= htmlspecialchars($s['diproses_oleh']); ?></td>
                                    <td><?= $ws; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
 
            <?php } else { ?>
 
                <div class="pa-empty">Belum ada riwayat.</div>
 
            <?php } ?>
 
        </div>
 
    <?php } ?>
 
</div>
</div>
 
<?php include "../layouts/footer.php"; ?>