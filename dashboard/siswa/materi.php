<?php
$page_title = 'Materi Pembelajaran';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get kelas siswa
$stmt = $conn->prepare("SELECT kelas_id FROM users WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa_info = $stmt->get_result()->fetch_assoc();
$kelas_id = $siswa_info['kelas_id'] ?? null;
$stmt->close();

// Get materi aktif (materi yang status aktif dan mata pelajarannya sesuai dengan sekolah)
$query = "SELECT m.*, mp.nama_pelajaran, mp.kode_pelajaran,
    (SELECT COUNT(*) FROM latihan WHERE materi_id = m.id AND status = 'aktif') as jumlah_latihan,
    (SELECT status FROM progress_materi_siswa WHERE materi_id = m.id AND siswa_id = ?) as progress_status,
    (SELECT progress FROM progress_materi_siswa WHERE materi_id = m.id AND siswa_id = ?) as progress_percent
    FROM materi_pelajaran m
    JOIN mata_pelajaran mp ON m.mata_pelajaran_id = mp.id
    WHERE m.status = 'aktif' AND mp.sekolah_id = ?
    ORDER BY m.urutan ASC, m.created_at DESC";
    
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $siswa_id, $siswa_id, $sekolah_id);
$stmt->execute();
$materi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Materi Pembelajaran</h2>
        <p class="text-muted mb-0">Pelajari materi dan kerjakan latihan</p>
    </div>
</div>

<?php if (empty($materi_list)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-journal-text text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
            <h5 class="mt-3 text-muted">Belum ada materi</h5>
            <p class="text-muted">Guru belum mengupload materi pembelajaran</p>
        </div>
    </div>
<?php else: ?>
    <div class="row">
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
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card shadow-sm h-100 hover-lift">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($materi['nama_pelajaran']); ?></span>
                                <h5 class="card-title mb-2"><?php echo htmlspecialchars($materi['judul']); ?></h5>
                                <?php if ($materi['deskripsi']): ?>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($materi['deskripsi'], 0, 100)); ?><?php echo strlen($materi['deskripsi']) > 100 ? '...' : ''; ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($materi['file_attachment']): ?>
                                <i class="bi bi-paperclip text-primary fs-4"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Progress</small>
                                <small class="text-muted"><?php echo $progress_percent; ?>%</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" style="width: <?php echo $progress_percent; ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <span class="badge bg-<?php echo $status_color; ?> mt-1">
                                    <?php echo ucfirst(str_replace('_', ' ', $progress_status)); ?>
                                </span>
                            </small>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-journal-check text-info"></i>
                                <small class="text-muted"><?php echo $materi['jumlah_latihan']; ?> Latihan</small>
                            </div>
                            <a href="detail_materi.php?id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-primary">
                                Baca Materi <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.hover-lift {
    transition: all 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
}
</style>

<?php require_once '../../includes/footer.php'; ?>

