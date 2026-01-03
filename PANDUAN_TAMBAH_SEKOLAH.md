# Panduan Menambahkan Sekolah Baru dan Kepala Sekolah Baru

## Cara Menambahkan Sekolah dan Kepala Sekolah

Sistem ini memiliki role **Developer** yang bertugas untuk menambahkan sekolah baru dan kepala sekolah baru ke dalam sistem. Berikut langkah-langkahnya:

---

## Prasyarat

1. **Login sebagai Developer**
   - Username: `developer`
   - Password: `admin123` (default)
   - Jika password tidak bekerja, jalankan file `setup.php` atau update melalui SQL

2. **Akses Dashboard Developer**
   - Setelah login sebagai developer, Anda akan diarahkan ke dashboard developer
   - Dashboard developer berisi menu untuk mengelola sekolah dan kepala sekolah

---

## Langkah 1: Menambahkan Sekolah Baru

### Via Halaman Kelola Sekolah

1. **Buka halaman "Kelola Sekolah"**
   - Dari dashboard developer, klik menu **"Sekolah"** atau akses langsung:
   - URL: `dashboard/developer/sekolah.php`

2. **Klik tombol "Tambah Sekolah"**
   - Tombol ini berada di bagian kanan atas tabel daftar sekolah

3. **Isi form tambah sekolah**
   - **Nama Sekolah** (Wajib diisi)
   - **Alamat** (Opsional)
   - **Telepon** (Opsional)
   - **Email** (Opsional)

4. **Klik "Simpan"**
   - Jika berhasil, Anda akan diarahkan kembali ke halaman daftar sekolah
   - Sekolah baru akan muncul dalam daftar

### Via URL Langsung

Akses: `dashboard/developer/tambah_sekolah.php`

---

## Langkah 2: Menambahkan Kepala Sekolah Baru

### Via Halaman Kelola Kepala Sekolah

1. **Buka halaman "Kelola Kepala Sekolah"**
   - Dari dashboard developer, klik menu **"Kepala Sekolah"** atau akses langsung:
   - URL: `dashboard/developer/kepala_sekolah.php`

2. **Klik tombol "Tambah Kepala Sekolah"**
   - Tombol ini berada di bagian kanan atas tabel daftar kepala sekolah

3. **Isi form tambah kepala sekolah**
   - **Username** (Wajib diisi, harus unik)
   - **Password** (Wajib diisi)
   - **Nama Lengkap** (Wajib diisi)
   - **Sekolah** (Wajib diisi, pilih dari dropdown)
     - **Catatan**: Hanya sekolah yang belum memiliki kepala sekolah yang akan muncul di dropdown
   - **Email** (Opsional)

4. **Klik "Simpan"**
   - Jika berhasil, kepala sekolah baru akan terdaftar dan otomatis di-assign ke sekolah yang dipilih
   - Sistem akan otomatis mengupdate field `kepala_sekolah_id` di tabel sekolah

### Via URL Langsung

Akses: `dashboard/developer/tambah_kepala_sekolah.php`

---

## Catatan Penting

### ⚠️ Batasan dan Aturan

1. **Satu Sekolah = Satu Kepala Sekolah**
   - Setiap sekolah hanya bisa memiliki **satu kepala sekolah**
   - Jika sekolah sudah memiliki kepala sekolah, sekolah tersebut tidak akan muncul di dropdown saat menambahkan kepala sekolah baru
   - Untuk mengganti kepala sekolah, hapus kepala sekolah lama terlebih dahulu

2. **Username Harus Unik**
   - Username kepala sekolah (dan semua user) harus unik di seluruh sistem
   - Jika username sudah digunakan, sistem akan menolak penambahan

3. **Urutan yang Disarankan**
   - **Langkah 1**: Tambahkan sekolah baru terlebih dahulu
   - **Langkah 2**: Setelah sekolah ditambahkan, barulah tambahkan kepala sekolah untuk sekolah tersebut

### 🔍 Melihat Daftar Sekolah dan Kepala Sekolah

1. **Daftar Sekolah**
   - Halaman: `dashboard/developer/sekolah.php`
   - Menampilkan semua sekolah beserta informasi kepala sekolahnya (jika ada)
   - Sekolah yang belum memiliki kepala sekolah akan menampilkan badge "Belum ditetapkan"

2. **Daftar Kepala Sekolah**
   - Halaman: `dashboard/developer/kepala_sekolah.php`
   - Menampilkan semua kepala sekolah beserta nama sekolahnya

---

## Troubleshooting

### Problem: Tidak bisa login sebagai developer

**Solusi:**
1. Pastikan akun developer sudah ada di database
2. Jika password default tidak bekerja, jalankan `setup.php` untuk reset password
3. Atau update manual melalui SQL:
   ```sql
   UPDATE users SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' 
   WHERE username = 'developer';
   ```
   (Password hash di atas adalah untuk password: `admin123`)

### Problem: Sekolah tidak muncul di dropdown saat menambahkan kepala sekolah

**Kemungkinan penyebab:**
- Sekolah tersebut sudah memiliki kepala sekolah
- Solusi: Hapus kepala sekolah lama terlebih dahulu, atau pilih sekolah lain

### Problem: Username sudah digunakan

**Solusi:**
- Gunakan username yang berbeda (harus unik di seluruh sistem)

---

## Struktur Menu Developer

Setelah login sebagai developer, Anda akan memiliki akses ke:

1. **Dashboard** (`dashboard/developer/index.php`)
   - Statistik sekolah, kepala sekolah, guru, dan siswa
   - Grafik trend pendaftaran

2. **Kelola Sekolah** (`dashboard/developer/sekolah.php`)
   - Daftar semua sekolah
   - Tambah sekolah baru
   - Hapus sekolah

3. **Kelola Kepala Sekolah** (`dashboard/developer/kepala_sekolah.php`)
   - Daftar semua kepala sekolah
   - Tambah kepala sekolah baru
   - Hapus kepala sekolah

4. **Profil** (`dashboard/developer/profil.php`)
   - Ubah profil developer

---

## Kesimpulan

**Ringkasan Proses:**

1. ✅ Login sebagai **Developer** (username: `developer`, password: `admin123`)
2. ✅ Buka menu **"Sekolah"** → Klik **"Tambah Sekolah"** → Isi form → Simpan
3. ✅ Buka menu **"Kepala Sekolah"** → Klik **"Tambah Kepala Sekolah"** → Isi form (pilih sekolah) → Simpan
4. ✅ Selesai! Kepala sekolah baru sudah bisa login dan mengelola sekolahnya

---

**Penting**: Setelah kepala sekolah ditambahkan, kepala sekolah tersebut dapat langsung login menggunakan username dan password yang telah dibuat, dan akan memiliki akses penuh untuk mengelola sekolahnya (menambahkan guru, siswa, kelas, dll).

