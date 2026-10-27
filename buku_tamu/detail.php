<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

date_default_timezone_set('Asia/Makassar');

$role = roleUser() ?? 'operator';
$is_admin = ($role === 'admin');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>alert('ID tamu tidak valid.'); window.location='index.php';</script>";
    exit;
}

$stmt = mysqli_prepare($koneksi, "SELECT * FROM tamu WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    echo "<script>alert('Data tamu tidak ditemukan.'); window.location='index.php';</script>";
    exit;
}

$data = mysqli_fetch_assoc($result);

$inisial = strtoupper(substr($data['nama'], 0, 1));

$tanggal_panjang = '-';
$tanggal_pendek = '-';

if (!empty($data['tanggal_kunjungan']) && $data['tanggal_kunjungan'] !== '0000-00-00') {
    $tanggal_panjang = date('d F Y', strtotime($data['tanggal_kunjungan']));
    $tanggal_pendek = date('d-m-Y', strtotime($data['tanggal_kunjungan']));
}

$jam_tampil = !empty($data['jam_kunjungan']) ? date('H:i', strtotime($data['jam_kunjungan'])) : '-';

/* Warna avatar konsisten dengan halaman daftar */
function warnaAvatar($nama) {
    $palet = [
        ['bg' => '#DCFCE7', 'fg' => '#158a45'],
        ['bg' => '#DBEAFE', 'fg' => '#1d4fa8'],
        ['bg' => '#FDE8DD', 'fg' => '#c9660f'],
        ['bg' => '#EDE4FB', 'fg' => '#6d3fc9'],
        ['bg' => '#FCE7F3', 'fg' => '#be185d'],
        ['bg' => '#FEF3C7', 'fg' => '#b45309'],
    ];
    $index = array_sum(array_map('ord', str_split(substr($nama, 0, 5)))) % count($palet);
    return $palet[$index];
}

$warna = warnaAvatar($data['nama']);

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";

?>

<style>

.dt-page { padding-bottom: 40px; }

.dt-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.dt-header-icon {
    width: 52px;
    height: 52px;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--card-green), #158a45);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 20px rgba(34,197,94,.30);
    flex-shrink: 0;
}

.dt-header-text { flex: 1; min-width: 200px; }
.dt-header-text h2 { margin: 0; font-size: 24px; font-weight: 800; color: var(--color-text); }
.dt-header-text p { margin: 4px 0 0; font-size: 13px; color: var(--color-text-muted); }

.btn-kembali {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 18px;
    border-radius: var(--radius-md);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: background .2s var(--ease-smooth), color .2s var(--ease-smooth), border-color .2s var(--ease-smooth);
}

.btn-kembali:hover { background: var(--card-green-soft); color: #158a45; border-color: transparent; }
.btn-kembali svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 2; fill: none; }

.dt-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 300px);
    gap: 20px;
    align-items: start;
}

@media (max-width: 980px) { .dt-layout { grid-template-columns: minmax(0, 1fr); } }

/* ===== HERO ===== */

.hero-card {
    position: relative;
    border-radius: var(--radius-lg);
    padding: 28px 26px;
    margin-bottom: 18px;
    background:
        radial-gradient(circle at 85% -10%, rgba(34,197,94,.4), transparent 50%),
        radial-gradient(circle at 10% 110%, rgba(47,111,237,.16), transparent 45%),
        linear-gradient(160deg, rgba(22,22,29,.85), rgba(22,22,29,.97)),
        linear-gradient(135deg, #22242d, #16161d);
    color: #fff;
    display: flex;
    align-items: center;
    gap: 20px;
    overflow: hidden;
    animation: fadeInUp .4s var(--ease-smooth);
}

.hero-card::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 16px 16px;
    pointer-events: none;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.hero-avatar {
    position: relative;
    z-index: 1;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,.28);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 0 0 8px rgba(34,197,94,.12);
}

.hero-info { position: relative; z-index: 1; min-width: 0; }

.hero-badges { display: flex; gap: 7px; flex-wrap: wrap; margin-bottom: 10px; }

.hero-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .3px;
    background: rgba(34,197,94,.3);
    color: #b9f6cf;
}

.hero-badge.waktu { background: rgba(255,255,255,.12); color: rgba(255,255,255,.82); }

.hero-nama { font-size: 21px; font-weight: 800; margin-bottom: 5px; word-break: break-word; }
.hero-instansi { font-size: 13px; color: rgba(255,255,255,.7); }

/* ===== SECTION CARD ===== */

.section-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 22px;
    margin-bottom: 18px;
}

.section-card-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 18px;
    font-size: 14.5px;
    font-weight: 750;
    color: var(--color-text);
}

.section-card-title svg { width: 17px; height: 17px; stroke: #158a45; stroke-width: 2; fill: none; }

.info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
@media (max-width: 560px) { .info-grid { grid-template-columns: minmax(0, 1fr); } }

.info-item { background: var(--color-bg); border-radius: var(--radius-md); padding: 14px 16px; }
.info-item.full { grid-column: 1 / -1; }

.info-item small { display: block; color: var(--color-text-muted); font-size: 10.5px; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px; font-weight: 700; }
.info-item span { display: block; color: var(--color-text); font-size: 14px; font-weight: 600; word-break: break-word; line-height: 1.5; }
.info-item span.muted-empty { color: var(--color-text-muted); font-weight: 400; font-style: italic; }

/* ===== SIDEBAR ===== */

.dt-sticky { position: sticky; top: 92px; }

.summary-card {
    background: var(--card-green-soft);
    border-radius: var(--radius-lg);
    padding: 18px;
    text-align: center;
    margin-bottom: 16px;
}

.summary-card .s-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: var(--card-green);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
}

