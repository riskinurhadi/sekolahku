-- Database Schema untuk Portal Sekolah

CREATE DATABASE IF NOT EXISTS sekolahku;
USE sekolahku;

-- Tabel Users (untuk semua layer)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('developer', 'kepala_sekolah', 'guru', 'siswa', 'akademik') NOT NULL,
    sekolah_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_sekolah (sekolah_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Sekolah
CREATE TABLE sekolah (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_sekolah VARCHAR(200) NOT NULL,
    alamat TEXT,
    telepon VARCHAR(20),
    email VARCHAR(100),
    kepala_sekolah_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kepala_sekolah_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_kepala_sekolah (kepala_sekolah_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update foreign key untuk sekolah_id di users
ALTER TABLE users ADD FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE SET NULL;

-- Tabel Kelas
CREATE TABLE kelas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kelas VARCHAR(50) NOT NULL,
    tingkat INT NOT NULL COMMENT '10, 11, atau 12',
    sekolah_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kelas_sekolah (nama_kelas, sekolah_id),
    INDEX idx_sekolah (sekolah_id),
    INDEX idx_tingkat (tingkat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tambah kolom kelas_id di users (untuk siswa)
ALTER TABLE users ADD COLUMN kelas_id INT NULL AFTER sekolah_id;
ALTER TABLE users ADD FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL;
ALTER TABLE users ADD INDEX idx_kelas (kelas_id);

-- Tambah kolom spesialisasi di users (untuk guru)
ALTER TABLE users ADD COLUMN spesialisasi VARCHAR(100) NULL AFTER kelas_id;
ALTER TABLE users ADD INDEX idx_spesialisasi (spesialisasi);

-- Tabel Mata Pelajaran
CREATE TABLE mata_pelajaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_pelajaran VARCHAR(100) NOT NULL,
    kode_pelajaran VARCHAR(20),
    sekolah_id INT NOT NULL,
    guru_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sekolah (sekolah_id),
    INDEX idx_guru (guru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Soal
CREATE TABLE soal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mata_pelajaran_id INT NOT NULL,
    guru_id INT NOT NULL,
    judul VARCHAR(200) NOT NULL,
    deskripsi TEXT,
    jenis ENUM('quiz', 'pilihan_ganda', 'isian') NOT NULL,
    waktu_pengerjaan INT DEFAULT 60, -- dalam menit
    tanggal_mulai DATETIME,
    tanggal_selesai DATETIME,
    status ENUM('draft', 'aktif', 'selesai') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_mata_pelajaran (mata_pelajaran_id),
    INDEX idx_guru (guru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Item Soal (untuk pertanyaan dalam soal)
CREATE TABLE item_soal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    soal_id INT NOT NULL,
    pertanyaan TEXT NOT NULL,
    jenis_jawaban ENUM('pilihan_ganda', 'isian', 'essay') NOT NULL,
    poin INT DEFAULT 1,
    urutan INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE,
    INDEX idx_soal (soal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Pilihan Jawaban (untuk pilihan ganda)
CREATE TABLE pilihan_jawaban (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_soal_id INT NOT NULL,
    pilihan TEXT NOT NULL,
    is_benar TINYINT(1) DEFAULT 0,
    urutan INT DEFAULT 1,
    FOREIGN KEY (item_soal_id) REFERENCES item_soal(id) ON DELETE CASCADE,
    INDEX idx_item_soal (item_soal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Jawaban Siswa
CREATE TABLE jawaban_siswa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    soal_id INT NOT NULL,
    siswa_id INT NOT NULL,
    item_soal_id INT NOT NULL,
    jawaban TEXT,
    pilihan_jawaban_id INT NULL,
    poin_diperoleh INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_soal_id) REFERENCES item_soal(id) ON DELETE CASCADE,
    FOREIGN KEY (pilihan_jawaban_id) REFERENCES pilihan_jawaban(id) ON DELETE SET NULL,
    INDEX idx_soal (soal_id),
    INDEX idx_siswa (siswa_id),
    INDEX idx_item_soal (item_soal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Hasil Ujian
CREATE TABLE hasil_ujian (
    id INT PRIMARY KEY AUTO_INCREMENT,
    soal_id INT NOT NULL,
    siswa_id INT NOT NULL,
    total_poin INT DEFAULT 0,
    poin_diperoleh INT DEFAULT 0,
    nilai DECIMAL(5,2),
    status ENUM('belum_selesai', 'selesai') DEFAULT 'belum_selesai',
    waktu_mulai DATETIME,
    waktu_selesai DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_soal_siswa (soal_id, siswa_id),
    INDEX idx_soal (soal_id),
    INDEX idx_siswa (siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Jadwal Pelajaran
CREATE TABLE jadwal_pelajaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mata_pelajaran_id INT NOT NULL,
    sekolah_id INT NOT NULL,
    kelas_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    ruangan VARCHAR(50),
    status ENUM('terjadwal', 'berlangsung', 'selesai', 'dibatalkan') DEFAULT 'terjadwal',
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    INDEX idx_tanggal (tanggal),
    INDEX idx_mata_pelajaran (mata_pelajaran_id),
    INDEX idx_sekolah (sekolah_id),
    INDEX idx_kelas (kelas_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default developer account
-- Password: admin123
-- Note: Jika hash tidak bekerja, jalankan setup.php untuk mendapatkan hash baru
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES
('developer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Developer', 'developer@portal.com', 'developer');

-- Jika password hash di atas tidak bekerja, gunakan query berikut untuk update:
-- UPDATE users SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' WHERE username = 'developer';

