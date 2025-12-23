<?php
$page_title = 'Bab Materi';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

$mapel_id = intval($_GET['mapel_id'] ?? 0);
if (!$mapel_id) {
    header("Location: materi.php");
    exit;
}

// Ambil info mapel
$stmt = $conn->prepare("SELECT * FROM mata_pelajaran WHERE id = ? AND sekolah_id = ?");
$stmt->bind_param("ii", $mapel_id, $sekolah_id);
$stmt->execute();
$mapel = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mapel) {
    header("Location: materi.php");
    exit;
}

// Ambil materi per mapel
$stmt = $conn->prepare("SELECT m.*, 
    (SELECT COUNT(*) FROM latihan WHERE materi_id = m.id AND status = 'aktif') as jumlah_latihan,
    (SELECT status FROM progress_materi_siswa WHERE materi_id = m.id AND siswa_id = ?) as progress_status,
    (SELECT progress FROM progress_materi_siswa WHERE materi_id = m.id AND siswa_id = ?) as progress_percent
    FROM materi_pelajaran m
    WHERE m.mata_pelajaran_id = ? AND m.status = 'aktif'
    ORDER BY m.urutan ASC, m.created_at DESC");
$stmt->bind_param("iii", $siswa_id, $siswa_id, $mapel_id);
$stmt->execute();
$materi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Bab / Materi: <?php echo htmlspecialchars($mapel['nama_pelajaran']); ?></h2>
        <p class="text-muted mb-0">Kode: <?php echo htmlspecialchars($mapel['kode_pelajaran'] ?? '-'); ?></p>
    </div>
    <a href="materi.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<?php if (empty($materi_list)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-journal-text text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
            <h5 class="mt-3 text-muted">Belum ada materi</h5>
            <p class="text-muted">Guru belum mengupload materi untuk mata pelajaran ini</p>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <?php foreach ($materi_list as $materi): ?>
                <?php
                $progress_status = $materi['progress_status'] ?? 'belum_dibaca';
                $progress_percent = $materi['progress_percent'] ?? 0;
                $status_colors = [
                    'belum_dibaca' => 'secondary',
                    'sedang_dibaca' => 'warning',
                    'selesai' => 'success'
                ];
                $status_color = $status_colors[$progress_status] ?? 'secondary';
                ?>
                <div class="mentor-row d-flex align-items-center mb-3">
                    <div class="mentor-avatar">
                        <i class="bi bi-book"></i>
                    </div>
                    <div class="mentor-info flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h6 class="mb-0"><?php echo htmlspecialchars($materi['judul']); ?></h6>
                            <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst(str_replace('_', ' ', $progress_status)); ?></span>
                        </div>
                        <?php if ($materi['deskripsi']): ?>
                            <div class="text-muted small"><?php echo htmlspecialchars(substr($materi['deskripsi'], 0, 120)); ?><?php echo strlen($materi['deskripsi']) > 120 ? '...' : ''; ?></div>
                        <?php endif; ?>
                        <div class="d-flex align-items-center gap-3 small text-muted mt-1">
                            <span><i class="bi bi-journal-check text-info"></i> <?php echo $materi['jumlah_latihan']; ?> Latihan</span>
                            <span><?php echo $progress_percent; ?>% Progress</span>
                        </div>
                    </div>
                    <div class="mentor-actions ms-3">
                        <a href="detail_materi.php?id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-primary">Buka</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.mentor-row {
    background: #ffffff;
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
.mentor-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #eef2ff;
    color: #4338ca;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-right: 12px;
}
.mentor-info h6 {
    font-size: 15px;
    font-weight: 600;
}
.mentor-actions .btn {
    min-width: 70px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>

