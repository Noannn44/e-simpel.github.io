<?php

/*==========================================================
 | ENTRY POINT APLIKASI
 | Redirect otomatis sesuai status login
 ==========================================================*/

 if (session_status() === PHP_SESSION_NONE) {
    session_start();
 }

 if (isset($_SESSION['username'])) {

    header("Location: dashboard/index.php");
    exit;
 } else {
    header("Location: login.php");
    exit;
 }

 ?>