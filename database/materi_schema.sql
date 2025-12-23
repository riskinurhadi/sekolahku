-- ============================================
-- SCHEMA DATABASE UNTUK FITUR MATERI
-- ============================================

-- 1. Tabel Materi Pelajaran
-- Menyimpan materi pembelajaran yang diupload oleh guru
CREATE TABLE IF NOT EXISTS `materi_pelajaran` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `mata_pelajaran_id` INT(11) NOT NULL,
  `guru_id` INT(11) NOT NULL,
  `judul` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT DEFAULT NULL,
  `konten` LONGTEXT DEFAULT NULL COMMENT 'Konten materi dalam format HTML/text',
  `file_attachment` VARCHAR(500) DEFAULT NULL COMMENT 'Path ke file attachment (PDF, DOC, dll)',
  `file_name` VARCHAR(255) DEFAULT NULL COMMENT 'Nama file asli',
  `file_size` INT(11) DEFAULT NULL COMMENT 'Ukuran file dalam bytes',
  `urutan` INT(11) DEFAULT 0 COMMENT 'Urutan tampil materi',
  `status` ENUM('draft', 'aktif', 'arsip') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mata_pelajaran` (`mata_pelajaran_id`),
  KEY `idx_guru` (`guru_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_materi_mata_pelajaran` FOREIGN KEY (`mata_pelajaran_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_materi_guru` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Latihan
-- Menyimpan latihan yang terkait dengan materi (bisa tugas file atau soal)
CREATE TABLE IF NOT EXISTS `latihan` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `materi_id` INT(11) NOT NULL,
  `judul` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT DEFAULT NULL,
  `jenis` ENUM('tugas_file', 'soal') NOT NULL COMMENT 'tugas_file = submit file, soal = mengerjakan soal',
  `deadline` DATETIME DEFAULT NULL COMMENT 'Batas waktu pengumpulan',
  `poin` INT(11) DEFAULT 100 COMMENT 'Total poin yang bisa didapat',
  `random_soal` TINYINT(1) DEFAULT 0 COMMENT '1 = soal random, 0 = urutan tetap',
  `jumlah_soal` INT(11) DEFAULT NULL COMMENT 'Jumlah soal jika random (hanya untuk jenis soal)',
  `waktu_pengerjaan` INT(11) DEFAULT NULL COMMENT 'Waktu pengerjaan dalam menit (hanya untuk jenis soal)',
  `status` ENUM('draft', 'aktif', 'selesai') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_materi` (`materi_id`),
  KEY `idx_jenis` (`jenis`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_latihan_materi` FOREIGN KEY (`materi_id`) REFERENCES `materi_pelajaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Latihan Soal
-- Menyimpan soal-soal yang ada dalam latihan (jika jenis = soal)
CREATE TABLE IF NOT EXISTS `latihan_soal` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `latihan_id` INT(11) NOT NULL,
  `soal_id` INT(11) NOT NULL COMMENT 'Referensi ke tabel soal',
  `urutan` INT(11) DEFAULT 0 COMMENT 'Urutan tampil soal',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_latihan` (`latihan_id`),
  KEY `idx_soal` (`soal_id`),
  KEY `idx_urutan` (`urutan`),
  CONSTRAINT `fk_latihan_soal_latihan` FOREIGN KEY (`latihan_id`) REFERENCES `latihan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_latihan_soal_soal` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_latihan_soal` (`latihan_id`, `soal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Submisi Tugas
-- Menyimpan submisi tugas file dari siswa
CREATE TABLE IF NOT EXISTS `submisi_tugas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `latihan_id` INT(11) NOT NULL,
  `siswa_id` INT(11) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Path ke file yang diupload',
  `file_name` VARCHAR(255) NOT NULL COMMENT 'Nama file asli',
  `file_size` INT(11) DEFAULT NULL COMMENT 'Ukuran file dalam bytes',
  `catatan` TEXT DEFAULT NULL COMMENT 'Catatan dari siswa',
  `nilai` DECIMAL(5,2) DEFAULT NULL COMMENT 'Nilai yang diberikan guru',
  `feedback` TEXT DEFAULT NULL COMMENT 'Feedback dari guru',
  `status` ENUM('menunggu', 'dinilai', 'selesai', 'ditolak') DEFAULT 'menunggu',
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu submit',
  `graded_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu dinilai',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_latihan` (`latihan_id`),
  KEY `idx_siswa` (`siswa_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_submisi_tugas_latihan` FOREIGN KEY (`latihan_id`) REFERENCES `latihan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submisi_tugas_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_siswa_latihan` (`latihan_id`, `siswa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Submisi Latihan Soal
-- Menyimpan hasil pengerjaan latihan soal dari siswa
CREATE TABLE IF NOT EXISTS `submisi_latihan_soal` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `latihan_id` INT(11) NOT NULL,
  `siswa_id` INT(11) NOT NULL,
  `total_soal` INT(11) DEFAULT 0,
  `soal_benar` INT(11) DEFAULT 0,
  `total_poin` DECIMAL(10,2) DEFAULT 0,
  `poin_diperoleh` DECIMAL(10,2) DEFAULT 0,
  `nilai` DECIMAL(5,2) DEFAULT NULL COMMENT 'Nilai akhir (0-100)',
  `waktu_mulai` TIMESTAMP NULL DEFAULT NULL,
  `waktu_selesai` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('belum_mulai', 'sedang_mengerjakan', 'selesai', 'terlambat') DEFAULT 'belum_mulai',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_latihan` (`latihan_id`),
  KEY `idx_siswa` (`siswa_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_submisi_latihan_soal_latihan` FOREIGN KEY (`latihan_id`) REFERENCES `latihan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submisi_latihan_soal_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_siswa_latihan_soal` (`latihan_id`, `siswa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Detail Submisi Latihan Soal
-- Menyimpan detail jawaban per soal dalam latihan
CREATE TABLE IF NOT EXISTS `detail_submisi_latihan_soal` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `submisi_latihan_id` INT(11) NOT NULL,
  `soal_id` INT(11) NOT NULL,
  `jawaban` TEXT DEFAULT NULL COMMENT 'Jawaban siswa',
  `poin` DECIMAL(10,2) DEFAULT 0 COMMENT 'Poin yang didapat untuk soal ini',
  `is_benar` TINYINT(1) DEFAULT 0 COMMENT '1 = benar, 0 = salah',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_submisi` (`submisi_latihan_id`),
  KEY `idx_soal` (`soal_id`),
  CONSTRAINT `fk_detail_submisi_latihan` FOREIGN KEY (`submisi_latihan_id`) REFERENCES `submisi_latihan_soal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_submisi_soal` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_submisi_soal` (`submisi_latihan_id`, `soal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel Progress Materi Siswa
-- Menyimpan progress siswa dalam mempelajari materi
CREATE TABLE IF NOT EXISTS `progress_materi_siswa` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `materi_id` INT(11) NOT NULL,
  `siswa_id` INT(11) NOT NULL,
  `progress` INT(11) DEFAULT 0 COMMENT 'Progress dalam persen (0-100)',
  `waktu_baca` INT(11) DEFAULT 0 COMMENT 'Total waktu membaca dalam detik',
  `terakhir_dibaca` TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu terakhir membaca',
  `status` ENUM('belum_dibaca', 'sedang_dibaca', 'selesai') DEFAULT 'belum_dibaca',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_materi` (`materi_id`),
  KEY `idx_siswa` (`siswa_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_progress_materi` FOREIGN KEY (`materi_id`) REFERENCES `materi_pelajaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_siswa_materi` (`materi_id`, `siswa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INDEXES TAMBAHAN UNTUK PERFORMANCE
-- ============================================

-- Index untuk pencarian materi berdasarkan mata pelajaran dan status
CREATE INDEX `idx_materi_pelajaran_status` ON `materi_pelajaran` (`mata_pelajaran_id`, `status`);

-- Index untuk pencarian latihan aktif
CREATE INDEX `idx_latihan_aktif` ON `latihan` (`materi_id`, `status`, `jenis`);

-- Index untuk pencarian submisi berdasarkan siswa
CREATE INDEX `idx_submisi_siswa_status` ON `submisi_tugas` (`siswa_id`, `status`);
CREATE INDEX `idx_submisi_latihan_siswa_status` ON `submisi_latihan_soal` (`siswa_id`, `status`);

-- ============================================
-- COMMENTS
-- ============================================

/*
STRUKTUR DATABASE FITUR MATERI:

1. materi_pelajaran
   - Menyimpan materi pembelajaran yang diupload guru
   - Bisa memiliki file attachment
   - Memiliki urutan untuk sorting

2. latihan
   - Latihan yang terkait dengan materi
   - Jenis: tugas_file (submit file) atau soal (mengerjakan soal)
   - Bisa random soal jika jenis = soal
   - Memiliki deadline

3. latihan_soal
   - Soal-soal dalam latihan (jika jenis = soal)
   - Bisa random atau urutan tetap

4. submisi_tugas
   - Submisi tugas file dari siswa
   - Bisa dinilai oleh guru
   - Memiliki status dan feedback

5. submisi_latihan_soal
   - Hasil pengerjaan latihan soal
   - Menyimpan total soal, soal benar, nilai
   - Tracking waktu mulai dan selesai

6. detail_submisi_latihan_soal
   - Detail jawaban per soal
   - Menyimpan poin per soal

7. progress_materi_siswa
   - Tracking progress siswa membaca materi
   - Menyimpan waktu baca dan status

CARA KERJA:
- Guru upload materi di materi_pelajaran
- Guru bisa menambahkan latihan (tugas_file atau soal) di latihan
- Jika latihan jenis soal, guru bisa menambahkan soal di latihan_soal
- Siswa bisa membaca materi dan progress tersimpan di progress_materi_siswa
- Siswa bisa submit tugas file di submisi_tugas
- Siswa bisa mengerjakan latihan soal di submisi_latihan_soal
- Detail jawaban tersimpan di detail_submisi_latihan_soal
*/

