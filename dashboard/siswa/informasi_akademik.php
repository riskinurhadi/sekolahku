<?php
$page_title = 'Informasi Akademik';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['siswa', 'guru', 'kepala_sekolah', 'developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$sekolah_id = $_SESSION['sekolah_id'] ?? null;

// Get sekolah_id if not set
if (!$sekolah_id) {
    $stmt = $conn->prepare("SELECT sekolah_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sekolah_id = $user['sekolah_id'] ?? null;
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'mark_read') {
    $informasi_id = intval($_POST['informasi_id']);
    
    // Check if already read
    $check_stmt = $conn->prepare("SELECT id FROM informasi_akademik_baca WHERE informasi_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $informasi_id, $user_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if (!$exists) {
        $stmt = $conn->prepare("INSERT INTO informasi_akademik_baca (informasi_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $informasi_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirect to detail page
    header('Location: detail_informasi.php?id=' . $informasi_id);
    exit;
}

// Get all informasi akademik untuk user ini
$informasi_list = [];
$table_check = $conn->query("SHOW TABLES LIKE 'informasi_akademik'");
if ($table_check && $table_check->num_rows > 0) {
    // Build query berdasarkan role dan sekolah
    $query = "SELECT ia.*, u.nama_lengkap as pengirim_nama,
        (SELECT COUNT(*) FROM informasi_akademik_baca WHERE informasi_id = ia.id AND user_id = ?) as sudah_dibaca
        FROM informasi_akademik ia
        JOIN users u ON ia.pengirim_id = u.id
        WHERE ia.status = 'terkirim'";
    
    $params = [$user_id];
    $types = "i";
    
    // Filter berdasarkan sekolah jika ada
    if ($sekolah_id) {
        $query .= " AND (ia.sekolah_id = ? OR ia.sekolah_id IS NULL)";
        $params[] = $sekolah_id;
        $types .= "i";
    }
    
    // Filter berdasarkan target role
    // Jika target_user_id tidak null, berarti pesan spesifik untuk user tersebut
    // Jika target_user_id null, filter berdasarkan target_role
    $query .= " AND ((ia.target_user_id IS NOT NULL AND ia.target_user_id = ?)";
    $params[] = $user_id;
    $types .= "i";
    
    $query .= " OR (ia.target_user_id IS NULL AND (ia.target_role = 'semua' OR ia.target_role = ?)))";
    $params[] = $user_role;
    $types .= "s";
    
    $query .= " ORDER BY 
        CASE ia.prioritas
            WHEN 'sangat_penting' THEN 1
            WHEN 'penting' THEN 2
            ELSE 3
        END,
        ia.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $informasi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$conn->close();
?>

<div class="page-header">
    <h2><i class="bi bi-megaphone"></i> Informasi Akademik</h2>
    <p>Daftar pesan dan informasi penting dari akademik</p>
</div>

<!-- Daftar Informasi Akademik -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (count($informasi_list) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($informasi_list as $info): ?>
                            <a href="detail_informasi.php?id=<?php echo $info['id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $info['sudah_dibaca'] > 0 ? '' : 'list-group-item-primary'; ?>"
                               style="border-left: 4px solid <?php 
                                   echo $info['prioritas'] == 'sangat_penting' ? '#ef4444' : 
                                       ($info['prioritas'] == 'penting' ? '#f59e0b' : '#6366f1'); 
                               ?>;">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php if ($info['sudah_dibaca'] == 0): ?>
                                                <span class="badge bg-primary me-2">Baru</span>
                                            <?php endif; ?>
                                            <?php if ($info['prioritas'] == 'sangat_penting'): ?>
                                                <span class="badge bg-danger me-1">Sangat Penting</span>
                                            <?php elseif ($info['prioritas'] == 'penting'): ?>
                                                <span class="badge bg-warning me-1">Penting</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($info['judul']); ?>
                                        </h6>
                                        <p class="mb-1 text-muted" style="font-size: 0.875rem;">
                                            <?php echo htmlspecialchars(substr(strip_tags($info['isi']), 0, 100)); ?>
                                            <?php echo strlen(strip_tags($info['isi'])) > 100 ? '...' : ''; ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($info['pengirim_nama']); ?>
                                            <span class="ms-2">
                                                <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($info['created_at'])); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <div>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1; opacity: 0.6;"></i>
                        <h5 class="mt-3 mb-2" style="font-size: 1rem; font-weight: 600; color: #1e293b;">Tidak ada informasi</h5>
                        <p class="text-muted mb-3" style="font-size: 0.875rem; color: #64748b;">
                            Belum ada informasi akademik yang tersedia
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

