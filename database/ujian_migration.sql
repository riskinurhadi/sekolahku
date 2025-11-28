-- Migration untuk fitur UTS/UAS

-- Tambah kolom tipe_ujian di tabel soal
ALTER TABLE soal ADD COLUMN tipe_ujian ENUM('latihan', 'uts', 'uas') DEFAULT 'latihan' AFTER jenis;
ALTER TABLE soal ADD INDEX idx_tipe_ujian (tipe_ujian);

-- Tabel Jadwal Ujian (untuk akademik mengatur jadwal UTS/UAS)
CREATE TABLE IF NOT EXISTS jadwal_ujian (
    id INT PRIMARY KEY AUTO_INCREMENT,
    soal_id INT NOT NULL,
    kelas_id INT NOT NULL,
    tanggal_ujian DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    ruangan VARCHAR(50),
    pengawas_id INT NULL,
    status ENUM('draft', 'terjadwal', 'berlangsung', 'selesai') DEFAULT 'draft',
    created_by INT NOT NULL COMMENT 'akademik yang membuat jadwal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (pengawas_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_soal (soal_id),
    INDEX idx_kelas (kelas_id),
    INDEX idx_tanggal (tanggal_ujian),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

