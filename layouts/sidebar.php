<?php
/* =========================================================
   SIDEBAR e-SIMPEL
   BKPSDM KABUPATEN TAPIN
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Alamat dasar aplikasi dibaca otomatis dari nama folder, jadi folder boleh
   diganti namanya (mis. /e-simpel) tanpa mengubah file ini. */
$base_url = "/" . basename(dirname(__DIR__));

$nama_user = $_SESSION['username'] ?? 'User';
$role      = $_SESSION['role'] ?? 'operator';
$isAdminMenu = in_array(strtolower(trim($role)), ['admin', 'administrator'], true);

$current_uri  = $_SERVER['REQUEST_URI'] ?? '';
$current_path = parse_url($current_uri, PHP_URL_PATH);

function menuAktif($path)
{
    global $current_path, $base_url;
    $target = $base_url . $path;
    return (strpos($current_path, $target) !== false);
}

$dashboardAktif = menuAktif('/dashboard/');

$administrasiAktif =
    menuAktif('/surat_masuk/') ||
    menuAktif('/surat_keluar/') ||
    menuAktif('/arsip/') ||
    menuAktif('/disposisi/') ||
    menuAktif('/agenda/');

$tamuAktif = menuAktif('/buku_tamu/');

$kepegawaianAktif =
    menuAktif('/pegawai/') ||
    menuAktif('/absensi/') ||
    menuAktif('/cuti/') ||
    menuAktif('/tugas_luar/');

$laporanAktif = menuAktif('/laporan/');

/* jumlah pendaftaran akun yang menunggu persetujuan (untuk lencana) */
$jmlPendaftar = 0;

if ($isAdminMenu && isset($koneksi) && $koneksi) {
    try {
        $rp = mysqli_query($koneksi, "SELECT COUNT(*) AS n FROM pendaftaran_akun WHERE status = 'menunggu'");
        $jmlPendaftar = $rp ? (int) mysqli_fetch_assoc($rp)['n'] : 0;
    } catch (Throwable $e) {
        $jmlPendaftar = 0;
    }
}

$pengaturanAktif =
    menuAktif('/users/') ||
    menuAktif('/pengaturan/');
?>


<style>

/* =========================================================
   SIDEBAR CONTAINER
========================================================= */

.sidebar-new {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: var(--sb-bg);
    color: var(--sb-text);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    transition: width .3s var(--ease-smooth), transform .3s var(--ease-smooth);
    box-shadow: 8px 0 30px rgba(0,0,0,.10);
}

/* =========================================================
   BRAND
========================================================= */

.sidebar-brand {
    height: 78px;
    padding: 0 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--sb-border);
}

.sidebar-logo {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: transparent;
    color: var(--color-primary-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(0,0,0,.18);
}

.sidebar-brand-text { min-width: 0; }

.sidebar-brand-text strong {
    display: block;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: .2px;
    color: #fff;
}

.sidebar-brand-text span {
    display: block;
    margin-top: 2px;
    color: var(--sb-text-muted);
    font-size: 11px;
}

/* =========================================================
   USER MINI PROFILE
========================================================= */

.sidebar-user {
    margin: 16px 14px 6px;
    padding: 11px;
    border-radius: var(--radius-md);
    background: var(--sb-bg-soft);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background .2s var(--ease-smooth);
}

.sidebar-user:hover { background: var(--sb-bg-hover); }

.user-avatar {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
}

.user-info { min-width: 0; }

.user-info strong {
    display: block;
    color: #fff;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-info span {
    display: block;
    margin-top: 2px;
    color: var(--sb-text-dim);
    font-size: 10px;
    text-transform: capitalize;
}

/* =========================================================
   SCROLL AREA
========================================================= */

.sidebar-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 10px 12px 20px;
}

.sidebar-scroll::-webkit-scrollbar { width: 4px; }
.sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.sidebar-scroll::-webkit-scrollbar-thumb {
    background: #35353f;
    border-radius: 10px;
}

