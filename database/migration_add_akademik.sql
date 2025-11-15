-- Migration: Tambah Role Akademik dan Tabel Jadwal Pelajaran
-- Jalankan file ini untuk update database yang sudah ada

USE sekolahku;

-- 1. Update ENUM role di tabel users untuk menambahkan 'akademik'
ALTER TABLE users MODIFY COLUMN role ENUM('developer', 'kepala_sekolah', 'guru', 'siswa', 'akademik') NOT NULL;

-- 2. Buat tabel jadwal_pelajaran
CREATE TABLE IF NOT EXISTS jadwal_pelajaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mata_pelajaran_id INT NOT NULL,
    sekolah_id INT NOT NULL,
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
    INDEX idx_tanggal (tanggal),
    INDEX idx_mata_pelajaran (mata_pelajaran_id),
    INDEX idx_sekolah (sekolah_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

