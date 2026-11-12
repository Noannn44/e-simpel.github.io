<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Semua variabel di file ini diawali nv_ agar tidak menimpa variabel halaman. */

$nv_username = $_SESSION['username'] ?? 'Administrator';
$nv_nama     = $_SESSION['nama'] ?? $nv_username;
$nv_role     = $_SESSION['role'] ?? '';

$nv_role_tampil = ucfirst(strtolower($nv_role));
$nv_nama_tampil = !empty($nv_nama) ? $nv_nama : $nv_username;
$nv_inisial     = strtoupper(substr($nv_nama_tampil, 0, 1));

/* ---------- tanggal & jam (WITA) ---------- */

$nv_hari_map  = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$nv_bulan_pdk = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$nv_sekarang = new DateTime('now', new DateTimeZone('Asia/Makassar'));

$nv_tanggal = $nv_hari_map[(int) $nv_sekarang->format('N')] . ', '
    . $nv_sekarang->format('j') . ' ' . $nv_bulan_pdk[(int) $nv_sekarang->format('n')] . ' ' . $nv_sekarang->format('Y');
$nv_jam = $nv_sekarang->format('H:i');

/* ---------- penunjuk halaman (breadcrumb) ---------- */

$nv_script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$nv_parts  = explode('/', trim($nv_script, '/'));
$nv_file   = array_pop($nv_parts);              // mis. detail.php
$nv_folder = array_pop($nv_parts);              // mis. surat_masuk
$nv_base   = '/' . implode('/', $nv_parts);     // mis. /administrasi-perkantoran
$nv_base   = ($nv_base === '/') ? '' : $nv_base;
$nv_nama_file = preg_replace('/\.php$/', '', (string) $nv_file);

/* [grup, nama modul, halaman indeks modul] */
$nv_modul = [
    'dashboard'    => [null, 'Dashboard', 'dashboard/index.php'],
    'surat_masuk'  => ['Administrasi', 'Surat Masuk', 'surat_masuk/index.php'],
    'surat_keluar' => ['Administrasi', 'Surat Keluar', 'surat_keluar/index.php'],
    'agenda'       => ['Administrasi', 'Agenda', 'agenda/index.php'],
    'buku_tamu'    => ['Layanan Tamu', 'Buku Tamu', 'buku_tamu/index.php'],
    'pegawai'      => ['Kepegawaian', 'Data Pegawai', 'pegawai/index.php'],
    'ketidakhadiran' => ['Kepegawaian', 'Ketidakhadiran Pegawai', 'ketidakhadiran/index.php'],
    'users'        => ['Pengaturan', 'Data User', 'users/index.php'],
    'laporan'      => ['Laporan', null, null],
    'pengaturan'   => ['Pengaturan', null, null],
];

/* nama halaman di dalam modul */
$nv_halaman = [
    'tambah'          => 'Tambah',
    'edit'            => 'Edit',
    'detail'          => 'Detail',
    'disposisi'       => 'Disposisi',
    'catat_disposisi' => 'Catat Hasil Disposisi',
    'cetak_disposisi' => 'Cetak Disposisi',
    'pendaftaran'     => 'Pendaftaran Akun',
    'cadangan'        => 'Cadangan Database',
    'riwayat'         => 'Riwayat Aktivitas',
];

$nv_laporan = [
    'surat_masuk'       => 'Laporan Surat Masuk',
    'surat_keluar'      => 'Laporan Surat Keluar',
    'buku_tamu'         => 'Laporan Buku Tamu',
    'pegawai'           => 'Laporan Data Pegawai',
    'disposisi_surat'   => 'Laporan Disposisi dan Arsip',
    'ketidakhadiran'    => 'Laporan Ketidakhadiran Pegawai',
    'cetak_surat_masuk'  => 'Cetak Surat Masuk',
    'cetak_surat_keluar' => 'Cetak Surat Keluar',
    'cetak_buku_tamu'    => 'Cetak Buku Tamu',
    'cetak_pegawai'      => 'Cetak Data Pegawai',
    'cetak_disposisi_surat' => 'Cetak Laporan Disposisi dan Arsip',
    'cetak_ketidakhadiran' => 'Cetak Laporan Ketidakhadiran',
];

