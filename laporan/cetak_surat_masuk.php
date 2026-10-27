<?php

session_start();

require_once "../config/auth.php";
require_once "../config/database.php";

/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}

/* =========================================================
   SARINGAN (sama dengan halaman laporan)
========================================================= */

$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int) date('Y');
}

$bulan = (isset($_GET['bulan']) && $_GET['bulan'] !== '') ? (int) $_GET['bulan'] : 0;

if ($bulan < 0 || $bulan > 12) {
    $bulan = 0;
}

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$label_bulan = ($bulan > 0) ? $nama_bulan[$bulan] : 'Semua Bulan';

$periode = ($bulan > 0) ? $label_bulan . ' ' . $tahun : 'Tahun ' . $tahun;

/* =========================================================
   DATA SURAT MASUK
========================================================= */

$sql    = "SELECT nomor_surat, tanggal_surat, tanggal_diterima, pengirim, perihal FROM surat_masuk WHERE YEAR(tanggal_surat) = ?";
$types  = "i";
$params = [$tahun];

if ($bulan > 0) {
    $sql .= " AND MONTH(tanggal_surat) = ?";
    $types .= "i";
    $params[] = $bulan;
}

if ($keyword !== '') {
    $sql .= " AND (nomor_surat LIKE ? OR pengirim LIKE ? OR perihal LIKE ?)";
    $like = "%" . $keyword . "%";
    $types .= "sss";
    array_push($params, $like, $like, $like);
}

$sql .= " ORDER BY tanggal_surat ASC, id ASC";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$rows = [];

while ($r = mysqli_fetch_assoc($hasil)) {
    $rows[] = $r;
}

/* =========================================================
   RENTANG NOMOR URUT (mis. nomor 1-6 atau 6-18)
   Nomor mengikuti kolom "No" pada tabel laporan
========================================================= */

$total_semua = count($rows);

$dari   = isset($_GET['dari']) ? (int) $_GET['dari'] : 0;
$sampai = isset($_GET['sampai']) ? (int) $_GET['sampai'] : 0;

