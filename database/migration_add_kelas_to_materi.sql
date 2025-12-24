-- Migration: Tambah field kelas_id ke tabel materi_pelajaran
-- Tanggal: 2025-01-XX

-- Tambah kolom kelas_id di materi_pelajaran
ALTER TABLE `materi_pelajaran` 
ADD COLUMN `kelas_id` INT(11) NULL AFTER `guru_id`,
ADD INDEX `idx_kelas` (`kelas_id`),
ADD CONSTRAINT `fk_materi_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

-- Update materi yang sudah ada (opsional, bisa diisi manual atau dihapus)
-- UPDATE materi_pelajaran SET kelas_id = NULL WHERE kelas_id IS NULL;

