-- Migration: Tambah Sistem Kelas untuk Siswa dan Jadwal Pelajaran
-- Jalankan file ini untuk update database yang sudah ada

USE sekolahku;

-- 1. Buat tabel kelas
CREATE TABLE IF NOT EXISTS kelas (
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

-- 2. Insert default kelas (10 D, 11 D, 12 D) untuk setiap sekolah yang ada
-- Note: Kelas akan dibuat otomatis untuk setiap sekolah yang sudah ada
INSERT INTO kelas (nama_kelas, tingkat, sekolah_id)
SELECT CONCAT(tingkat_list.tingkat, ' D') as nama_kelas, tingkat_list.tingkat, sekolah.id as sekolah_id
FROM sekolah
CROSS JOIN (SELECT 10 as tingkat UNION SELECT 11 UNION SELECT 12) as tingkat_list
WHERE NOT EXISTS (
    SELECT 1 FROM kelas k 
    WHERE k.nama_kelas = CONCAT(tingkat_list.tingkat, ' D') 
    AND k.sekolah_id = sekolah.id
);

-- 3. Tambah kolom kelas_id di tabel users (untuk siswa)
ALTER TABLE users ADD COLUMN kelas_id INT NULL AFTER sekolah_id;
ALTER TABLE users ADD FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL;
ALTER TABLE users ADD INDEX idx_kelas (kelas_id);

-- 4. Tambah kolom kelas_id di tabel jadwal_pelajaran
ALTER TABLE jadwal_pelajaran ADD COLUMN kelas_id INT NULL AFTER sekolah_id;
ALTER TABLE jadwal_pelajaran ADD FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE;
ALTER TABLE jadwal_pelajaran ADD INDEX idx_kelas (kelas_id);

