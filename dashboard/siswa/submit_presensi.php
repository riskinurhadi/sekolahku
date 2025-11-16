<?php
// AJAX endpoint untuk submit presensi siswa
header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['siswa']);

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'] ?? null;

if (!$sekolah_id) {
    // Get sekolah_id from user
    $stmt = $conn->prepare("SELECT sekolah_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sekolah_id = $user['sekolah_id'] ?? null;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    $conn->close();
    exit();
}

if (!isset($_POST['action']) || $_POST['action'] !== 'presensi') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    $conn->close();
    exit();
}

$kode_presensi = strtoupper(trim($_POST['kode_presensi'] ?? ''));

if (empty($kode_presensi)) {
    echo json_encode(['success' => false, 'message' => 'Kode presensi tidak boleh kosong!']);
    $conn->close();
    exit();
}

if (!$sekolah_id) {
    echo json_encode(['success' => false, 'message' => 'Anda belum terdaftar di sekolah!']);
    $conn->close();
    exit();
}

// Cari sesi dengan kode tersebut yang masih aktif
$stmt = $conn->prepare("SELECT sp.id, sp.mata_pelajaran_id, sp.guru_id, sp.kode_presensi, 
    sp.waktu_mulai, sp.waktu_selesai, sp.status, 
    sp.created_at, sp.updated_at, 
    mp.nama_pelajaran 
    FROM sesi_pelajaran sp 
    JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id 
    WHERE sp.kode_presensi = ? AND sp.status = 'aktif' 
    AND NOW() <= sp.waktu_selesai
    AND mp.sekolah_id = ?");
$stmt->bind_param("si", $kode_presensi, $sekolah_id);
$stmt->execute();
$sesi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sesi) {
    echo json_encode(['success' => false, 'message' => 'Kode presensi tidak valid atau sesi sudah berakhir!']);
    $conn->close();
    exit();
}

// Cek apakah sudah presensi
$stmt = $conn->prepare("SELECT * FROM presensi WHERE sesi_pelajaran_id = ? AND siswa_id = ?");
$stmt->bind_param("ii", $sesi['id'], $siswa_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Anda sudah melakukan presensi untuk sesi ini!']);
    $conn->close();
    exit();
}

// Cek apakah kode masih valid (dalam 30 menit)
$waktu_sekarang_db = $conn->query("SELECT NOW() as waktu_sekarang")->fetch_assoc()['waktu_sekarang'];

// Gunakan updated_at karena saat regenerate kode, updated_at akan berubah
$waktu_kode_dibuat = $sesi['updated_at'] ?? $sesi['created_at'];

// Hitung selisih dalam menit menggunakan query SQL untuk akurasi
$stmt_selisih = $conn->prepare("SELECT TIMESTAMPDIFF(MINUTE, ?, ?) as selisih_menit");
$stmt_selisih->bind_param("ss", $waktu_kode_dibuat, $waktu_sekarang_db);
$stmt_selisih->execute();
$result_selisih = $stmt_selisih->get_result()->fetch_assoc();
$selisih_menit = $result_selisih['selisih_menit'] ?? 0;
$stmt_selisih->close();

// Jika selisih negatif (waktu kode di masa depan), berarti ada masalah timezone, anggap valid
if ($selisih_menit < 0) {
    $selisih_menit = 0;
}

if ($selisih_menit > 30) {
    echo json_encode([
        'success' => false, 
        'expired' => true, 
        'message' => 'yahh kode sudah kadaluarsa (selisih: ' . round($selisih_menit, 2) . ' menit)',
        'debug' => [
            'waktu_kode' => $waktu_kode_dibuat,
            'waktu_sekarang' => $waktu_sekarang_db,
            'selisih_menit' => $selisih_menit
        ]
    ]);
    $conn->close();
    exit();
}

// Jika dalam 30 menit, presensi berhasil dengan status hadir
$status = 'hadir';

// Insert presensi
$stmt = $conn->prepare("INSERT INTO presensi (sesi_pelajaran_id, siswa_id, waktu_presensi, status) VALUES (?, ?, NOW(), ?)");
$stmt->bind_param("iis", $sesi['id'], $siswa_id, $status);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Presensi berhasil!']);
    exit();
} else {
    $error_msg = $conn->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Gagal melakukan presensi! Error: ' . $error_msg]);
    exit();
}
?>

