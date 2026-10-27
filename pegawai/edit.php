<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

hanyaAdmin();

include "../config/database.php";

/* =========================
   CEK ID & AMBIL DATA
========================= */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($koneksi, "SELECT * FROM pegawai WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    echo "<script>
            alert('Data pegawai tidak ditemukan.');
            window.location='index.php';
          </script>";
    exit;
}

$status_aktif = strtolower($data['status']) === 'aktif';

/* =========================
   SARAN UNIT KERJA & JABATAN
========================= */

$saran_unit = [];
$qu = mysqli_query($koneksi, "SELECT DISTINCT unit_kerja FROM pegawai WHERE unit_kerja <> '' AND unit_kerja <> 'Pimpinan' ORDER BY unit_kerja ASC");
while ($r = mysqli_fetch_assoc($qu)) {
    $saran_unit[] = $r['unit_kerja'];
}

$saran_jabatan = [];
$qj = mysqli_query($koneksi, "SELECT DISTINCT jabatan FROM pegawai WHERE jabatan <> '' ORDER BY jabatan ASC");
while ($r = mysqli_fetch_assoc($qj)) {
    $saran_jabatan[] = $r['jabatan'];
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.pt-wrap {
    --pg: #8B5CF6;
    --pg-dark: #6D28D9;
    --pg-soft: #EDE9FE;
}

.pt-shell {
    max-width: 1120px;
    margin: 0 auto;
}

/* ===== HEADER HALAMAN ===== */

.pt-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}

.pt-head h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #1d1d27;
}

.pt-head p {
    margin: 5px 0 0;
    font-size: 13px;
    color: #8a8a98;
}

.pt-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 20px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid var(--color-border, #e8e8ef);
    color: #555;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(0,0,0,.04);
    transition: all .25s var(--ease-smooth, ease);
    white-space: nowrap;
}

.pt-back:hover {
    color: var(--pg-dark);
    border-color: rgba(139,92,246,.4);
    transform: translateY(-2px);
}

/* ===== CARD DUA PANEL ===== */

.pt-card {
    display: grid;
    grid-template-columns: 340px minmax(0, 1fr);
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 26px;
    overflow: hidden;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

/* ===== PANEL KIRI ===== */

.pt-side {
    position: relative;
    background: linear-gradient(165deg, #17171f 0%, #221d33 55%, #2f2350 100%);
    color: #fff;
    overflow: hidden;
}

.pt-side::before {
    content: "";
    position: absolute;
    width: 300px;
    height: 300px;
    right: -120px;
    top: -120px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,.06);
    background: radial-gradient(circle, rgba(139,92,246,.28), transparent 70%);
}

.pt-side::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.pt-side-inner {
    position: relative;
    z-index: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 56px 24px 26px;
}

.pt-center { text-align: center; }

.pt-ring {
    width: 92px;
    height: 92px;
    margin: 0 auto 18px;
    border-radius: 50%;
    border: 2px solid var(--pg);
    background: rgba(139,92,246,.14);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    box-shadow: 0 0 0 8px rgba(139,92,246,.1);
    transition: all .3s var(--ease-smooth, ease);
}

.pt-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(251,124,60,.18);
    border: 1px solid rgba(251,124,60,.4);
    color: #FFD2B3;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .6px;
}

.pt-pill i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #FB7C3C;
}

.pt-name {
    margin: 14px 0 0;
    min-height: 22px;
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    word-break: break-word;
}

.pt-name.kosong {
    font-size: 12.5px;
    font-weight: 500;
    font-style: italic;
    color: rgba(255,255,255,.4);
}

.pt-tier {
    display: inline-block;
    margin-top: 10px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
    transition: all .25s;
}

