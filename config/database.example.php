<?php

/*==========================================================
 | CONTOH KONFIGURASI DATABASE
 | Salin file ini menjadi database.php lalu isi sesuai server.
 | File database.php tidak ikut ke GitHub (lihat .gitignore).
 ==========================================================*/

$host      = "localhost";
$username  = "USER_DATABASE";
$password  = "PASSWORD_DATABASE";
$database  = "e_simpel";

$koneksi = mysqli_connect($host, $username, $password, $database);

if (!$koneksi) {
    die("Koneksi database gagal : " . mysqli_connect_error());
}

?>
