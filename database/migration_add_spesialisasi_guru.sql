-- Migration: Tambah Kolom Spesialisasi untuk Guru
-- Jalankan file ini untuk update database yang sudah ada

USE sekolahku;

-- Tambah kolom spesialisasi di tabel users (untuk guru)
ALTER TABLE users ADD COLUMN spesialisasi VARCHAR(100) NULL AFTER kelas_id;
ALTER TABLE users ADD INDEX idx_spesialisasi (spesialisasi);

