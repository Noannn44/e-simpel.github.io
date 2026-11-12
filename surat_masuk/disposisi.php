<?php

require_once "../config/auth.php";

adminDanOperator();

include "../config/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo "<script>alert('ID surat tidak valid.'); window.location='index.php';</script>";
    exit;
}

$query = mysqli_query($koneksi, "SELECT * FROM surat_masuk WHERE id = $id LIMIT 1");

if (!$query || mysqli_num_rows($query) === 0) {
    echo "<script>alert('Data surat tidak ditemukan.'); window.location='index.php';</script>";
    exit;
}

$data = mysqli_fetch_assoc($query);

$nomor_surat = htmlspecialchars($data['nomor_surat'] ?? '-');
$kode_berkas = htmlspecialchars(explode('/', $data['nomor_surat'] ?? '')[0]);
$pengirim = htmlspecialchars($data['pengirim'] ?? '-');
$perihal = htmlspecialchars($data['perihal'] ?? '-');

$tanggal_surat = (!empty($data['tanggal_surat']) && $data['tanggal_surat'] !== '0000-00-00')
    ? date('d F Y', strtotime($data['tanggal_surat'])) : '-';

$tanggal_diterima = (!empty($data['tanggal_diterima']) && $data['tanggal_diterima'] !== '0000-00-00')
    ? date('d F Y', strtotime($data['tanggal_diterima'])) : '-';

$romawi_bulan = [
    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
    7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
];

$no_agenda = '-';

