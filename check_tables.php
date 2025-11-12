<?php
/**
 * Script untuk memverifikasi dan membuat tabel presensi jika belum ada
 * Jalankan file ini sekali melalui browser
 */

require_once 'config/database.php';

$conn = getConnection();

echo "<h2>Verifikasi Tabel Presensi</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
</style>";

// Check and create sesi_pelajaran table
$table_check = $conn->query("SHOW TABLES LIKE 'sesi_pelajaran'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p class='success'>✓ Tabel sesi_pelajaran sudah ada</p>";
} else {
    echo "<p class='error'>✗ Tabel sesi_pelajaran belum ada. Membuat tabel...</p>";
    
    $sql = "CREATE TABLE IF NOT EXISTS sesi_pelajaran (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>✓ Tabel sesi_pelajaran berhasil dibuat</p>";
    } else {
        echo "<p class='error'>✗ Gagal membuat tabel sesi_pelajaran: " . $conn->error . "</p>";
    }
}

// Check and create presensi table
$table_check = $conn->query("SHOW TABLES LIKE 'presensi'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p class='success'>✓ Tabel presensi sudah ada</p>";
} else {
    echo "<p class='error'>✗ Tabel presensi belum ada. Membuat tabel...</p>";
    
    $sql = "CREATE TABLE IF NOT EXISTS presensi (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "<p class='success'>✓ Tabel presensi berhasil dibuat</p>";
    } else {
        echo "<p class='error'>✗ Gagal membuat tabel presensi: " . $conn->error . "</p>";
    }
}

// Check for active sessions
$sessions = $conn->query("SELECT COUNT(*) as total FROM sesi_pelajaran WHERE status = 'aktif'");
if ($sessions) {
    $total = $sessions->fetch_assoc()['total'];
    echo "<p class='info'>ℹ Total sesi pelajaran aktif: $total</p>";
}

// Check for presensi records
$presensi = $conn->query("SELECT COUNT(*) as total FROM presensi");
if ($presensi) {
    $total = $presensi->fetch_assoc()['total'];
    echo "<p class='info'>ℹ Total presensi: $total</p>";
}

$conn->close();

echo "<hr>";
echo "<p><a href='dashboard/siswa/presensi.php'>Kembali ke Halaman Presensi</a></p>";
?>

