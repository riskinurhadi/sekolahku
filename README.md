# Portal Sekolah - Sistem E-Learning

Aplikasi portal sekolah berbasis web menggunakan PHP, CSS, Bootstrap, dan JavaScript untuk mengelola pembelajaran online dengan 4 layer akses: Developer, Kepala Sekolah, Guru, dan Siswa.

## Fitur Utama

### Layer 1: Developer
- Kontrol penuh terhadap sistem
- Kelola sekolah
- Kelola kepala sekolah
- Dashboard dengan statistik lengkap

### Layer 2: Kepala Sekolah
- Kontrol terhadap sekolahnya masing-masing
- Kelola guru dan staf
- Kelola siswa
- Dashboard dengan statistik sekolah

### Layer 3: Guru
- Kelola siswa
- Kelola mata pelajaran
- Buat dan kelola soal (Quiz, Pilihan Ganda, Isian)
- Dashboard dengan statistik pembelajaran

### Layer 4: Siswa
- Lihat dan kerjakan soal
- Lihat hasil ujian
- Kelola profil pribadi
- Dashboard dengan statistik pembelajaran

## Teknologi yang Digunakan

- **Backend**: PHP
- **Frontend**: HTML, CSS, Bootstrap 5
- **JavaScript**: jQuery, SweetAlert2
- **Database**: MySQL

## Instalasi

1. Clone atau download repository ini
2. Import database schema dari file `database/schema.sql`
3. Konfigurasi koneksi database di `config/database.php`
4. Pastikan web server (Apache/Nginx) dan PHP sudah terinstall
5. Akses aplikasi melalui browser

## Konfigurasi Database

Edit file `config/database.php` dan sesuaikan dengan konfigurasi database Anda:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'portal_sekolah');
```

## Default Login

- **Username**: developer
- **Password**: admin123

## Struktur Folder

```
portal-sekolah/
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
├── config/
│   ├── database.php
│   └── session.php
├── dashboard/
│   ├── developer/
│   ├── kepala_sekolah/
│   ├── guru/
│   └── siswa/
├── database/
│   └── schema.sql
├── includes/
│   ├── header.php
│   └── footer.php
├── login.php
└── logout.php
```

## Fitur UI/UX

- Design modern dan clean
- Sidebar navigation untuk setiap dashboard
- Responsive design (mobile-friendly)
- SweetAlert2 untuk notifikasi
- Statistik cards dengan icon
- Tabel data yang mudah dibaca
- Form validation

## Lisensi

Proyek ini dibuat untuk keperluan pembelajaran dan dapat digunakan secara bebas.

