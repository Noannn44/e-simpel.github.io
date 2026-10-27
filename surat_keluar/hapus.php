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
   CEK ROLE
   HANYA ADMIN YANG BOLEH MENGHAPUS
========================================================= */

hanyaAdmin();


/* =========================================================
   CEK METHOD
   HAPUS HARUS MENGGUNAKAN POST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit;

}


/* =========================================================
   CEK CSRF
========================================================= */

$csrf_token = $_POST['csrf_token'] ?? '';

if (!verify_csrf_token($csrf_token)) {

    echo "
    <script>

        alert('Permintaan tidak valid. Silakan muat ulang halaman terlebih dahulu.');

        window.location='index.php';

    </script>
    ";

    exit;

}


/* =========================================================
   AMBIL ID
========================================================= */

$id = isset($_POST['id'])
    ? (int) $_POST['id']
    : 0;


/* =========================================================
   VALIDASI ID
========================================================= */

if ($id <= 0) {

    echo "
    <script>

        alert('ID surat tidak valid.');

        window.location='index.php';

    </script>
    ";

    exit;

}


/* =========================================================
   CEK DATA SURAT
========================================================= */

$query = mysqli_query(
    $koneksi,
    "
    SELECT id, nomor_surat
    FROM surat_keluar
    WHERE id = $id
    LIMIT 1
    "
);


/* =========================================================
   CEK QUERY
========================================================= */

if (!$query) {

    echo "
    <script>

        alert('Terjadi kesalahan saat mengambil data surat.');

        window.location='index.php';

    </script>
    ";

    exit;

}


/* =========================================================
   CEK DATA DITEMUKAN
========================================================= */

if (mysqli_num_rows($query) === 0) {

    echo "
    <script>

        alert('Data surat tidak ditemukan.');

        window.location='index.php';

    </script>
    ";

    exit;

}


/* =========================================================
   AMBIL DATA
========================================================= */

$data = mysqli_fetch_assoc($query);

$nomor_surat = $data['nomor_surat'] ?? '';


/* =========================================================
   HAPUS DATA
========================================================= */

$hapus = mysqli_query(
    $koneksi,
    "
    DELETE FROM surat_keluar
    WHERE id = $id
    LIMIT 1
    "
);


/* =========================================================
   HASIL HAPUS
========================================================= */

if ($hapus) {

    $nomor_surat_js = json_encode(
        $nomor_surat,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    );

    echo "
    <script>

        alert(
            'Surat dengan nomor ' +
            $nomor_surat_js +
            ' berhasil dihapus.'
        );

        window.location='index.php';

    </script>
    ";

    exit;

}


/* =========================================================
   GAGAL HAPUS
========================================================= */

echo "
<script>

    alert('Surat gagal dihapus. Silakan coba lagi.');

    window.location='index.php';

</script>
";

exit;

?>