/* =========================================================
   MENU ITEM (level 1)
========================================================= */

.sidebar-link {
    position: relative;
    width: 100%;
    min-height: 44px;
    margin-bottom: 4px;
    padding: 0 13px;
    border: none;
    border-radius: var(--radius-md);
    background: transparent;
    color: var(--sb-text-muted);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 11px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background .22s var(--ease-smooth),
                color .22s var(--ease-smooth),
                box-shadow .22s var(--ease-smooth);
}

.sidebar-link:hover {
    background: var(--sb-bg-hover);
    color: #ffffff;
}

/* Active: solid orange pill, seperti pada gambar acuan */
.sidebar-link.active {
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    color: #ffffff;
    box-shadow: 0 6px 16px rgba(251,124,60,.35);
}

.sidebar-link.active .menu-icon svg { stroke: #ffffff; }

/* =========================================================
   ICON
========================================================= */

.menu-icon {
    width: 19px;
    height: 19px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: inherit;
}

.menu-icon svg {
    width: 17px;
    height: 17px;
    stroke: currentColor;
    stroke-width: 1.8;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    transition: stroke .22s var(--ease-smooth);
}

.menu-text { flex: 1; text-align: left; }

/* =========================================================
   ARROW DROPDOWN
========================================================= */

.menu-arrow {
    width: 15px;
    height: 15px;
    transition: transform .28s var(--ease-smooth);
}

.menu-arrow svg {
    width: 13px;
    height: 13px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
}

.menu-dropdown.submenu-open .menu-arrow { transform: rotate(180deg); }

/* =========================================================
   SUBMENU (level 2)
========================================================= */

.submenu {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transform: translateY(-4px);
    transition: max-height .32s var(--ease-smooth),
                opacity .25s var(--ease-smooth),
                transform .25s var(--ease-smooth);
    margin: 2px 0 4px 30px;
}

.submenu.open {
    max-height: 500px;
    opacity: 1;
    transform: translateY(0);
}

.submenu a {
    position: relative;
    display: flex;
    align-items: center;
    min-height: 38px;
    padding: 0 11px;
    color: var(--sb-text-dim);
    text-decoration: none;
    font-size: 12px;
    border-radius: 8px;
    transition: background .2s var(--ease-smooth),
                color .2s var(--ease-smooth),
                padding-left .2s var(--ease-smooth);
}

.submenu a::before {
    content: "";
    width: 4px;
    height: 4px;
    margin-right: 10px;
    border-radius: 50%;
    background: #4a4a54;
    transition: background .2s var(--ease-smooth), transform .2s var(--ease-smooth);
}

.submenu a:hover {
    background: var(--sb-bg-soft);
    color: #ffffff;
    padding-left: 15px;
}

.submenu a:hover::before {
    background: var(--color-primary);
    transform: scale(1.5);
}

.submenu a.active {
    color: var(--color-primary);
    background: rgba(251,124,60,.10);
    font-weight: 600;
}

.submenu a.active::before {
    background: var(--color-primary);
    transform: scale(1.5);
    box-shadow: 0 0 8px rgba(251,124,60,.6);
}

.submenu a.disabled {
    pointer-events: none;
    opacity: .4;
}

/* =========================================================
   SECTION SPACER (pengganti label section lama)
========================================================= */

.menu-gap { height: 10px; }

/* =========================================================
   LOGOUT
========================================================= */

.sidebar-bottom {
    padding: 12px 12px 16px;
    border-top: 1px solid var(--sb-border);
}

.logout-link { color: var(--sb-text-muted); }

.logout-link:hover {
    color: #ff8a5c;
    background: rgba(251,124,60,.09);
}

/* =========================================================
   MOBILE OVERLAY
========================================================= */

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    backdrop-filter: blur(2px);
    z-index: 1040;
    transition: opacity .25s var(--ease-smooth);
}

.sidebar-overlay.show { display: block; }

/* =========================================================
   CONTENT OFFSET
========================================================= */