/* hasil: daftar [label, url|null] */
$nv_crumb = [];

if (isset($nv_modul[$nv_folder])) {

    [$nv_grup, $nv_nm, $nv_idx] = $nv_modul[$nv_folder];

    if ($nv_grup !== null) {
        $nv_crumb[] = [$nv_grup, null];
    }

    if ($nv_folder === 'laporan') {

        $nv_crumb[] = [$nv_laporan[$nv_nama_file] ?? 'Laporan', null];

    } elseif ($nv_folder === 'pengaturan') {

        $nv_crumb[] = [$nv_halaman[$nv_nama_file] ?? 'Pengaturan', null];

    } elseif ($nv_folder === 'users' && $nv_nama_file === 'pendaftaran') {

        $nv_crumb[] = ['Pendaftaran Akun', null];

    } else {

        $nv_crumb[] = [$nv_nm, ($nv_nama_file === 'index') ? null : $nv_base . '/' . $nv_idx];

        if ($nv_nama_file !== 'index' && isset($nv_halaman[$nv_nama_file])) {
            $nv_crumb[] = [$nv_halaman[$nv_nama_file], null];
        }
    }
}

if (!$nv_crumb) {
    $nv_crumb[] = ['e-SIMPEL', null];
}

?>

<style>

.navbar {
    height: 74px;
    padding: 0 28px;
    margin-left: 260px;
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    position: sticky;
    top: 0;
    z-index: 1030;
    transition: margin-left .3s var(--ease-smooth);
}

body.sidebar-collapsed .navbar { margin-left: 82px; }

@media (max-width: 991px) {
    .navbar { margin-left: 0 !important; }
}

.navbar-left {
    display: flex;
    align-items: center;
    gap: 18px;
    flex: 1;
    min-width: 0;
}

.sidebar-toggle-btn {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-bg);
    color: var(--color-text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: background .2s var(--ease-smooth), transform .2s var(--ease-smooth);
}

.sidebar-toggle-btn:hover {
    background: var(--color-primary-soft);
    color: var(--color-primary-dark);
}

.sidebar-toggle-btn:active { transform: scale(.92); }

.sidebar-toggle-btn svg {
    width: 20px; height: 20px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
}

.nv-crumb {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    font-size: 13px;
    color: var(--color-text-muted);
    white-space: nowrap;
}

.nv-crumb .sep {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    stroke: #c4c4d0;
    stroke-width: 2.2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.nv-crumb a {
    color: var(--color-text-muted);
    text-decoration: none;
    font-weight: 600;
    transition: color .2s var(--ease-smooth);
}

.nv-crumb a:hover { color: var(--color-primary-dark); }

.nv-crumb .grup { font-weight: 600; }

.nv-crumb .now {
    color: var(--color-text);
    font-weight: 800;
    font-size: 14.5px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nv-clock {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: 1.2;
    padding: 0 4px;
    white-space: nowrap;
}

.nv-clock b { font-size: 14px; font-weight: 800; color: var(--color-text); letter-spacing: .2px; }
.nv-clock small { font-size: 11px; color: var(--color-text-muted); }

.nv-icon-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--color-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    text-decoration: none;
    transition: background .2s var(--ease-smooth), color .2s, transform .2s var(--ease-smooth);
}

.nv-icon-btn:hover { background: var(--color-primary-soft); color: var(--color-primary-dark); transform: translateY(-1px); }

.nv-icon-btn svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
    stroke-width: 2.2;
    fill: none;
    stroke-linecap: round;
}

@media (max-width: 991px) {
    .nv-clock small { display: none; }
    .nv-crumb .grup, .nv-crumb .grup + .sep { display: none; }
}

@media (max-width: 600px) {
    .nv-clock { display: none; }
    .navbar { padding: 0 14px; gap: 10px; }
    .navbar-left { gap: 10px; }
    .nv-crumb a, .nv-crumb .sep { display: none; }
    .nv-crumb .now { font-size: 14px; }
    .user-text, .user-info-nav .chevron { display: none; }
    .user-area { gap: 8px; }
}

