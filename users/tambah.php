<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

adminDanOperator();

include "../config/database.php";

date_default_timezone_set('Asia/Makassar');

$opsi_sifat = ['Biasa', 'Penting', 'Segera', 'Sangat Segera'];

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.pt-wrap {
    --ac: #2F6FED;
    --ac-dark: #1D4ED8;
    --ac-soft: #DBEAFE;
}

.pt-shell {
    max-width: 1120px;
    margin: 0 auto;
}

/* ===== HEADER ===== */

.pt-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.pt-head h2 {
    margin: 0;
    font-size: 25px;
    font-weight: 700;
    color: #1d1d27;
    letter-spacing: -.3px;
}

.pt-head p {
    margin: 4px 0 0;
    font-size: 13px;
    color: #8a8a98;
}

.pt-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 42px;
    padding: 0 18px;
    border-radius: 13px;
    background: #fff;
    border: 1px solid var(--color-border, #eee);
    color: #444;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-back:hover {
    transform: translateX(-3px);
    border-color: var(--ac);
    color: var(--ac-dark);
}

/* ===== CARD ===== */

.pt-card {
    display: grid;
    grid-template-columns: 340px 1fr;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #eee);
    border-radius: 26px;
    overflow: hidden;
    box-shadow: var(--shadow-card, 0 6px 24px rgba(0,0,0,.04));
}

/* ===== PANEL KIRI ===== */

.pt-side {
    position: relative;
    padding: 34px 28px;
    background: linear-gradient(160deg, #14181f 0%, #16213a 55%, #1a3270 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    overflow: hidden;
}

.pt-side::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 18px 18px;
    pointer-events: none;
}

.pt-side::after {
    content: "";
    position: absolute;
    width: 280px;
    height: 280px;
    right: -110px;
    top: -120px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(47,111,237,.5), transparent 70%);
    pointer-events: none;
}

