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
   AMBIL DATA USER
========================= */

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>alert('ID user tidak valid.');window.location='index.php';</script>";
    exit;
}

$stmt = mysqli_prepare($koneksi, "SELECT id, nama, username, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$data) {
    echo "<script>alert('Data user tidak ditemukan.');window.location='index.php';</script>";
    exit;
}

// normalisasi role agar cocok dengan pilihan form
$role_now = (strtolower($data['role']) === 'operator') ? 'Operator' : 'Administrator';

// apakah ini akun yang sedang dipakai login
$akun_saya = ($data['username'] === $_SESSION['username']);

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.pt-wrap {
    --us: #14B8A6;
    --us-dark: #0F766E;
    --us-soft: #CCFBF1;
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
    border-color: var(--us);
    color: var(--us-dark);
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
    background: linear-gradient(160deg, #14181f 0%, #17232b 55%, #123a3d 100%);
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
    background: radial-gradient(circle, rgba(20,184,166,.4), transparent 70%);
    pointer-events: none;
}

.pt-side > * { position: relative; z-index: 1; }

.pt-ring {
    width: 108px;
    height: 108px;
    border-radius: 50%;
    padding: 5px;
    background: conic-gradient(from 200deg, #14B8A6, #5EEAD4, #14B8A6);
    margin-bottom: 18px;
}

.pt-ring div {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #1b2a32;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    font-weight: 800;
    color: #5EEAD4;
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
    background: var(--us);
    box-shadow: 0 0 0 4px rgba(20,184,166,.3);
}

.pt-side h3 {
    margin: 0;
    font-size: 19px;
    font-weight: 700;
    color: #fff !important;
    word-break: break-word;
}

.pt-side .uname {
    margin-top: 3px;
    font-size: 12.5px;
    color: rgba(255,255,255,.55);
    word-break: break-all;
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

.pt-chip.admin { background: #FB7C3C; color: #fff; }
.pt-chip.operator { background: var(--us); color: #04201d; }

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
.pt-rows b { color: #fff; font-weight: 600; text-align: right; }

.pt-changes {
    width: 100%;
    margin-top: 20px;
    padding: 13px 14px;
    border-radius: 14px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.08);
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
    transition: all .3s;
}

.pt-changes .num {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: rgba(255,255,255,.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    font-weight: 800;
    flex-shrink: 0;
    transition: all .3s;
}

.pt-changes small {
    display: block;
    font-size: 11.5px;
    line-height: 1.45;
    color: rgba(255,255,255,.7);
}

.pt-changes.on {
    background: rgba(251,124,60,.16);
    border-color: rgba(251,124,60,.4);
}

.pt-changes.on .num {
    background: #FB7C3C;
    color: #fff;
}

.pt-note {
    width: 100%;
    margin-top: 14px;
    padding: 13px 14px;
    border-radius: 14px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.08);
    font-size: 11.5px;
    line-height: 1.6;
    color: rgba(255,255,255,.7);
    text-align: left;
}

.pt-note b { color: #5EEAD4; }

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
    color: var(--us-dark);
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

.pt-field label em {
    font-style: normal;
    color: #EF4444;
}

.pt-field label .opt {
    color: #9a9aa8;
    font-weight: 500;
    font-size: 11.5px;
}

.pt-tag {
    display: none;
    margin-left: auto;
    padding: 2px 9px;
    border-radius: 999px;
    background: #FFEDD5;
    color: #C2410C;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .4px;
    text-transform: uppercase;
}

.pt-field.changed .pt-tag,
.pt-sec-role.changed .pt-tag { display: inline-block; }

.pt-input { position: relative; }

.pt-input input {
    width: 100%;
    height: 49px;
    padding: 0 16px;
    border: 1.5px solid #e6e6ee;
    background: #fafafd;
    border-radius: 14px;
    font-size: 13.5px;
    color: #1d1d27;
    outline: none;
    transition: all .25s var(--ease-smooth, ease);
}

.pt-input input:hover { border-color: #cfcfdc; }

.pt-input input:focus {
    background: #fff;
    border-color: var(--us);
    box-shadow: 0 0 0 4px rgba(20,184,166,.14);
}

.pt-field.changed .pt-input input {
    border-color: #FB9D6A;
    background: #FFFAF6;
}

.pt-input.has-eye input { padding-right: 50px; }

.pt-eye {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 10px;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background .2s;
}

.pt-eye:hover { background: #eeeef5; }

.pt-hint {
    margin-top: 6px;
    font-size: 11.5px;
    color: #9a9aa8;
}

.pt-meter {
    display: flex;
    gap: 5px;
    margin-top: 9px;
}

.pt-meter i {
    flex: 1;
    height: 5px;
    border-radius: 4px;
    background: #ececf3;
    transition: background .3s;
}

.pt-meter.s1 i:nth-child(1) { background: #EF4444; }
.pt-meter.s2 i:nth-child(-n+2) { background: #F59E0B; }
.pt-meter.s3 i:nth-child(-n+3) { background: #84CC16; }
.pt-meter.s4 i { background: #14B8A6; }

.pt-meter-txt {
    margin-top: 6px;
    font-size: 11.5px;
    color: #9a9aa8;
    min-height: 16px;
}

/* pilihan role */

.pt-roles {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.pt-role input { display: none; }

.pt-role label {
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

.pt-role label:hover {
    transform: translateY(-2px);
    border-color: #cfcfdc;
}

.pt-role .ic {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pt-role.adm .ic { background: #FFEDD5; }
.pt-role.opr .ic { background: var(--us-soft); }

.pt-role strong {
    display: block;
    font-size: 13px;
    color: #1d1d27;
}

.pt-role small {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    line-height: 1.45;
    color: #8a8a98;
    font-weight: 400;
}

.pt-role.adm input:checked + label {
    border-color: #FB7C3C;
    background: #FFF7F0;
    box-shadow: 0 0 0 4px rgba(251,124,60,.13);
}

.pt-role.opr input:checked + label {
    border-color: var(--us);
    background: #F0FDFA;
    box-shadow: 0 0 0 4px rgba(20,184,166,.14);
}

.pt-roles.locked .pt-role label {
    cursor: not-allowed;
    opacity: .55;
}

.pt-roles.locked .pt-role label:hover { transform: none; border-color: #e6e6ee; }

.pt-roles.locked .pt-role input:checked + label { opacity: 1; }

.pt-lock-note {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 12px;
    padding: 11px 14px;
    border-radius: 12px;
    background: #FFF7ED;
    border: 1px solid #FED7AA;
    font-size: 11.8px;
    line-height: 1.55;
    color: #9A3412;
}

.pt-lock-note svg { flex-shrink: 0; margin-top: 1px; }

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
    background: linear-gradient(135deg, #14B8A6, #0F766E);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 12px 26px rgba(15,118,110,.32);
    transition: transform .25s var(--ease-smooth, ease), box-shadow .25s;
}

.pt-save:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px rgba(15,118,110,.4);
}

/* ===== RESPONSIVE ===== */

@media (max-width: 900px) {
    .pt-card { grid-template-columns: 1fr; }
    .pt-side { padding: 28px 22px; }
}

@media (max-width: 600px) {
    .pt-form { padding: 26px 20px; }
    .pt-roles { grid-template-columns: 1fr; }
    .pt-foot { flex-direction: column-reverse; }
    .pt-cancel, .pt-save { width: 100%; justify-content: center; }
}

</style>

<div class="content">
<div class="container-fluid pt-wrap">
<div class="pt-shell">

    <div class="pt-head">
        <div>
            <h2>Edit User</h2>
            <p>Perbarui data akun pengguna. Password hanya diganti jika Anda mengisinya.</p>
        </div>

        <a href="index.php" class="pt-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <div class="pt-card">

        <!-- ===== PANEL KIRI ===== -->

        <aside class="pt-side">

            <div class="pt-ring"><div id="pvInisial">?</div></div>

            <div class="pt-pill"><i></i> Mode Edit</div>

            <h3 id="pvNama"><?= htmlspecialchars($data['nama']); ?></h3>
            <div class="uname" id="pvUser">@<?= htmlspecialchars($data['username']); ?></div>

            <div class="pt-chip" id="pvRole">-</div>

            <div class="pt-rows">
                <div><span>ID User</span><b>#<?= (int) $data['id']; ?></b></div>
                <div><span>Password</span><b id="pvPass">Tidak diubah</b></div>
                <div><span>Hak akses</span><b id="pvAkses">-</b></div>
            </div>

            <div class="pt-changes" id="boxUbah">
                <div class="num" id="jmlUbah">0</div>
                <small id="txtUbah">Belum ada perubahan pada data ini.</small>
            </div>

            <div class="pt-note">
                <b>Catatan:</b> password lama tidak dapat dilihat. Isi kolom password hanya bila ingin menggantinya.
            </div>

        </aside>

        <!-- ===== FORM ===== -->

        <form action="update.php" method="POST" class="pt-form" autocomplete="off" id="formUser">

            <input type="hidden" name="id" value="<?= (int) $data['id']; ?>">

            <section>

                <div class="pt-sec">Identitas</div>

                <div class="pt-field" data-field="nama">
                    <label for="nama">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Nama Lengkap <em>*</em>
                        <span class="pt-tag">Diubah</span>
                    </label>
                    <div class="pt-input">
                        <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($data['nama']); ?>" required maxlength="100" autocomplete="off" data-asli="<?= htmlspecialchars($data['nama']); ?>">
                    </div>
                </div>

                <div class="pt-field" data-field="username">
                    <label for="username">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                        Username <em>*</em>
                        <span class="pt-tag">Diubah</span>
                    </label>
                    <div class="pt-input">
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($data['username']); ?>" required maxlength="50" autocomplete="off" data-asli="<?= htmlspecialchars($data['username']); ?>">
                    </div>
                    <div class="pt-hint">Dipakai untuk login. Gunakan huruf, angka, titik, atau garis bawah.</div>
                </div>

            </section>

            <section>

                <div class="pt-sec">Keamanan</div>

                <div class="pt-field" data-field="password">
                    <label for="password">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        Password Baru <span class="opt">(opsional)</span>
                        <span class="pt-tag">Diganti</span>
                    </label>
                    <div class="pt-input has-eye">
                        <input type="password" id="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah password" minlength="6" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')" data-asli="">
                        <button type="button" class="pt-eye" id="toggleEye" title="Tampilkan / sembunyikan">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#8a8a98" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="pt-meter" id="meter"><i></i><i></i><i></i><i></i></div>
                    <div class="pt-meter-txt" id="meterTxt">Minimal 6 karakter bila diisi.</div>
                </div>

            </section>

            <section class="pt-sec-role" id="secRole">

                <div class="pt-sec">Hak Akses <span class="pt-tag" style="margin-left:0">Diubah</span></div>

                <div class="pt-roles <?= $akun_saya ? 'locked' : ''; ?>">

                    <div class="pt-role adm">
                        <input type="radio" name="role" id="roleAdmin" value="Administrator" <?= $role_now === 'Administrator' ? 'checked' : ''; ?> <?= $akun_saya ? 'disabled' : ''; ?> required>
                        <label for="roleAdmin">
                            <span class="ic">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#C2410C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg>
                            </span>
                            <span>
                                <strong>Administrator</strong>
                                <small>Akses penuh, termasuk kelola pegawai dan user.</small>
                            </span>
                        </label>
                    </div>

                    <div class="pt-role opr">
                        <input type="radio" name="role" id="roleOperator" value="Operator" <?= $role_now === 'Operator' ? 'checked' : ''; ?> <?= $akun_saya ? 'disabled' : ''; ?>>
                        <label for="roleOperator">
                            <span class="ic">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#0F766E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                            </span>
                            <span>
                                <strong>Operator</strong>
                                <small>Mengelola surat masuk, surat keluar, dan buku tamu.</small>
                            </span>
                        </label>
                    </div>

                </div>

                <?php if ($akun_saya) { ?>
                    <!-- radio yang disabled tidak terkirim, jadi role dikirim lewat hidden -->
                    <input type="hidden" name="role" value="<?= htmlspecialchars($role_now); ?>">

                    <div class="pt-lock-note">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C2410C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <span>Ini adalah akun yang sedang Anda gunakan, jadi role tidak dapat diubah agar Anda tidak kehilangan akses admin.</span>
                    </div>
                <?php } ?>

            </section>

            <div class="pt-foot">
                <a href="index.php" class="pt-cancel">Batal</a>

                <button type="submit" class="pt-save">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
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

    var form     = document.getElementById('formUser');
    var nama     = document.getElementById('nama');
    var username = document.getElementById('username');
    var password = document.getElementById('password');
    var roles    = document.querySelectorAll('input[name="role"][type="radio"]');
    var roleAsli = <?= json_encode($role_now); ?>;

    var pvInisial = document.getElementById('pvInisial');
    var pvNama    = document.getElementById('pvNama');
    var pvUser    = document.getElementById('pvUser');
    var pvRole    = document.getElementById('pvRole');
    var pvPass    = document.getElementById('pvPass');
    var pvAkses   = document.getElementById('pvAkses');

    var meter    = document.getElementById('meter');
    var meterTxt = document.getElementById('meterTxt');

    var boxUbah = document.getElementById('boxUbah');
    var jmlUbah = document.getElementById('jmlUbah');
    var txtUbah = document.getElementById('txtUbah');
    var secRole = document.getElementById('secRole');

    var sudahKirim = false;

    function inisial(teks) {
        var kata = teks.trim().split(/\s+/), hasil = '';
        for (var i = 0; i < kata.length; i++) {
            if (/^[A-Za-z]/.test(kata[i])) hasil += kata[i].charAt(0).toUpperCase();
            if (hasil.length >= 2) break;
        }
        return hasil || '?';
    }

    function roleDipilih() {
        var nilai = roleAsli;
        roles.forEach(function (r) { if (r.checked) nilai = r.value; });
        return nilai;
    }

    function kekuatan() {

        var p = password.value, skor = 0;

        if (p.length >= 6)  skor++;
        if (p.length >= 10) skor++;
        if (/[A-Z]/.test(p) && /[a-z]/.test(p)) skor++;
        if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) skor++;

        if (p.length === 0) skor = 0;

        meter.className = 'pt-meter' + (skor ? ' s' + skor : '');

        var teks = [
            'Minimal 6 karakter bila diisi.',
            'Lemah, tambahkan karakter lain.',
            'Cukup, bisa lebih kuat.',
            'Baik.',
            'Kuat.'
        ];

        meterTxt.textContent = teks[skor];
    }

    function perbarui() {

        var n = nama.value.trim();
        var u = username.value.trim();
        var r = roleDipilih();

        pvInisial.textContent = inisial(n);
        pvNama.textContent    = n !== '' ? n : '-';
        pvUser.textContent    = '@' + (u !== '' ? u : '-');

        pvRole.className = 'pt-chip';

        if (r === 'Administrator') {
            pvRole.textContent = 'Administrator';
            pvRole.classList.add('admin');
            pvAkses.textContent = 'Penuh';
        } else {
            pvRole.textContent = 'Operator';
            pvRole.classList.add('operator');
            pvAkses.textContent = 'Surat & Buku Tamu';
        }

        pvPass.textContent = password.value.length === 0 ? 'Tidak diubah' : 'Akan diganti';

        /* hitung perubahan */

        var jumlah = 0;

        [nama, username, password].forEach(function (el) {
            var berubah = el.value !== el.getAttribute('data-asli');
            el.closest('.pt-field').classList.toggle('changed', berubah);
            if (berubah) jumlah++;
        });

        var roleBerubah = (r !== roleAsli);
        secRole.classList.toggle('changed', roleBerubah);
        if (roleBerubah) jumlah++;

        jmlUbah.textContent = jumlah;
        boxUbah.classList.toggle('on', jumlah > 0);
        txtUbah.textContent = jumlah > 0
            ? jumlah + ' bagian telah diubah dan belum disimpan.'
            : 'Belum ada perubahan pada data ini.';

        return jumlah;
    }

    [nama].forEach(function (el) { el.addEventListener('input', perbarui); });

    username.addEventListener('input', function () {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
        perbarui();
    });

    password.addEventListener('input', function () { kekuatan(); perbarui(); });
    roles.forEach(function (r) { r.addEventListener('change', perbarui); });

    document.getElementById('toggleEye').addEventListener('click', function () {
        password.type = (password.type === 'password') ? 'text' : 'password';
    });

    /* peringatan jika keluar tanpa menyimpan */

    form.addEventListener('submit', function () { sudahKirim = true; });

    window.addEventListener('beforeunload', function (e) {
        if (!sudahKirim && perbarui() > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    perbarui();
    kekuatan();

})();

</script>

<?php
include "../layouts/footer.php";
?>