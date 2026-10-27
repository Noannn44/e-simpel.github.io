<?php
/*
 * Pembantu role user.
 * Membaca nilai role yang diizinkan langsung dari kolom `role`
 * (ENUM atau VARCHAR), lalu mencocokkan input form ke nilai yang valid,
 * sehingga tidak ada lagi role yang tersimpan kosong.
 */

function daftarRoleDb($koneksi)
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];

    $r = mysqli_query($koneksi, "SHOW COLUMNS FROM users LIKE 'role'");

    if ($r && ($row = mysqli_fetch_assoc($r))) {

        if (preg_match("/^enum\((.*)\)$/i", $row['Type'], $m)) {

            preg_match_all("/'((?:[^']|'')*)'/", $m[1], $mm);

            foreach ($mm[1] as $nilai) {
                $cache[] = str_replace("''", "'", $nilai);
            }
        }
    }

    return $cache;
}

/*
 * Ubah input form (Administrator / Operator / admin / dll)
 * menjadi nilai yang benar-benar diterima kolom role.
 * Mengembalikan null jika tidak valid.
 */
function roleKeDb($koneksi, $input)
{
    $x = strtolower(trim((string) $input));

    if ($x === 'admin') {
        $x = 'administrator';
    }

    if (!in_array($x, ['administrator', 'operator'], true)) {
        return null;
    }

    $opsi = daftarRoleDb($koneksi);

    // kolom ENUM: cari nilai yang cocok tanpa peduli huruf besar/kecil
    if (count($opsi) > 0) {

        foreach ($opsi as $o) {

            $lo = strtolower($o);

            if ($lo === $x || ($x === 'administrator' && $lo === 'admin')) {
                return $o;
            }
        }

        return null;
    }

    // kolom VARCHAR: pakai penulisan standar
    return $x === 'administrator' ? 'Administrator' : 'Operator';
}

function adalahAdminRole($role)
{
    return in_array(strtolower(trim((string) $role)), ['administrator', 'admin'], true);
}