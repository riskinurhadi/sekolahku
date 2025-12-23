<?php
require_once '../../config/session.php';
requireRole(['siswa']);

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $materi_id = intval($_POST['materi_id'] ?? 0);
    $progress = intval($_POST['progress'] ?? 0);
    $status = $_POST['status'] ?? 'sedang_dibaca';
    
    if ($materi_id > 0) {
        $stmt = $conn->prepare("INSERT INTO progress_materi_siswa (materi_id, siswa_id, progress, status, terakhir_dibaca) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE progress = ?, status = ?, terakhir_dibaca = NOW()");
        $stmt->bind_param("iiisisi", $materi_id, $siswa_id, $progress, $status, $progress, $status);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid materi_id']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}

