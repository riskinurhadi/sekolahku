<?php
/**
 * Setup Script untuk Portal Sekolah
 * Jalankan file ini sekali untuk setup database dan akun developer
 */

require_once 'config/database.php';

// Generate password hash untuk admin123
$password_hash = password_hash('admin123', PASSWORD_DEFAULT);

echo "<h2>Setup Portal Sekolah</h2>";
echo "<p>Password hash untuk 'admin123': <code>$password_hash</code></p>";

// Test connection
try {
    $conn = getConnection();
    echo "<p style='color: green;'>✓ Koneksi database berhasil!</p>";
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Tabel users sudah ada</p>";
        
        // Check if developer user exists
        $result = $conn->query("SELECT * FROM users WHERE username = 'developer'");
        if ($result->num_rows > 0) {
            echo "<p style='color: orange;'>⚠ Akun developer sudah ada</p>";
            echo "<p>Jika ingin reset password, jalankan query berikut:</p>";
            echo "<pre>UPDATE users SET password = '$password_hash' WHERE username = 'developer';</pre>";
        } else {
            echo "<p style='color: blue;'>ℹ Akun developer belum ada. Silakan import database/schema.sql</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Tabel users belum ada. Silakan import database/schema.sql terlebih dahulu</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Pastikan:</p>";
    echo "<ul>";
    echo "<li>Database 'portal_sekolah' sudah dibuat</li>";
    echo "<li>Konfigurasi di config/database.php sudah benar</li>";
    echo "<li>File database/schema.sql sudah diimport</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Instruksi Setup:</h3>";
echo "<ol>";
echo "<li>Buat database 'portal_sekolah' di MySQL</li>";
echo "<li>Import file database/schema.sql</li>";
echo "<li>Edit config/database.php sesuai konfigurasi database Anda</li>";
echo "<li>Default login: username = 'developer', password = 'admin123'</li>";
echo "</ol>";
?>

