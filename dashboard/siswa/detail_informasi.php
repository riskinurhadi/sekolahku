<?php
$page_title = 'Detail Informasi Akademik';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['siswa', 'guru', 'kepala_sekolah', 'developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$sekolah_id = $_SESSION['sekolah_id'] ?? null;

// Get informasi ID
$informasi_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$informasi_id) {
    header('Location: informasi_akademik.php');
    exit;
}

// Get informasi detail
$informasi = null;
$table_check = $conn->query("SHOW TABLES LIKE 'informasi_akademik'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT ia.*, u.nama_lengkap as pengirim_nama, u.email as pengirim_email
        FROM informasi_akademik ia
        JOIN users u ON ia.pengirim_id = u.id
        WHERE ia.id = ? AND ia.status = 'terkirim'");
    $stmt->bind_param("i", $informasi_id);
    $stmt->execute();
    $informasi = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Check if user has access to this informasi
    if ($informasi) {
        $has_access = false;
        
        // Check if target is semua or matches user role
        if ($informasi['target_role'] == 'semua' || $informasi['target_role'] == $user_role) {
            $has_access = true;
        }
        
        // Check if specifically targeted to this user (target_user_id tidak null)
        if ($informasi['target_user_id'] && $informasi['target_user_id'] == $user_id) {
            $has_access = true;
        }
        
        // Check sekolah_id if set
        if ($informasi['sekolah_id'] && $sekolah_id && $informasi['sekolah_id'] != $sekolah_id) {
            $has_access = false;
        }
        
        if (!$has_access) {
            $informasi = null;
        } else {
            // Mark as read
            $check_stmt = $conn->prepare("SELECT id FROM informasi_akademik_baca WHERE informasi_id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $informasi_id, $user_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if (!$exists) {
                $insert_stmt = $conn->prepare("INSERT INTO informasi_akademik_baca (informasi_id, user_id) VALUES (?, ?)");
                $insert_stmt->bind_param("ii", $informasi_id, $user_id);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
        }
    }
}

if (!$informasi) {
    header('Location: informasi_akademik.php');
    exit;
}

$conn->close();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-megaphone"></i> Detail Informasi</h2>
        <p>Informasi akademik detail</p>
    </div>
    <a href="informasi_akademik.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<!-- Detail Informasi -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="border-left: 4px solid <?php 
                echo $informasi['prioritas'] == 'sangat_penting' ? '#ef4444' : 
                    ($informasi['prioritas'] == 'penting' ? '#f59e0b' : '#6366f1'); 
            ?>;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-2">
                            <?php if ($informasi['prioritas'] == 'sangat_penting'): ?>
                                <span class="badge bg-danger me-2">Sangat Penting</span>
                            <?php elseif ($informasi['prioritas'] == 'penting'): ?>
                                <span class="badge bg-warning me-2">Penting</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($informasi['judul']); ?>
                        </h5>
                        <div class="text-muted small">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($informasi['pengirim_nama']); ?>
                            <span class="ms-3">
                                <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($informasi['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="informasi-content" style="line-height: 1.8; font-size: 1rem;">
                    <?php echo nl2br(htmlspecialchars($informasi['isi'])); ?>
                </div>
            </div>
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Diterbitkan pada <?php echo date('d F Y, H:i', strtotime($informasi['created_at'])); ?>
                    </small>
                    <a href="informasi_akademik.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