.summary-card .s-icon svg { width: 21px; height: 21px; stroke: #fff; stroke-width: 1.8; fill: none; }

.summary-card h6 { margin: 0 0 4px; font-size: 13px; font-weight: 750; color: #158a45; }
.summary-card p { margin: 0; font-size: 11.5px; color: #1e8a4f; line-height: 1.5; }

.action-list-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 18px;
}

.action-list-card h5 { margin: 0 0 13px; font-size: 12.5px; font-weight: 750; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: .4px; }

.action-list-card form { margin: 0; }

.action-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--color-text);
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 5px;
    border: none;
    width: 100%;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font-family: inherit;
    transition: background .2s var(--ease-smooth), transform .2s var(--ease-smooth);
}

.action-item:last-child { margin-bottom: 0; }
.action-item:hover { background: var(--color-bg); color: var(--color-text); transform: translateX(3px); }

.action-item .ai-icon {
    width: 34px; height: 34px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.action-item .ai-icon svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; }

.ai-icon.green { background: var(--card-green-soft); color: #158a45; }
.ai-icon.gray { background: var(--color-bg); color: var(--color-text-muted); }
.ai-icon.red { background: #FCE9E9; color: var(--color-danger); }

@media (max-width: 700px) {
    .dt-header { flex-direction: column; align-items: flex-start; }
    .dt-sticky { position: static; }
    .hero-card { flex-direction: column; align-items: flex-start; }
}

</style>

<div class="content">

    <div class="container-fluid dt-page">

        <div class="dt-header">
            <div class="dt-header-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </div>
            <div class="dt-header-text">
                <h2>Detail Tamu</h2>
                <p>Informasi lengkap kunjungan tamu.</p>
            </div>
            <a href="index.php" class="btn-kembali">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
                Kembali
            </a>
        </div>

        <div class="dt-layout">

            <div class="dt-main">

                <!-- HERO -->

                <div class="hero-card">

                    <div class="hero-avatar" style="background:<?= $warna['bg']; ?>; color:<?= $warna['fg']; ?>;"><?= htmlspecialchars($inisial); ?></div>

                    <div class="hero-info">

                        <div class="hero-badges">
                            <span class="hero-badge">🧑‍💼 BUKU TAMU</span>
                            <span class="hero-badge waktu">🕐 <?= $tanggal_pendek; ?> · <?= $jam_tampil; ?> WITA</span>
                        </div>

                        <div class="hero-nama"><?= htmlspecialchars($data['nama']); ?></div>
                        <div class="hero-instansi"><?= htmlspecialchars(!empty($data['instansi_asal']) ? $data['instansi_asal'] : 'Tanpa instansi'); ?></div>

                    </div>

                </div>

                <!-- INFORMASI KUNJUNGAN -->

                <div class="section-card">

                    <div class="section-card-title">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                        Informasi Kunjungan
                    </div>

                    <div class="info-grid">

                        <div class="info-item">
                            <small>Tanggal Kunjungan</small>
                            <span><?= $tanggal_panjang; ?></span>
                        </div>

                        <div class="info-item">
                            <small>Jam Kunjungan</small>
                            <span><?= $jam_tampil; ?><?= $jam_tampil !== '-' ? ' WITA' : ''; ?></span>
                        </div>

                        <div class="info-item full">
                            <small>Instansi Asal</small>
                            <?php if (!empty($data['instansi_asal'])) { ?>
                                <span><?= htmlspecialchars($data['instansi_asal']); ?></span>
                            <?php } else { ?>
                                <span class="muted-empty">Tidak dicantumkan.</span>
                            <?php } ?>
                        </div>

                        <div class="info-item full">
                            <small>Keperluan</small>
                            <?php if (!empty($data['keperluan'])) { ?>
                                <span><?= nl2br(htmlspecialchars($data['keperluan'])); ?></span>
                            <?php } else { ?>
                                <span class="muted-empty">Tidak dicantumkan.</span>
                            <?php } ?>
                        </div>

                        <div class="info-item full">
                            <small>Yang Memberi Konsultasi</small>
                            <?php if (!empty($data['yang_melayani'])) { ?>
                                <span><?= htmlspecialchars($data['yang_melayani']); ?></span>
                            <?php } else { ?>
                                <span class="muted-empty">Tidak dicantumkan.</span>
                            <?php } ?>
                        </div>

                    </div>

                </div>

            </div>

            <!-- ===== KOLOM KANAN ===== -->

            <div class="dt-side">
                <div class="dt-sticky">

                    <div class="summary-card">
                        <div class="s-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-6 6-6"></path><circle cx="17" cy="8" r="3"></circle><path d="M15 14c3.3 0 6 2.7 6 6"></path></svg></div>
                        <h6>Buku Tamu</h6>
                        <p>Berkunjung pada <?= $tanggal_pendek; ?></p>
                    </div>

                    <div class="action-list-card">

                        <h5>Aksi</h5>

                        <a href="edit.php?id=<?= (int) $data['id']; ?>" class="action-item">
                            <span class="ai-icon green"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"></path></svg></span>
                            Edit Data Tamu
                        </a>

                        <?php if ($is_admin) { ?>
                        <form method="POST" action="hapus.php" onsubmit="return confirm('Yakin ingin menghapus data tamu ini?')">
                            <input type="hidden" name="id" value="<?= (int) $data['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
                            <button type="submit" class="action-item">
                                <span class="ai-icon red"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6M14 11v6"></path></svg></span>
                                Hapus Data
                            </button>
                        </form>
                        <?php } ?>

                        <a href="index.php" class="action-item">
                            <span class="ai-icon gray"><svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg></span>
                            Kembali ke Daftar
                        </a>

                    </div>

                </div>
            </div>

        </div>

    </div>

</div>

<?php

include "../layouts/footer.php";

?>