.pt-side > * { position: relative; z-index: 1; }
/* tema ungu saat jenis surat = Undangan */
.pt-side { transition: background .4s; }
.pt-side.theme-und { background: linear-gradient(160deg, #15121f 0%, #241a42 55%, #3b2680 100%); }
.pt-side.theme-und::after { background: radial-gradient(circle, rgba(139,92,246,.55), transparent 70%); }
.pt-side.theme-und .pt-ring { background: conic-gradient(from 200deg, #8B5CF6, #C4B0FF, #8B5CF6); }
.pt-side.theme-und .pt-ring div { background: #231a3f; }
.pt-side.theme-und .pt-pill i { background: #B79CFF; box-shadow: 0 0 0 4px rgba(139,92,246,.4); }


.pt-ring {
    width: 108px;
    height: 108px;
    border-radius: 50%;
    padding: 5px;
    background: conic-gradient(from 200deg, #2F6FED, #8DB6FF, #2F6FED);
    margin-bottom: 18px;
}

.pt-ring div {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #182640;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pt-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 13px;
    border-radius: 999px;
    background: rgba(255,255,255,.1);
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: .5px;
    text-transform: uppercase;
    margin-bottom: 12px;
}

.pt-pill i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #6CA0FF;
    box-shadow: 0 0 0 4px rgba(47,111,237,.35);
}

.pt-side h3 {
    margin: 0;
    font-size: 17px;
    line-height: 1.4;
    font-weight: 700;
    color: #fff !important;
    word-break: break-word;
}

.pt-side .sub {
    margin-top: 4px;
    font-size: 12.5px;
    color: rgba(255,255,255,.55);
    word-break: break-word;
}

.pt-chip {
    margin-top: 14px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(255,255,255,.12);
    color: #fff;
    transition: all .3s;
}

.pt-chip.undangan { background: #8B5CF6; color: #fff; }
.pt-chip.biasa    { background: #2F6FED; color: #fff; }

.pt-rows {
    width: 100%;
    margin-top: 24px;
    border-top: 1px solid rgba(255,255,255,.1);
}

.pt-rows div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,.08);
    font-size: 12px;
}

.pt-rows span { color: rgba(255,255,255,.5); }
.pt-rows b { color: #fff; font-weight: 600; text-align: right; word-break: break-word; }

.pt-note {
    width: 100%;
    margin-top: auto;
    padding: 13px 14px;
    border-radius: 14px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.08);
    font-size: 11.5px;
    line-height: 1.6;
    color: rgba(255,255,255,.7);
    text-align: left;
}

.pt-note b { color: #8DB6FF; }
.pt-side .pt-note { margin-top: 22px; }

/* ===== FORM ===== */

.pt-form {
    padding: 34px 38px;
    display: flex;
    flex-direction: column;
    gap: 26px;
}

.pt-sec {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: .9px;
    text-transform: uppercase;
    color: var(--ac-dark);
}

.pt-sec::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--color-border, #eee);
}

.pt-field { margin-bottom: 16px; }
.pt-field:last-child { margin-bottom: 0; }

.pt-field label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 7px;
    font-size: 12.5px;
    font-weight: 600;
    color: #3a3a48;
}

.pt-field label em { font-style: normal; color: #EF4444; }

.pt-field label .opt {
    color: #9a9aa8;
    font-weight: 500;
    font-size: 11.5px;
}

.pt-input input,
.pt-input textarea {
    width: 100%;
    padding: 0 16px;
    border: 1.5px solid #e6e6ee;
    background: #fafafd;
    border-radius: 14px;
    font-family: inherit;
    font-size: 13.5px;
    color: #1d1d27;
    outline: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-input input { height: 49px; }

.pt-input textarea {
    padding: 13px 16px;
    min-height: 96px;
    resize: vertical;
    line-height: 1.55;
}

.pt-input input:hover,
.pt-input textarea:hover { border-color: #cfcfdc; }

.pt-input input:focus,
.pt-input textarea:focus {
    background: #fff;
    border-color: var(--ac);
    box-shadow: 0 0 0 4px rgba(47,111,237,.14);
}

.pt-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.pt-2col .pt-field { margin-bottom: 0; }

.pt-hint {
    margin-top: 6px;
    font-size: 11.5px;
    color: #9a9aa8;
}

/* jenis surat */

.pt-types {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.pt-type input { display: none; }

.pt-type label {
    margin: 0;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px 16px;
    border: 1.5px solid #e6e6ee;
    background: #fafafd;
    border-radius: 16px;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-type label:hover {
    transform: translateY(-2px);
    border-color: #cfcfdc;
}

.pt-type .ic {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pt-type.biasa .ic { background: var(--ac-soft); }
.pt-type.und .ic   { background: #EDE4FD; }

.pt-type strong { display: block; font-size: 13px; color: #1d1d27; }

.pt-type small {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    line-height: 1.45;
    color: #8a8a98;
    font-weight: 400;
}

.pt-type.biasa input:checked + label {
    border-color: var(--ac);
    background: #F3F8FF;
    box-shadow: 0 0 0 4px rgba(47,111,237,.13);
}

.pt-type.und input:checked + label {
    border-color: #8B5CF6;
    background: #F7F3FF;
    box-shadow: 0 0 0 4px rgba(139,92,246,.15);
}

/* sifat */

.pt-sifat {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.pt-sifat input { display: none; }

.pt-sifat label {
    margin: 0;
    padding: 9px 18px;
    border: 1.5px solid #e6e6ee;
    background: #fafafd;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 600;
    color: #55556a;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-sifat label:hover { border-color: #cfcfdc; transform: translateY(-2px); }

.pt-sifat input:checked + label.s-biasa  { background: #E0E7FF; border-color: #6366F1; color: #4338CA; }
.pt-sifat input:checked + label.s-penting { background: #FEF3C7; border-color: #F59E0B; color: #B45309; }
.pt-sifat input:checked + label.s-segera { background: #FFEDD5; border-color: #FB7C3C; color: #C2410C; }
.pt-sifat input:checked + label.s-sangat { background: #FEE2E2; border-color: #EF4444; color: #B91C1C; }

/* kegiatan */

.pt-event {
    display: none;
    padding: 20px 20px 18px;
    border-radius: 18px;
    background: #F8F4FF;
    border: 1.5px dashed #CDB6FA;
}

.pt-event.show {
    display: block;
    animation: ptIn .35s var(--ease-smooth, ease);
}

@keyframes ptIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.pt-event .pt-sec { color: #6D3FC9; }

/* berkas */

.pt-file {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border: 1.5px dashed #cfd6e6;
    background: #fafbff;
    border-radius: 16px;
    cursor: pointer;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-file:hover {
    border-color: var(--ac);
    background: #F3F8FF;
}

.pt-file.has {
    border-style: solid;
    border-color: var(--ac);
    background: #F3F8FF;
}

.pt-file .fi {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    background: var(--ac-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pt-file b {
    display: block;
    font-size: 13px;
    color: #1d1d27;
    word-break: break-all;
}

.pt-file small { font-size: 11.5px; color: #8a8a98; }

.pt-file input { display: none; }

.pt-file-err {
    margin-top: 8px;
    font-size: 12px;
    color: #DC2626;
    display: none;
}

/* footer */

.pt-foot {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 22px;
    border-top: 1px solid var(--color-border, #eee);
}

.pt-cancel {
    height: 50px;
    padding: 0 22px;
    display: inline-flex;
    align-items: center;
    border-radius: 14px;
    background: #f4f4f7;
    color: #666;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all .25s;
}

.pt-cancel:hover { background: #e9e9ef; color: #222; }

.pt-save {
    flex: 1;
    height: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, #2F6FED, #1D4ED8);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 12px 26px rgba(47,111,237,.34);
    transition: transform .25s var(--ease-smooth, ease), box-shadow .25s;
}

.pt-save:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px rgba(47,111,237,.42);
}

/* ===== RESPONSIVE ===== */

@media (max-width: 900px) {
    .pt-card { grid-template-columns: 1fr; }
    .pt-side { padding: 28px 22px; }
}

@media (max-width: 600px) {
    .pt-form { padding: 26px 20px; }
    .pt-types, .pt-2col { grid-template-columns: 1fr; }
    .pt-2col .pt-field { margin-bottom: 16px; }
    .pt-foot { flex-direction: column-reverse; }
    .pt-cancel, .pt-save { width: 100%; justify-content: center; }
}

</style>

<div class="content">
<div class="container-fluid pt-wrap">
<div class="pt-shell">

    <div class="pt-head">
        <div>
            <h2>Tambah Surat Masuk</h2>
            <p>Catat surat yang diterima oleh instansi.</p>
        </div>

        <a href="index.php" class="pt-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <div class="pt-card">

        <!-- ===== PANEL KIRI : PRATINJAU ===== -->

        <aside class="pt-side">

            <div class="pt-ring">
                <div>
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#8DB6FF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                </div>
            </div>

            <div class="pt-pill"><i></i> Pratinjau Surat</div>

            <h3 id="pvNomor">Nomor surat</h3>
            <div class="sub" id="pvPengirim">Pengirim</div>

            <div class="pt-chip biasa" id="pvJenis">Surat Biasa</div>

            <div class="pt-rows">
                <div><span>Sifat</span><b id="pvSifat">Biasa</b></div>
                <div><span>Tanggal surat</span><b id="pvTgl">-</b></div>
                <div><span>Diterima</span><b id="pvTerima">-</b></div>
                <div><span>Berkas PDF</span><b id="pvFile">Belum ada</b></div>
            </div>

            <div class="pt-note">
                <b>Catatan:</b> bila jenis surat adalah <b>Undangan</b>, isi juga tanggal, tempat, dan agenda kegiatannya.
            </div>

        </aside>

        <!-- ===== FORM ===== -->

        <form action="simpan.php" method="POST" enctype="multipart/form-data" class="pt-form" id="formSurat">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">

            <!-- JENIS -->

            <section>

                <div class="pt-sec">Jenis Surat</div>

                <div class="pt-types">

                    <div class="pt-type biasa">
                        <input type="radio" name="jenis_surat" id="jenisBiasa" value="Biasa" checked>
                        <label for="jenisBiasa">
                            <span class="ic">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
                            </span>
                            <span>
                                <strong>Surat Biasa</strong>
                                <small>Surat umum tanpa informasi kegiatan.</small>
                            </span>
                        </label>
                    </div>

                    <div class="pt-type und">
                        <input type="radio" name="jenis_surat" id="jenisUndangan" value="Undangan">
                        <label for="jenisUndangan">
                            <span class="ic">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#6D3FC9" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            </span>
                            <span>
                                <strong>Surat Undangan</strong>
                                <small>Undangan rapat, acara, atau kegiatan.</small>
                            </span>
                        </label>
                    </div>

                </div>

            </section>

            <!-- DATA SURAT -->

            <section>

                <div class="pt-sec">Data Surat</div>

                <div class="pt-field">
                    <label for="nomor_surat">Nomor Surat <em>*</em></label>
                    <div class="pt-input">
                        <input type="text" id="nomor_surat" name="nomor_surat" placeholder="Contoh: 800/123/BKPSDM/2026" required maxlength="100" autocomplete="off">
                    </div>
                </div>

                <div class="pt-2col" style="margin-bottom:16px">

                    <div class="pt-field">
                        <label for="tanggal_surat">Tanggal Surat <em>*</em></label>
                        <div class="pt-input">
                            <input type="date" id="tanggal_surat" name="tanggal_surat" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="pt-field">
                        <label for="tanggal_diterima">Tanggal Diterima <em>*</em></label>
                        <div class="pt-input">
                            <input type="date" id="tanggal_diterima" name="tanggal_diterima" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                </div>

                <div class="pt-field">
                    <label for="pengirim">Pengirim <em>*</em></label>
                    <div class="pt-input">
                        <input type="text" id="pengirim" name="pengirim" placeholder="Nama instansi atau pengirim" required maxlength="150" autocomplete="off">
                    </div>
                </div>

                <div class="pt-field">
                    <label for="perihal">Perihal <em>*</em></label>
                    <div class="pt-input">
                        <textarea id="perihal" name="perihal" placeholder="Masukkan perihal surat" required></textarea>
                    </div>
                </div>

            </section>

            <!-- SIFAT -->

            <section>

                <div class="pt-sec">Sifat Surat</div>

                <div class="pt-sifat">

                    <?php
                    $kelas_sifat = [
                        'Biasa'         => 's-biasa',
                        'Penting'       => 's-penting',
                        'Segera'        => 's-segera',
                        'Sangat Segera' => 's-sangat',
                    ];

                    foreach ($opsi_sifat as $i => $s) {
                        $idp = 'sifat' . $i;
                    ?>
                        <input type="radio" name="sifat" id="<?= $idp; ?>" value="<?= htmlspecialchars($s); ?>" <?= $s === 'Biasa' ? 'checked' : ''; ?>>
                        <label for="<?= $idp; ?>" class="<?= $kelas_sifat[$s]; ?>"><?= htmlspecialchars($s); ?></label>
                    <?php } ?>

                </div>

            </section>

            <!-- KEGIATAN (hanya untuk undangan) -->

            <section class="pt-event" id="eventCard">

                <div class="pt-sec">Informasi Kegiatan</div>

                <div class="pt-field">
                    <label for="tanggal_acara">Tanggal Acara <em>*</em></label>
                    <div class="pt-input">
                        <input type="date" id="tanggal_acara" name="tanggal_acara">
                    </div>
                </div>

                <div class="pt-field">
                    <label for="tempat_acara">Tempat Acara <em>*</em></label>
                    <div class="pt-input">
                        <input type="text" id="tempat_acara" name="tempat_acara" placeholder="Contoh: Aula BKPSDM Kabupaten Tapin" maxlength="200" autocomplete="off">
                    </div>
                </div>

                <div class="pt-field">
                    <label for="agenda_acara">Agenda Acara <em>*</em></label>
                    <div class="pt-input">
                        <textarea id="agenda_acara" name="agenda_acara" placeholder="Contoh: Rapat Koordinasi Kepegawaian"></textarea>
                    </div>
                </div>

            </section>

            <!-- BERKAS -->

            <section>

                <div class="pt-sec">Berkas Surat</div>

                <label class="pt-file" id="fileBox" for="file_surat">
                    <span class="fi">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/></svg>
                    </span>
                    <span>
                        <b id="fileNama">Pilih berkas PDF</b>
                        <small id="fileInfo">Opsional. Format PDF, maksimal 5 MB.</small>
                    </span>
                    <input type="file" id="file_surat" name="file_surat" accept=".pdf,application/pdf">
                </label>

                <div class="pt-file-err" id="fileErr"></div>

            </section>

            <div class="pt-foot">
                <a href="index.php" class="pt-cancel">Batal</a>

                <button type="submit" class="pt-save">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                    Simpan Surat
                </button>
            </div>

        </form>

    </div>

</div>
</div>
</div>

<script>

(function () {

    var form      = document.getElementById('formSurat');
    var nomor     = document.getElementById('nomor_surat');
    var pengirim  = document.getElementById('pengirim');
    var tglSurat  = document.getElementById('tanggal_surat');
    var tglTerima = document.getElementById('tanggal_diterima');
    var fileIn    = document.getElementById('file_surat');
    var fileBox   = document.getElementById('fileBox');
    var fileNama  = document.getElementById('fileNama');
    var fileInfo  = document.getElementById('fileInfo');
    var fileErr   = document.getElementById('fileErr');

    var eventCard = document.getElementById('eventCard');
    var tAcara    = document.getElementById('tanggal_acara');
    var tpAcara   = document.getElementById('tempat_acara');
    var agAcara   = document.getElementById('agenda_acara');

    var pvNomor    = document.getElementById('pvNomor');
    var pvPengirim = document.getElementById('pvPengirim');
    var pvJenis    = document.getElementById('pvJenis');
    var pvSifat    = document.getElementById('pvSifat');
    var pvTgl      = document.getElementById('pvTgl');
    var pvTerima   = document.getElementById('pvTerima');
    var pvFile     = document.getElementById('pvFile');

    var MAKS = 5 * 1024 * 1024;

    function nilaiRadio(nama, awal) {
        var v = awal;
        document.querySelectorAll('input[name="' + nama + '"]').forEach(function (r) {
            if (r.checked) v = r.value;
        });
        return v;
    }

    function format(tgl) {
        if (!tgl) return '-';
        var p = tgl.split('-');
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function perbarui() {

        pvNomor.textContent    = nomor.value.trim() !== '' ? nomor.value.trim() : 'Nomor surat';
        pvPengirim.textContent = pengirim.value.trim() !== '' ? pengirim.value.trim() : 'Pengirim';
        pvTgl.textContent      = format(tglSurat.value);
        pvTerima.textContent   = format(tglTerima.value);
        pvSifat.textContent    = nilaiRadio('sifat', 'Biasa');

        var undangan = (nilaiRadio('jenis_surat', 'Biasa') === 'Undangan');

        pvJenis.className   = 'pt-chip ' + (undangan ? 'undangan' : 'biasa');
        pvJenis.textContent = undangan ? 'Surat Undangan' : 'Surat Biasa';

        eventCard.classList.toggle('show', undangan);
        document.querySelector('.pt-side').classList.toggle('theme-und', undangan);

        tAcara.required  = undangan;
        tpAcara.required = undangan;
        agAcara.required = undangan;

        if (!undangan) {
            tAcara.value = '';
            tpAcara.value = '';
            agAcara.value = '';
        }
    }

    [nomor, pengirim, tglSurat, tglTerima].forEach(function (el) {
        el.addEventListener('input', perbarui);
    });

    document.querySelectorAll('input[name="jenis_surat"], input[name="sifat"]').forEach(function (r) {
        r.addEventListener('change', perbarui);
    });

    /* berkas */

    function resetFile(pesan) {
        fileIn.value = '';
        fileBox.classList.remove('has');
        fileNama.textContent = 'Pilih berkas PDF';
        fileInfo.textContent = 'Opsional. Format PDF, maksimal 5 MB.';
        pvFile.textContent = 'Belum ada';
        fileErr.style.display = pesan ? 'block' : 'none';
        fileErr.textContent = pesan || '';
    }

    fileIn.addEventListener('change', function () {

        var f = this.files[0];

        if (!f) { resetFile(''); return; }

        if (!/\.pdf$/i.test(f.name)) {
            resetFile('File harus berformat PDF.');
            return;
        }

        if (f.size > MAKS) {
            resetFile('Ukuran file terlalu besar. Maksimal 5 MB.');
            return;
        }

        fileErr.style.display = 'none';
        fileBox.classList.add('has');
        fileNama.textContent = f.name;
        fileInfo.textContent = (f.size / 1024 / 1024).toFixed(2) + ' MB · klik untuk mengganti';
        pvFile.textContent = 'Terlampir';
    });

    perbarui();

})();

</script>

<?php
include "../layouts/footer.php";
?>