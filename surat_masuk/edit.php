<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

adminDanOperator();

include "../config/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>
        alert('ID surat tidak valid.');
        window.location='index.php';
    </script>";
    exit;
}

$query = mysqli_query($koneksi, "SELECT * FROM surat_masuk WHERE id = $id LIMIT 1");

if (!$query || mysqli_num_rows($query) === 0) {
    echo "<script>
        alert('Data surat tidak ditemukan.');
        window.location='index.php';
    </script>";
    exit;
}

$data = mysqli_fetch_assoc($query);

$jenis_aktif = ($data['jenis_surat'] ?? 'Biasa') === 'Undangan' ? 'Undangan' : 'Biasa';
$is_undangan = ($jenis_aktif === 'Undangan');
$sifat_aktif = $data['sifat'] ?? 'Biasa';

$file_surat = $data['file_surat'] ?? '';
$file_tersedia = (!empty($file_surat) && file_exists(__DIR__ . '/uploads/' . $file_surat));

$tanggal_acara_value = (!empty($data['tanggal_acara']) && $data['tanggal_acara'] !== '0000-00-00') ? $data['tanggal_acara'] : '';

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

.btn-kembali:hover { background: var(--card-blue-soft); color: var(--card-blue); }
.btn-kembali svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }

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
        radial-gradient(circle at 15% -5%, rgba(47,111,237,.45), transparent 55%),
        radial-gradient(circle at 95% 105%, rgba(139,92,246,.26), transparent 50%),
        linear-gradient(165deg, rgba(22,22,29,.9), rgba(22,22,29,.99)),
        linear-gradient(135deg, #22242d, #16161d);
    color: #fff;
    overflow: hidden;
    transition: background .4s var(--ease-smooth);
}

