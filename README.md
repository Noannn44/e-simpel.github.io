# e-SIMPEL

Sistem Informasi Manajemen Pegawai, Persuratan, dan Layanan Tamu
BKPSDM Kabupaten Tapin

Aplikasi web berbasis PHP native dan MySQL/MariaDB untuk mengelola surat masuk, surat keluar, agenda kegiatan, buku tamu, data pegawai, laporan, dan akun pengguna.

## Fitur
- Surat masuk (biasa dan undangan), disposisi, dan surat keluar
- Agenda kegiatan dan pengingat 7 hari ke depan
- Buku tamu dan data pegawai
- Laporan dengan cetak dan unduh Excel
- Hak akses Admin dan Operator, pendaftaran akun dengan persetujuan admin
- Riwayat aktivitas dan cadangan database

## Kebutuhan
- PHP 8.1 atau lebih baru (ekstensi mysqli, zip, fileinfo)
- MySQL atau MariaDB
- Apache (mis. XAMPP)

## Pemasangan
1. Salin folder ke `htdocs` dengan nama `e-simpel`.
2. Impor `database/schema.sql` lewat phpMyAdmin.
3. Salin `config/database.example.php` menjadi `config/database.php`, lalu isi user dan password database.
4. Buat akun admin pertama (lihat catatan di akhir `database/schema.sql`).
5. Buka `http://localhost/e-simpel/`.

## Catatan keamanan
- Jangan menaruh `config/database.php`, berkas surat, atau cadangan database di GitHub.
- Gunakan user database khusus aplikasi, bukan `root`.
- Ganti semua password awal sebelum dipakai.
