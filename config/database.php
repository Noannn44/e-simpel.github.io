<?php

/*==========================================================
 | KONFIGURASI DATABASE
 ==========================================================*/

$host      = "localhost";
$username  = "root";
$password  = "";
$database  = "e_simpel";


/*==========================================================
 | MEMBUAT KONEKSI
 ==========================================================*/

$koneksi = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);


/*==========================================================
 | CEK KONEKSI
 ==========================================================*/

if (!$koneksi) {

    die(
        "Koneksi database gagal : "
        . mysqli_connect_error()
    );

}

?>