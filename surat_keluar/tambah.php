<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

$role = roleUser() ?? '';

if ($role !== 'admin' && $role !== 'operator') {
    header("Location: index.php");
    exit;
}

$error = "";

$kode_depan  = "";
$kode_tengah = "";
$tanggal_surat = date('Y-m-d');
$tujuan = "";
$perihal = "";
$keterangan = "";

$nomor_otomatis = "001";
$preview_nomor = "800/001/SEKRT/BKPSDM/" . date('Y');

function getNomorBerikutnya($koneksi, $tahun)
{
    $nomor_urut = 1;
    $tahun = (int) $tahun;

    $query = mysqli_query($koneksi, "
        SELECT nomor_surat FROM surat_keluar
        WHERE YEAR(tanggal_surat) = '$tahun'
    ");

    if ($query) {
        while ($data = mysqli_fetch_assoc($query)) {
            $bagian = explode('/', $data['nomor_surat']);
            if (isset($bagian[1])) {
                $angka = (int) $bagian[1];
                if ($angka >= $nomor_urut) {
                    $nomor_urut = $angka + 1;
                }
            }
        }
    }

    return str_pad($nomor_urut, 3, '0', STR_PAD_LEFT);
}

$nomor_otomatis = getNomorBerikutnya($koneksi, date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } else {

        $kode_depan  = trim($_POST['kode_depan'] ?? '');
        $kode_tengah = trim($_POST['kode_tengah'] ?? '');
        $tanggal_surat = $_POST['tanggal_surat'] ?? date('Y-m-d');
        $tujuan = trim($_POST['tujuan'] ?? '');
        $perihal = trim($_POST['perihal'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($kode_depan === '') {
            $error = "Bagian depan nomor surat wajib diisi.";
        } elseif ($kode_tengah === '') {
            $error = "Bagian tengah nomor surat wajib diisi.";
        } elseif ($tanggal_surat === '') {
            $error = "Tanggal surat wajib diisi.";
        } elseif ($tujuan === '') {
            $error = "Tujuan surat wajib diisi.";
        } elseif ($perihal === '') {
            $error = "Perihal surat wajib diisi.";
        } else {

            $timestamp = strtotime($tanggal_surat);
            $tahun = (int) date('Y', $timestamp);

            $nomor_otomatis = getNomorBerikutnya($koneksi, $tahun);

            $kode_depan = preg_replace('/[^A-Za-z0-9\-_]/', '', $kode_depan);
            $kode_tengah = preg_replace('/[^A-Za-z0-9\-_]/', '', $kode_tengah);

            $nomor_surat = $kode_depan . "/" . $nomor_otomatis . "/" . $kode_tengah . "/BKPSDM/" . $tahun;

            $nomor_surat_safe = mysqli_real_escape_string($koneksi, $nomor_surat);
            $tanggal_safe = mysqli_real_escape_string($koneksi, $tanggal_surat);
            $tujuan_safe = mysqli_real_escape_string($koneksi, $tujuan);
            $perihal_safe = mysqli_real_escape_string($koneksi, $perihal);
            $keterangan_safe = mysqli_real_escape_string($koneksi, $keterangan);

            $sql = "
                INSERT INTO surat_keluar (nomor_surat, tanggal_surat, tujuan, perihal, keterangan)
                VALUES ('$nomor_surat_safe', '$tanggal_safe', '$tujuan_safe', '$perihal_safe', '$keterangan_safe')
            ";

            $simpan = mysqli_query($koneksi, $sql);

            if ($simpan) {

                $id_surat = mysqli_insert_id($koneksi);

                if ($id_surat > 0) {
                    header("Location: detail.php?id=" . $id_surat);
                    exit;
                }

                header("Location: index.php?tahun=" . $tahun);
                exit;

            } else {
                $error = "Data surat gagal disimpan. " . mysqli_error($koneksi);
            }
        }
    }

    $tahun_preview = !empty($tanggal_surat) ? date('Y', strtotime($tanggal_surat)) : date('Y');
    $nomor_otomatis = getNomorBerikutnya($koneksi, $tahun_preview);

    if ($kode_depan !== '' && $kode_tengah !== '') {
        $preview_nomor = $kode_depan . "/" . $nomor_otomatis . "/" . $kode_tengah . "/BKPSDM/" . $tahun_preview;
    }
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";

?>

<style>

.ci-page { padding-top: 26px; padding-bottom: 40px; display: flex; justify-content: center; }

.ci-wrap { width: 100%; max-width: 1120px; animation: fadeInUp .45s var(--ease-smooth); }

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}

.ci-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.ci-top h2 { margin: 0; font-size: 24px; font-weight: 800; color: var(--color-text); }
.ci-top p { margin: 4px 0 0; font-size: 13px; color: var(--color-text-muted); }

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
    font-size: 12.5px;
    font-weight: 700;
    transition: background .2s var(--ease-smooth), color .2s var(--ease-smooth);
}

.btn-kembali:hover { background: var(--card-orange-soft); color: #c9660f; }
.btn-kembali svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }

.alert-custom {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border-radius: var(--radius-md);
    margin-bottom: 18px;
    font-size: 13px;
    font-weight: 600;
    background: #FCE9E9;
    color: var(--color-danger);
    border: 1px solid #f3c6c6;
}

/* ===== KARTU DUA PANEL ===== */

.ci-card {
    display: grid;
    grid-template-columns: 340px minmax(0, 1fr);
    background: var(--color-surface);
    border-radius: 22px;
    box-shadow: 0 22px 54px rgba(20,20,40,.11);
    border: 1px solid var(--color-border);
    overflow: hidden;
}

/* ===== PANEL KIRI ===== */

.ci-side {
    position: relative;
    background:
        radial-gradient(circle at 15% -5%, rgba(251,140,60,.48), transparent 55%),
        radial-gradient(circle at 95% 105%, rgba(47,111,237,.2), transparent 50%),
        linear-gradient(165deg, rgba(22,22,29,.9), rgba(22,22,29,.99)),
        linear-gradient(135deg, #22242d, #16161d);
    color: #fff;
    overflow: hidden;
}

.ci-side::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.055) 1px, transparent 1px);
    background-size: 16px 16px;
    pointer-events: none;
}

