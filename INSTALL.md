# Panduan Instalasi Portal Sekolah

## Persyaratan Sistem

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi (atau MariaDB)
- Web Server (Apache/Nginx)
- Extension PHP: mysqli, mbstring

## Langkah Instalasi

### 1. Persiapan Database

1. Buat database baru di MySQL:
```sql
CREATE DATABASE portal_sekolah;
```

2. Import schema database:
```bash
mysql -u root -p portal_sekolah < database/schema.sql
```

Atau melalui phpMyAdmin:
- Buka phpMyAdmin
- Pilih database `portal_sekolah`
- Klik tab "Import"
- Pilih file `database/schema.sql`
- Klik "Go"

### 2. Konfigurasi Database

Edit file `config/database.php` dan sesuaikan dengan konfigurasi database Anda:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Ganti dengan username database Anda
define('DB_PASS', '');            // Ganti dengan password database Anda
define('DB_NAME', 'portal_sekolah');
```

### 3. Setup Password Developer (Opsional)

Jika password default tidak bekerja, jalankan file `setup.php` melalui browser:
```
http://localhost/portal-sekolah/setup.php
```

Atau update manual melalui SQL:
```sql
UPDATE users SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' 
WHERE username = 'developer';
```

### 4. Akses Aplikasi

1. Buka browser dan akses:
```
http://localhost/portal-sekolah/login.php
```

2. Login dengan kredensial default:
   - **Username**: developer
   - **Password**: admin123

### 5. Struktur Folder

Pastikan struktur folder seperti berikut:
```
portal-sekolah/
├── assets/
│   ├── css/
│   └── js/
├── config/
├── dashboard/
│   ├── developer/
│   ├── kepala_sekolah/
│   ├── guru/
│   └── siswa/
├── database/
├── includes/
├── login.php
├── logout.php
└── setup.php
```

## Troubleshooting

### Masalah: Tidak bisa login dengan password default

**Solusi**: 
1. Jalankan `setup.php` untuk mendapatkan hash password baru
2. Atau reset password melalui SQL:
```sql
UPDATE users SET password = '$2y$10$[hash_dari_setup.php]' WHERE username = 'developer';
```

### Masalah: Error koneksi database

**Solusi**:
1. Pastikan MySQL service berjalan
2. Periksa konfigurasi di `config/database.php`
3. Pastikan database `portal_sekolah` sudah dibuat
4. Pastikan user database memiliki hak akses

### Masalah: CSS/JS tidak loading

**Solusi**:
1. Pastikan path relatif benar
2. Periksa apakah file CSS/JS ada di folder `assets/`
3. Clear cache browser

### Masalah: Session tidak bekerja

**Solusi**:
1. Pastikan folder session writable
2. Periksa konfigurasi PHP session
3. Pastikan `session_start()` dipanggil di setiap halaman

## Alur Penggunaan

1. **Developer** login dan tambahkan sekolah serta kepala sekolah
2. **Kepala Sekolah** login dan tambahkan guru serta siswa
3. **Guru** login dan tambahkan mata pelajaran serta soal
4. **Siswa** login dan kerjakan soal yang tersedia

## Catatan Keamanan

- Ganti password default setelah instalasi
- Gunakan HTTPS di production
- Backup database secara berkala
- Update password secara berkala

## Support

Jika mengalami masalah, periksa:
1. Error log PHP
2. Error log MySQL
3. Console browser (F12)

