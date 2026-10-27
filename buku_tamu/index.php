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

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : "";
$keyword_safe = mysqli_real_escape_string($koneksi, $keyword);

$sql = "SELECT * FROM tamu WHERE 1=1";

if ($keyword !== "") {
    $sql .= "
        AND (
            nama LIKE '%$keyword_safe%'
            OR instansi_asal LIKE '%$keyword_safe%'
            OR keperluan LIKE '%$keyword_safe%'
            OR yang_melayani LIKE '%$keyword_safe%'
        )
    ";
}

$sql .= " ORDER BY tanggal_kunjungan DESC, jam_kunjungan DESC, id DESC";

$query = mysqli_query($koneksi, $sql);

if (!$query) {
    die("Terjadi kesalahan database: " . mysqli_error($koneksi));
}

$tamu_list = [];
while ($row = mysqli_fetch_assoc($query)) {
    $tamu_list[] = $row;
}

$total_tamu = count($tamu_list);

$hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');

$total_hari_ini = 0;
$total_bulan_ini = 0;

foreach ($tamu_list as $t) {
    if ($t['tanggal_kunjungan'] === $hari_ini) {
        $total_hari_ini++;
    }
    if (substr($t['tanggal_kunjungan'], 0, 7) === $bulan_ini) {
        $total_bulan_ini++;
    }
}

$nama_hari = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$nama_bulan = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];

/* Warna avatar bervariasi, konsisten berdasarkan nama */
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

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";

?>

<style>

.bt2-page { padding-bottom: 40px; }

/* ===== HERO ===== */

.bt2-hero {
    position: relative;
    border-radius: var(--radius-lg);
    padding: 30px 28px;
    margin-bottom: 22px;
    background:
        radial-gradient(circle at 85% -10%, rgba(34,197,94,.34), transparent 50%),
        radial-gradient(circle at 10% 110%, rgba(47,111,237,.18), transparent 45%),
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

.bt2-hero::before {
    content: "";
    position: absolute;
    top: -40%;
    right: -6%;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(34,197,94,.2), transparent 70%);
    pointer-events: none;
}

.bt2-hero-text { position: relative; z-index: 1; }

