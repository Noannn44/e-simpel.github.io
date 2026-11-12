<?php

session_start();

$kh_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($kh_id <= 0) {
    echo "<script>alert('ID tidak valid.'); window.location='index.php';</script>";
    exit;
}

require __DIR__ . "/_form.php";