-- Script untuk membuat tabel Informasi Akademik
-- Jalankan script ini di database sekolahku

USE sekolahku;

-- Tabel Pesan Akademik
CREATE TABLE IF NOT EXISTS informasi_akademik (
    id INT PRIMARY KEY AUTO_INCREMENT,
    judul VARCHAR(200) NOT NULL,
    isi TEXT NOT NULL,
    pengirim_id INT NOT NULL,
    sekolah_id INT NULL,
    target_role ENUM('semua', 'siswa', 'guru', 'kepala_sekolah') DEFAULT 'semua',
    target_user_id INT NULL, -- NULL untuk broadcast, atau ID user spesifik
    prioritas ENUM('normal', 'penting', 'sangat_penting') DEFAULT 'normal',
    status ENUM('draft', 'terkirim', 'dihapus') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pengirim_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pengirim (pengirim_id),
    INDEX idx_sekolah (sekolah_id),
    INDEX idx_target_role (target_role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Status Baca Pesan (untuk tracking pesan yang sudah dibaca)
CREATE TABLE IF NOT EXISTS informasi_akademik_baca (
    id INT PRIMARY KEY AUTO_INCREMENT,
    informasi_id INT NOT NULL,
    user_id INT NOT NULL,
    dibaca_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (informasi_id) REFERENCES informasi_akademik(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_informasi (informasi_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_informasi (informasi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

