-- Schema untuk fitur Presensi

-- Tabel Sesi Pelajaran
CREATE TABLE IF NOT EXISTS sesi_pelajaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mata_pelajaran_id INT NOT NULL,
    guru_id INT NOT NULL,
    kode_presensi VARCHAR(10) UNIQUE NOT NULL,
    waktu_mulai DATETIME NOT NULL,
    waktu_selesai DATETIME NOT NULL,
    status ENUM('aktif', 'selesai') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_mata_pelajaran (mata_pelajaran_id),
    INDEX idx_guru (guru_id),
    INDEX idx_kode (kode_presensi),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Presensi
CREATE TABLE IF NOT EXISTS presensi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sesi_pelajaran_id INT NOT NULL,
    siswa_id INT NOT NULL,
    waktu_presensi DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('hadir', 'terlambat', 'tidak_hadir') DEFAULT 'hadir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_pelajaran_id) REFERENCES sesi_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sesi_siswa (sesi_pelajaran_id, siswa_id),
    INDEX idx_sesi (sesi_pelajaran_id),
    INDEX idx_siswa (siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