// bila terbalik (mis. 10 sampai 6), tukar otomatis
if ($dari > 0 && $sampai > 0 && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

if ($dari < 1) {
    $dari = 1;
}

if ($sampai < 1 || $sampai > $total_semua) {
    $sampai = $total_semua;
}

if ($dari > $sampai) {
    $dari = 1;
}

$rentang_aktif = ($total_semua > 0 && ($dari > 1 || $sampai < $total_semua));

if ($total_semua > 0) {
    $rows = array_slice($rows, $dari - 1, $sampai - $dari + 1);
}

$total = count($rows);

/* =========================================================
   HELPER
========================================================= */

function tanggalIndo($ts = null)
{
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $ts = $ts ?: time();

    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function tgl($v)
{
    return (!empty($v) && $v !== '0000-00-00') ? date('d-m-Y', strtotime($v)) : '-';
}

$dicetak_oleh = $_SESSION['nama'] ?? $_SESSION['username'];

$balik = 'surat_masuk.php?' . http_build_query(array_filter([
    'tahun'   => $tahun,
    'bulan'   => $bulan > 0 ? $bulan : null,
    'keyword' => $keyword !== '' ? $keyword : null,
]));

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Surat Masuk - BKPSDM Kabupaten Tapin</title>

    <style>

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #e9e9ef;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ===== BILAH ALAT (hanya di layar) ===== */

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            padding: 12px 24px;
            background: #14181f;
            color: #fff;
        }

        .toolbar .baris {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .toolbar .opsi {
            display: flex;
            align-items: flex-end;
            gap: 22px;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,.12);
        }

        .toolbar .grup {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
        }

        .toolbar .grup .judul-grup {
            width: 100%;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: rgba(255,255,255,.55);
        }

        .toolbar label {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 11px;
            color: rgba(255,255,255,.7);
        }

        .toolbar input[type="number"],
        .toolbar input[type="text"] {
            height: 36px;
            padding: 0 10px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 9px;
            background: rgba(255,255,255,.08);
            color: #fff;
            font-size: 12.5px;
            font-family: inherit;
            outline: none;
        }

        .toolbar input[type="number"] { width: 82px; }
        .toolbar input.lebar { width: 210px; }
        .toolbar input.sedang { width: 170px; }

        .toolbar input:focus {
            border-color: #7aa7ff;
            background: rgba(255,255,255,.13);
        }

        .toolbar .btn-kecil {
            height: 36px;
            padding: 0 16px;
            border: none;
            border-radius: 9px;
            background: #2F6FED;
            color: #fff;
            font-size: 12.5px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .toolbar .btn-kecil.abu { background: rgba(255,255,255,.14); }

        .toolbar label.centang {
            flex-direction: row;
            align-items: center;
            gap: 8px;
            height: 36px;
            padding: 0 12px;
            border-radius: 9px;
            background: rgba(255,255,255,.08);
            font-size: 12.5px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
        }

        .toolbar label.centang input {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: #2F6FED;
            cursor: pointer;
        }

        .toolbar .grup.mati .isian { opacity: .4; pointer-events: none; }

        .toolbar .info {
            font-size: 12.5px;
            color: rgba(255,255,255,.7);
        }

        .toolbar .info b { color: #fff; }

        .toolbar .aksi { display: flex; gap: 10px; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 40px;
            padding: 0 18px;
            border: none;
            border-radius: 11px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: transform .2s, background .2s;
        }

        .btn:hover { transform: translateY(-2px); }

        .btn-back { background: rgba(255,255,255,.12); color: #fff; }
        .btn-back:hover { background: rgba(255,255,255,.2); color: #fff; }

        .btn-print { background: #2F6FED; color: #fff; }
        .btn-print:hover { background: #4F86F5; }

        /* ===== LEMBAR KERTAS ===== */

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 24px auto;
            padding: 18mm 16mm 20mm;
            background: #fff;
            box-shadow: 0 10px 40px rgba(0,0,0,.15);
        }

        /* ===== KOP ===== */

        .kop {
            text-align: center;
            line-height: 1.35;
        }

        .kop { display: grid; grid-template-columns: 96px 1fr 96px; align-items: center; column-gap: 6px; }
        .kop-logo { height: 96px; width: auto; justify-self: start; display: block; }
        .kop-text { grid-column: 2; }
        .kop .l1 { font-size: 13px; font-weight: 700; letter-spacing: .5px; }
        .kop .l2 { font-size: 16px; font-weight: 800; letter-spacing: .6px; }
        .kop .l3 { font-size: 13px; font-weight: 700; letter-spacing: .5px; }

        .garis {
            margin: 10px 0 16px;
            border-top: 3px solid #111;
            border-bottom: 1px solid #111;
            height: 6px;
        }

        /* ===== JUDUL ===== */

        .judul {
            text-align: center;
            margin-bottom: 14px;
        }

        .judul h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .6px;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .judul p {
            margin: 6px 0 0;
            font-size: 12px;
            color: #333;
        }

        .saringan {
            margin: -6px 0 12px;
            font-size: 11px;
            color: #333;
        }

        /* ===== TABEL ===== */

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        thead { display: table-header-group; }

        th, td {
            border: 1px solid #111;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #e4e4ea;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            text-align: center;
            vertical-align: middle;
        }

        tbody tr { page-break-inside: avoid; }
        tbody tr:nth-child(even) td { background: #f6f6f9; }

        .center { text-align: center; }
        .nowrap { white-space: nowrap; }

        /* ===== TANDA TANGAN ===== */

        .ttd-wrap {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            page-break-inside: avoid;
        }

        .ttd {
            width: 240px;
            font-size: 12px;
            line-height: 1.5;
            text-align: center;
        }

        .ttd .space { height: 68px; }
        .ttd .nama { font-weight: 700; }
        .ttd .nama.terisi { text-decoration: underline; text-underline-offset: 2px; }
        .ttd .jabatan { min-height: 18px; }

        .cetak-info {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #bbb;
            font-size: 9.5px;
            color: #666;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        /* ===== CETAK ===== */

        @page {
            size: A4 portrait;
            margin: 14mm 12mm;
        }

        @media print {

            body { background: #fff; }

            .toolbar { display: none !important; }

            .sheet {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }

        @media (max-width: 800px) {
            .sheet {
                width: auto;
                margin: 12px;
                padding: 20px 14px;
                overflow-x: auto;
            }
        }

    </style>

</head>

<body>

<!-- ===== BILAH ALAT ===== -->

<div class="toolbar">

    <div class="baris">

        <div class="info">
            Pratinjau cetak &middot;
            <?php if ($rentang_aktif) { ?>
                nomor <b><?= $dari; ?>&ndash;<?= $sampai; ?></b> dari <b><?= $total_semua; ?></b> surat masuk
            <?php } else { ?>
                <b><?= $total; ?></b> surat masuk
            <?php } ?>
            &middot; <b><?= htmlspecialchars($periode); ?></b>
            <?= $keyword !== '' ? '&middot; saringan: <b>' . htmlspecialchars($keyword) . '</b>' : ''; ?>
        </div>

        <div class="aksi">

            <a href="<?= htmlspecialchars($balik); ?>" class="btn btn-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Kembali
            </a>

            <button type="button" class="btn btn-print" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak
            </button>

        </div>

    </div>

    <div class="opsi">

        <!-- RENTANG NOMOR -->

        <form method="GET" class="grup" autocomplete="off">

            <input type="hidden" name="tahun" value="<?= (int) $tahun; ?>">
            <input type="hidden" name="bulan" value="<?= $bulan > 0 ? (int) $bulan : ''; ?>">
            <input type="hidden" name="keyword" value="<?= htmlspecialchars($keyword); ?>">

            <div class="judul-grup">Cetak per nomor (<?= $total_semua; ?> surat pada periode ini)</div>

            <label>Dari nomor
                <input type="number" name="dari" min="1" max="<?= max(1, $total_semua); ?>" value="<?= $dari; ?>">
            </label>

            <label>Sampai nomor
                <input type="number" name="sampai" min="1" max="<?= max(1, $total_semua); ?>" value="<?= $sampai; ?>">
            </label>

            <button type="submit" class="btn-kecil">Terapkan</button>

            <?php if ($rentang_aktif) { ?>
                <a class="btn-kecil abu" href="?<?= htmlspecialchars(http_build_query(array_filter(['tahun' => $tahun, 'bulan' => $bulan > 0 ? $bulan : null, 'keyword' => $keyword !== '' ? $keyword : null]))); ?>">Semua nomor</a>
            <?php } ?>

        </form>

        <!-- PENANDATANGAN -->

        <div class="grup" id="grupTtd">

            <div class="judul-grup">Penandatangan (mengetahui)</div>

            <label class="centang">
                <input type="checkbox" id="inTampil" checked>
                Tampilkan tanda tangan
            </label>

            <label class="isian">Jabatan
                <input type="text" id="inJabatan" class="lebar" placeholder="mis. Kepala Badan" maxlength="80">
            </label>

            <label class="isian">Nama
                <input type="text" id="inNama" class="lebar" placeholder="Nama lengkap beserta gelar" maxlength="80">
            </label>

            <label class="isian">NIP
                <input type="text" id="inNip" class="sedang" placeholder="NIP" maxlength="30">
            </label>

        </div>

    </div>

</div>

<!-- ===== LEMBAR ===== -->

<div class="sheet">

    <!-- KOP -->

    <div class="kop">
        <img class="kop-logo" src="../assets/img/lambang_tapin.png" alt="Lambang Kabupaten Tapin">
        <div class="kop-text">
        <div class="l1">PEMERINTAH KABUPATEN TAPIN</div>
        <div class="l2">BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA</div>
        <div class="l3">KABUPATEN TAPIN</div>
        </div>
    </div>

    <div class="garis"></div>

    <!-- JUDUL -->

    <div class="judul">
        <h3>LAPORAN SURAT MASUK</h3>
        <p>Periode <?= htmlspecialchars($periode); ?></p>
    </div>

    <?php if ($keyword !== '') { ?>
        <div class="saringan">Saringan pencarian: <b>"<?= htmlspecialchars($keyword); ?>"</b></div>
    <?php } ?>

    <!-- TABEL -->

    <table>

        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:19%">Nomor Surat</th>
                <th style="width:12%">Tanggal Surat</th>
                <th style="width:12%">Tanggal Diterima</th>
                <th style="width:20%">Pengirim</th>
                <th>Perihal</th>
            </tr>
        </thead>

        <tbody>

        <?php if ($total > 0) {

            $no = $dari;

            foreach ($rows as $s) { ?>

                <tr>
                    <td class="center"><?= $no++; ?></td>
                    <td><?= htmlspecialchars(($s['nomor_surat'] ?? '') !== '' ? $s['nomor_surat'] : '-'); ?></td>
                    <td class="center nowrap"><?= tgl($s['tanggal_surat'] ?? ''); ?></td>
                    <td class="center nowrap"><?= tgl($s['tanggal_diterima'] ?? ''); ?></td>
                    <td><?= htmlspecialchars(($s['pengirim'] ?? '') !== '' ? $s['pengirim'] : '-'); ?></td>
                    <td><?= htmlspecialchars(($s['perihal'] ?? '') !== '' ? $s['perihal'] : '-'); ?></td>
                </tr>

            <?php }

        } else { ?>

            <tr>
                <td colspan="6" class="center" style="padding:22px">
                    Tidak ada data surat masuk untuk <?= htmlspecialchars($periode); ?><?= $keyword !== '' ? ' dengan saringan tersebut' : ''; ?>.
                </td>
            </tr>

        <?php } ?>

        </tbody>

    </table>

    <!-- TANDA TANGAN -->

    <div class="ttd-wrap">

        <div class="ttd">
            <div>Tapin, <?= tanggalIndo(); ?></div>
            <div>Mengetahui,</div>
            <div class="jabatan" id="ttdJabatan"></div>
            <div class="space"></div>
            <div class="nama" id="ttdNama">______________________________</div>
            <div id="ttdNip">NIP. _________________________</div>
        </div>

    </div>

    <div class="cetak-info">
        <span>Dicetak oleh: <?= htmlspecialchars($dicetak_oleh); ?></span>
        <span>e-SIMPEL &middot; BKPSDM Kabupaten Tapin</span>
    </div>

</div>

<script>
(function () {

    var KUNCI = 'bkpsdm_ttd_laporan';

    var inJabatan = document.getElementById('inJabatan');
    var inNama    = document.getElementById('inNama');
    var inNip     = document.getElementById('inNip');
    var inTampil  = document.getElementById('inTampil');
    var grupTtd   = document.getElementById('grupTtd');
    var wrapTtd   = document.querySelector('.ttd-wrap');

    var elJabatan = document.getElementById('ttdJabatan');
    var elNama    = document.getElementById('ttdNama');
    var elNip     = document.getElementById('ttdNip');

    var KOSONG_NAMA = '______________________________';
    var KOSONG_NIP  = 'NIP. _________________________';

    function tampil() {

        // tanda tangan bisa disembunyikan sepenuhnya dari cetakan
        wrapTtd.style.display = inTampil.checked ? '' : 'none';
        grupTtd.className     = inTampil.checked ? 'grup' : 'grup mati';

        var jabatan = inJabatan.value.trim();
        var nama    = inNama.value.trim();
        var nip     = inNip.value.trim();

        // textContent: isian tidak pernah dibaca sebagai HTML
        elJabatan.textContent = jabatan;

        elNama.textContent = nama !== '' ? nama : KOSONG_NAMA;
        elNama.className   = nama !== '' ? 'nama terisi' : 'nama';

        elNip.textContent = nip !== '' ? 'NIP. ' + nip : KOSONG_NIP;
    }

    function simpan() {
        try {
            localStorage.setItem(KUNCI, JSON.stringify({
                jabatan: inJabatan.value,
                nama: inNama.value,
                nip: inNip.value,
                tampil: inTampil.checked
            }));
        } catch (e) { /* penyimpanan browser tidak tersedia: abaikan */ }
    }

    try {
        var tersimpan = JSON.parse(localStorage.getItem(KUNCI) || 'null');

        if (tersimpan) {
            inJabatan.value = tersimpan.jabatan || '';
            inNama.value    = tersimpan.nama || '';
            inNip.value     = tersimpan.nip || '';
            inTampil.checked = (tersimpan.tampil !== false);
        }
    } catch (e) { /* abaikan */ }

    [inJabatan, inNama, inNip, inTampil].forEach(function (el) {
        ['input', 'change'].forEach(function (ev) {
            el.addEventListener(ev, function () {
                tampil();
                simpan();
            });
        });
    });

    tampil();

})();
</script>

</body>

</html>