<?php

/* =========================================================
   FORM TAMBAH / UBAH KETIDAKHADIRAN
   Dipanggil dari tambah.php ($kh_id = 0) dan edit.php ($kh_id > 0)
========================================================= */

if (!isset($kh_id)) {
    http_response_code(403);
    exit;
}

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/auth.php";

adminDanOperator();

include "../config/database.php";
require_once "../config/log.php";
require_once "../config/ketidakhadiran.php";

date_default_timezone_set('Asia/Makassar');

siapkanTabelKetidakhadiran($koneksi);

$mode_ubah = ($kh_id > 0);
$jenis_opsi = ['Tugas Luar', 'Cuti'];

/* daftar pegawai */
$daftar_pegawai = [];
$qp = mysqli_query($koneksi, "SELECT id, nama, nip, jabatan FROM pegawai ORDER BY nama ASC");

while ($qp && ($r = mysqli_fetch_assoc($qp))) {
    $daftar_pegawai[(int) $r['id']] = $r;
}

/* nilai awal */
$f = [
    'pegawai_id'      => 0,
    'jenis'           => 'Tugas Luar',
    'tanggal_mulai'   => date('Y-m-d'),
    'tanggal_selesai' => date('Y-m-d'),
    'tujuan'          => '',
    'keperluan'       => '',
    'no_surat_tugas'  => '',
    'keterangan'      => '',
];

/* isian awal dari tombol "Catat tidak hadir" di daftar pegawai */
if (!$mode_ubah && $_SERVER['REQUEST_METHOD'] !== 'POST') {

    $pre_p = (int) ($_GET['pegawai_id'] ?? 0);

    if (isset($daftar_pegawai[$pre_p])) {
        $f['pegawai_id'] = $pre_p;
    }

    $pre_t = (string) ($_GET['tanggal'] ?? '');
    $pre_d = DateTime::createFromFormat('Y-m-d', $pre_t);

    if ($pre_d && $pre_d->format('Y-m-d') === $pre_t) {
        $f['tanggal_mulai'] = $pre_t;
        $f['tanggal_selesai'] = $pre_t;
    }
}