.ci-side::after {
    content: "";
    position: absolute;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    right: -90px;
    top: -90px;
    border: 1px solid rgba(255,255,255,.08);
    box-shadow: 0 0 0 36px rgba(255,255,255,.025);
    pointer-events: none;
}

.ci-side-inner {
    position: sticky;
    top: 92px;
    z-index: 1;
    padding: 38px 26px 30px;
    text-align: center;
}

.ci-avatar {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    background: rgba(251,140,60,.24);
    border: 3px solid rgba(251,140,60,.55);
    color: #ffc899;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 800;
    margin: 0 auto 18px;
    transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s var(--ease-smooth);
}

.ci-avatar svg { width: 38px; height: 38px; stroke: currentColor; stroke-width: 1.7; fill: none; }
.ci-avatar.filled { transform: scale(1.07); box-shadow: 0 0 0 12px rgba(251,140,60,.13); }

.ci-badge {
    display: flex;
    width: fit-content;
    align-items: center;
    gap: 6px;
    margin: 0 auto 14px;
    padding: 6px 15px;
    border-radius: 999px;
    background: rgba(251,140,60,.3);
    color: #ffd2ab;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .4px;
}

.ci-side-nomor {
    font-size: 16px;
    font-weight: 700;
    min-height: 24px;
    word-break: break-all;
    font-family: 'Courier New', monospace;
    line-height: 1.45;
}

.ci-side-nomor.placeholder { color: rgba(255,255,255,.42); font-weight: 400; }

.ci-side-sub { margin-top: 10px; font-size: 12px; color: rgba(255,255,255,.62); line-height: 1.55; min-height: 18px; word-break: break-word; }