.user-area {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-shrink: 0;
}

.notification {
    position: relative;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--color-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 17px;
    transition: background .2s var(--ease-smooth), transform .2s var(--ease-smooth);
}

.notification:hover { background: var(--color-primary-soft); transform: translateY(-1px); }

.notification::after {
    content: "";
    position: absolute;
    top: 9px;
    right: 10px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--color-danger);
    border: 2px solid var(--color-surface);
}

.user-info-nav {
    display: flex;
    align-items: center;
    gap: 11px;
    cursor: pointer;
    padding: 6px 10px 6px 6px;
    border-radius: 999px;
    transition: background .2s var(--ease-smooth);
}

.user-info-nav:hover { background: var(--color-bg); }

.user-avatar-nav {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
}

.user-text { line-height: 1.25; }

.user-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text);
    white-space: nowrap;
}

.user-role {
    font-size: 11px;
    color: var(--color-text-muted);
}

.user-info-nav .chevron {
    color: var(--color-text-muted);
    font-size: 14px;
    transition: transform .2s var(--ease-smooth);
}

.user-info-nav:hover .chevron { transform: translateY(2px); }

</style>

<div class="navbar">

    <div class="navbar-left">

        <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" title="Buka/Tutup Menu">
            <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>

        <nav class="nv-crumb" aria-label="Penunjuk halaman">
            <?php foreach ($nv_crumb as $nv_i => $nv_c) {

                $nv_terakhir = ($nv_i === count($nv_crumb) - 1);

                if ($nv_i > 0) { ?>
                    <svg class="sep" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg>
                <?php }

                if ($nv_terakhir) { ?>
                    <span class="now"><?= htmlspecialchars($nv_c[0]); ?></span>
                <?php } elseif ($nv_c[1] !== null) { ?>
                    <a href="<?= htmlspecialchars($nv_c[1]); ?>"><?= htmlspecialchars($nv_c[0]); ?></a>
                <?php } else { ?>
                    <span class="grup"><?= htmlspecialchars($nv_c[0]); ?></span>
                <?php }
            } ?>
        </nav>

    </div>

    <div class="user-area">

        <div class="nv-clock" title="Waktu Indonesia Tengah">
            <b id="nvJam"><?= $nv_jam; ?> WITA</b>
            <small id="nvTanggal"><?= htmlspecialchars($nv_tanggal); ?></small>
        </div>

        <div class="notification" title="Notifikasi">🔔</div>

        <div class="user-info-nav">
            <div class="user-avatar-nav"><?= $nv_inisial; ?></div>
            <div class="user-text">
                <div class="user-name"><?= htmlspecialchars($nv_nama_tampil); ?></div>
                <div class="user-role"><?= htmlspecialchars($nv_role_tampil); ?></div>
            </div>
            <span class="chevron">⌄</span>
        </div>

    </div>

</div>

<script>
/* jam & tanggal hidup, zona waktu WITA (Asia/Makassar) */
(function () {
    var jam = document.getElementById('nvJam');
    var tgl = document.getElementById('nvTanggal');
    if (!jam || !tgl) { return; }

    var hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    function perbarui() {
        try {
            var f = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Asia/Makassar', hour12: false,
                year: 'numeric', month: 'numeric', day: 'numeric',
                hour: '2-digit', minute: '2-digit', weekday: 'short'
            });
            var p = {};
            f.formatToParts(new Date()).forEach(function (x) { p[x.type] = x.value; });

            var peta = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };

            jam.textContent = p.hour + ':' + p.minute + ' WITA';
            tgl.textContent = hari[peta[p.weekday]] + ', ' + parseInt(p.day, 10) + ' ' + bulan[parseInt(p.month, 10) - 1] + ' ' + p.year;
        } catch (e) { /* biarkan tampilan dari server */ }
    }

    perbarui();
    setInterval(perbarui, 15000);
})();
</script>