if ($mode_ubah) {

    $st0 = mysqli_prepare($koneksi, "SELECT * FROM ketidakhadiran WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($st0, "i", $kh_id);
    mysqli_stmt_execute($st0);
    $r0 = mysqli_stmt_get_result($st0);
    $lama = $r0 ? mysqli_fetch_assoc($r0) : null;

    if (!$lama) {
        echo "<script>alert('Data tidak ditemukan.'); window.location='index.php';</script>";
        exit;
    }

    foreach ($f as $k => $v) {
        $f[$k] = (string) ($lama[$k] ?? $v);
    }

    $f['pegawai_id'] = (int) $lama['pegawai_id'];
}

$galat = '';

/* =========================================================
   SIMPAN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {

        $galat = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';

    } else {

        $f['pegawai_id']      = (int) ($_POST['pegawai_id'] ?? 0);
        $f['jenis']           = in_array($_POST['jenis'] ?? '', $jenis_opsi, true) ? $_POST['jenis'] : 'Tugas Luar';
        $f['tanggal_mulai']   = (string) ($_POST['tanggal_mulai'] ?? '');
        $f['tanggal_selesai'] = (string) ($_POST['tanggal_selesai'] ?? '');
        $f['tujuan']          = mb_substr(trim((string) ($_POST['tujuan'] ?? '')), 0, 255);
        $f['keperluan']       = mb_substr(trim((string) ($_POST['keperluan'] ?? '')), 0, 255);
        $f['no_surat_tugas']  = mb_substr(trim((string) ($_POST['no_surat_tugas'] ?? '')), 0, 100);
        $f['keterangan']      = mb_substr(trim((string) ($_POST['keterangan'] ?? '')), 0, 500);

        $d1 = DateTime::createFromFormat('Y-m-d', $f['tanggal_mulai']);
        $d2 = DateTime::createFromFormat('Y-m-d', $f['tanggal_selesai']);

        if (!isset($daftar_pegawai[$f['pegawai_id']])) {

            $galat = 'Pilih pegawai terlebih dahulu.';

        } elseif (!$d1 || $d1->format('Y-m-d') !== $f['tanggal_mulai'] || !$d2 || $d2->format('Y-m-d') !== $f['tanggal_selesai']) {

            $galat = 'Tanggal mulai dan tanggal selesai harus diisi dengan benar.';

        } elseif ($f['tanggal_selesai'] < $f['tanggal_mulai']) {

            $galat = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';

        } elseif ($f['jenis'] === 'Tugas Luar' && $f['tujuan'] === '') {

            $galat = 'Isi tujuan atau lokasi tugas luar.';

        } else {

            if ($f['jenis'] === 'Cuti') {
                $f['tujuan'] = '';
                $f['keperluan'] = '';
                $f['no_surat_tugas'] = '';
            }

            $pencatat = (string) ($_SESSION['nama'] ?? $_SESSION['username']);

            if ($mode_ubah) {

                $st = mysqli_prepare($koneksi, "UPDATE ketidakhadiran
                    SET pegawai_id = ?, jenis = ?, tanggal_mulai = ?, tanggal_selesai = ?, tujuan = ?, keperluan = ?,
                        no_surat_tugas = ?, keterangan = ?, dicatat_oleh = ?
                    WHERE id = ?");

                mysqli_stmt_bind_param($st, "issssssssi", $f['pegawai_id'], $f['jenis'], $f['tanggal_mulai'], $f['tanggal_selesai'],
                    $f['tujuan'], $f['keperluan'], $f['no_surat_tugas'], $f['keterangan'], $pencatat, $kh_id);

                $aksi = 'ubah';

            } else {

                $st = mysqli_prepare($koneksi, "INSERT INTO ketidakhadiran
                    (pegawai_id, jenis, tanggal_mulai, tanggal_selesai, tujuan, keperluan, no_surat_tugas, keterangan, dicatat_oleh)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                mysqli_stmt_bind_param($st, "issssssss", $f['pegawai_id'], $f['jenis'], $f['tanggal_mulai'], $f['tanggal_selesai'],
                    $f['tujuan'], $f['keperluan'], $f['no_surat_tugas'], $f['keterangan'], $pencatat);

                $aksi = 'tambah';
            }

            if ($st && mysqli_stmt_execute($st)) {

                catatAktivitas($koneksi, $aksi, 'ketidakhadiran',
                    $f['jenis'] . ': ' . $daftar_pegawai[$f['pegawai_id']]['nama'] . ' (' . $f['tanggal_mulai'] . ' s.d. ' . $f['tanggal_selesai'] . ')');

                header("Location: index.php?pesan=" . $aksi);
                exit;
            }

            $galat = 'Data gagal disimpan. Coba lagi.';
        }
    }
}

include "../layouts/header.php";
include "../layouts/navbar.php";
include "../layouts/sidebar.php";
?>

<style>

.kh-wrap { --ac:#0EA5E9; --ac-dark:#0369A1; --ac-soft:#E0F2FE; --cuti:#F59E0B; }

.kf-shell { max-width: 1120px; margin: 0 auto; }

.kf-top { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
.kf-eyebrow { display:inline-flex; align-items:center; gap:7px; font-size:11px; font-weight:800; letter-spacing:1.3px; color:var(--ac-dark); margin-bottom:6px; }
.kf-eyebrow i { width:7px; height:7px; border-radius:50%; background:var(--ac); box-shadow:0 0 0 4px rgba(14,165,233,.22); }
.kf-top h2 { margin:0; font-size:26px; font-weight:800; letter-spacing:-.3px; color:var(--color-text); }
.kf-top p { margin:4px 0 0; font-size:13.5px; color:var(--color-text-muted); }
.kf-back { display:inline-flex; align-items:center; gap:8px; height:42px; padding:0 18px; border-radius:12px; font-size:13px; font-weight:700; text-decoration:none;
    background:var(--color-surface); border:1.5px solid var(--color-border); color:var(--color-text); transition:all .2s; }
.kf-back:hover { border-color:var(--ac); color:var(--ac-dark); transform:translateY(-2px); }

.kf-grid { display:grid; grid-template-columns: minmax(0,1fr) 330px; gap:20px; align-items:start; }

.kf-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:24px; padding:8px 28px 26px; box-shadow:var(--shadow-card); }

.kf-sec { display:flex; align-items:center; gap:12px; margin:24px 0 16px; }
.kf-sec .no { width:28px; height:28px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; color:#fff;
    background:linear-gradient(135deg,#38BDF8,#0369A1); box-shadow:0 6px 12px rgba(14,165,233,.3); }
.kf-sec h3 { margin:0; font-size:15px; font-weight:800; color:var(--color-text); }
.kf-sec small { display:block; font-size:12px; font-weight:500; color:var(--color-text-muted); margin-top:1px; }

.kf-field { display:flex; flex-direction:column; gap:7px; margin-bottom:16px; }
.kf-field > label { font-size:12.5px; font-weight:700; color:var(--color-text); }
.kf-field > label em { font-style:normal; font-weight:500; color:var(--color-text-muted); }
.kf-field small { font-size:12px; color:var(--color-text-muted); }

.kf-ctl, .kf-field select, .kf-field textarea {
    width:100%; padding:13px 15px; border:1.5px solid var(--color-border); border-radius:14px; background:var(--color-bg);
    color:var(--color-text); font-size:14px; font-family:inherit; outline:none; transition:border-color .2s, box-shadow .2s, background .2s;
}
.kf-ctl:hover, .kf-field select:hover, .kf-field textarea:hover { border-color:#BAE6FD; }
.kf-ctl:focus, .kf-field select:focus, .kf-field textarea:focus { border-color:var(--ac); background:var(--color-surface); box-shadow:0 0 0 4px rgba(14,165,233,.14); }
.kf-field textarea { min-height:90px; resize:vertical; }

.kf-ico { position:relative; }
.kf-ico svg { position:absolute; left:14px; top:50%; transform:translateY(-50%); pointer-events:none; color:var(--color-text-muted); }
.kf-ico .kf-ctl { padding-left:42px; }

.kf-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

/* kartu jenis */
.kf-jenis { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.kf-jenis input { position:absolute; opacity:0; pointer-events:none; }
.kf-jenis label { position:relative; display:flex; gap:14px; align-items:center; padding:16px 18px; border:2px solid var(--color-border); border-radius:18px; cursor:pointer;
    background:var(--color-surface); transition:all .2s ease; }
.kf-jenis label:hover { transform:translateY(-2px); border-color:#BAE6FD; }
.kf-jenis .ic { flex:0 0 46px; width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; background:var(--color-bg); color:var(--color-text-muted); transition:all .2s; }
.kf-jenis b { display:block; font-size:15px; color:var(--color-text); }
.kf-jenis span.t { display:block; font-size:12px; font-weight:500; color:var(--color-text-muted); margin-top:2px; }
.kf-jenis .cek { position:absolute; right:14px; top:14px; width:22px; height:22px; border-radius:50%; border:2px solid var(--color-border); display:flex; align-items:center; justify-content:center; color:transparent; transition:all .2s; }
.kf-jenis input:checked + label.j-tl { border-color:#0EA5E9; background:#F0F9FF; box-shadow:0 0 0 4px rgba(14,165,233,.14); }
.kf-jenis input:checked + label.j-tl .ic { background:#0EA5E9; color:#fff; }
.kf-jenis input:checked + label.j-tl .cek { background:#0EA5E9; border-color:#0EA5E9; color:#fff; }
.kf-jenis input:checked + label.j-cuti { border-color:#F59E0B; background:#FFFBEB; box-shadow:0 0 0 4px rgba(245,158,11,.16); }
.kf-jenis input:checked + label.j-cuti .ic { background:#F59E0B; color:#fff; }
.kf-jenis input:checked + label.j-cuti .cek { background:#F59E0B; border-color:#F59E0B; color:#fff; }

/* pilihan cepat lama */
.kf-cepat { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:-4px; margin-bottom:6px; }
.kf-cepat span { font-size:12px; font-weight:700; color:var(--color-text-muted); margin-right:2px; }
.kf-cepat button { border:1.5px solid var(--color-border); background:var(--color-surface); color:var(--color-text); border-radius:999px; padding:6px 14px; font-size:12.5px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; }
.kf-cepat button:hover { border-color:var(--ac); color:var(--ac-dark); background:#F0F9FF; }

.kf-galat { background:#FEF2F2; border:1px solid #FECACA; color:#B91C1C; border-radius:14px; padding:13px 16px; font-size:13.5px; font-weight:600; margin-bottom:16px; }

.kf-aksi { display:flex; justify-content:flex-end; gap:10px; margin-top:10px; padding-top:20px; border-top:1px dashed var(--color-border); }
.kf-btn { border:none; border-radius:14px; padding:13px 26px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; font-family:inherit; transition:all .2s; display:inline-flex; align-items:center; gap:8px; }
.kf-btn.abu { background:var(--color-bg); color:var(--color-text); }
.kf-btn.abu:hover { background:var(--color-border); }
.kf-btn.utama { background:var(--ac); color:#fff; box-shadow:0 10px 22px rgba(14,165,233,.38); }
.kf-btn.utama:hover { background:var(--ac-dark); transform:translateY(-2px); }

.kf-sembunyi { display:none; }

/* ringkasan */
.kf-side { position:sticky; top:90px; }
.kf-sum { border-radius:24px; overflow:hidden; background:var(--color-surface); border:1px solid var(--color-border); box-shadow:var(--shadow-card); }
.kf-sum-head { padding:22px 22px 20px; background:linear-gradient(135deg,#071a2b 0%,#0b3354 60%,#0a6aa8 100%); color:#fff; position:relative; transition:background .3s; }
.kf-sum-head.cuti { background:linear-gradient(135deg,#3a2105 0%,#92400E 60%,#D97706 100%); }
.kf-sum-head small { font-size:10.5px; font-weight:800; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.6); }
.kf-orang { display:flex; align-items:center; gap:13px; margin-top:12px; }
.kf-ava { flex:0 0 52px; width:52px; height:52px; border-radius:16px; background:rgba(255,255,255,.16); display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800; }
.kf-orang b { display:block; font-size:15.5px; line-height:1.3; }
.kf-orang span { display:block; font-size:12px; color:rgba(255,255,255,.7); margin-top:2px; }
.kf-sum-body { padding:20px 22px 22px; }
.kf-baris { display:flex; align-items:flex-start; gap:12px; padding:11px 0; border-bottom:1px solid var(--color-border); font-size:13px; }
.kf-baris:last-child { border-bottom:none; padding-bottom:0; }
.kf-baris .k { flex:0 0 74px; font-size:11px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; color:var(--color-text-muted); padding-top:2px; }
.kf-baris .v { flex:1; font-weight:600; color:var(--color-text); word-break:break-word; }
.kf-lama { display:inline-block; padding:3px 11px; border-radius:999px; background:var(--ac-soft); color:var(--ac-dark); font-size:12px; font-weight:800; }
.kf-lama.cuti { background:#FEF3C7; color:#B45309; }
.kf-kosong { color:var(--color-text-muted); font-weight:500 !important; }
.kf-tip { margin-top:14px; padding:13px 16px; border-radius:16px; background:var(--color-bg); font-size:12px; line-height:1.6; color:var(--color-text-muted); }

@media (max-width: 900px) {
    .kf-grid { grid-template-columns:1fr; }
    .kf-side { position:static; order:-1; }
}
@media (max-width: 640px) {
    .kf-row, .kf-jenis { grid-template-columns:1fr; }
    .kf-card { padding:4px 18px 22px; }
}

</style>

<?php
$peta_pegawai = [];

foreach ($daftar_pegawai as $pid => $p) {
    $peta_pegawai[$pid] = ['nama' => $p['nama'], 'jabatan' => $p['jabatan'], 'nip' => $p['nip']];
}
?>

<div class="content">
<div class="container-fluid kh-wrap">
<div class="kf-shell">

    <div class="kf-top">
        <div>
            <div class="kf-eyebrow"><i></i> KEPEGAWAIAN</div>
            <h2><?= $mode_ubah ? 'Ubah Ketidakhadiran' : 'Catat Ketidakhadiran'; ?></h2>
            <p>Catat pegawai yang sedang tugas luar atau cuti.</p>
        </div>

        <a href="index.php" class="kf-back">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <?php if ($galat !== '') { ?>
        <div class="kf-galat"><?= htmlspecialchars($galat); ?></div>
    <?php } ?>

    <div class="kf-grid">

    <form method="POST" class="kf-card" autocomplete="off">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">

        <div class="kf-sec"><span class="no">1</span><div><h3>Pegawai dan jenis</h3><small>Siapa yang tidak hadir, dan karena apa</small></div></div>

        <div class="kf-field">
            <label for="pegawai_id">Pegawai</label>
            <select name="pegawai_id" id="pegawai_id" required>
                <option value="">Pilih pegawai</option>
                <?php foreach ($daftar_pegawai as $pid => $p) { ?>
                    <option value="<?= $pid; ?>" <?= ((int) $f['pegawai_id'] === $pid) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($p['nama'] . ($p['jabatan'] !== '' ? ' · ' . $p['jabatan'] : '')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="kf-field">
            <label>Jenis</label>
            <div class="kf-jenis">
                <div>
                    <input type="radio" name="jenis" id="j1" value="Tugas Luar" <?= $f['jenis'] === 'Tugas Luar' ? 'checked' : ''; ?>>
                    <label for="j1" class="j-tl">
                        <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 13h20"/></svg></span>
                        <span><b>Tugas Luar</b><span class="t">Dinas atau kegiatan di luar kantor</span></span>
                        <span class="cek"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                    </label>
                </div>
                <div>
                    <input type="radio" name="jenis" id="j2" value="Cuti" <?= $f['jenis'] === 'Cuti' ? 'checked' : ''; ?>>
                    <label for="j2" class="j-cuti">
                        <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></span>
                        <span><b>Cuti</b><span class="t">Pegawai sedang cuti</span></span>
                        <span class="cek"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="kf-sec"><span class="no">2</span><div><h3>Waktu</h3><small>Rentang tanggal tidak hadir</small></div></div>

        <div class="kf-row">
            <div class="kf-field">
                <label for="mulai">Tanggal mulai</label>
                <input type="date" name="tanggal_mulai" id="mulai" class="kf-ctl" value="<?= htmlspecialchars($f['tanggal_mulai']); ?>" required>
            </div>
            <div class="kf-field">
                <label for="selesai">Sampai tanggal</label>
                <input type="date" name="tanggal_selesai" id="selesai" class="kf-ctl" value="<?= htmlspecialchars($f['tanggal_selesai']); ?>" required>
            </div>
        </div>

        <div class="kf-cepat">
            <span>Lama:</span>
            <button type="button" data-hari="1">1 hari</button>
            <button type="button" data-hari="2">2 hari</button>
            <button type="button" data-hari="3">3 hari</button>
            <button type="button" data-hari="5">5 hari</button>
            <button type="button" data-hari="7">1 minggu</button>
        </div>

        <div id="bagianTugas">

            <div class="kf-sec"><span class="no">3</span><div><h3>Rincian tugas luar</h3><small>Ke mana dan untuk acara apa</small></div></div>

            <div class="kf-field">
                <label for="tujuan">Tujuan / lokasi</label>
                <div class="kf-ico">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <input type="text" name="tujuan" id="tujuan" class="kf-ctl" maxlength="255" value="<?= htmlspecialchars($f['tujuan']); ?>" placeholder="Contoh: Hotel Novotel Banjarmasin">
                </div>
            </div>

            <div class="kf-field">
                <label for="keperluan">Acara / keperluan</label>
                <div class="kf-ico">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <input type="text" name="keperluan" id="keperluan" class="kf-ctl" maxlength="255" value="<?= htmlspecialchars($f['keperluan']); ?>" placeholder="Contoh: Workshop implementasi sistem kerja">
                </div>
            </div>

            <div class="kf-field">
                <label for="no_surat_tugas">Nomor surat tugas <em>(opsional)</em></label>
                <div class="kf-ico">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <input type="text" name="no_surat_tugas" id="no_surat_tugas" class="kf-ctl" maxlength="100" value="<?= htmlspecialchars($f['no_surat_tugas']); ?>">
                </div>
            </div>

        </div>

        <div class="kf-field" style="margin-top:8px">
            <label for="keterangan">Keterangan <em>(opsional)</em></label>
            <textarea name="keterangan" id="keterangan" maxlength="500"><?= htmlspecialchars($f['keterangan']); ?></textarea>
            <small>Untuk cuti, tidak perlu menuliskan alasan pribadi atau kesehatan.</small>
        </div>

        <div class="kf-aksi">
            <a href="index.php" class="kf-btn abu">Batal</a>
            <button type="submit" class="kf-btn utama">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $mode_ubah ? 'Simpan Perubahan' : 'Simpan'; ?>
            </button>
        </div>

    </form>

    <!-- ===== RINGKASAN ===== -->

    <aside class="kf-side">
        <div class="kf-sum">
            <div class="kf-sum-head" id="sumHead">
                <small>Ringkasan</small>
                <div class="kf-orang">
                    <div class="kf-ava" id="sumAva">?</div>
                    <div>
                        <b id="sumNama">Belum memilih pegawai</b>
                        <span id="sumJabatan">Pilih pegawai di formulir</span>
                    </div>
                </div>
            </div>
            <div class="kf-sum-body">
                <div class="kf-baris"><div class="k">Jenis</div><div class="v" id="sumJenis">Tugas Luar</div></div>
                <div class="kf-baris"><div class="k">Waktu</div><div class="v" id="sumWaktu">-</div></div>
                <div class="kf-baris"><div class="k">Lama</div><div class="v"><span class="kf-lama" id="sumLama">1 hari</span></div></div>
                <div class="kf-baris" id="barisTujuan"><div class="k">Tujuan</div><div class="v kf-kosong" id="sumTujuan">Belum diisi</div></div>
                <div class="kf-baris" id="barisAcara"><div class="k">Acara</div><div class="v kf-kosong" id="sumAcara">Belum diisi</div></div>
            </div>
        </div>
        <div class="kf-tip">Data ini akan tampil di halaman Status Pegawai, panel Dashboard, dan Laporan Ketidakhadiran.</div>
    </aside>

    </div>

</div>
</div>
</div>

<script>
(function () {

    var pegawai = <?= json_encode($peta_pegawai, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

    var $ = function (id) { return document.getElementById(id); };
    var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    function tgl(v) {
        if (!v) return '';
        var p = v.split('-');
        return parseInt(p[2], 10) + ' ' + bulan[parseInt(p[1], 10) - 1] + ' ' + p[0];
    }

    function selisih(a, b) {
        var d1 = new Date(a + 'T00:00:00'), d2 = new Date(b + 'T00:00:00');
        return Math.round((d2 - d1) / 86400000) + 1;
    }

    function tambahHari(v, n) {
        var d = new Date(v + 'T00:00:00');
        d.setDate(d.getDate() + n);
        var m = ('0' + (d.getMonth() + 1)).slice(-2), t = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + m + '-' + t;
    }

    function isi(el, teks, kosong) {
        el.textContent = teks && teks.trim() ? teks : kosong;
        el.classList.toggle('kf-kosong', !(teks && teks.trim()));
    }

    function segarkan() {

        var cuti = $('j2').checked;

        $('bagianTugas').classList.toggle('kf-sembunyi', cuti);
        $('barisTujuan').style.display = cuti ? 'none' : '';
        $('barisAcara').style.display = cuti ? 'none' : '';
        $('tujuan').required = !cuti;

        $('sumHead').classList.toggle('cuti', cuti);
        $('sumJenis').textContent = cuti ? 'Cuti' : 'Tugas Luar';
        $('sumLama').classList.toggle('cuti', cuti);

        var p = pegawai[$('pegawai_id').value];

        if (p) {
            $('sumNama').textContent = p.nama;
            $('sumJabatan').textContent = p.jabatan || '-';
            $('sumAva').textContent = p.nama.replace(/^(dr\.?|drs\.?|ir\.?|h\.?|hj\.?)\s+/i, '').charAt(0).toUpperCase();
        } else {
            $('sumNama').textContent = 'Belum memilih pegawai';
            $('sumJabatan').textContent = 'Pilih pegawai di formulir';
            $('sumAva').textContent = '?';
        }

        var m = $('mulai').value, s = $('selesai').value;

        if (m && s && s >= m) {
            $('sumWaktu').textContent = (m === s) ? tgl(m) : tgl(m) + ' s.d. ' + tgl(s);
            $('sumLama').textContent = selisih(m, s) + ' hari';
        } else {
            $('sumWaktu').textContent = '-';
            $('sumLama').textContent = '-';
        }

        isi($('sumTujuan'), $('tujuan').value, 'Belum diisi');
        isi($('sumAcara'), $('keperluan').value, 'Belum diisi');
    }

    ['pegawai_id', 'tujuan', 'keperluan'].forEach(function (id) {
        $(id).addEventListener('input', segarkan);
        $(id).addEventListener('change', segarkan);
    });

    document.querySelectorAll('input[name="jenis"]').forEach(function (r) { r.addEventListener('change', segarkan); });

    $('mulai').addEventListener('change', function () {
        if (!$('selesai').value || $('selesai').value < $('mulai').value) {
            $('selesai').value = $('mulai').value;
        }
        $('selesai').min = $('mulai').value;
        segarkan();
    });

    $('selesai').addEventListener('change', segarkan);

    document.querySelectorAll('.kf-cepat button').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!$('mulai').value) return;
            $('selesai').value = tambahHari($('mulai').value, parseInt(b.dataset.hari, 10) - 1);
            segarkan();
        });
    });

    $('selesai').min = $('mulai').value;
    segarkan();

})();
</script>

<?php
include "../layouts/footer.php";