.side-divider { height: 1px; background: rgba(255,255,255,.12); margin: 24px 0 18px; }

.side-summary { text-align: left; }

.ss-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px dashed rgba(255,255,255,.1);
}

.ss-row:last-child { border-bottom: none; }

.ss-row small { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: rgba(255,255,255,.5); font-weight: 700; }
.ss-row span { font-size: 12.5px; font-weight: 600; color: #fff; text-align: right; }

.side-hint {
    margin-top: 20px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
    font-size: 11px;
    line-height: 1.6;
    color: rgba(255,255,255,.64);
    text-align: left;
}

/* ===== PANEL KANAN ===== */

.ci-main { padding: 30px 32px 0; min-width: 0; }

.ci-section-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 18px;
    font-size: 11.5px;
    font-weight: 800;
    color: #c9660f;
    text-transform: uppercase;
    letter-spacing: .6px;
}

.ci-section-title::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--color-border);
}

.ci-field { margin-bottom: 22px; }

.ci-field label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 9px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--color-text);
}

.ci-field label svg { width: 15px; height: 15px; stroke: var(--card-orange); stroke-width: 2; fill: none; flex-shrink: 0; }

.required { color: var(--color-danger); }

.ci-input {
    width: 100%;
    height: 49px;
    box-sizing: border-box;
    border: 1.5px solid var(--color-border);
    border-radius: 13px;
    padding: 0 17px;
    outline: none;
    background: var(--color-bg);
    color: var(--color-text);
    font-size: 14px;
    font-family: inherit;
    transition: border-color .2s var(--ease-smooth), background .2s var(--ease-smooth), box-shadow .2s var(--ease-smooth);
}

.ci-input:focus { border-color: var(--card-orange); background: var(--color-surface); box-shadow: 0 0 0 4px var(--card-orange-soft); }

textarea.ci-input { height: auto; min-height: 104px; padding: 14px 17px; resize: vertical; line-height: 1.55; }

.ci-hint { margin-top: 6px; font-size: 11px; color: var(--color-text-muted); }
.ci-hint strong { color: var(--color-text); }

.ci-row-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }

/* ===== KOTAK NOMOR OTOMATIS ===== */

