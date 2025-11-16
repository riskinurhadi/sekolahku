<?php
// AJAX endpoint untuk update status jadwal
header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);

$conn = getConnection();
$guru_id = $_SESSION['user_id'];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    $conn->close();
    exit();
}

if (!isset($_POST['action']) || $_POST['action'] !== 'update_status') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    $conn->close();
    exit();
}

$jadwal_id = intval($_POST['jadwal_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($jadwal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID jadwal tidak valid!']);
    $conn->close();
    exit();
}

// Validate status
$allowed_statuses = ['terjadwal', 'berlangsung', 'selesai', 'dibatalkan'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid!']);
    $conn->close();
    exit();
}

// Verify that this jadwal belongs to a mata pelajaran taught by this guru
$stmt = $conn->prepare("SELECT jp.*, mp.id as mata_pelajaran_id
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

// Update status
$stmt = $conn->prepare("UPDATE jadwal_pelajaran SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $jadwal_id);

if ($stmt->execute()) {
    $stmt->close();
    
    // Jika status diubah menjadi 'selesai', update sesi_pelajaran yang aktif untuk jadwal ini
    if ($status === 'selesai') {
        // Cari sesi_pelajaran yang terkait dengan jadwal ini
        // Match berdasarkan mata_pelajaran_id, guru_id, tanggal, dan jam_mulai
        $tanggal = $jadwal['tanggal'];
        $jam_mulai = $jadwal['jam_mulai'];
        $mata_pelajaran_id = $jadwal['mata_pelajaran_id'];
        
        // Update sesi_pelajaran yang aktif untuk jadwal ini
        $update_sesi = $conn->prepare("UPDATE sesi_pelajaran 
            SET status = 'selesai', waktu_selesai = NOW() 
            WHERE mata_pelajaran_id = ? 
            AND guru_id = ? 
            AND DATE(waktu_mulai) = ? 
            AND TIME(waktu_mulai) = ? 
            AND status = 'aktif'");
        $update_sesi->bind_param("iiss", $mata_pelajaran_id, $guru_id, $tanggal, $jam_mulai);
        $update_sesi->execute();
        $update_sesi->close();
    }
    
    $conn->close();
    
    $status_text = [
        'terjadwal' => 'Terjadwal',
        'berlangsung' => 'Berlangsung',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Status jadwal berhasil diubah menjadi ' . ($status_text[$status] ?? $status) . '!',
        'status' => $status,
        'status_text' => $status_text[$status] ?? ucfirst($status)
    ]);
    exit();
} else {
    $error_msg = $conn->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Gagal mengubah status jadwal: ' . $error_msg]);
    exit();
}
?>

