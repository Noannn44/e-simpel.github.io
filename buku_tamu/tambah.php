<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

adminDanOperator();

date_default_timezone_set('Asia/Makassar');

$error = "";

$nama = "";
$instansi = "";
$keperluan = "";
$yang_melayani = "";
$tanggal = date('Y-m-d');
$jam = date('H:i');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama          = trim($_POST['nama_tamu_manual'] ?? '');
    $instansi      = trim($_POST['instansi_asal'] ?? '');
    $keperluan     = trim($_POST['keperluan'] ?? '');
    $yang_melayani = trim($_POST['yang_melayani'] ?? '');
    $tanggal       = $_POST['tanggal_kunjungan'] ?? '';
    $jam           = $_POST['jam_kunjungan'] ?? '';

    $tgl_obj = DateTime::createFromFormat('Y-m-d', $tanggal);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif ($nama === '' || $tanggal === '') {

        $error = "Nama dan tanggal kunjungan wajib diisi.";

    } elseif (!$tgl_obj || $tgl_obj->format('Y-m-d') !== $tanggal) {

        $error = "Format tanggal kunjungan tidak valid.";

    } elseif ($jam !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam)) {

        $error = "Format jam kunjungan tidak valid.";

    } else {

        if ($jam === '') {
            $jam = date('H:i');
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO tamu (nama, instansi_asal, keperluan, yang_melayani, tanggal_kunjungan, jam_kunjungan)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssssss",
            $nama,
            $instansi,
            $keperluan,
            $yang_melayani,
            $tanggal,
            $jam
        );

        try {

            mysqli_stmt_execute($stmt);

            header("Location: index.php");
            exit;

        } catch (Throwable $e) {

            error_log("buku_tamu/tambah.php: " . $e->getMessage());
            $error = "Data gagal disimpan. Periksa kembali isian Anda.";
        }

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

.btn-kembali:hover { background: var(--card-green-soft); color: #158a45; }
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
        radial-gradient(circle at 15% -5%, rgba(34,197,94,.45), transparent 55%),
        radial-gradient(circle at 95% 105%, rgba(47,111,237,.22), transparent 50%),
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
    background: rgba(34,197,94,.22);
    border: 3px solid rgba(34,197,94,.5);
    color: #86f0b0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 800;
    margin: 0 auto 18px;
    transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s var(--ease-smooth);
}

.ci-avatar svg { width: 38px; height: 38px; stroke: currentColor; stroke-width: 1.7; fill: none; }
.ci-avatar.filled { transform: scale(1.07); box-shadow: 0 0 0 12px rgba(34,197,94,.12); }

.ci-badge {
    display: flex;
    width: fit-content;
    align-items: center;
    gap: 6px;
    margin: 0 auto 14px;
    padding: 6px 15px;
    border-radius: 999px;
    background: rgba(34,197,94,.28);
    color: #b9f6cf;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .4px;
}

.ci-side-nama {
    font-size: 18px;
    font-weight: 700;
    min-height: 24px;
    word-break: break-word;
    line-height: 1.4;
}

.ci-side-nama.placeholder { color: rgba(255,255,255,.4); font-weight: 400; font-style: italic; font-size: 14px; }

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
.ss-row span { font-size: 12.5px; font-weight: 600; color: #fff; text-align: right; word-break: break-word; }

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

.ci-section-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 18px;
    font-size: 11.5px;
    font-weight: 800;
    color: #158a45;
    text-transform: uppercase;
    letter-spacing: .6px;
}

.ci-section-title::after { content: ""; flex: 1; height: 1px; background: var(--color-border); }

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

.ci-field label svg { width: 15px; height: 15px; stroke: var(--card-green); stroke-width: 2; fill: none; flex-shrink: 0; }

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

.ci-input:focus { border-color: var(--card-green); background: var(--color-surface); box-shadow: 0 0 0 4px var(--card-green-soft); }

.ci-row-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }

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
.btn-batal:hover { background: var(--card-green-soft); color: #158a45; }

.btn-checkin {
    flex: 1;
    background: linear-gradient(135deg, var(--card-green), #158a45);
    color: white;
    box-shadow: 0 12px 24px rgba(34,197,94,.34);
}

.btn-checkin:hover { color: white; transform: translateY(-2px); box-shadow: 0 16px 30px rgba(34,197,94,.44); }
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
            <h2>Check-in Tamu</h2>
            <p>Catat kedatangan tamu secara manual oleh resepsionis.</p>
        </div>
        <a href="index.php" class="btn-kembali">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"></path></svg>
            Kembali
        </a>
    </div>

    <?php if ($error !== '') { ?>
        <div class="alert-custom">⚠️ <?= htmlspecialchars($error); ?></div>
    <?php } ?>

    <form method="POST" id="formCheckin" autocomplete="off">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">

        <div class="ci-card">

            <!-- ============ PANEL KIRI (PRATINJAU HIDUP) ============ -->

            <div class="ci-side">
                <div class="ci-side-inner">

                    <div class="ci-avatar" id="pv_avatar">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M5 21c0-4 3-7 7-7s7 3 7 7"></path></svg>
                    </div>

                    <div class="ci-badge">🧑‍💼 BUKU TAMU</div>

                    <div class="ci-side-nama placeholder" id="pv_nama">Nama tamu akan muncul di sini</div>
                    <div class="ci-side-sub" id="pv_sub">&nbsp;</div>

                    <div class="side-divider"></div>

                    <div class="side-summary">
                        <div class="ss-row"><small>Tanggal</small><span id="sm_tgl">-</span></div>
                        <div class="ss-row"><small>Jam</small><span id="sm_jam">-</span></div>
                        <div class="ss-row"><small>Dilayani</small><span id="sm_melayani">-</span></div>
                    </div>

                    <div class="side-hint">
                        Tanggal dan jam terisi otomatis sesuai waktu saat ini. Ubah bila tamu datang pada waktu yang berbeda.
                    </div>

                </div>
            </div>

            <!-- ============ PANEL KANAN (FORM) ============ -->

            <div class="ci-main">

                <div class="ci-section-title">Data Tamu</div>

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M5 21c0-4 3-7 7-7s7 3 7 7"></path></svg>
                        Nama Tamu <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="nama_tamu_manual"
                        id="f_nama"
                        class="ci-input"
                        value=""
                        placeholder="Ketik nama tamu di sini"
                        autocomplete="off"
                        readonly
                        onfocus="this.removeAttribute('readonly'); this.classList.add('touched');"
                        oninput="updatePreview()"
                        required
                    >
                </div>

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Instansi Asal
                    </label>
                    <input
                        type="text"
                        name="instansi_asal"
                        id="f_instansi"
                        class="ci-input"
                        value="<?= htmlspecialchars($instansi); ?>"
                        placeholder="Nama instansi atau perusahaan"
                        autocomplete="off"
                        oninput="updatePreview()"
                    >
                </div>

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        Keperluan
                    </label>
                    <input
                        type="text"
                        name="keperluan"
                        id="f_keperluan"
                        class="ci-input"
                        value="<?= htmlspecialchars($keperluan); ?>"
                        placeholder="Contoh: Konsultasi kepegawaian"
                        autocomplete="off"
                        oninput="updatePreview()"
                    >
                </div>

                <div class="ci-section-title">Pelayanan &amp; Waktu</div>

                <div class="ci-field">
                    <label>
                        <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20c0-3.3 2.7-6 6-6"></path><circle cx="17" cy="8" r="3"></circle><path d="M15 14c3.3 0 6 2.7 6 6"></path></svg>
                        Yang Memberi Konsultasi
                    </label>
                    <input
                        type="text"
                        name="yang_melayani"
                        id="f_melayani"
                        class="ci-input"
                        value="<?= htmlspecialchars($yang_melayani); ?>"
                        placeholder="Nama petugas yang melayani"
                        autocomplete="off"
                        oninput="updatePreview()"
                    >
                </div>

                <div class="ci-field ci-row-2">
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                            Tanggal Kunjungan <span class="required">*</span>
                        </label>
                        <input
                            type="date"
                            name="tanggal_kunjungan"
                            id="f_tanggal"
                            class="ci-input"
                            value="<?= htmlspecialchars($tanggal); ?>"
                            oninput="updatePreview()"
                            required
                        >
                    </div>
                    <div>
                        <label>
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                            Jam Kunjungan
                        </label>
                        <input
                            type="time"
                            name="jam_kunjungan"
                            id="f_jam"
                            class="ci-input"
                            value="<?= htmlspecialchars($jam); ?>"
                            oninput="updatePreview()"
                        >
                    </div>
                </div>

                <!-- FOOTER -->

                <div class="ci-footer">
                    <a href="index.php" class="btn-batal">Batal</a>
                    <button type="submit" class="btn-checkin">
                        ✅ Simpan Check-in
                    </button>
                </div>

            </div>

        </div>

    </form>

</div>

</div>
</div>

<script>

var svgOrang = '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M5 21c0-4 3-7 7-7s7 3 7 7"></path></svg>';

// Pastikan field Nama Tamu benar-benar kosong saat halaman dimuat,
// walau browser mencoba mengisinya otomatis setelah render.
window.addEventListener('DOMContentLoaded', function () {
    var namaField = document.getElementById('f_nama');
    if (namaField && !namaField.classList.contains('touched')) {
        namaField.value = '';
    }
    updatePreview();
});

function formatTanggal(nilai) {
    if (!nilai) { return '-'; }
    var d = new Date(nilai + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function updatePreview() {

    var nama = document.getElementById('f_nama').value.trim();
    var instansi = document.getElementById('f_instansi').value.trim();
    var keperluan = document.getElementById('f_keperluan').value.trim();
    var melayani = document.getElementById('f_melayani').value.trim();
    var tanggal = document.getElementById('f_tanggal').value;
    var jam = document.getElementById('f_jam').value;

    var pvNama = document.getElementById('pv_nama');
    var pvAvatar = document.getElementById('pv_avatar');

    if (nama !== '') {
        pvNama.textContent = nama;
        pvNama.classList.remove('placeholder');
        pvAvatar.textContent = nama.charAt(0).toUpperCase();
        pvAvatar.classList.add('filled');
    } else {
        pvNama.textContent = 'Nama tamu akan muncul di sini';
        pvNama.classList.add('placeholder');
        pvAvatar.innerHTML = svgOrang;
        pvAvatar.classList.remove('filled');
    }

    var sub = [];
    if (instansi !== '') sub.push(instansi);
    if (keperluan !== '') sub.push(keperluan);
    document.getElementById('pv_sub').textContent = sub.length > 0 ? sub.join(' · ') : '\u00A0';

    document.getElementById('sm_tgl').textContent = formatTanggal(tanggal);
    document.getElementById('sm_jam').textContent = jam !== '' ? jam + ' WITA' : '-';
    document.getElementById('sm_melayani').textContent = melayani !== '' ? melayani : '-';

}

</script>

<?php

include "../layouts/footer.php";

?>