.pt-tier.lead  { background: #FFEDD5; color: #C2410C; }
.pt-tier.struk { background: #EDE9FE; color: #6D28D9; }
.pt-tier.fung  { background: #DBEAFE; color: #1D4ED8; }
.pt-tier.pel   { background: #EEEEF3; color: #6b6b78; }

.pt-rows {
    margin-top: 34px;
    border-top: 1px solid rgba(255,255,255,.1);
}

.pt-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 14px;
    padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,.07);
}

.pt-row span {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: rgba(255,255,255,.45);
    flex-shrink: 0;
}

.pt-row b {
    font-size: 12.5px;
    font-weight: 600;
    color: #fff;
    text-align: right;
    word-break: break-word;
}

.pt-row b.on  { color: #4ADE80; }
.pt-row b.off { color: #F87171; }

/* ringkasan perubahan */

.pt-change {
    margin-top: 24px;
    padding: 14px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
    font-size: 12px;
    line-height: 1.6;
    color: rgba(255,255,255,.7);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all .3s;
}

.pt-change .dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #6b6b80;
    flex-shrink: 0;
    transition: all .3s;
}

.pt-change.dirty {
    background: rgba(251,124,60,.14);
    border-color: rgba(251,124,60,.4);
    color: #fff;
}

.pt-change.dirty .dot {
    background: #FB7C3C;
    box-shadow: 0 0 0 4px rgba(251,124,60,.28);
}

.pt-note {
    margin-top: 10px;
    padding: 14px 16px;
    border-radius: 14px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
    font-size: 11.5px;
    line-height: 1.6;
    color: rgba(255,255,255,.7);
}

/* ===== PANEL KANAN ===== */

.pt-form {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pt-body { padding: 30px 32px 10px; flex: 1; }

.pt-sec {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 0 18px;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: var(--pg-dark);
}

.pt-sec::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--color-border, #e8e8ef);
}

.pt-sec.next { margin-top: 26px; }

.pt-field { margin-bottom: 18px; }

.pt-field label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 9px;
    font-size: 13px;
    font-weight: 700;
    color: #2a2a36;
}

.pt-field label svg { flex-shrink: 0; }

.pt-field label em {
    font-style: normal;
    color: #EF4444;
}

.pt-field label .chg {
    display: none;
    margin-left: auto;
    padding: 2px 9px;
    border-radius: 999px;
    background: #FFEDD5;
    color: #C2410C;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: .4px;
    text-transform: uppercase;
}

.pt-field.changed label .chg { display: inline-block; }

.pt-field input[type=text] {
    width: 100%;
    height: 49px;
    padding: 0 18px;
    border: 1px solid #eceef3;
    border-radius: 14px;
    background: #f7f7fb;
    font-size: 13.5px;
    color: #222;
    outline: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-field input[type=text]::placeholder { color: #a6a6b4; }
.pt-field input[type=text]:hover { border-color: #dcdce6; }

.pt-field input[type=text]:focus {
    background: #fff;
    border-color: var(--pg);
    box-shadow: 0 0 0 4px rgba(139,92,246,.13);
}

.pt-field.changed input[type=text] {
    border-color: #FB7C3C;
    background: #fffaf6;
}

.pt-field input[readonly] {
    background: #f1effb !important;
    border-color: #d9d0f7 !important;
    color: var(--pg-dark);
    font-weight: 700;
    cursor: not-allowed;
}

.pt-field.changed input[readonly] { border-color: #FB7C3C !important; }

.pt-hint {
    margin-top: 6px;
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #a3a3b1;
}

.pt-lock {
    display: none;
    margin-top: 8px;
    padding: 9px 12px;
    border-radius: 12px;
    background: #FFF4E8;
    border: 1px solid #fde0c2;
    color: #B45309;
    font-size: 11.5px;
    font-weight: 600;
    line-height: 1.5;
}

.pt-field.locked .pt-lock { display: block; }
.pt-field.locked .pt-hint { display: none; }

/* segmented status */

.pt-seg {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    padding: 5px;
    background: #f1f1f6;
    border-radius: 16px;
}

.pt-seg input { position: absolute; opacity: 0; pointer-events: none; }

.pt-seg label {
    margin: 0;
    padding: 12px 10px;
    justify-content: center;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    color: #7a7a88;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-seg label::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #c4c4d0;
    transition: all .25s;
}

.pt-seg input:checked + label { background: #fff; box-shadow: 0 5px 14px rgba(0,0,0,.08); }

#st-aktif:checked + label { color: #15803D; }
#st-aktif:checked + label::before { background: #22C55E; box-shadow: 0 0 0 4px rgba(34,197,94,.2); }
#st-non:checked + label { color: #B91C1C; }
#st-non:checked + label::before { background: #EF4444; box-shadow: 0 0 0 4px rgba(239,68,68,.18); }

.pt-seg input:focus-visible + label { outline: 2px solid var(--pg); }

/* footer */

.pt-foot {
    display: flex;
    gap: 12px;
    padding: 20px 32px;
    border-top: 1px solid var(--color-border, #eee);
}

.pt-btn {
    height: 51px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-btn.cancel {
    width: 120px;
    background: #fff;
    border: 1px solid var(--color-border, #e8e8ef);
    color: #777;
}

.pt-btn.cancel:hover { background: #f5f5f9; color: #222; }

.pt-btn.save {
    flex: 1;
    border: none;
    background: linear-gradient(135deg, #8B5CF6, #6D28D9);
    color: #fff;
    box-shadow: 0 12px 26px rgba(139,92,246,.32);
}

.pt-btn.save:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px rgba(139,92,246,.42);
}

/* ===== RESPONSIVE ===== */

@media (max-width: 960px) {
    .pt-card { grid-template-columns: 1fr; }
    .pt-side-inner { padding: 34px 22px 22px; }
}

@media (max-width: 620px) {
    .pt-head { flex-direction: column; }
    .pt-body { padding: 24px 20px 6px; }
    .pt-foot { padding: 18px 20px; flex-direction: column-reverse; }
    .pt-btn.cancel { width: 100%; }
}

</style>

<div class="content">
<div class="container-fluid pt-wrap">
<div class="pt-shell">

    <div class="pt-head">
        <div>
            <h2>Edit Pegawai</h2>
            <p>Perbarui data pegawai. Kolom yang diubah akan ditandai.</p>
        </div>
        <a href="index.php" class="pt-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <div class="pt-card">

        <!-- ===== PANEL KIRI ===== -->

        <aside class="pt-side">
            <div class="pt-side-inner">

                <div class="pt-center">

                    <div class="pt-ring" id="pvAvatar">?</div>

                    <span class="pt-pill"><i></i> MODE EDIT</span>

                    <div class="pt-name" id="pvNama">Nama Pegawai</div>

                    <span class="pt-tier pel" id="pvTier">Pelaksana</span>

                </div>

                <div class="pt-rows">
                    <div class="pt-row"><span>NIP</span><b id="pvNip">-</b></div>
                    <div class="pt-row"><span>Jabatan</span><b id="pvJabatan">-</b></div>
                    <div class="pt-row"><span>Unit Kerja</span><b id="pvUnit">-</b></div>
                    <div class="pt-row"><span>Status</span><b class="on" id="pvStatus">Aktif</b></div>
                </div>

                <div class="pt-change" id="changeBox">
                    <span class="dot"></span>
                    <span id="changeText">Belum ada perubahan</span>
                </div>

                <div class="pt-note">
                    Bila NIP diubah, pastikan tidak sama dengan NIP pegawai lain. Jabatan Kepala Badan / Kepala Dinas otomatis tanpa unit kerja.
                </div>

            </div>
        </aside>

        <!-- ===== PANEL KANAN ===== -->

        <form action="update.php" method="POST" autocomplete="off" class="pt-form" id="formEdit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">

            <input type="hidden" name="id" value="<?= (int) $data['id']; ?>">

            <div class="pt-body">

                <div class="pt-sec">Data Pegawai</div>

                <div class="pt-field" data-field="nip">
                    <label for="nip">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"/></svg>
                        NIP <em>*</em> <span class="chg">Diubah</span>
                    </label>
                    <input type="text" id="nip" name="nip" inputmode="numeric" maxlength="30"
                           value="<?= htmlspecialchars($data['nip']); ?>"
                           data-orig="<?= htmlspecialchars($data['nip']); ?>" required>
                    <div class="pt-hint"><span>Nomor induk pegawai</span><span id="nipCount">0 digit</span></div>
                </div>

                <div class="pt-field" data-field="nama">
                    <label for="nama">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Nama Lengkap <em>*</em> <span class="chg">Diubah</span>
                    </label>
                    <input type="text" id="nama" name="nama" maxlength="120"
                           value="<?= htmlspecialchars($data['nama']); ?>"
                           data-orig="<?= htmlspecialchars($data['nama']); ?>" required>
                </div>

                <div class="pt-sec next">Penempatan &amp; Status</div>

                <div class="pt-field" data-field="jabatan">
                    <label for="jabatan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                        Jabatan <em>*</em> <span class="chg">Diubah</span>
                    </label>
                    <input type="text" id="jabatan" name="jabatan" list="listJabatan" maxlength="150"
                           value="<?= htmlspecialchars($data['jabatan']); ?>"
                           data-orig="<?= htmlspecialchars($data['jabatan']); ?>" required>
                    <datalist id="listJabatan">
                        <?php foreach ($saran_jabatan as $j) { ?>
                            <option value="<?= htmlspecialchars($j); ?>">
                        <?php } ?>
                    </datalist>
                </div>

                <div class="pt-field" data-field="unit_kerja" id="fieldUnit">
                    <label for="unit_kerja">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                        Unit Kerja <em>*</em> <span class="chg">Diubah</span>
                    </label>
                    <input type="text" id="unit_kerja" name="unit_kerja" list="listUnit" maxlength="150"
                           value="<?= htmlspecialchars($data['unit_kerja']); ?>"
                           data-orig="<?= htmlspecialchars($data['unit_kerja']); ?>" required>
                    <datalist id="listUnit">
                        <?php foreach ($saran_unit as $u) { ?>
                            <option value="<?= htmlspecialchars($u); ?>">
                        <?php } ?>
                    </datalist>
                    <div class="pt-hint"><span>Pegawai dikelompokkan per unit kerja</span></div>
                    <div class="pt-lock">Pimpinan (Kepala Badan / Kepala Dinas) tidak memiliki unit kerja, sehingga otomatis dikelompokkan sebagai <b>Pimpinan</b>.</div>
                </div>

                <div class="pt-field">
                    <label>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg>
                        Status Pegawai <em>*</em>
                    </label>
                    <div class="pt-seg">
                        <input type="radio" name="status" id="st-aktif" value="Aktif" <?= $status_aktif ? 'checked' : ''; ?>>
                        <label for="st-aktif">Aktif</label>
                        <input type="radio" name="status" id="st-non" value="Nonaktif" <?= !$status_aktif ? 'checked' : ''; ?>>
                        <label for="st-non">Nonaktif</label>
                    </div>
                </div>

            </div>

            <div class="pt-foot">
                <a href="index.php" class="pt-btn cancel">Batal</a>
                <button type="submit" class="pt-btn save">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    Simpan Perubahan
                </button>
            </div>

        </form>

    </div>

</div>
</div>
</div>

<script>
(function () {

    var el = function (id) { return document.getElementById(id); };

    var nip     = el('nip');
    var nama    = el('nama');
    var jabatan = el('jabatan');
    var unit    = el('unit_kerja');
    var fieldUnit = el('fieldUnit');

    var statusAsli = <?= $status_aktif ? "'Aktif'" : "'Nonaktif'"; ?>;

    var unitAsli = unit.getAttribute('data-orig');
    var unitTersimpan = (unitAsli === 'Pimpinan') ? '' : unitAsli;
    var terkunci = false;

    function inisial(teks) {
        var kata = teks.trim().split(/\s+/);
        var hasil = '';
        for (var i = 0; i < kata.length; i++) {
            if (/^[A-Za-z]/.test(kata[i])) {
                hasil += kata[i].charAt(0).toUpperCase();
            }
            if (hasil.length >= 2) break;
        }
        return hasil || '?';
    }

    // sama dengan tingkatJabatan() di index.php
    function tingkat(j) {
        j = j.toLowerCase();
        var aturan = [
            [1, /kepala\s+(badan|dinas|bkpsdm)|^kaban|^kadis/],
            [2, /sekretaris/],
            [3, /kepala\s+bidang|^kabid/],
            [4, /kepala\s+(sub|seksi|unit|upt)|kasubag|kasubbid|kasi\b|subbagian|subbidang/],
            [5, /madya/],
            [6, /muda/],
            [7, /pertama|analis|pranata|pengadministrasi|arsiparis|auditor/],
            [8, /penyelia|terampil|mahir/],
            [9, /pengelola|pelaksana|staf|operator|pramu|pengemudi|petugas|resepsionis/]
        ];
        for (var i = 0; i < aturan.length; i++) {
            if (aturan[i][1].test(j)) return aturan[i][0];
        }
        return 10;
    }

    function kelasTier(t) {
        if (t <= 1) return ['Pimpinan', 'lead'];
        if (t <= 4) return ['Struktural', 'struk'];
        if (t <= 8) return ['Fungsional', 'fung'];
        return ['Pelaksana', 'pel'];
    }

    function aturKunciUnit(tingkatJab) {

        var harusKunci = (tingkatJab === 1);

        if (harusKunci && !terkunci) {
            if (unit.value !== 'Pimpinan') {
                unitTersimpan = unit.value;
            }
            unit.value = 'Pimpinan';
            unit.readOnly = true;
            unit.removeAttribute('list');
            fieldUnit.classList.add('locked');
            terkunci = true;
        }

        if (!harusKunci && terkunci) {
            unit.value = unitTersimpan;
            unit.readOnly = false;
            unit.setAttribute('list', 'listUnit');
            fieldUnit.classList.remove('locked');
            terkunci = false;
        }
    }

    function tandaiPerubahan() {

        var jumlah = 0;

        [nip, nama, jabatan, unit].forEach(function (inp) {
            var berubah = inp.value.trim() !== inp.getAttribute('data-orig').trim();
            inp.closest('.pt-field').classList.toggle('changed', berubah);
            if (berubah) jumlah++;
        });

        var statusSekarang = el('st-aktif').checked ? 'Aktif' : 'Nonaktif';
        if (statusSekarang !== statusAsli) jumlah++;

        el('changeBox').classList.toggle('dirty', jumlah > 0);
        el('changeText').textContent = jumlah > 0
            ? jumlah + ' bagian telah diubah'
            : 'Belum ada perubahan';
    }

    function render() {

        var tj = tingkat(jabatan.value);
        aturKunciUnit(tj);

        el('pvNama').textContent    = nama.value.trim() || 'Nama Pegawai';
        el('pvAvatar').textContent  = inisial(nama.value);
        el('pvNip').textContent     = nip.value.trim() || '-';
        el('pvJabatan').textContent = jabatan.value.trim() || '-';
        el('pvUnit').textContent    = terkunci ? 'Pimpinan' : (unit.value.trim() || '-');

        el('nipCount').textContent = nip.value.replace(/\D/g, '').length + ' digit';

        var t = kelasTier(tj);
        var tierEl = el('pvTier');
        tierEl.textContent = t[0];
        tierEl.className = 'pt-tier ' + t[1];

        var aktif = el('st-aktif').checked;
        var st = el('pvStatus');
        st.textContent = aktif ? 'Aktif' : 'Nonaktif';
        st.className = aktif ? 'on' : 'off';

        tandaiPerubahan();
    }

    [nip, nama, jabatan, unit, el('st-aktif'), el('st-non')].forEach(function (x) {
        x.addEventListener('input', render);
        x.addEventListener('change', render);
    });

    // peringatan bila keluar halaman dengan perubahan belum tersimpan
    var sedangSimpan = false;
    el('formEdit').addEventListener('submit', function () { sedangSimpan = true; });

    window.addEventListener('beforeunload', function (e) {
        if (!sedangSimpan && el('changeBox').classList.contains('dirty')) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    render();

})();
</script>

<?php
include "../layouts/footer.php";
?>