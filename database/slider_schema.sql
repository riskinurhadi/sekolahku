-- Schema untuk Tabel Slider Dashboard
-- Jalankan query ini untuk menambahkan tabel slider

CREATE TABLE IF NOT EXISTS slider (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gambar VARCHAR(255) NOT NULL COMMENT 'Nama file gambar',
    judul VARCHAR(200) NULL COMMENT 'Judul slider (optional)',
    deskripsi TEXT NULL COMMENT 'Deskripsi slider (optional)',
    link VARCHAR(500) NULL COMMENT 'Link ketika diklik (optional)',
    urutan INT DEFAULT 0 COMMENT 'Urutan tampil (0 = pertama)',
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    sekolah_id INT NULL COMMENT 'Jika null, tampil untuk semua sekolah',
    created_by INT NULL COMMENT 'User yang membuat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_urutan (urutan),
    INDEX idx_sekolah (sekolah_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

