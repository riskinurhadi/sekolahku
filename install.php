<?php
/**
 * Script Instalasi Otomatis Portal Sekolah
 * Jalankan file ini sekali untuk setup database
 */

require_once 'config/database.php';

// Generate password hash untuk admin123
$password_hash = password_hash('admin123', PASSWORD_DEFAULT);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Portal Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; }
        .card { margin-bottom: 20px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Instalasi Portal Sekolah</h3>
            </div>
            <div class="card-body">
                <?php
                try {
                    $conn = getConnection();
                    echo "<p class='success'>✓ Koneksi database berhasil!</p>";
                    
                    // Create tables
                    $tables = [
                        "CREATE TABLE IF NOT EXISTS users (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            username VARCHAR(50) UNIQUE NOT NULL,
                            password VARCHAR(255) NOT NULL,
                            nama_lengkap VARCHAR(100) NOT NULL,
                            email VARCHAR(100),
                            role ENUM('developer', 'kepala_sekolah', 'guru', 'siswa') NOT NULL,
                            sekolah_id INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_role (role),
                            INDEX idx_sekolah (sekolah_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS sekolah (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            nama_sekolah VARCHAR(200) NOT NULL,
                            alamat TEXT,
                            telepon VARCHAR(20),
                            email VARCHAR(100),
                            kepala_sekolah_id INT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_kepala_sekolah (kepala_sekolah_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS mata_pelajaran (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            nama_pelajaran VARCHAR(100) NOT NULL,
                            kode_pelajaran VARCHAR(20),
                            sekolah_id INT NOT NULL,
                            guru_id INT NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_sekolah (sekolah_id),
                            INDEX idx_guru (guru_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS soal (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            mata_pelajaran_id INT NOT NULL,
                            guru_id INT NOT NULL,
                            judul VARCHAR(200) NOT NULL,
                            deskripsi TEXT,
                            jenis ENUM('quiz', 'pilihan_ganda', 'isian') NOT NULL,
                            waktu_pengerjaan INT DEFAULT 60,
                            tanggal_mulai DATETIME,
                            tanggal_selesai DATETIME,
                            status ENUM('draft', 'aktif', 'selesai') DEFAULT 'draft',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_mata_pelajaran (mata_pelajaran_id),
                            INDEX idx_guru (guru_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS item_soal (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            soal_id INT NOT NULL,
                            pertanyaan TEXT NOT NULL,
                            jenis_jawaban ENUM('pilihan_ganda', 'isian', 'essay') NOT NULL,
                            poin INT DEFAULT 1,
                            urutan INT DEFAULT 1,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_soal (soal_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS pilihan_jawaban (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            item_soal_id INT NOT NULL,
                            pilihan TEXT NOT NULL,
                            is_benar TINYINT(1) DEFAULT 0,
                            urutan INT DEFAULT 1,
                            INDEX idx_item_soal (item_soal_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS jawaban_siswa (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            soal_id INT NOT NULL,
                            siswa_id INT NOT NULL,
                            item_soal_id INT NOT NULL,
                            jawaban TEXT,
                            pilihan_jawaban_id INT NULL,
                            poin_diperoleh INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_soal (soal_id),
                            INDEX idx_siswa (siswa_id),
                            INDEX idx_item_soal (item_soal_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS hasil_ujian (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            soal_id INT NOT NULL,
                            siswa_id INT NOT NULL,
                            total_poin INT DEFAULT 0,
                            poin_diperoleh INT DEFAULT 0,
                            nilai DECIMAL(5,2),
                            status ENUM('belum_selesai', 'selesai') DEFAULT 'belum_selesai',
                            waktu_mulai DATETIME,
                            waktu_selesai DATETIME,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY unique_soal_siswa (soal_id, siswa_id),
                            INDEX idx_soal (soal_id),
                            INDEX idx_siswa (siswa_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS sesi_pelajaran (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            mata_pelajaran_id INT NOT NULL,
                            guru_id INT NOT NULL,
                            kode_presensi VARCHAR(10) UNIQUE NOT NULL,
                            waktu_mulai DATETIME NOT NULL,
                            waktu_selesai DATETIME NOT NULL,
                            status ENUM('aktif', 'selesai') DEFAULT 'aktif',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_mata_pelajaran (mata_pelajaran_id),
                            INDEX idx_guru (guru_id),
                            INDEX idx_kode (kode_presensi),
                            INDEX idx_status (status)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        
                        "CREATE TABLE IF NOT EXISTS presensi (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            sesi_pelajaran_id INT NOT NULL,
                            siswa_id INT NOT NULL,
                            waktu_presensi DATETIME DEFAULT CURRENT_TIMESTAMP,
                            status ENUM('hadir', 'terlambat', 'tidak_hadir') DEFAULT 'hadir',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY unique_sesi_siswa (sesi_pelajaran_id, siswa_id),
                            INDEX idx_sesi (sesi_pelajaran_id),
                            INDEX idx_siswa (siswa_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                    ];
                    
                    // Add foreign keys after tables are created
                    $foreign_keys = [
                        "ALTER TABLE users ADD FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE SET NULL",
                        "ALTER TABLE sekolah ADD FOREIGN KEY (kepala_sekolah_id) REFERENCES users(id) ON DELETE SET NULL",
                        "ALTER TABLE mata_pelajaran ADD FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE CASCADE",
                        "ALTER TABLE mata_pelajaran ADD FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE",
                        "ALTER TABLE soal ADD FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE",
                        "ALTER TABLE soal ADD FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE",
                        "ALTER TABLE item_soal ADD FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE",
                        "ALTER TABLE pilihan_jawaban ADD FOREIGN KEY (item_soal_id) REFERENCES item_soal(id) ON DELETE CASCADE",
                        "ALTER TABLE jawaban_siswa ADD FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE",
                        "ALTER TABLE jawaban_siswa ADD FOREIGN KEY (siswa_id) REFERENCES users(id) ON DELETE CASCADE",
                        "ALTER TABLE jawaban_siswa ADD FOREIGN KEY (item_soal_id) REFERENCES item_soal(id) ON DELETE CASCADE",
                        "ALTER TABLE jawaban_siswa ADD FOREIGN KEY (pilihan_jawaban_id) REFERENCES pilihan_jawaban(id) ON DELETE SET NULL",
                        "ALTER TABLE hasil_ujian ADD FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE",
                        "ALTER TABLE hasil_ujian ADD FOREIGN KEY (siswa_id) REFERENCES users(id) ON DELETE CASCADE",
                        "ALTER TABLE sesi_pelajaran ADD FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE",
                        "ALTER TABLE sesi_pelajaran ADD FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE",
                        "ALTER TABLE presensi ADD FOREIGN KEY (sesi_pelajaran_id) REFERENCES sesi_pelajaran(id) ON DELETE CASCADE",
                        "ALTER TABLE presensi ADD FOREIGN KEY (siswa_id) REFERENCES users(id) ON DELETE CASCADE"
                    ];
                    
                    echo "<h5>Membuat Tabel...</h5>";
                    foreach ($tables as $sql) {
                        if ($conn->query($sql)) {
                            echo "<p class='success'>✓ Tabel berhasil dibuat</p>";
                        } else {
                            // Check if error is "already exists"
                            if (strpos($conn->error, 'already exists') !== false) {
                                echo "<p class='warning'>⚠ Tabel sudah ada (diabaikan)</p>";
                            } else {
                                echo "<p class='error'>✗ Error: " . htmlspecialchars($conn->error) . "</p>";
                            }
                        }
                    }
                    
                    echo "<h5>Menambahkan Foreign Keys...</h5>";
                    foreach ($foreign_keys as $sql) {
                        if ($conn->query($sql)) {
                            echo "<p class='success'>✓ Foreign key berhasil ditambahkan</p>";
                        } else {
                            // Ignore errors for existing foreign keys
                            if (strpos($conn->error, 'Duplicate') !== false || 
                                strpos($conn->error, 'already exists') !== false ||
                                strpos($conn->error, 'Cannot add') !== false) {
                                echo "<p class='warning'>⚠ Foreign key sudah ada (diabaikan)</p>";
                            } else {
                                echo "<p class='error'>✗ Error: " . htmlspecialchars($conn->error) . "</p>";
                            }
                        }
                    }
                    
                    // Check if developer user exists
                    $result = $conn->query("SELECT * FROM users WHERE username = 'developer'");
                    if ($result->num_rows > 0) {
                        echo "<h5>Mengupdate Akun Developer...</h5>";
                        // Update password
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'developer'");
                        $stmt->bind_param("s", $password_hash);
                        if ($stmt->execute()) {
                            echo "<p class='success'>✓ Password developer berhasil diupdate</p>";
                        }
                        $stmt->close();
                    } else {
                        echo "<h5>Membuat Akun Developer...</h5>";
                        // Insert developer user
                        $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, ?)");
                        $username = 'developer';
                        $nama = 'Developer';
                        $email = 'developer@portal.com';
                        $role = 'developer';
                        $stmt->bind_param("sssss", $username, $password_hash, $nama, $email, $role);
                        
                        if ($stmt->execute()) {
                            echo "<p class='success'>✓ Akun developer berhasil dibuat</p>";
                        } else {
                            echo "<p class='error'>✗ Gagal membuat akun developer: " . htmlspecialchars($stmt->error) . "</p>";
                        }
                        $stmt->close();
                    }
                    
                    $conn->close();
                    
                    echo "<hr>";
                    echo "<div class='alert alert-success'>";
                    echo "<h5>Instalasi Selesai!</h5>";
                    echo "<p>Anda dapat login dengan:</p>";
                    echo "<ul>";
                    echo "<li><strong>Username:</strong> developer</li>";
                    echo "<li><strong>Password:</strong> admin123</li>";
                    echo "</ul>";
                    echo "<a href='login.php' class='btn btn-primary btn-lg'>Ke Halaman Login</a>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>";
                    echo "<h5>Error!</h5>";
                    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "<p>Pastikan:</p>";
                    echo "<ul>";
                    echo "<li>Database 'sekolahku' sudah dibuat</li>";
                    echo "<li>Konfigurasi di config/database.php sudah benar</li>";
                    echo "<li>User database memiliki hak akses CREATE, ALTER, INSERT</li>";
                    echo "</ul>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>
