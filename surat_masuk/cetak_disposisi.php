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

$dq = mysqli_query($koneksi, "SELECT * FROM disposisi_surat WHERE surat_id = $id ORDER BY id DESC LIMIT 1");
$disposisi = ($dq && mysqli_num_rows($dq) > 0) ? mysqli_fetch_assoc($dq) : null;

if (!$disposisi) {
    echo "<script>
        alert('Surat ini belum memiliki data disposisi. Buat disposisi terlebih dahulu.');
        window.location='disposisi.php?id={$id}';
    </script>";
    exit;
}

$tujuan_list = array_map('trim', explode(',', $disposisi['tujuan_disposisi']));
$instruksi_list = array_map('trim', explode(',', $disposisi['isi_disposisi']));

function centang($list, $value) {
    return in_array($value, $list) ? 'cek' : '';
}

$nomor_surat = htmlspecialchars($data['nomor_surat'] ?? '-');
$kode_berkas = htmlspecialchars(explode('/', $data['nomor_surat'] ?? '')[0]);
$pengirim = htmlspecialchars($data['pengirim'] ?? '-');
$perihal = htmlspecialchars($data['perihal'] ?? '-');

$tanggal_surat = (!empty($data['tanggal_surat']) && $data['tanggal_surat'] !== '0000-00-00')
    ? date('d F Y', strtotime($data['tanggal_surat'])) : '-';

$tanggal_diterima = (!empty($data['tanggal_diterima']) && $data['tanggal_diterima'] !== '0000-00-00')
    ? date('d F Y', strtotime($data['tanggal_diterima'])) : '-';

$tanggal_disposisi_fmt = !empty($disposisi['tanggal_disposisi'])
    ? date('d F Y', strtotime($disposisi['tanggal_disposisi'])) : '-';

$nama_penerima = htmlspecialchars($disposisi['nama_penerima'] ?? '-');
$no_kontak = htmlspecialchars($disposisi['jabatan_penerima'] ?? '');
$sifat = htmlspecialchars($disposisi['sifat'] ?? 'Biasa');

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

$instruksi_lain = array_diff($instruksi_list, $opsi_instruksi);

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lembar Disposisi - <?= $nomor_surat; ?></title>

<style>

* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 24px;
    background: #eeeeee;
    font-family: Arial, Helvetica, sans-serif;
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
    margin: 0 auto 14px;
    background: #ffffff;
    padding: 12mm 14mm;
    box-shadow: 0 2px 10px rgba(0,0,0,.12);
}

.lh { text-align: center; margin-bottom: 8px; }
.lh { padding: 0; }
.lh .lh-row { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; column-gap: 6px; }
.lh .lh-logo { height: 90px; width: auto; justify-self: start; display: block; }
.lh .lh-text { grid-column: 2; }
.lh h1 { margin: 0; font-size: 13.5px; font-weight: bold; text-transform: uppercase; }
.lh h2 { margin: 2px 0 0; font-size: 13.5px; font-weight: bold; text-transform: uppercase; }
.lh h3 { margin: 2px 0 0; font-size: 13px; font-weight: bold; text-transform: uppercase; }
.lh p { margin: 3px 0 0; font-size: 9.5px; }

.title-bar {
    background: #fff2a8;
    border: 1px solid #000;
    text-align: center;
    font-weight: bold;
    font-size: 12px;
    padding: 4px;
    text-transform: uppercase;
    margin-top: 6px;
}