.ci-side.theme-undangan {
    background:
        radial-gradient(circle at 15% -5%, rgba(139,92,246,.5), transparent 55%),
        radial-gradient(circle at 95% 105%, rgba(47,111,237,.22), transparent 50%),
        linear-gradient(165deg, rgba(22,22,29,.9), rgba(22,22,29,.99)),
        linear-gradient(135deg, #22242d, #16161d);
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
    background: rgba(47,111,237,.24);
    border: 3px solid rgba(47,111,237,.55);
    color: #a9c4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 800;
    margin: 0 auto 18px;
    box-shadow: 0 0 0 12px rgba(47,111,237,.12);
    transition: background .3s var(--ease-smooth), border-color .3s var(--ease-smooth), color .3s var(--ease-smooth), box-shadow .3s var(--ease-smooth);
}

.theme-undangan .ci-avatar { background: rgba(139,92,246,.26); border-color: rgba(139,92,246,.6); color: #cbb2ff; box-shadow: 0 0 0 12px rgba(139,92,246,.14); }

.ci-badge {
    display: flex;
    width: fit-content;
    align-items: center;
    gap: 6px;
    margin: 0 auto 14px;
    padding: 6px 15px;
    border-radius: 999px;
    background: rgba(47,111,237,.28);
    color: #bcd2ff;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .4px;
    transition: background .3s var(--ease-smooth), color .3s var(--ease-smooth);
}

.theme-undangan .ci-badge { background: rgba(139,92,246,.3); color: #d9c9ff; }

.ci-side-nomor {
    font-size: 17px;
    font-weight: 700;
    min-height: 24px;
    word-break: break-word;
    font-family: 'Courier New', monospace;
    line-height: 1.4;
}

.ci-side-nomor.placeholder { color: rgba(255,255,255,.4); font-weight: 400; font-style: italic; font-family: inherit; font-size: 14px; }

.ci-side-sub { margin-top: 8px; font-size: 12px; color: rgba(255,255,255,.6); line-height: 1.55; min-height: 18px; word-break: break-word; }

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
    color: rgba(255,255,255,.62);
    text-align: left;
}

/* ===== PANEL KANAN ===== */

.ci-main { padding: 30px 32px 0; min-width: 0; }

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

.ci-field label svg { width: 15px; height: 15px; stroke: var(--card-blue); stroke-width: 2; fill: none; flex-shrink: 0; }

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

.ci-input:focus { border-color: var(--card-blue); background: var(--color-surface); box-shadow: 0 0 0 4px var(--card-blue-soft); }

textarea.ci-input { height: auto; min-height: 104px; padding: 14px 17px; resize: vertical; line-height: 1.55; }

.ci-row-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }

/* ===== SEGMENTED JENIS SURAT ===== */

.segmented {
    position: relative;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    background: var(--color-bg);
    border-radius: 16px;
    padding: 6px;
    gap: 6px;
}

.segmented-thumb {
    position: absolute;
    top: 6px;
    left: 6px;
    width: calc(50% - 6px);
    height: calc(100% - 12px);
    border-radius: 12px;
    background: linear-gradient(135deg, var(--card-blue), #1d4fa8);
    box-shadow: 0 8px 18px rgba(47,111,237,.32);
    transition: transform .3s cubic-bezier(.4,0,.2,1), background .3s var(--ease-smooth), box-shadow .3s var(--ease-smooth);
    z-index: 0;
}

.segmented-thumb.pos-undangan {
    transform: translateX(100%);
    background: linear-gradient(135deg, #a78bfa, #6d3fc9);
    box-shadow: 0 8px 18px rgba(139,92,246,.32);
}

.segment-btn {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 15px 10px;
    border: none;
    background: transparent;
    border-radius: 12px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 750;
    color: var(--color-text-muted);
    transition: color .3s var(--ease-smooth);
}

.segment-btn svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 1.8; fill: none; flex-shrink: 0; }
.segment-btn.active { color: #ffffff; }

.segment-desc {
    margin-top: 10px;
    padding: 12px 15px;
    border-radius: 12px;
    font-size: 11.5px;
    line-height: 1.6;
    background: var(--card-blue-soft);
    color: #1d4fa8;
    transition: background .25s var(--ease-smooth), color .25s var(--ease-smooth);
}

.segment-desc.undangan { background: var(--card-purple-soft); color: #6d3fc9; }

/* ===== SIFAT PILL ===== */

.pill-group { display: flex; gap: 9px; flex-wrap: wrap; }

.pill-radio { position: relative; }
.pill-radio input { position: absolute; opacity: 0; pointer-events: none; }

.pill-label {
    display: inline-flex;
    align-items: center;
    padding: 11px 19px;
    border-radius: 999px;
    background: var(--color-bg);
    color: var(--color-text-muted);
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    border: 1.5px solid transparent;
    transition: all .2s var(--ease-smooth);
}

.pill-label:hover { background: var(--card-blue-soft); }

.pill-radio input:checked + .pill-label {
    background: var(--card-blue-soft);
    color: var(--card-blue);
    border-color: var(--card-blue);
}

/* ===== LAMPIRAN ===== */

.current-file {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 14px;
    background: var(--card-blue-soft);
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.current-file-info { display: flex; align-items: center; gap: 11px; font-size: 12.5px; color: #1d4fa8; font-weight: 600; word-break: break-all; }
.current-file-info svg { width: 18px; height: 18px; stroke: #1d4fa8; stroke-width: 1.8; fill: none; flex-shrink: 0; }

.btn-lihat-file {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 9px;
    background: var(--card-blue);
    color: white;
    text-decoration: none;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
    transition: transform .2s var(--ease-smooth);
}

.btn-lihat-file:hover { color: white; transform: translateY(-2px); }

.file-drop {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 18px;
    border: 1.5px dashed var(--color-border);
    border-radius: 14px;
    background: var(--color-bg);
    transition: border-color .2s var(--ease-smooth), background .2s var(--ease-smooth);
}

.file-drop:hover { border-color: var(--card-blue); background: var(--card-blue-soft); }

.file-drop-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--color-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: var(--shadow-card);
}

.file-drop-icon svg { width: 21px; height: 21px; stroke: var(--card-blue); stroke-width: 1.8; fill: none; }
.file-drop input[type="file"] { font-size: 12px; width: 100%; }
.file-drop-help { font-size: 10.5px; color: var(--color-text-muted); margin-top: 4px; }

/* ===== INFORMASI KEGIATAN (kondisional) ===== */

.event-box {
    display: none;
    margin-bottom: 22px;
    padding: 20px;
    border: 1px solid #E6D9FA;
    border-radius: 16px;
    background: linear-gradient(180deg, var(--card-purple-soft) 0%, #ffffff 75%);
}

.event-box.show { display: block; animation: fadeInUp .3s var(--ease-smooth); }

.event-box-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 12.5px;
    font-weight: 800;
    color: #6d3fc9;
    text-transform: uppercase;
    letter-spacing: .3px;
}

.event-box-title svg { width: 16px; height: 16px; stroke: #6d3fc9; stroke-width: 2; fill: none; }

.event-box .ci-field:last-child { margin-bottom: 0; }
.event-box .ci-field label svg { stroke: #6d3fc9; }
.event-box .ci-input:focus { border-color: #8B5CF6; box-shadow: 0 0 0 4px var(--card-purple-soft); }

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
.btn-batal:hover { background: var(--card-blue-soft); color: var(--card-blue); }

.btn-checkin {
    flex: 1;
    background: linear-gradient(135deg, var(--card-blue), #1d4fa8);
    color: white;
    box-shadow: 0 12px 24px rgba(47,111,237,.34);
}

.btn-checkin:hover { color: white; transform: translateY(-2px); box-shadow: 0 16px 30px rgba(47,111,237,.44); }
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
            <h2>Edit Surat Masuk</h2>
            <p>Perbarui data surat <?= htmlspecialchars($data['nomor_surat']); ?>.</p>
        </div>
        <a href="detail.php?id=<?= $id; ?>" class="btn-kembali">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
            Kembali
        </a>
    </div>

    <form action="update.php" method="POST" enctype="multipart/form-data" id="formEdit" autocomplete="off">

        <input type="hidden" name="id" value="<?= $id; ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="jenis_surat" id="jenis_surat" value="<?= $jenis_aktif; ?>">

        <div class="ci-card">

            <!-- ============ PANEL KIRI (PRATINJAU HIDUP) ============ -->

            <div class="ci-side<?= $is_undangan ? ' theme-undangan' : ''; ?>" id="side">
                <div class="ci-side-inner">

                    <div class="ci-avatar" id="pv_avatar"><?= htmlspecialchars(strtoupper(substr($data['pengirim'], 0, 1))); ?></div>

                    <div class="ci-badge" id="pv_badge"><?= $is_undangan ? '✉️ SURAT UNDANGAN' : '📄 SURAT BIASA'; ?></div>

                    <div class="ci-side-nomor" id="pv_nomor"><?= htmlspecialchars($data['nomor_surat']); ?></div>
                    <div class="ci-side-sub" id="pv_sub">&nbsp;</div>

                    <div class="side-divider"></div>

                    <div class="side-summary">
                        <div class="ss-row"><small>Tanggal Surat</small><span id="sm_tglsurat">-</span></div>
                        <div class="ss-row"><small>Diterima</small><span id="sm_tglditerima">-</span></div>
                        <div class="ss-row"><small>Sifat</small><span id="sm_sifat"><?= htmlspecialchars($sifat_aktif); ?></span></div>
                    </div>

                    <div class="side-hint">
                        Perubahan akan langsung menimpa data surat ini setelah disimpan.
                    </div>

                </div>
            </div>

            <!-- ============ PANEL KANAN (FORM) ============ -->

            <div class="ci-main">

                <!-- JENIS SURAT -->

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        Jenis Surat
                    </label>

                    <div class="segmented">
                        <div class="segmented-thumb<?= $is_undangan ? ' pos-undangan' : ''; ?>" id="segThumb"></div>

                        <button type="button" class="segment-btn<?= !$is_undangan ? ' active' : ''; ?>" id="btnBiasa" onclick="pilihJenis('Biasa')">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            Surat Biasa
                        </button>

                        <button type="button" class="segment-btn<?= $is_undangan ? ' active' : ''; ?>" id="btnUndangan" onclick="pilihJenis('Undangan')">
                            <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg>
                            Surat Undangan
                        </button>
                    </div>

                    <div class="segment-desc<?= $is_undangan ? ' undangan' : ''; ?>" id="segDesc">
                        <?= $is_undangan
                            ? 'Surat yang berisi undangan kegiatan, rapat, acara, atau agenda tertentu.'
                            : 'Surat masuk umum seperti pemberitahuan, permohonan, atau surat resmi lainnya.'; ?>
                    </div>
                </div>

                <!-- NOMOR SURAT -->

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"></path></svg>
                        Nomor Surat <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="nomor_surat"
                        id="f_nomor"
                        class="ci-input"
                        value="<?= htmlspecialchars($data['nomor_surat']); ?>"
                        autocomplete="off"
                        oninput="updatePreview()"
                        required
                    >
                </div>

                <!-- TANGGAL -->

                <div class="ci-field ci-row-2">
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                            Tanggal Surat <span class="required">*</span>
                        </label>
                        <input
                            type="date"
                            name="tanggal_surat"
                            id="f_tglsurat"
                            class="ci-input"
                            value="<?= htmlspecialchars($data['tanggal_surat']); ?>"
                            oninput="updatePreview()"
                            required
                        >
                    </div>
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                            Tanggal Diterima <span class="required">*</span>
                        </label>
                        <input
                            type="date"
                            name="tanggal_diterima"
                            id="f_tglditerima"
                            class="ci-input"
                            value="<?= htmlspecialchars($data['tanggal_diterima']); ?>"
                            oninput="updatePreview()"
                            required
                        >
                    </div>
                </div>

                <!-- PENGIRIM -->

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Pengirim <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="pengirim"
                        id="f_pengirim"
                        class="ci-input"
                        value="<?= htmlspecialchars($data['pengirim']); ?>"
                        autocomplete="off"
                        oninput="updatePreview()"
                        required
                    >
                </div>

                <!-- PERIHAL -->

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        Perihal <span class="required">*</span>
                    </label>
                    <textarea
                        name="perihal"
                        id="f_perihal"
                        class="ci-input"
                        oninput="updatePreview()"
                        required
                    ><?= htmlspecialchars($data['perihal']); ?></textarea>
                </div>

                <!-- SIFAT -->

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M12 2l3 7 7 .6-5.3 4.6 1.7 7.3L12 17.8 5.6 21.5l1.7-7.3L2 9.6 9 9z"></path></svg>
                        Sifat Surat
                    </label>
                    <div class="pill-group">
                        <?php foreach (['Biasa', 'Penting', 'Segera', 'Sangat Segera'] as $s) { ?>
                            <label class="pill-radio">
                                <input type="radio" name="sifat" value="<?= $s; ?>" <?= ($sifat_aktif === $s) ? 'checked' : ''; ?> onchange="updatePreview()">
                                <span class="pill-label"><?= $s; ?></span>
                            </label>
                        <?php } ?>
                    </div>
                </div>

                <!-- INFORMASI KEGIATAN (hanya untuk undangan) -->

                <div class="event-box<?= $is_undangan ? ' show' : ''; ?>" id="eventCard">

                    <div class="event-box-title">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                        Informasi Kegiatan
                    </div>

                    <div class="ci-field ci-row-2">
                        <div>
                            <label>Tanggal Acara <span class="required">*</span></label>
                            <input
                                type="date"
                                name="tanggal_acara"
                                id="tanggal_acara"
                                class="ci-input"
                                value="<?= htmlspecialchars($tanggal_acara_value); ?>"
                                <?= $is_undangan ? 'required' : ''; ?>
                            >
                        </div>
                        <div>
                            <label>Tempat Acara <span class="required">*</span></label>
                            <input
                                type="text"
                                name="tempat_acara"
                                id="tempat_acara"
                                class="ci-input"
                                value="<?= htmlspecialchars($data['tempat_acara'] ?? ''); ?>"
                                placeholder="Contoh: Aula BKPSDM Kabupaten Tapin"
                                autocomplete="off"
                                <?= $is_undangan ? 'required' : ''; ?>
                            >
                        </div>
                    </div>

                    <div class="ci-field">
                        <label>Agenda Acara <span class="required">*</span></label>
                        <textarea
                            name="agenda_acara"
                            id="agenda_acara"
                            class="ci-input"
                            placeholder="Contoh: Rapat Koordinasi Kepegawaian"
                            <?= $is_undangan ? 'required' : ''; ?>
                        ><?= htmlspecialchars($data['agenda_acara'] ?? ''); ?></textarea>
                    </div>

                </div>

                <!-- LAMPIRAN -->

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a5 5 0 0 1-7.07-7.07l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95L10.13 17.1a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                        Lampiran Surat
                    </label>

                    <?php if ($file_tersedia) { ?>
                        <div class="current-file">
                            <div class="current-file-info">
                                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                File saat ini: <?= htmlspecialchars($file_surat); ?>
                            </div>
                            <a href="berkas.php?f=<?= rawurlencode($file_surat); ?>" target="_blank" class="btn-lihat-file">Lihat File</a>
                        </div>
                    <?php } ?>

                    <div class="file-drop">
                        <div class="file-drop-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <input type="file" name="file_surat" accept=".pdf">
                            <div class="file-drop-help">Format PDF, maksimal 5 MB. Kosongkan bila tidak ingin mengganti file.</div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->

                <div class="ci-footer">
                    <a href="detail.php?id=<?= $id; ?>" class="btn-batal">Batal</a>
                    <button type="submit" class="btn-checkin">
                        💾 Simpan Perubahan
                    </button>
                </div>

            </div>

        </div>

    </form>

</div>

</div>
</div>

<script>

function formatTanggal(nilai) {
    if (!nilai) { return '-'; }
    var d = new Date(nilai + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function pilihJenis(jenis) {

    var btnBiasa = document.getElementById("btnBiasa");
    var btnUndangan = document.getElementById("btnUndangan");
    var segThumb = document.getElementById("segThumb");
    var segDesc = document.getElementById("segDesc");
    var jenisInput = document.getElementById("jenis_surat");
    var eventCard = document.getElementById("eventCard");
    var side = document.getElementById("side");
    var pvBadge = document.getElementById("pv_badge");
    var tanggalAcara = document.getElementById("tanggal_acara");
    var tempatAcara = document.getElementById("tempat_acara");
    var agendaAcara = document.getElementById("agenda_acara");

    if (jenis === "Biasa") {

        btnBiasa.classList.add("active");
        btnUndangan.classList.remove("active");
        segThumb.classList.remove("pos-undangan");

        segDesc.className = "segment-desc";
        segDesc.textContent = "Surat masuk umum seperti pemberitahuan, permohonan, atau surat resmi lainnya.";

        jenisInput.value = "Biasa";
        eventCard.classList.remove("show");
        side.classList.remove("theme-undangan");
        pvBadge.textContent = "📄 SURAT BIASA";

        tanggalAcara.required = false;
        tempatAcara.required = false;
        agendaAcara.required = false;
        tanggalAcara.value = "";
        tempatAcara.value = "";
        agendaAcara.value = "";

    } else {

        btnBiasa.classList.remove("active");
        btnUndangan.classList.add("active");
        segThumb.classList.add("pos-undangan");

        segDesc.className = "segment-desc undangan";
        segDesc.textContent = "Surat yang berisi undangan kegiatan, rapat, acara, atau agenda tertentu.";

        jenisInput.value = "Undangan";
        eventCard.classList.add("show");
        side.classList.add("theme-undangan");
        pvBadge.textContent = "✉️ SURAT UNDANGAN";

        tanggalAcara.required = true;
        tempatAcara.required = true;
        agendaAcara.required = true;

    }

}

function updatePreview() {

    var nomor = document.getElementById("f_nomor").value.trim();
    var pengirim = document.getElementById("f_pengirim").value.trim();
    var perihal = document.getElementById("f_perihal").value.trim();
    var tglSurat = document.getElementById("f_tglsurat").value;
    var tglDiterima = document.getElementById("f_tglditerima").value;
    var sifatEl = document.querySelector('input[name="sifat"]:checked');

    var pvNomor = document.getElementById("pv_nomor");

    if (nomor !== "") {
        pvNomor.textContent = nomor;
        pvNomor.classList.remove("placeholder");
    } else {
        pvNomor.textContent = "Nomor surat akan muncul di sini";
        pvNomor.classList.add("placeholder");
    }

    document.getElementById("pv_avatar").textContent = pengirim !== "" ? pengirim.charAt(0).toUpperCase() : "?";

    var sub = [];
    if (pengirim !== "") sub.push(pengirim);
    if (perihal !== "") sub.push(perihal);
    document.getElementById("pv_sub").textContent = sub.length > 0 ? sub.join(" · ") : "\u00A0";

    document.getElementById("sm_tglsurat").textContent = formatTanggal(tglSurat);
    document.getElementById("sm_tglditerima").textContent = formatTanggal(tglDiterima);
    document.getElementById("sm_sifat").textContent = sifatEl ? sifatEl.value : "-";

}

document.addEventListener("DOMContentLoaded", updatePreview);

</script>

<?php
include "../layouts/footer.php";
?>