<?php

session_start();

include "config/database.php";

/* =========================
   JIKA SUDAH LOGIN
========================= */

if (isset($_SESSION['username'])) {
    header("Location: dashboard/index.php");
    exit;
}

$error    = "";
$username = "";

$MAKS_GAGAL = 5;
$DURASI_KUNCI = 30; // detik

/* =========================
   STATUS KUNCI
========================= */

$sisa_kunci = 0;

if (!empty($_SESSION['kunci_sampai']) && $_SESSION['kunci_sampai'] > time()) {
    $sisa_kunci = $_SESSION['kunci_sampai'] - time();
}

/* =========================
   PROSES LOGIN
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($sisa_kunci > 0) {

        $error = "Terlalu banyak percobaan. Coba lagi dalam {$sisa_kunci} detik.";

    } elseif ($username === '' || $password === '') {

        $error = "Username dan password wajib diisi.";

    } else {

        $stmt = mysqli_prepare($koneksi, "SELECT id, nama, username, password, role FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $cocok = false;

        if ($data) {

            $tersimpan = (string) $data['password'];

            if (password_verify($password, $tersimpan)) {

                $cocok = true;

                // perbarui hash jika algoritmanya sudah usang
                if (password_needs_rehash($tersimpan, PASSWORD_DEFAULT)) {
                    $baru = password_hash($password, PASSWORD_DEFAULT);
                    $up = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($up, "si", $baru, $data['id']);
                    mysqli_stmt_execute($up);
                }

            } elseif (
                // akun lama: password masih tersimpan polos (bukan hash)
                !preg_match('/^\$2[aby]\$|^\$argon2/', $tersimpan) &&
                $tersimpan !== '' &&
                hash_equals($tersimpan, $password)
            ) {

                $cocok = true;

                // otomatis ubah menjadi hash
                $baru = password_hash($password, PASSWORD_DEFAULT);
                $up = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($up, "si", $baru, $data['id']);
                mysqli_stmt_execute($up);
            }
        }

        if ($cocok) {

            if (trim((string) $data['role']) === '') {

                $error = "Role akun ini belum diatur. Hubungi administrator.";

            } else {

                session_regenerate_id(true);

                unset($_SESSION['gagal'], $_SESSION['kunci_sampai']);

                $_SESSION['username'] = $data['username'];
                $_SESSION['nama']     = $data['nama'];
                $_SESSION['role']     = $data['role'];
                $_SESSION['user_id']  = $data['id'];

                header("Location: dashboard/index.php");
                exit;
            }

        } else {

            $_SESSION['gagal'] = ($_SESSION['gagal'] ?? 0) + 1;

            if ($_SESSION['gagal'] >= $MAKS_GAGAL) {
                $_SESSION['gagal'] = 0;
                $_SESSION['kunci_sampai'] = time() + $DURASI_KUNCI;
                $sisa_kunci = $DURASI_KUNCI;
                $error = "Terlalu banyak percobaan. Coba lagi dalam {$DURASI_KUNCI} detik.";
            } else {
                $error = "Username atau password salah.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Masuk - e-SIMPEL BKPSDM Kabupaten Tapin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%2220%2010%20870%20870%22%3E%3Cdefs%3E%3ClinearGradient%20id=%22bg%22%20x1=%220%22%20y1=%220%22%20x2=%221%22%20y2=%221%22%3E%3Cstop%20offset=%220%22%20stop-color=%22%231D528E%22/%3E%3Cstop%20offset=%22.55%22%20stop-color=%22%230B315E%22/%3E%3Cstop%20offset=%221%22%20stop-color=%22%2305264B%22/%3E%3C/linearGradient%3E%3ClinearGradient%20id=%22or%22%20x1=%220%22%20y1=%221%22%20x2=%221%22%20y2=%220%22%3E%3Cstop%20offset=%220%22%20stop-color=%22%23E8622A%22/%3E%3Cstop%20offset=%22.55%22%20stop-color=%22%23FDA02E%22/%3E%3Cstop%20offset=%221%22%20stop-color=%22%23FFCB4A%22/%3E%3C/linearGradient%3E%3ClinearGradient%20id=%22or2%22%20x1=%220%22%20y1=%220%22%20x2=%221%22%20y2=%221%22%3E%3Cstop%20offset=%220%22%20stop-color=%22%23FFC443%22/%3E%3Cstop%20offset=%221%22%20stop-color=%22%23EE6A27%22/%3E%3C/linearGradient%3E%3ClinearGradient%20id=%22wh%22%20x1=%220%22%20y1=%220%22%20x2=%220%22%20y2=%221%22%3E%3Cstop%20offset=%220%22%20stop-color=%22%23FFFFFF%22/%3E%3Cstop%20offset=%221%22%20stop-color=%22%23E4E9F0%22/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect%20x=%2220%22%20y=%2210%22%20width=%22870%22%20height=%22870%22%20rx=%22195%22%20fill=%22url%28%23bg%29%22/%3E%3Cpath%20d=%22M195%20676Q200%20652%20252%20647L420%20704L556%20664L480%20766Q466%20786%20436%20783L215%20696Q194%20688%20195%20676Z%22%20fill=%22url%28%23or2%29%22/%3E%3Cpath%20d=%22M478%20312L690%20425L455%20546L215%20432Z%22%20fill=%22url%28%23or%29%22/%3E%3Cpath%20d=%22M690%20425Q745%20445%20745%20520Q743%20585%20690%20604L452%20714Q430%20722%20410%20706L250%20656L455%20548Z%22%20fill=%22url%28%23wh%29%22/%3E%3Cpath%20d=%22M580%20105Q560%2098%20535%20110L245%20238Q170%20275%20165%20340Q165%20400%20215%20432L478%20308L692%20205Q705%20196%20696%20182Z%22%20fill=%22url%28%23wh%29%22/%3E%3C/svg%3E">

    <style>

        :root {
            --or: #FB7C3C;
            --or-dark: #E2621F;
            --or-soft: #FFEDD5;
            --ink: #1d1d27;
            --ease-smooth: cubic-bezier(.22, .61, .36, 1);
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            background: #f4f4f7;
            color: var(--ink);
        }

        .lg-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
        }

        /* ===== PANEL KIRI ===== */

        .lg-left {
            position: relative;
            overflow: hidden;
            padding: 48px 56px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
            background: linear-gradient(150deg, #14181f 0%, #1a1f29 55%, #2a1a12 100%);
        }

        .lg-left::before {
            content: "";
            position: absolute;
            width: 520px;
            height: 520px;
            right: -180px;
            top: -200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(251,124,60,.45), transparent 70%);
            pointer-events: none;
        }

        .lg-left::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
        }

        .lg-left > * { position: relative; z-index: 1; }

        .lg-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .lg-mark {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 24px rgba(5,38,75,.45);
        }

        .lg-brand strong {
            display: block;
            font-size: 16px;
            letter-spacing: .4px;
        }

        .lg-brand span {
            font-size: 11.5px;
            color: rgba(255,255,255,.55);
        }

        .lg-hero small {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 13px;
            border-radius: 999px;
            background: rgba(255,255,255,.1);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .lg-hero small i {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--or);
            box-shadow: 0 0 0 4px rgba(251,124,60,.3);
        }

        .lg-hero h2 {
            margin: 18px 0 14px;
            font-size: 40px;
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: -.8px;
            color: #fff;
        }

        .lg-hero h2 em {
            font-style: normal;
            color: var(--or);
        }

        .lg-hero p {
            margin: 0;
            max-width: 430px;
            font-size: 14.5px;
            line-height: 1.7;
            color: rgba(255,255,255,.65);
        }

        .lg-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 30px;
        }

        .lg-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 15px;
            border-radius: 14px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.09);
            font-size: 12.5px;
            font-weight: 600;
            transition: transform .25s var(--ease-smooth), background .25s;
        }

        .lg-chip:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,.13);
        }

        .lg-foot {
            font-size: 12px;
            color: rgba(255,255,255,.45);
        }

        /* ===== PANEL KANAN ===== */

        .lg-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 28px;
        }

        .lg-box {
            width: 100%;
            max-width: 410px;
            animation: lgIn .55s var(--ease-smooth) both;
        }

        @keyframes lgIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .lg-box h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -.5px;
            color: var(--ink);
        }

        .lg-box .sub {
            margin: 8px 0 28px;
            font-size: 13.5px;
            line-height: 1.6;
            color: #8a8a98;
        }

        .lg-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 13px 15px;
            border-radius: 14px;
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #B91C1C;
            font-size: 13px;
            line-height: 1.5;
            animation: lgShake .4s;
        }

        .lg-alert svg { flex-shrink: 0; margin-top: 1px; }

        @keyframes lgShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .lg-field { margin-bottom: 18px; }

        .lg-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: #3a3a48;
        }

        .lg-input { position: relative; }

        .lg-input .ic {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .lg-input input {
            width: 100%;
            height: 52px;
            padding: 0 16px 0 46px;
            border: 1.5px solid #e6e6ee;
            background: #fff;
            border-radius: 14px;
            font-size: 14px;
            color: var(--ink);
            outline: none;
            transition: all .25s var(--ease-smooth);
        }

        .lg-input input:hover { border-color: #cfcfdc; }

        .lg-input input:focus {
            border-color: var(--or);
            box-shadow: 0 0 0 4px rgba(251,124,60,.15);
        }

        .lg-input.has-eye input { padding-right: 52px; }

        .lg-eye {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 11px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background .2s;
        }

        .lg-eye:hover { background: #f0f0f6; }

        .lg-submit {
            width: 100%;
            height: 54px;
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, #FB7C3C, #E2621F);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(251,124,60,.35);
            transition: transform .25s var(--ease-smooth), box-shadow .25s, opacity .25s;
        }

        .lg-submit:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 18px 34px rgba(251,124,60,.42);
        }

        .lg-submit:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .lg-help {
            margin-top: 22px;
            padding: 13px 15px;
            border-radius: 14px;
            background: #fff;
            border: 1px dashed #dcdce6;
            font-size: 12.3px;
            line-height: 1.6;
            color: #8a8a98;
            text-align: center;
        }

        .lg-copy {
            margin-top: 26px;
            text-align: center;
            font-size: 11.5px;
            color: #a3a3b1;
        }

        /* ===== RESPONSIVE ===== */

        @media (max-width: 960px) {
            .lg-shell { grid-template-columns: 1fr; }
            .lg-left { display: none; }
            .lg-right { min-height: 100vh; }
        }

        @media (max-width: 480px) {
            .lg-right { padding: 28px 20px; }
            .lg-box h1 { font-size: 24px; }
        }

    </style>

</head>

<body>

<div class="lg-shell">

    <!-- ===== KIRI ===== -->

    <section class="lg-left">

        <div class="lg-brand">
            <div class="lg-mark">
                <svg width="46" height="46" aria-label="e-SIMPEL" viewBox="20 10 870 870"><defs><linearGradient id="lgbg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1D528E"/><stop offset=".55" stop-color="#0B315E"/><stop offset="1" stop-color="#05264B"/></linearGradient><linearGradient id="lgor" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#E8622A"/><stop offset=".55" stop-color="#FDA02E"/><stop offset="1" stop-color="#FFCB4A"/></linearGradient><linearGradient id="lgor2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFC443"/><stop offset="1" stop-color="#EE6A27"/></linearGradient><linearGradient id="lgwh" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#E4E9F0"/></linearGradient></defs><rect x="20" y="10" width="870" height="870" rx="195" fill="url(#lgbg)"/><path d="M195 676Q200 652 252 647L420 704L556 664L480 766Q466 786 436 783L215 696Q194 688 195 676Z" fill="url(#lgor2)"/><path d="M478 312L690 425L455 546L215 432Z" fill="url(#lgor)"/><path d="M690 425Q745 445 745 520Q743 585 690 604L452 714Q430 722 410 706L250 656L455 548Z" fill="url(#lgwh)"/><path d="M580 105Q560 98 535 110L245 238Q170 275 165 340Q165 400 215 432L478 308L692 205Q705 196 696 182Z" fill="url(#lgwh)"/></svg>
            </div>
            <div>
                <strong>e-SIMPEL</strong>
                <span>BKPSDM Kabupaten Tapin</span>
            </div>
        </div>

        <div class="lg-hero">

            <small><i></i> Sistem Informasi Terpadu</small>

            <h2>Sistem Persuratan<br>dan <em>Layanan</em>.</h2>

            <p>Kelola data pegawai, surat menyurat, dan kedatangan tamu dalam satu sistem yang terintegrasi.</p>

            <div class="lg-chips">

                <span class="lg-chip">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FB7C3C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                    Surat Masuk
                </span>

                <span class="lg-chip">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FB7C3C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
                    Surat Keluar
                </span>

                <span class="lg-chip">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FB7C3C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Buku Tamu
                </span>

                <span class="lg-chip">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FB7C3C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-1.5 1.7-2 3-2s2.5.5 3 2"/></svg>
                    Data Pegawai
                </span>

            </div>

        </div>

        <div class="lg-foot">&copy; <?= date('Y'); ?> BKPSDM Kabupaten Tapin</div>

    </section>

    <!-- ===== KANAN ===== -->

    <section class="lg-right">

        <div class="lg-box">

            <h1>Selamat Datang</h1>
            <p class="sub">Masuk dengan akun Anda untuk melanjutkan ke e-SIMPEL.</p>

            <?php if ($error !== "") { ?>
                <div class="lg-alert" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    <span id="alertTeks"><?= htmlspecialchars($error); ?></span>
                </div>
            <?php } ?>

            <form method="POST" autocomplete="on">

                <div class="lg-field">
                    <label for="username">Username</label>
                    <div class="lg-input">
                        <span class="ic">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username); ?>" placeholder="Masukkan username" autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="lg-field">
                    <label for="password">Password</label>
                    <div class="lg-input has-eye">
                        <span class="ic">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a3a3b1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                        <button type="button" class="lg-eye" id="toggleEye" title="Tampilkan / sembunyikan password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8a8a98" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="lg-submit" id="btnMasuk" <?= $sisa_kunci > 0 ? 'disabled' : ''; ?>>
                    <span id="btnTeks">Masuk</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>

            </form>

            <div class="lg-help">
                Belum punya akun? <a href="daftar.php" style="color:var(--or-dark);font-weight:700;text-decoration:none">Daftar di sini</a>
                <br>
                Lupa password? Hubungi administrator sistem untuk melakukan reset akun.
            </div>

            <div class="lg-copy"><strong>e-SIMPEL</strong> &middot; Sistem Informasi Manajemen Pegawai, Persuratan, dan Layanan Tamu<br>BKPSDM Kabupaten Tapin</div>

        </div>

    </section>

</div>

<script>

(function () {

    var pass = document.getElementById('password');

    document.getElementById('toggleEye').addEventListener('click', function () {
        pass.type = (pass.type === 'password') ? 'text' : 'password';
        pass.focus();
    });

    /* hitung mundur jika sedang dikunci */

    var sisa = <?= (int) $sisa_kunci; ?>;

    if (sisa > 0) {

        var btn  = document.getElementById('btnMasuk');
        var teks = document.getElementById('btnTeks');

        var tick = function () {
            if (sisa <= 0) {
                btn.disabled = false;
                teks.textContent = 'Masuk';
                var a = document.getElementById('alertTeks');
                if (a) a.parentNode.style.display = 'none';
                return;
            }
            teks.textContent = 'Coba lagi dalam ' + sisa + ' detik';
            sisa--;
            setTimeout(tick, 1000);
        };

        tick();
    }

    /* cegah klik ganda */

    document.querySelector('form').addEventListener('submit', function () {
        var b = document.getElementById('btnMasuk');
        setTimeout(function () { b.disabled = true; document.getElementById('btnTeks').textContent = 'Memproses...'; }, 0);
    });

})();

</script>

</body>

</html>