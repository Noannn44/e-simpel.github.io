<?php require_once __DIR__ . '/../config/log.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (function_exists('csrf_token')) { ?>
<script>
/* Tombol hapus (link hapus.php) dikirim sebagai POST + token CSRF */
document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href*="hapus.php"]');
    if (!a) { return; }
    e.preventDefault();

    var url = new URL(a.getAttribute('href'), window.location.href);
    var id  = url.searchParams.get('id');
    if (!id) { return; }
    if (!confirm('Yakin ingin menghapus data ini?')) { return; }

    var f = document.createElement('form');
    f.method = 'POST';
    f.action = url.origin + url.pathname;

    var i1 = document.createElement('input');
    i1.type = 'hidden'; i1.name = 'id'; i1.value = id;
    var i2 = document.createElement('input');
    i2.type = 'hidden'; i2.name = 'csrf_token';
    i2.value = <?= json_encode(csrf_token()); ?>;

    f.appendChild(i1);
    f.appendChild(i2);
    document.body.appendChild(f);
    f.submit();
});
</script>
<?php } ?>

<?php
/* Operator hanya boleh melihat Data Pegawai: sembunyikan Tambah, Edit, Hapus */
$footerRole  = function_exists('roleUser') ? roleUser() : ($_SESSION['role'] ?? '');
$footerPath  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$halamanPegawai = (strpos((string) $footerPath, '/' . basename(dirname(__DIR__)) . '/pegawai/') !== false);

if ($halamanPegawai && $footerRole !== 'admin') {
?>
<script>
(function () {
    var sel = 'a[href*="tambah.php"], a[href*="edit.php"], a[href*="hapus.php"]';
    document.querySelectorAll(sel).forEach(function (el) {
        el.style.display = 'none';
    });
})();
</script>
<?php } ?>

<script>
/* Filter & pencarian otomatis:
   - pilihan Bulan / Tahun / dropdown lain langsung diterapkan saat diganti
   - kotak pencarian teks diterapkan otomatis sesaat setelah selesai mengetik
   Form yang tidak ingin otomatis: tambahkan atribut data-manual pada <form>. */
(function () {
    var jeda = 500;
    var timer = null;

    function formFilter(el) {
        var f = el.form;
        if (!f) { return null; }
        if ((f.method || '').toLowerCase() !== 'get') { return null; }
        if (f.hasAttribute('data-manual')) { return null; }
        return f;
    }

    function kirim(f) {
        if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); }
    }

    function kunci(el) {
        return 'auto_fokus:' + window.location.pathname + ':' + (el.name || '');
    }

    /* dropdown, bulan, tanggal */
    document.addEventListener('change', function (e) {
        var el = e.target;
        var f  = formFilter(el);
        if (!f) { return; }

        var tipe = (el.type || '').toLowerCase();
        var ok   = el.tagName === 'SELECT' || tipe === 'month' || tipe === 'date';

        /* select yang sudah punya onchange sendiri tidak dikirim dua kali */
        if (!ok || el.hasAttribute('onchange')) { return; }

        kirim(f);
    });

    /* kotak pencarian teks */
    document.addEventListener('input', function (e) {
        var el = e.target;
        var f  = formFilter(el);
        if (!f) { return; }

        var tipe = (el.type || '').toLowerCase();
        if (el.tagName !== 'INPUT' || (tipe !== 'text' && tipe !== 'search')) { return; }

        clearTimeout(timer);
        timer = setTimeout(function () {
            try { sessionStorage.setItem(kunci(el), '1'); } catch (x) {}
            kirim(f);
        }, jeda);
    });

    /* kembalikan fokus ke kotak pencarian setelah halaman dimuat ulang */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form input[type="text"], form input[type="search"]').forEach(function (el) {
            var f = formFilter(el);
            if (!f) { return; }

            var ada = null;
            try { ada = sessionStorage.getItem(kunci(el)); } catch (x) {}
            if (!ada) { return; }

            try { sessionStorage.removeItem(kunci(el)); } catch (x) {}
            el.focus();
            var n = el.value.length;
            try { el.setSelectionRange(n, n); } catch (x) {}
        });
    });
})();
</script>

<script>
/* Pesan saat operator membuka halaman khusus admin */
(function () {
    if (window.location.search.indexOf('error=akses_ditolak') !== -1) {
        alert('Anda tidak memiliki akses ke halaman tersebut.');
        window.history.replaceState(null, '', window.location.pathname);
    }
})();
</script>

</body>
</html>