.nomor-box {
    border: 1.5px dashed #f0c395;
    background: linear-gradient(180deg, #FFF7EE, #FFFBF6);
    border-radius: 14px;
    padding: 14px 16px;
}

.nomor-box-label { font-size: 10.5px; color: #a8683a; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px; }

.nomor-box-value {
    font-size: 17px;
    font-weight: 800;
    color: #8a4a17;
    word-break: break-all;
    font-family: 'Courier New', monospace;
    letter-spacing: -.1px;
}

/* ===== FOOTER ===== */

.ci-footer {
    display: flex;
    gap: 12px;
    margin: 8px -32px 0;
    padding: 20px 32px 26px;
    border-top: 1px solid var(--color-border);
    background: linear-gradient(180deg, #ffffff, var(--color-bg));
}

.btn-batal, .btn-checkin {
    height: 51px;
    border: none;
    border-radius: 14px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    transition: transform .25s var(--ease-smooth), background .2s var(--ease-smooth), box-shadow .25s var(--ease-smooth);
}

.btn-batal { flex: 0 0 120px; background: var(--color-surface); color: var(--color-text-muted); border: 1px solid var(--color-border); }
.btn-batal:hover { background: var(--card-orange-soft); color: #c9660f; }

.btn-checkin {
    flex: 1;
    background: linear-gradient(135deg, var(--card-orange), #c9660f);
    color: white;
    box-shadow: 0 12px 24px rgba(251,140,60,.36);
}

.btn-checkin:hover { color: white; transform: translateY(-2px); box-shadow: 0 16px 30px rgba(251,140,60,.46); }
.btn-checkin:active { transform: translateY(0); }

/* ===== RESPONSIVE ===== */

@media (max-width: 960px) {
    .ci-card { grid-template-columns: minmax(0, 1fr); }
    .ci-side-inner { position: static; padding: 30px 22px 24px; }
    .side-hint { display: none; }
    .ci-main { padding: 24px 20px 0; }
    .ci-footer { margin: 8px -20px 0; padding: 18px 20px 22px; }
}

@media (max-width: 560px) {
    .ci-row-2 { grid-template-columns: minmax(0, 1fr); gap: 0; }
    .ci-row-2 > div { margin-bottom: 22px; }
    .ci-row-2 > div:last-child { margin-bottom: 0; }
    .ci-footer { flex-direction: column-reverse; }
    .btn-batal { flex: 1; }
}

</style>

<div class="content">
<div class="container-fluid ci-page">

<div class="ci-wrap">

    <div class="ci-top">
        <div>
            <h2>Tambah Surat Keluar</h2>
            <p>Tambahkan data surat keluar baru. Nomor urut dibuat otomatis oleh sistem.</p>
        </div>
        <a href="index.php" class="btn-kembali">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
            Kembali
        </a>
    </div>

    <?php if ($error !== '') { ?>
        <div class="alert-custom">⚠️ <?= htmlspecialchars($error); ?></div>
    <?php } ?>

    <form method="POST" id="formSuratKeluar" autocomplete="off">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">

        <div class="ci-card">

            <!-- ============ PANEL KIRI (PRATINJAU HIDUP) ============ -->

            <div class="ci-side">
                <div class="ci-side-inner">

                    <div class="ci-avatar" id="pv_avatar">
                        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg>
                    </div>

                    <div class="ci-badge">📤 SURAT KELUAR</div>

                    <div class="ci-side-nomor" id="pv_nomor"><?= htmlspecialchars($preview_nomor); ?></div>
                    <div class="ci-side-sub" id="pv_sub">&nbsp;</div>

                    <div class="side-divider"></div>

                    <div class="side-summary">
                        <div class="ss-row"><small>Tanggal Surat</small><span id="sm_tgl">-</span></div>
                        <div class="ss-row"><small>Nomor Urut</small><span id="sm_urut"><?= htmlspecialchars($nomor_otomatis); ?></span></div>
                        <div class="ss-row"><small>Tahun</small><span id="sm_tahun"><?= date('Y'); ?></span></div>
                    </div>

                    <div class="side-hint">
                        Periksa format nomor surat di atas sebelum menyimpan. Nomor surat tidak dapat diubah setelah disimpan.
                    </div>

                </div>
            </div>

            <!-- ============ PANEL KANAN (FORM) ============ -->

            <div class="ci-main">

                <!-- NOMOR SURAT -->

                <div class="ci-section-title">Nomor Surat</div>

                <div class="ci-field ci-row-2">
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"></path></svg>
                            Bagian Depan <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            name="kode_depan"
                            id="kode_depan"
                            class="ci-input"
                            value="<?= htmlspecialchars($kode_depan); ?>"
                            placeholder="Contoh: 800"
                            autocomplete="off"
                            required
                        >
                        <div class="ci-hint">Contoh: <strong>800</strong></div>
                    </div>
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"></path></svg>
                            Bagian Tengah <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            name="kode_tengah"
                            id="kode_tengah"
                            class="ci-input"
                            value="<?= htmlspecialchars($kode_tengah); ?>"
                            placeholder="Contoh: SEKRT"
                            autocomplete="off"
                            required
                        >
                        <div class="ci-hint">Contoh: <strong>SEKRT</strong></div>
                    </div>
                </div>

                <div class="ci-field ci-row-2">
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                            Tanggal Surat <span class="required">*</span>
                        </label>
                        <input
                            type="date"
                            name="tanggal_surat"
                            id="tanggal_surat"
                            class="ci-input"
                            value="<?= htmlspecialchars($tanggal_surat); ?>"
                            required
                        >
                    </div>
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4l3 2"></path></svg>
                            Nomor Surat Lengkap
                        </label>
                        <div class="nomor-box">
                            <div class="nomor-box-label">Dibuat otomatis</div>
                            <div class="nomor-box-value" id="previewNomorBesar"><?= htmlspecialchars($preview_nomor); ?></div>
                        </div>
                    </div>
                </div>

                <!-- DATA SURAT -->

                <div class="ci-section-title">Data Surat</div>

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Tujuan Surat <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="tujuan"
                        id="f_tujuan"
                        class="ci-input"
                        value="<?= htmlspecialchars($tujuan); ?>"
                        placeholder="Masukkan tujuan surat"
                        autocomplete="off"
                        oninput="updatePreview()"
                        required
                    >
                </div>

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        Perihal <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="perihal"
                        id="f_perihal"
                        class="ci-input"
                        value="<?= htmlspecialchars($perihal); ?>"
                        placeholder="Masukkan perihal surat"
                        autocomplete="off"
                        oninput="updatePreview()"
                        required
                    >
                </div>

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M9 13h6M9 17h6"></path></svg>
                        Keterangan
                    </label>
                    <textarea
                        name="keterangan"
                        class="ci-input"
                        placeholder="Masukkan keterangan jika diperlukan"
                    ><?= htmlspecialchars($keterangan); ?></textarea>
                </div>

                <!-- FOOTER -->

                <div class="ci-footer">
                    <a href="index.php" class="btn-batal">Batal</a>
                    <button type="submit" class="btn-checkin">
                        ✅ Simpan Surat
                    </button>
                </div>

            </div>

        </div>

    </form>

</div>

</div>
</div>

<script>

var svgAmplop = '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg>';
var nomorOtomatis = "<?= htmlspecialchars($nomor_otomatis); ?>";

function formatTanggal(nilai) {
    if (!nilai) { return '-'; }
    var d = new Date(nilai + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function updatePreview() {

    var kodeDepan = document.getElementById('kode_depan').value.trim();
    var kodeTengah = document.getElementById('kode_tengah').value.trim();
    var tanggal = document.getElementById('tanggal_surat').value;
    var tujuan = document.getElementById('f_tujuan').value.trim();
    var perihal = document.getElementById('f_perihal').value.trim();

    var tahun = new Date().getFullYear();

    if (tanggal) {
        tahun = new Date(tanggal + 'T00:00:00').getFullYear();
    }

    var depan = kodeDepan !== '' ? kodeDepan : '800';
    var tengah = kodeTengah !== '' ? kodeTengah : 'SEKRT';

    var nomor = depan + '/' + nomorOtomatis + '/' + tengah + '/BKPSDM/' + tahun;

    var pvNomor = document.getElementById('pv_nomor');
    pvNomor.textContent = nomor;

    if (kodeDepan === '' && kodeTengah === '') {
        pvNomor.classList.add('placeholder');
    } else {
        pvNomor.classList.remove('placeholder');
    }

    document.getElementById('previewNomorBesar').textContent = nomor;

    var pvAvatar = document.getElementById('pv_avatar');

    if (tujuan !== '') {
        pvAvatar.textContent = tujuan.charAt(0).toUpperCase();
        pvAvatar.classList.add('filled');
    } else {
        pvAvatar.innerHTML = svgAmplop;
        pvAvatar.classList.remove('filled');
    }

    var sub = [];
    if (tujuan !== '') sub.push('Kepada: ' + tujuan);
    if (perihal !== '') sub.push(perihal);
    document.getElementById('pv_sub').textContent = sub.length > 0 ? sub.join(' · ') : '\u00A0';

    document.getElementById('sm_tgl').textContent = formatTanggal(tanggal);
    document.getElementById('sm_urut').textContent = nomorOtomatis;
    document.getElementById('sm_tahun').textContent = tahun;

}

document.getElementById('kode_depan').addEventListener('input', updatePreview);
document.getElementById('kode_tengah').addEventListener('input', updatePreview);
document.getElementById('tanggal_surat').addEventListener('change', updatePreview);
document.addEventListener('DOMContentLoaded', updatePreview);

</script>

<?php

include "../layouts/footer.php";

?>