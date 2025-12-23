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
// Check if table exists first
$table_check = $conn->query("SHOW TABLES LIKE 'materi_pelajaran'");
if ($table_check->num_rows > 0) {
    $query = "SELECT m.*, mp.nama_pelajaran, mp.kode_pelajaran,
        (SELECT COUNT(*) FROM latihan WHERE materi_id = m.id AND status = 'aktif') as jumlah_latihan,
        (SELECT status FROM progress_materi_siswa WHERE materi_id = m.id AND siswa_id = ?) as progress_status,
        (SELECT progress FROM progress_materi_siswa WHERE materi_id = m.id AND siswa_id = ?) as progress_percent
        FROM materi_pelajaran m
        JOIN mata_pelajaran mp ON m.mata_pelajaran_id = mp.id
        WHERE m.status = 'aktif' AND mp.sekolah_id = ?
        ORDER BY mp.nama_pelajaran ASC, m.urutan ASC, m.created_at DESC";
        
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $siswa_id, $siswa_id, $sekolah_id);
    $stmt->execute();
    $materi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $materi_list = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Materi Pembelajaran</h2>
        <p class="text-muted mb-0">Pelajari materi dan kerjakan latihan</p>
    </div>
</div>

<?php 
// Debug: Check if table exists
$table_exists = $conn->query("SHOW TABLES LIKE 'materi_pelajaran'")->num_rows > 0;
?>

<?php if (!$table_exists): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Perhatian:</strong> Tabel database untuk materi belum dibuat. Silakan import file <code>database/materi_schema.sql</code> terlebih dahulu.
    </div>
<?php elseif (empty($materi_list)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-journal-text text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
            <h5 class="mt-3 text-muted">Belum ada materi</h5>
            <p class="text-muted">Guru belum mengupload materi pembelajaran</p>
        </div>
    </div>
<?php else: ?>
    <?php
    // Group materi by mata pelajaran
    $materi_by_mapel = [];
    foreach ($materi_list as $materi) {
        $key = $materi['nama_pelajaran'];
        if (!isset($materi_by_mapel[$key])) {
            $materi_by_mapel[$key] = [
                'kode' => $materi['kode_pelajaran'] ?? '',
                'items' => []
            ];
        }
        $materi_by_mapel[$key]['items'][] = $materi;
    }
    ?>
    <div class="row">
        <?php foreach ($materi_by_mapel as $mapel => $data): ?>
            <div class="col-md-6 col-lg-6 mb-4">
                <div class="card shadow-sm h-100 hover-lift">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3"> 
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($mapel); ?></h5>
                                <?php if (!empty($data['kode'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($data['kode']); ?></small>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-primary">Total <?php echo count($data['items']); ?> materi</span>
                        </div>

                        <div class="materi-list">
                            <?php foreach ($data['items'] as $materi): ?>
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
                                <div class="materi-item mb-3 p-3 rounded border">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($materi['judul']); ?></h6>
                                            <?php if ($materi['deskripsi']): ?>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($materi['deskripsi'], 0, 90)); ?><?php echo strlen($materi['deskripsi']) > 90 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                            <div class="d-flex align-items-center gap-2 flex-wrap small text-muted">
                                                <span><i class="bi bi-journal-check text-info"></i> <?php echo $materi['jumlah_latihan']; ?> Latihan</span>
                                                <?php if ($materi['file_attachment']): ?>
                                                    <span class="text-primary"><i class="bi bi-paperclip"></i> File</span>
                                                <?php endif; ?>
                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst(str_replace('_', ' ', $progress_status)); ?></span>
                                            </div>
                                        </div>
                                        <a href="detail_materi.php?id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-outline-primary">Buka</a>
                                    </div>
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Progress</small>
                                            <small class="text-muted"><?php echo $progress_percent; ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" style="width: <?php echo $progress_percent; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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

.materi-item {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    transition: all 0.2s ease;
}

.materi-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}
</style>

<?php require_once '../../includes/footer.php'; ?>