table.doc-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: -1px; }
table.doc-table td { border: 1px solid #000; padding: 5px 7px; vertical-align: top; font-size: 11px; }

.label-col { width: 95px; }
.colon-col { width: 10px; }
.bold { font-weight: bold; }
.upper { text-transform: uppercase; }

.kode-berkas-box { text-align: center; font-weight: bold; }
.kode-berkas-box .kb-title { font-size: 10px; font-weight: bold; margin-bottom: 6px; }

.disp-cols { display: flex; }
.disp-col { flex: 1; border: 1px solid #000; border-top: none; padding: 8px; }
.disp-col + .disp-col { border-left: none; }
.disp-col-title { font-weight: bold; margin-bottom: 6px; font-size: 11px; }
.disp-col-title.center { text-align: center; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 8px; }

.cb {
    display: inline-block;
    width: 11px;
    height: 11px;
    border: 1px solid #000;
    margin-right: 6px;
    vertical-align: middle;
    text-align: center;
    line-height: 10px;
    font-size: 9px;
    font-weight: bold;
}

.cb.cek::after { content: "✓"; }

.opsi-line { margin-bottom: 6px; font-size: 10.5px; }

.bottom-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; }
.bottom-table td { border: 1px solid #000; padding: 6px 7px; font-size: 10px; vertical-align: top; }
.bottom-table .bt-title { font-size: 9px; }
.bottom-table .bt-value { font-weight: bold; margin-top: 10px; }

.cut-line { display: flex; align-items: center; gap: 8px; margin: 6px 0; font-size: 12px; }
.cut-line .dash { flex: 1; border-top: 1px dashed #000; }

@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }
    html, body {
        width: 210mm;
        padding: 0;
        margin: 0;
        background: white;
    }
    .toolbar { display: none; }
    .sheet {
        width: 210mm;
        min-height: 297mm;
        max-height: 297mm;
        margin: 0;
        box-shadow: none;
        padding: 12mm 14mm;
        overflow: hidden;
        page-break-after: always;
    }
    .sheet:last-child { page-break-after: auto; }
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
    <button type="button" onclick="window.print()" class="btn btn-print">🖨 Cetak Disposisi</button>
</div>

<!-- ===================== SHEET 1: LEMBAR DISPOSISI + LEMBAR KENDALI ===================== -->

<div class="sheet">

    <div class="lh">
        <div class="lh-row">
            <img class="lh-logo" src="../assets/img/lambang_tapin.png" alt="Lambang Kabupaten Tapin">
            <div class="lh-text">
                <h1>Pemerintah Kabupaten Tapin</h1>
        <h2>Badan Kepegawaian dan Pengembangan</h2>
        <h3>Sumber Daya Manusia</h3>
        <p>Jl. SPG RT.01 No.78 Kel. Rangda Malingkung Kec. Tapin Utara - Rantau Kab. Tapin - Kalimantan Selatan, 71114</p>
        <p>Email. bkpsdmtapin@gmail.com Website: https://bkpsdm.profile.tapinkab.go.id/</p>
            </div>
        </div>
    </div>

    <div class="title-bar">Lembar Disposisi</div>

    <table class="doc-table">
        <tr>
            <td class="label-col">Surat dari</td>
            <td class="colon-col">:</td>
            <td class="bold upper"><?= $pengirim; ?></td>
            <td class="label-col">Tgl. Diterima</td>
            <td class="colon-col">:</td>
            <td><?= $tanggal_diterima; ?></td>
            <td rowspan="3" class="kode-berkas-box" width="90">
                <div class="kb-title">Kode Berkas</div>
                <div style="font-size:13px;"><?= $kode_berkas; ?></div>
            </td>
        </tr>
        <tr>
            <td class="label-col">No. Surat</td>
            <td class="colon-col">:</td>
            <td><?= $nomor_surat; ?></td>
            <td class="label-col">No. Agenda</td>
            <td class="colon-col">:</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td class="label-col">Tgl. Surat</td>
            <td class="colon-col">:</td>
            <td><?= $tanggal_surat; ?></td>
            <td class="label-col">Sifat</td>
            <td class="colon-col">:</td>
            <td><?= $sifat; ?></td>
        </tr>
        <tr>
            <td class="label-col">Perihal</td>
            <td class="colon-col">:</td>
            <td colspan="5"><?= $perihal; ?></td>
        </tr>
    </table>

    <div class="disp-cols">

        <div class="disp-col">
            <div class="disp-col-title">Diteruskan kepada:</div>
            <?php foreach ($opsi_tujuan as $opsi) { ?>
                <div class="opsi-line"><span class="cb <?= centang($tujuan_list, $opsi); ?>"></span><?= htmlspecialchars($opsi); ?></div>
            <?php } ?>
        </div>

        <div class="disp-col">
            <div class="disp-col-title center">Disposisi</div>
            <div style="font-weight:bold; margin-bottom:6px; font-size:10.5px;">Dengan Hormat Harap:</div>
            <?php foreach ($opsi_instruksi as $opsi) { ?>
                <div class="opsi-line"><span class="cb <?= centang($instruksi_list, $opsi); ?>"></span><?= htmlspecialchars($opsi); ?></div>
            <?php } ?>
            <div class="opsi-line"><span class="cb <?= !empty($instruksi_lain) ? 'cek' : ''; ?>"></span><?= !empty($instruksi_lain) ? htmlspecialchars(implode(', ', $instruksi_lain)) : '...............................'; ?></div>
        </div>

    </div>

    <table class="bottom-table">
        <tr>
            <td width="24%">
                <div class="bt-title">Berkas diserahkan oleh:</div>
            </td>
            <td width="24%">
                <div class="bt-title">No. Kontak yg dapat dihubungi</div>
                <div class="bt-value"><?= $no_kontak !== '' ? $no_kontak : '&nbsp;'; ?></div>
            </td>
            <td width="28%">
                <div class="bt-title">Diterima oleh:</div>
                <div class="bt-value"><?= $nama_penerima; ?></div>
            </td>
            <td width="24%">
                <div class="bt-title">Pada Tanggal</div>
                <div class="bt-value"><?= $tanggal_disposisi_fmt; ?></div>
            </td>
        </tr>
    </table>

    <div class="cut-line"><span>&#9986;</span><span class="dash"></span></div>

    <!-- LEMBAR KENDALI -->

    <div class="lh">
        <div class="lh-row">
            <img class="lh-logo" src="../assets/img/lambang_tapin.png" alt="Lambang Kabupaten Tapin">
            <div class="lh-text">
                <h1>Pemerintah Kabupaten Tapin</h1>
        <h2>Badan Kepegawaian dan Pengembangan</h2>
        <h3>Sumber Daya Manusia</h3>
        <p>Jl. SPG RT.01 No.78 Kel. Rangda Malingkung Kec. Tapin Utara - Rantau Kab. Tapin - Kalimantan Selatan, 71114</p>
        <p>Email. bkpsdmtapin@gmail.com Website: https://bkpsdm.profile.tapinkab.go.id/</p>
            </div>
        </div>
    </div>

    <div class="title-bar">Lembar Kendali Surat Masuk</div>

    <table class="doc-table">
        <tr>
            <td width="18%">Surat dari</td>
            <td class="colon-col">:</td>
            <td rowspan="3"><span class="bold upper"><?= $pengirim; ?></span><br>
                Nomor Surat : <?= $nomor_surat; ?><br>
                Tanggal Surat : <?= $tanggal_surat; ?>
            </td>
            <td width="17%">Diterima Tanggal</td>
            <td class="colon-col">:</td>
            <td><?= $tanggal_diterima; ?></td>
            <td rowspan="3" class="kode-berkas-box" width="90">
                <div class="kb-title">Kode Berkas</div>
                <div style="font-size:13px;"><?= $kode_berkas; ?></div>
            </td>
        </tr>
        <tr>
            <td>Nomor Agenda</td>
            <td class="colon-col">:</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td>Sifat</td>
            <td class="colon-col">:</td>
            <td><?= $sifat; ?></td>
        </tr>
        <tr>
            <td class="bold">Perihal:</td>
            <td colspan="6"><?= $perihal; ?></td>
        </tr>
    </table>

</div>

<!-- ===================== SHEET 2: LEMBAR TANDA TERIMA ===================== -->

<div class="sheet">

    <div class="lh">
        <div class="lh-row">
            <img class="lh-logo" src="../assets/img/lambang_tapin.png" alt="Lambang Kabupaten Tapin">
            <div class="lh-text">
                <h1>Pemerintah Kabupaten Tapin</h1>
        <h2>Badan Kepegawaian dan Pengembangan</h2>
        <h3>Sumber Daya Manusia</h3>
        <p>Jl. SPG RT.01 No.78 Kel. Rangda Malingkung Kec. Tapin Utara - Rantau Kab. Tapin - Kalimantan Selatan, 71114</p>
        <p>Email. bkpsdmtapin@gmail.com Website: https://bkpsdm.profile.tapinkab.go.id/</p>
            </div>
        </div>
    </div>

    <div class="title-bar">Lembar Tanda Terima Surat/Berkas Masuk</div>

    <table class="doc-table">
        <tr>
            <td width="24%">
                <div class="bt-title">Berkas diserahkan oleh:</div>
            </td>
            <td width="24%">
                <div class="bt-title">No. Kontak yg dapat dihubungi</div>
                <div class="bt-value"><?= $no_kontak !== '' ? $no_kontak : '&nbsp;'; ?></div>
            </td>
            <td width="24%">
                <div class="bt-title">Diterima oleh:</div>
                <div class="bt-value"><?= $nama_penerima; ?></div>
            </td>
            <td width="24%">
                <div class="bt-title">Pada Tanggal</div>
                <div class="bt-value"><?= $tanggal_disposisi_fmt; ?></div>
            </td>
        </tr>
        <tr>
            <td>Surat dari</td>
            <td class="colon-col" colspan="2">: <span class="bold upper"><?= $pengirim; ?></span></td>
            <td rowspan="4" style="text-align:center;">
                <div class="bt-title">Penerima Surat<br><i>(Nama dan Paraf)</i></div>
                <div class="bt-value" style="margin-top:30px;"><?= $nama_penerima; ?></div>
            </td>
        </tr>
        <tr>
            <td>Nomor Surat</td>
            <td colspan="2">: <?= $nomor_surat; ?></td>
        </tr>
        <tr>
            <td>Tanggal Surat</td>
            <td colspan="2">: <?= $tanggal_surat; ?></td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td colspan="2">: <?= $perihal; ?></td>
        </tr>
        <tr>
            <td>Lampiran</td>
            <td colspan="3">: <?= !empty($data['file_surat']) ? '1 (satu) berkas' : '-'; ?></td>
        </tr>
    </table>

</div>

</body>
</html>