.bt2-hero-text h2 {
    margin: 0 0 7px;
    font-size: 24px;
    font-weight: 800;
    background: linear-gradient(135deg, #ffffff, #b9f6cf);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.bt2-hero-text p { margin: 0; font-size: 13px; color: rgba(255,255,255,.62); max-width: 420px; line-height: 1.6; }

.bt2-hero-stats { display: flex; gap: 12px; flex-wrap: wrap; position: relative; z-index: 1; }

.bt2-stat-pill {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: var(--radius-md);
    padding: 12px 20px;
    text-align: center;
    min-width: 92px;
    backdrop-filter: blur(6px);
    transition: transform .25s var(--ease-smooth), background .25s var(--ease-smooth);
}

.bt2-stat-pill:hover { transform: translateY(-3px); background: rgba(255,255,255,.1); }

.bt2-stat-pill strong { display: block; font-size: 23px; font-weight: 800; color: #fff; letter-spacing: -.3px; }
.bt2-stat-pill small { display: block; font-size: 9.5px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; }

.bt2-stat-pill.active {
    background: linear-gradient(135deg, rgba(34,197,94,.3), rgba(34,197,94,.15));
    border-color: rgba(34,197,94,.42);
    box-shadow: 0 8px 20px rgba(34,197,94,.2);
}

.bt2-stat-pill.active strong { color: #86f0b0; }

.btn-add-fab {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: linear-gradient(135deg, var(--card-green), #158a45);
    color: white;
    border: none;
    padding: 14px 24px;
    border-radius: 999px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(34,197,94,.42);
    transition: transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s var(--ease-smooth);
    position: relative;
    z-index: 1;
}

.btn-add-fab:hover { color: white; transform: translateY(-3px) scale(1.02); box-shadow: 0 14px 28px rgba(34,197,94,.5); }
.btn-add-fab svg { transition: transform .25s var(--ease-smooth); }
.btn-add-fab:hover svg { transform: rotate(90deg); }

/* ===== SEARCH ===== */

.bt2-search-bar { display: flex; gap: 8px; margin-bottom: 26px; }

.bt2-search-bar input {
    flex: 1;
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

.bt2-search-bar input:focus { border-color: var(--card-green); box-shadow: 0 0 0 4px var(--card-green-soft); }
.bt2-search-bar input::placeholder { color: #a8adb8; }

.bt2-search-bar button {
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

.bt2-search-bar button:hover { background: var(--card-green); transform: scale(1.06); }

.bt2-reset-chip {
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

.bt2-reset-chip:hover { background: var(--card-green-soft); color: #158a45; }

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

.day-label.today { background: linear-gradient(135deg, var(--card-green), #158a45); }

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
    background: var(--card-green);
    border: 3px solid var(--color-bg);
    box-shadow: 0 0 0 2px var(--card-green);
}

.t-dot.today-dot { animation: pulseDot 2s infinite; }

@keyframes pulseDot {
    0%   { box-shadow: 0 0 0 2px var(--card-green), 0 0 0 0 rgba(34,197,94,.5); }
    70%  { box-shadow: 0 0 0 2px var(--card-green), 0 0 0 8px rgba(34,197,94,0); }
    100% { box-shadow: 0 0 0 2px var(--card-green), 0 0 0 0 rgba(34,197,94,0); }
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
    background: var(--card-green);
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

.t-time { width: 56px; flex-shrink: 0; text-align: center; padding: 7px 4px; border-radius: 11px; background: var(--card-green-soft); }
.t-time strong { display: block; font-size: 15px; font-weight: 800; color: #158a45; letter-spacing: -.2px; line-height: 1.15; }
.t-time span { display: block; font-size: 9px; color: #158a45; text-transform: uppercase; font-weight: 700; letter-spacing: .3px; }

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
.t-body strong { display: block; font-size: 14px; font-weight: 700; color: var(--color-text); word-break: break-word; }
.t-body .t-meta { font-size: 12px; color: var(--color-text-muted); margin-top: 3px; }
.t-body .t-melayani { font-size: 11.5px; color: #158a45; margin-top: 5px; font-weight: 600; }

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
.btn-edit   { background: var(--card-green-soft); color: #158a45; }
.btn-delete { background: #FCE9E9; color: var(--color-danger); }

.btn-detail:hover { background: var(--card-blue); color: white; transform: translateY(-2px); }
.btn-edit:hover   { background: var(--card-green); color: white; transform: translateY(-2px); }
.btn-delete:hover { background: var(--color-danger); color: white; transform: translateY(-2px); }

/* ===== EMPTY STATE ===== */

.bt2-empty {
    text-align: center;
    padding: 70px 20px;
    color: var(--color-text-muted);
    background: var(--color-surface);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-lg);
}

.bt2-empty .e-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--card-green-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.bt2-empty strong { display: block; font-size: 14px; color: var(--color-text); margin-bottom: 4px; }
.bt2-empty p { margin: 0; font-size: 12.5px; }

@media (max-width: 700px) {
    .bt2-hero { flex-direction: column; align-items: flex-start; }
    .bt2-hero-stats { width: 100%; }
    .bt2-stat-pill { flex: 1; }
    .btn-add-fab { width: 100%; justify-content: center; }
    .t-card { flex-wrap: wrap; }
    .t-main { flex-basis: 100%; }
    .t-actions { width: 100%; justify-content: flex-end; }
}

</style>

<div class="content">

    <div class="container-fluid bt2-page">

        <!-- HERO -->

        <div class="bt2-hero">

            <div class="bt2-hero-text">
                <h2>Buku Tamu</h2>
                <p>Linimasa kunjungan tamu yang datang ke instansi, dicatat oleh resepsionis.</p>
            </div>

            <div class="bt2-hero-stats">
                <div class="bt2-stat-pill active">
                    <strong><?= $total_hari_ini; ?></strong>
                    <small>Hari Ini</small>
                </div>
                <div class="bt2-stat-pill">
                    <strong><?= $total_bulan_ini; ?></strong>
                    <small>Bulan Ini</small>
                </div>
                <div class="bt2-stat-pill">
                    <strong><?= $total_tamu; ?></strong>
                    <small>Total</small>
                </div>
            </div>

            <a href="tambah.php" class="btn-add-fab">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>
                Check-in Tamu
            </a>

        </div>

        <!-- SEARCH -->

        <form method="GET" class="bt2-search-bar">
            <input
                type="text"
                name="keyword"
                value="<?= htmlspecialchars($keyword); ?>"
                placeholder="Cari nama, instansi, keperluan, atau petugas..."
            >
            <button type="submit" title="Cari">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
            </button>
            <?php if ($keyword !== '') { ?>
                <a href="index.php" class="bt2-reset-chip">Reset</a>
            <?php } ?>
        </form>

        <!-- TIMELINE -->

        <?php if ($total_tamu > 0) {

            $tanggal_terakhir = null;
            $sedang_buka = false;

            foreach ($tamu_list as $t) {

                $tgl = $t['tanggal_kunjungan'];
                $is_today = ($tgl === $hari_ini);

                if ($tgl !== $tanggal_terakhir) {

                    if ($sedang_buka) {
                        echo '</div></div>';
                    }

                    $jumlah_hari = 0;
                    foreach ($tamu_list as $cek) {
                        if ($cek['tanggal_kunjungan'] === $tgl) {
                            $jumlah_hari++;
                        }
                    }

                    $ts = strtotime($tgl);
                    $label_tanggal = $nama_hari[date('l', $ts)] . ', ' . date('d', $ts) . ' ' . $nama_bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);

                    if ($is_today) {
                        $label_tanggal = '✨ Hari Ini — ' . $label_tanggal;
                    }

                    echo '<div class="day-group">';
                    echo '<div class="day-label' . ($is_today ? ' today' : '') . '">' . htmlspecialchars($label_tanggal) . ' <span class="day-count">' . $jumlah_hari . ' tamu</span></div>';
                    echo '<div class="timeline">';

                    $tanggal_terakhir = $tgl;
                    $sedang_buka = true;
                }

                $jam_tampil = !empty($t['jam_kunjungan']) ? date('H:i', strtotime($t['jam_kunjungan'])) : '--:--';
                $inisial = strtoupper(substr($t['nama'], 0, 1));
                $warna = warnaAvatar($t['nama']);

        ?>
            <div class="t-item">
                <div class="t-dot<?= $is_today ? ' today-dot' : ''; ?>"></div>

                <div class="t-card">

                    <a href="detail.php?id=<?= (int) $t['id']; ?>" class="t-main">

                        <div class="t-time">
                            <strong><?= $jam_tampil; ?></strong>
                            <span>WITA</span>
                        </div>

                        <div class="t-avatar" style="background:<?= $warna['bg']; ?>; color:<?= $warna['fg']; ?>;"><?= htmlspecialchars($inisial); ?></div>

                        <div class="t-body">
                            <strong><?= htmlspecialchars($t['nama']); ?></strong>
                            <div class="t-meta">
                                <?= htmlspecialchars(!empty($t['instansi_asal']) ? $t['instansi_asal'] : 'Tanpa instansi'); ?>
                                <?php if (!empty($t['keperluan'])) { ?> &middot; <?= htmlspecialchars($t['keperluan']); ?><?php } ?>
                            </div>
                            <?php if (!empty($t['yang_melayani'])) { ?>
                                <div class="t-melayani">Dilayani: <?= htmlspecialchars($t['yang_melayani']); ?></div>
                            <?php } ?>
                        </div>

                    </a>

                    <div class="t-actions">

                        <a href="detail.php?id=<?= (int) $t['id']; ?>" class="action-btn btn-detail" title="Detail"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>

                        <a href="edit.php?id=<?= (int) $t['id']; ?>" class="action-btn btn-edit" title="Edit"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"></path></svg></a>

                        <?php if ($is_admin) { ?>
                        <form method="POST" action="hapus.php" onsubmit="return confirm('Yakin ingin menghapus data tamu ini?')">
                            <input type="hidden" name="id" value="<?= (int) $t['id']; ?>">
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
            <div class="bt2-empty">
                <div class="e-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-6 6-6"></path><circle cx="17" cy="8" r="3"></circle><path d="M15 14c3.3 0 6 2.7 6 6"></path></svg></div>
                <strong>Belum ada tamu tercatat</strong>
                <p>Klik "Check-in Tamu" untuk mencatat kunjungan pertama.</p>
            </div>
        <?php } ?>

    </div>

</div>

<?php

include "../layouts/footer.php";

?>