<?php
// AJAX endpoint untuk generate kode presensi
header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);

$conn = getConnection();
$guru_id = $_SESSION['user_id'];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (!isset($_POST['action']) || $_POST['action'] !== 'generate_kode') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    $conn->close();
    exit();
}

$jadwal_id = intval($_POST['jadwal_id'] ?? 0);

if ($jadwal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID jadwal tidak valid!']);
    $conn->close();
    exit();
}

// Verify that this jadwal belongs to a mata pelajaran taught by this guru
$stmt = $conn->prepare("SELECT jp.*, mp.id as mata_pelajaran_id, mp.nama_pelajaran
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    WHERE jp.id = ? AND mp.guru_id = ?");
$stmt->bind_param("ii", $jadwal_id, $guru_id);
$stmt->execute();
$jadwal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$jadwal) {
    echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan atau tidak memiliki akses!']);
    $conn->close();
    exit();
}

// Generate unique code (6 characters alphanumeric)
function generateKodePresensi($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude confusing chars like 0, O, I, 1
    do {
        $kode = '';
        for ($i = 0; $i < 6; $i++) {
            $kode .= $chars[rand(0, strlen($chars) - 1)];
        }
        // Check if code already exists
        $check = $conn->prepare("SELECT id FROM sesi_pelajaran WHERE kode_presensi = ?");
        $check->bind_param("s", $kode);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
    } while ($exists);
    return $kode;
}

$kode_presensi = generateKodePresensi($conn);

// Calculate waktu_mulai and waktu_selesai from jadwal
$tanggal = $jadwal['tanggal'];
$waktu_mulai = $tanggal . ' ' . $jadwal['jam_mulai'];
$waktu_selesai = $tanggal . ' ' . $jadwal['jam_selesai'];

// Create sesi_pelajaran
$stmt = $conn->prepare("INSERT INTO sesi_pelajaran (mata_pelajaran_id, guru_id, kode_presensi, waktu_mulai, waktu_selesai, status) 
    VALUES (?, ?, ?, ?, ?, 'aktif')");
$stmt->bind_param("iisss", $jadwal['mata_pelajaran_id'], $guru_id, $kode_presensi, $waktu_mulai, $waktu_selesai);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'kode' => $kode_presensi, 'message' => 'Kode presensi berhasil dibuat!']);
    exit();
} else {
    $error_msg = $conn->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Gagal membuat kode presensi: ' . $error_msg]);
    exit();
}
?>