if (!empty($data['tanggal_diterima']) && $data['tanggal_diterima'] !== '0000-00-00') {

    $tahun_agenda = date('Y', strtotime($data['tanggal_diterima']));
    $bulan_agenda = (int) date('n', strtotime($data['tanggal_diterima']));
    $tgl_diterima_sql = mysqli_real_escape_string($koneksi, $data['tanggal_diterima']);

    $agenda_query = mysqli_query($koneksi, "
        SELECT COUNT(*) AS jumlah FROM surat_masuk
        WHERE YEAR(tanggal_diterima) = '$tahun_agenda'
        AND (
            tanggal_diterima < '$tgl_diterima_sql'
            OR (tanggal_diterima = '$tgl_diterima_sql' AND id <= {$data['id']})
        )
    ");

    $urutan = $agenda_query ? (int) mysqli_fetch_assoc($agenda_query)['jumlah'] : 0;

    if ($urutan > 0) {
        $no_agenda = $urutan . '-' . $romawi_bulan[$bulan_agenda];
    }
}

$sifat_surat = htmlspecialchars($data['sifat'] ?? '');

$opsi_tujuan = [
    'Kepala Badan',
    'Sekretaris',
    'Kepala Bidang Pengadaan, Pensiun dan Informasi',
    'Kepala Bidang Pengembangan Kompetensi Aparatur',
    'Kepala Bidang Penilaian Kinerja Aparatur, Pengembangan Karier, dan Disiplin',
];

$opsi_instruksi = [
    'Surat Untuk Kaban',
    'Mohon Disposisi Kaban',
    'Mohon Tanggapan dan Saran',
    'Harap Proses Lebih Lanjut',
    'Mohon Koordinasi / Konfirmasi',
];

$opsi_sifat = ['Biasa', 'Penting', 'Segera', 'Sangat Segera'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lembar Disposisi - <?= $nomor_surat; ?></title>

<style>
:root{
    --aksen: #1E3A5F;
    --aksen-tua: #12294A;
    --tint: #DDE6F3;
} 

* { box-sizing: border-box; }

html, body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

body {
    margin: 0;
    padding: 24px;
    background: #eeeeee;
    font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
    color: #000;
    font-size: 11.5px;
}

.toolbar {
    width: 210mm;
    margin: 0 auto 14px;
    display: flex;
    justify-content: space-between;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    border: none;
    cursor: pointer;
}

.btn-back { background: #55555a; color: white; }
.btn-print { background: #FB7C3C; color: white; }

.sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto 14px;
    background: #ffffff;
    padding: 10mm 10mm;
    box-shadow: 0 2px 10px rgba(0,0,0,.12);
    display: flex;
    flex-direction: column;
}

/* ===================== KOP SURAT ===================== */

.lh { text-align: center; margin-bottom: 6px; }
.lh { padding: 0; }
.lh .lh-row { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; column-gap: 6px; }
.lh .lh-logo { height: 90px; width: auto; justify-self: start; display: block; }
.lh .lh-text { grid-column: 2; }
.lh h1 { margin: 0; font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.lh h2 { margin: 2px 0 0; font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.lh h3 { margin: 2px 0 0; font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.lh p { margin: 3px 0 0; font-size: 10.5px; color: #222; }
.lh .kop-line { height: 3px; background: #FB7C3C; margin-top: 7px; border-radius: 3px; border-bottom: 1.5px solid #000; padding-bottom: 0; }
.lh .kop-line-2 { height: 1px; background: #000; margin-top: 2px; }


/* ===================== HEADING SECTION ===================== */

.sec-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    margin-bottom: 7px;
}

.sec-heading .bar { width: 7px; height: 24px; border-radius: 3px; flex-shrink: 0; }
.sec-heading.disposisi .bar { background: #FB7C3C; }
.sec-heading.kendali .bar { background: #2F6FED; }

.sec-heading h2 { margin: 0; font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
.sec-heading span { font-size: 10px; color: #333; margin-top: 1px; display: block; }

/* ===================== PEMISAH PUTUS-PUTUS ===================== */

.cut-separator {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 8px 0 0;
}

.cut-separator .scissors { font-size: 16px; color: #000; flex-shrink: 0; }
.cut-separator .dash-line {
    flex: 1;
    border-top: 2px dashed #000;
}

/* ===================== INFO PANEL (dipakai di kedua lembar) ===================== */

.info-panel { display: flex; gap: 8px; margin-bottom: 8px; }

.info-main { flex: 1; border: 2px solid #000; border-radius: 8px; overflow: hidden; }

.info-row { display: flex; border-bottom: 1.5px solid #000; }
.info-row:last-child { border-bottom: none; }

.info-cell { flex: 1; padding: 4px 11px; border-right: 1.5px solid #000; }
.info-cell:last-child { border-right: none; }

.info-cell .i-label { font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; color: #333; font-weight: 700; margin-bottom: 1px; }
.info-cell .i-value { font-size: 12px; font-weight: 700; color: #000; }
.info-cell .i-value.upper { text-transform: uppercase; }
.info-cell .i-value.small { font-size: 11px; font-weight: 500; color: #222; line-height: 1.4; }

.info-row.perihal-row .info-cell { border-right: none; }

.kode-box {
    width: 84px;
    flex-shrink: 0;
    border: 2px solid #000;
    border-radius: 8px;
    background: var(--tint);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 6px 4px;
}

.kode-box.orange { background: #fcdcc0; }
.kode-box.orange .kb-title, .kode-box.orange .kb-value { color: #000; }

.kode-box.blue { background:  #bed6f8; }
.kode-box.blue .kb-title, .kode-box.blue .kb-value { color: #000; }

.kode-box .kb-title { font-size: 9px; text-transform: uppercase; letter-spacing: .3px; font-weight: 700; margin-bottom: 5px; }
.kode-box .kb-value { font-size: 16px; font-weight: 900; word-break: break-all; }

/* ===================== DUA KOLOM PANEL ===================== */

.two-col { display: flex; gap: 8px; }

.panel { flex: 1; border: 2px solid #000; border-radius: 8px; padding: 8px 11px 9px; }

.panel-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .3px;
    color: #000;
    margin-bottom: 6px;
    padding-bottom: 5px;
    border-bottom: 1.5px solid #000;
}

.panel.tujuan .panel-title { background: #fcdcc0;; }
.panel.instruksi .panel-title { background: #bed6f8; }
.panel.sifat .panel-title { background: #bed6f8; }
.panel.ttd .panel-title { background: #fcdcc0; }

.panel-title .dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.panel.tujuan .panel-title .dot { background: #FB7C3C; }
.panel.instruksi .panel-title .dot { background: #2F6FED; }
.panel.sifat .panel-title .dot { background: #2F6FED; }
.panel.ttd .panel-title .dot { background: #FB7C3C; }

.panel-body { padding: 8px 11px 9px; }

.opt-line { display: flex; align-items: center; gap: 7px; margin-bottom: 5px; font-size: 11px; font-weight: 600; color: #000; }
.opt-line:last-child { margin-bottom: 0; }
.opt-line.filled { font-weight: 700; }


.chk {
    width: 12px;
    height: 12px;
    border: 2px solid #000;
    border-radius: 3px;
    flex-shrink: 0;
    text-align: center;
    line-height: 9px;
    font-size: 9px;
    font-weight: 900;
}

.chk.on::after { content: "✓"; }

.panel.instruksi .chk { border-color: #000; }
.panel.sifat .chk { border-color: #000; }

.opt-note { color: #333; font-style: italic; }

.ttd-space { min-height: 15mm; }

/* ===================== FOOTER SERAH TERIMA ===================== */

.footer-panel { margin-top: 7px; border: 2px solid #000; border-radius: 8px; display: flex; overflow: hidden; min-height: 13mm; }
.footer-cell { flex: 1; padding: 6px 10px; border-right: 1.5px solid #000; }
.footer-cell:last-child { border-right: none; }
.footer-cell .f-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .3px; color: #333; font-weight: 700; }
.footer-cell .f-value { margin-top: 8px; font-size: 10.5px; font-weight: 700; color: #000; }

@media print {
    @page { size: A4 portrait; margin: 7mm 10mm; }
    html, body { width: 190mm; padding: 0; margin: 0; background: white; }
    .toolbar { display: none; }
    .sheet { width: 190mm; min-height: 283mm; margin: 0; box-shadow: none; padding: 0; }
}

@media screen and (max-width: 800px) {
    body { padding: 10px; }
    .toolbar, .sheet { width: 100%; }
}

</style>
</head>
<body>

<div class="toolbar">
    <a href="detail.php?id=<?= $id; ?>" class="btn btn-back">← Kembali</a>
    <button type="button" onclick="window.print()" class="btn btn-print">🖨 Cetak Lembar Disposisi</button>
</div>

<div class="sheet">

    <!-- ===================== KOP SURAT ===================== -->

    <div class="lh">
        <div class="lh-row">
            <img class="lh-logo" src="../assets/img/lambang_tapin.png" alt="Lambang Kabupaten Tapin">
            <div class="lh-text">
                <h1>Pemerintah Kabupaten Tapin</h1>
        <h2>Badan Kepegawaian dan Pengembangan</h2>
        <h3>Sumber Daya Manusia</h3>
        <p>Jl. SPG RT.01 No.78 Kel. Rangda Malingkung Kec. Tapin Utara - Rantau Kab. Tapin - Kalimantan Selatan, 71114</p>
        <p>Email. bkpsdmtapin@gmail.com &nbsp;Website: https://bkpsdm.profile.tapinkab.go.id/</p>
            </div>
        </div>
        <div class="kop-line"></div>
    </div>

    <!-- ===================== LEMBAR DISPOSISI ===================== -->

    <div class="sec-heading disposisi">
        <div class="bar"></div>
        <div>
            <h2>Lembar Disposisi</h2>
            <span>Diisi oleh petugas penerima surat</span>
        </div>
    </div>

    <div class="info-panel">

        <div class="info-main">
            <div class="info-row">
                <div class="info-cell">
                    <div class="i-label">Surat Dari</div>
                    <div class="i-value upper"><?= $pengirim; ?></div>
                </div>
                <div class="info-cell">
                    <div class="i-label">Tanggal Diterima</div>
                    <div class="i-value"><?= $tanggal_diterima; ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <div class="i-label">Nomor Surat</div>
                    <div class="i-value"><?= $nomor_surat; ?></div>
                </div>
                <div class="info-cell">
                    <div class="i-label">No. Agenda</div>
                    <div class="i-value"><?= $no_agenda; ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <div class="i-label">Tanggal Surat</div>
                    <div class="i-value"><?= $tanggal_surat; ?></div>
                </div>
                <div class="info-cell">
                    <div class="i-label">Sifat</div>
                    <div class="i-value"><?= $sifat_surat !== '' ? $sifat_surat : '&nbsp;'; ?></div>
                </div>
            </div>
            <div class="info-row perihal-row">
                <div class="info-cell">
                    <div class="i-label">Perihal</div>
                    <div class="i-value"><?= $perihal; ?></div>
                </div>
            </div>
        </div>

        <div class="kode-box orange">
            <div class="kb-title">Kode Berkas</div>
            <div class="kb-value"><?= $kode_berkas; ?></div>
        </div>

    </div>

    <div class="two-col">

        <div class="panel tujuan">
            <div class="panel-title"><span class="dot"></span>Diteruskan Kepada</div>
            <?php foreach ($opsi_tujuan as $opsi) { ?>
                <div class="opt-line"><span class="chk"></span><?= htmlspecialchars($opsi); ?></div>
            <?php } ?>
        </div>

        <div class="panel instruksi">
            <div class="panel-title"><span class="dot"></span>Disposisi — Dengan Hormat Harap</div>
            <?php foreach ($opsi_instruksi as $opsi) { ?>
                <div class="opt-line"><span class="chk"></span><?= htmlspecialchars($opsi); ?></div>
            <?php } ?>
            <div class="opt-line"><span class="chk"></span><span class="opt-note">...............................</span></div>
        </div>

    </div>

    <div class="footer-panel">
        <div class="footer-cell"><div class="f-label">Berkas Diserahkan Oleh</div></div>
        <div class="footer-cell"><div class="f-label">No. Kontak yg Dapat Dihubungi</div></div>
        <div class="footer-cell"><div class="f-label">Diterima Oleh</div></div>
        <div class="footer-cell"><div class="f-label">Pada Tanggal</div></div>
    </div>

    <!-- ===================== PEMISAH GUNTING ===================== -->

    <div class="cut-separator">
        <span class="scissors">&#9986;</span>
        <span class="dash-line"></span>
    </div>

    <!-- ===================== LEMBAR KENDALI ===================== -->

    <div class="sec-heading kendali">
        <div class="bar"></div>
        <div>
            <h2>Lembar Kendali Surat Masuk</h2>
            <span>Arsip pengendalian internal surat masuk</span>
        </div>
    </div>

    <div class="info-panel">

        <div class="info-main">
            <div class="info-row">
                <div class="info-cell">
                    <div class="i-label">Surat Dari</div>
                    <div class="i-value upper"><?= $pengirim; ?></div>
                    <div class="i-value small">
                        Nomor Surat: <?= $nomor_surat; ?><br>
                        Tanggal Surat: <?= $tanggal_surat; ?>
                    </div>
                </div>
                <div class="info-cell">
                    <div class="i-label">Diterima Tanggal</div>
                    <div class="i-value"><?= $tanggal_diterima; ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <div class="i-label">Nomor Agenda</div>
                    <div class="i-value"><?= $no_agenda; ?></div>
                </div>
                <div class="info-cell">
                    <div class="i-label">Sifat</div>
                    <div class="i-value"><?= $sifat_surat !== '' ? $sifat_surat : '&nbsp;'; ?></div>
                </div>
            </div>
            <div class="info-row perihal-row">
                <div class="info-cell">
                    <div class="i-label">Perihal</div>
                    <div class="i-value"><?= $perihal; ?></div>
                </div>
            </div>
        </div>

        <div class="kode-box blue">
            <div class="kb-title">Kode Berkas</div>
            <div class="kb-value"><?= $kode_berkas; ?></div>
        </div>

    </div>

    <div class="two-col">

        <div class="panel sifat">
            <div class="panel-title"><span class="dot"></span>Sifat Surat (Jenis Penanganan)</div>
            <?php foreach ($opsi_sifat as $s) { ?>
                <div class="opt-line <?= ($sifat_surat === $s) ? 'filled' : ''; ?>">
                    <span class="chk <?= ($sifat_surat === $s) ? 'on' : ''; ?>"></span><?= $s; ?>
                </div>
            <?php } ?>
        </div>

        <div class="panel ttd">
            <div class="panel-title"><span class="dot"></span>Nama dan Tanda Tangan Penerima</div>
            <div class="ttd-space"></div>
        </div>

    </div>

    <div class="footer-panel">
        <div class="footer-cell"><div class="f-label">Berkas Diserahkan Oleh</div></div>
        <div class="footer-cell"><div class="f-label">No. Kontak yg Dapat Dihubungi</div></div>
        <div class="footer-cell"><div class="f-label">Diterima Oleh</div></div>
        <div class="footer-cell"><div class="f-label">Pada Tanggal</div></div>
    </div>

</div>

<script>
// Bila dibuka di dalam popup (iframe) pada halaman detail, tombol Kembali menjadi Tutup.
if (window.self !== window.top) {
    var tombolKembali = document.querySelector('.btn-back');
    if (tombolKembali) {
        tombolKembali.textContent = '\u2715 Tutup';
        tombolKembali.setAttribute('href', '#');
        tombolKembali.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.parent && typeof window.parent.tutupDisposisi === 'function') {
                window.parent.tutupDisposisi();
            }
        });
    }
}
</script>
</body>
</html>