.content {
    margin-left: 260px;
    transition: margin-left .3s var(--ease-smooth);
    background: var(--color-bg);
    min-height: 100vh;
}

/* =========================================================
   COLLAPSED DESKTOP
========================================================= */

body.sidebar-collapsed .sidebar-new { width: 82px; }
body.sidebar-collapsed .content { margin-left: 82px; }

body.sidebar-collapsed .sidebar-brand-text,
body.sidebar-collapsed .sidebar-user,
body.sidebar-collapsed .menu-text,
body.sidebar-collapsed .menu-arrow {
    display: none;
}

body.sidebar-collapsed .sidebar-brand { justify-content: center; padding: 0; }
body.sidebar-collapsed .sidebar-link { justify-content: center; padding: 0; }
body.sidebar-collapsed .submenu { display: none; }

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 991px) {
    .sidebar-new { transform: translateX(-100%); }
    .sidebar-new.mobile-open { transform: translateX(0); }
    .content { margin-left: 0 !important; }
}

.sidebar-new, .sidebar-link, .submenu, .submenu a {
    -webkit-tap-highlight-color: transparent;
}

</style>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar-new" id="sidebarNew">

    <!-- BRAND -->
    <div class="sidebar-brand">
        <div class="sidebar-logo"><svg width="42" height="42" aria-label="e-SIMPEL" viewBox="20 10 870 870"><defs><linearGradient id="sbbg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1D528E"/><stop offset=".55" stop-color="#0B315E"/><stop offset="1" stop-color="#05264B"/></linearGradient><linearGradient id="sbor" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#E8622A"/><stop offset=".55" stop-color="#FDA02E"/><stop offset="1" stop-color="#FFCB4A"/></linearGradient><linearGradient id="sbor2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFC443"/><stop offset="1" stop-color="#EE6A27"/></linearGradient><linearGradient id="sbwh" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#E4E9F0"/></linearGradient></defs><rect x="20" y="10" width="870" height="870" rx="195" fill="url(#sbbg)"/><path d="M195 676Q200 652 252 647L420 704L556 664L480 766Q466 786 436 783L215 696Q194 688 195 676Z" fill="url(#sbor2)"/><path d="M478 312L690 425L455 546L215 432Z" fill="url(#sbor)"/><path d="M690 425Q745 445 745 520Q743 585 690 604L452 714Q430 722 410 706L250 656L455 548Z" fill="url(#sbwh)"/><path d="M580 105Q560 98 535 110L245 238Q170 275 165 340Q165 400 215 432L478 308L692 205Q705 196 696 182Z" fill="url(#sbwh)"/></svg></div>
        <div class="sidebar-brand-text">
            <strong>e-SIMPEL</strong>
            <span>BKPSDM Kabupaten Tapin</span>
        </div>
    </div>

    <!-- USER -->
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($nama_user, 0, 1)); ?></div>
        <div class="user-info">
            <strong><?= htmlspecialchars($nama_user, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <!-- MENU -->
    <div class="sidebar-scroll">

        <!-- DASHBOARD -->
        <a href="<?= $base_url; ?>/dashboard/index.php"
           class="sidebar-link <?= $dashboardAktif ? 'active' : ''; ?>">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                </svg>
            </span>
            <span class="menu-text">Dashboard</span>
        </a>

        <div class="menu-gap"></div>

        <!-- ADMINISTRASI -->
        <button type="button"
                class="sidebar-link menu-dropdown <?= $administrasiAktif ? 'active submenu-open' : ''; ?>"
                data-menu="administrasi">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M4 4h16v16H4z"></path>
                    <path d="M8 8h8"></path>
                    <path d="M8 12h8"></path>
                    <path d="M8 16h5"></path>
                </svg>
            </span>
            <span class="menu-text">Administrasi</span>
            <span class="menu-arrow">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
        </button>

        <div class="submenu <?= $administrasiAktif ? 'open' : ''; ?>" id="submenu-administrasi">
            <a href="<?= $base_url; ?>/surat_masuk/index.php"
               class="<?= menuAktif('/surat_masuk/') ? 'active' : ''; ?>">Surat Masuk</a>
            <a href="<?= $base_url; ?>/surat_keluar/index.php"
               class="<?= menuAktif('/surat_keluar/') ? 'active' : ''; ?>">Surat Keluar</a>
            <a href="<?= $base_url; ?>/agenda/index.php" class="<?= menuAktif('/agenda/') ? 'active' : ''; ?>">Agenda</a>
        </div>

        <div class="menu-gap"></div>

        <!-- LAYANAN TAMU -->
        <button type="button"
                class="sidebar-link menu-dropdown <?= $tamuAktif ? 'active submenu-open' : ''; ?>"
                data-menu="tamu">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M5 21c0-4 3-7 7-7s7 3 7 7"></path>
                </svg>
            </span>
            <span class="menu-text">Layanan Tamu</span>
            <span class="menu-arrow">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
        </button>

        <div class="submenu <?= $tamuAktif ? 'open' : ''; ?>" id="submenu-tamu">
            <a href="<?= $base_url; ?>/buku_tamu/index.php"
               class="<?= menuAktif('/buku_tamu/') ? 'active' : ''; ?>">Buku Tamu</a>
        </div>

        <div class="menu-gap"></div>

        <!-- KEPEGAWAIAN -->
        <button type="button"
                class="sidebar-link menu-dropdown <?= $kepegawaianAktif ? 'active submenu-open' : ''; ?>"
                data-menu="kepegawaian">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <circle cx="9" cy="8" r="3"></circle>
                    <path d="M3 20c0-3.3 2.7-6 6-6"></path>
                    <circle cx="17" cy="8" r="3"></circle>
                    <path d="M15 14c3.3 0 6 2.7 6 6"></path>
                </svg>
            </span>
            <span class="menu-text">Kepegawaian</span>
            <span class="menu-arrow">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
        </button>

        <div class="submenu <?= $kepegawaianAktif ? 'open' : ''; ?>" id="submenu-kepegawaian">
            <a href="<?= $base_url; ?>/pegawai/index.php"
               class="<?= menuAktif('/pegawai/') ? 'active' : ''; ?>">Data Pegawai</a>
        </div>

        <div class="menu-gap"></div>

        <!-- LAPORAN -->
        <button type="button"
                class="sidebar-link menu-dropdown <?= $laporanAktif ? 'active submenu-open' : ''; ?>"
                data-menu="laporan">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M4 19V5"></path>
                    <path d="M4 19h17"></path>
                    <polyline points="7 15 11 11 14 13 20 7"></polyline>
                </svg>
            </span>
            <span class="menu-text">Laporan</span>
            <span class="menu-arrow">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
        </button>

        <div class="submenu <?= $laporanAktif ? 'open' : ''; ?>" id="submenu-laporan">
            <a href="<?= $base_url; ?>/laporan/pegawai.php"
               class="<?= menuAktif('/laporan/pegawai.php') ? 'active' : ''; ?>">Laporan Pegawai</a>
            <a href="<?= $base_url; ?>/laporan/surat_masuk.php"
               class="<?= menuAktif('/laporan/surat_masuk.php') ? 'active' : ''; ?>">Laporan Surat Masuk</a>
            <a href="<?= $base_url; ?>/laporan/surat_keluar.php"
               class="<?= menuAktif('/laporan/surat_keluar.php') ? 'active' : ''; ?>">Laporan Surat Keluar</a>
            <a href="<?= $base_url; ?>/laporan/buku_tamu.php"
               class="<?= menuAktif('/laporan/buku_tamu.php') || menuAktif('/laporan/cetak_buku_tamu.php') ? 'active' : ''; ?>">Laporan Buku Tamu</a>
        </div>

        <?php if ($isAdminMenu) { ?>

        <div class="menu-gap"></div>

        <!-- PENGATURAN -->
        <button type="button"
                class="sidebar-link menu-dropdown <?= $pengaturanAktif ? 'active submenu-open' : ''; ?>"
                data-menu="pengaturan">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V20h-2.5v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8 15a1.7 1.7 0 0 0-1.5-1H6.4v-2.5h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V5h2.5v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1V14h-.1a1.7 1.7 0 0 0-1.5 1z"></path>
                </svg>
            </span>
            <span class="menu-text">Pengaturan</span>
            <span class="menu-arrow">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
        </button>

        <div class="submenu <?= $pengaturanAktif ? 'open' : ''; ?>" id="submenu-pengaturan">
            <a href="<?= $base_url; ?>/users/index.php"
               class="<?= (menuAktif('/users/') && !menuAktif('/users/pendaftaran.php')) ? 'active' : ''; ?>">Data User</a>
            <a href="<?= $base_url; ?>/users/pendaftaran.php"
               class="<?= menuAktif('/users/pendaftaran.php') ? 'active' : ''; ?>">Pendaftaran Akun<?php if ($jmlPendaftar > 0) { ?> <span style="display:inline-block;min-width:18px;padding:1px 6px;margin-left:4px;border-radius:999px;background:#ef4444;color:#fff;font-size:10px;font-weight:800;text-align:center"><?= $jmlPendaftar; ?></span><?php } ?></a>
            <a href="<?= $base_url; ?>/pengaturan/cadangan.php"
               class="<?= menuAktif('/pengaturan/cadangan.php') ? 'active' : ''; ?>">Cadangan Database</a>
            <a href="<?= $base_url; ?>/pengaturan/riwayat.php"
               class="<?= menuAktif('/pengaturan/riwayat.php') ? 'active' : ''; ?>">Riwayat Aktivitas</a>
        </div>

        <?php } ?>

    </div>

    <!-- LOGOUT -->
    <div class="sidebar-bottom">
        <a href="<?= $base_url; ?>/logout.php" class="sidebar-link logout-link">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5"></path>
                    <path d="M15 12H3"></path>
                    <path d="M21 3v18"></path>
                </svg>
            </span>
            <span class="menu-text">Keluar</span>
        </a>
    </div>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const dropdowns = document.querySelectorAll(".menu-dropdown");

    dropdowns.forEach(function (button) {
        button.addEventListener("click", function () {

            const menuName = this.dataset.menu;
            const submenu = document.getElementById("submenu-" + menuName);
            if (!submenu) return;

            const isOpen = submenu.classList.contains("open");

            document.querySelectorAll(".submenu.open").forEach(function (other) {
                if (other !== submenu) {
                    other.classList.remove("open");
                    const parentButton = document.querySelector(
                        '[data-menu="' + other.id.replace("submenu-", "") + '"]'
                    );
                    if (parentButton) parentButton.classList.remove("submenu-open");
                }
            });

            if (isOpen) {
                submenu.classList.remove("open");
                this.classList.remove("submenu-open");
            } else {
                submenu.classList.add("open");
                this.classList.add("submenu-open");
            }
        });
    });

    const sidebar = document.getElementById("sidebarNew");
    const overlay = document.getElementById("sidebarOverlay");
    const toggle  = document.getElementById("sidebarToggle");

    if (toggle) {
        toggle.addEventListener("click", function () {
            if (window.innerWidth <= 991) {
                sidebar.classList.toggle("mobile-open");
                overlay.classList.toggle("show");
            } else {
                document.body.classList.toggle("sidebar-collapsed");
            }
        });
    }

    if (overlay) {
        overlay.addEventListener("click", function () {
            sidebar.classList.remove("mobile-open");
            overlay.classList.remove("show");
        });
    }

    window.addEventListener("resize", function () {
        if (window.innerWidth > 991) {
            sidebar.classList.remove("mobile-open");
            overlay.classList.remove("show");
        }
    });

});
</script>