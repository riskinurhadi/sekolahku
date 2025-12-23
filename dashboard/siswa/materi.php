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
    
    // Warna gradient bergantian untuk card
    $gradient_classes = ['mapel-card-purple', 'mapel-card-orange', 'mapel-card-blue', 'mapel-card-teal'];
    $gindex = 0;
    ?>
    <div class="row">
        <?php foreach ($materi_by_mapel as $mapel => $data): ?>
            <?php
                $card_class = $gradient_classes[$gindex % count($gradient_classes)];
                $gindex++;
                $total_progress = 0; $count_items = count($data['items']);
                foreach ($data['items'] as $m) {
                    $total_progress += ($m['progress_percent'] ?? 0);
                }
                $avg_progress = $count_items > 0 ? round($total_progress / $count_items) : 0;
                $kode = $data['kode'] ?: '-';
                $collapse_id = 'mapelCollapse' . $gindex;
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="mapel-card <?php echo $card_class; ?> shadow-sm h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small">Kode: <?php echo htmlspecialchars($kode); ?></div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($mapel); ?></h5>
                            <div class="text-muted small">Start: <?php echo date('d M Y'); ?></div>
                        </div>
                        <div class="icon-badge">
                            <i class="bi bi-journal-text"></i>
                        </div>
                    </div>
                    <p class="mb-3 text-muted small">Total <?php echo $count_items; ?> materi tersedia untuk mata pelajaran ini.</p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small>Progress</small>
                            <small><?php echo $avg_progress; ?>%</small>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $avg_progress; ?>%"></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="false" aria-controls="<?php echo $collapse_id; ?>">
                            Lihat Bab <i class="bi bi-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Daftar bab/materi per mapel -->
            <div class="col-12">
                <div class="collapse" id="<?php echo $collapse_id; ?>">
                    <div class="mentor-list-card shadow-sm mb-4">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h6 class="mb-0">Bab / Materi: <?php echo htmlspecialchars($mapel); ?></h6>
                            <small class="text-muted">Total <?php echo $count_items; ?> materi</small>
                        </div>
                        <div class="mentor-list-body">
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
                                <div class="mentor-row d-flex align-items-center">
                                    <div class="mentor-avatar">
                                        <i class="bi bi-book"></i>
                                    </div>
                                    <div class="mentor-info flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($materi['judul']); ?></h6>
                                            <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst(str_replace('_', ' ', $progress_status)); ?></span>
                                        </div>
                                        <?php if ($materi['deskripsi']): ?>
                                            <div class="text-muted small"><?php echo htmlspecialchars(substr($materi['deskripsi'], 0, 80)); ?><?php echo strlen($materi['deskripsi']) > 80 ? '...' : ''; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mentor-meta text-center">
                                        <div class="small text-muted"><?php echo $materi['jumlah_latihan']; ?> Latihan</div>
                                        <div class="small text-muted"><?php echo $progress_percent; ?>% Progress</div>
                                    </div>
                                    <div class="mentor-actions ms-3">
                                        <a href="detail_materi.php?id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-primary">Buka</a>
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

/* Mapel cards */
.mapel-card {
    border: 0;
    border-radius: 16px;
    padding: 18px;
    color: #0f172a;
    position: relative;
    overflow: hidden;
}
.mapel-card .progress-thin {
    height: 6px;
    background: rgba(255,255,255,0.4);
    border-radius: 10px;
}
.mapel-card .progress-bar {
    background: rgba(255,255,255,0.9);
    border-radius: 10px;
}
.mapel-card .icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.3);
    color: #0f172a;
    font-size: 1.2rem;
}
.mapel-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 28px rgba(0,0,0,0.12);
}

/* Gradient variants */
.mapel-card-purple {
    background: linear-gradient(135deg, #f1e8ff 0%, #d6c5ff 100%);
}
.mapel-card-orange {
    background: linear-gradient(135deg, #ffe8d6 0%, #ffd1a1 100%);
}
.mapel-card-blue {
    background: linear-gradient(135deg, #e6f4ff 0%, #c3e4ff 100%);
}
.mapel-card-teal {
    background: linear-gradient(135deg, #e6fffb 0%, #c3f7ef 100%);
}

/* Mentor-style list for bab/materi */
.mentor-list-card {
    background: #f7fbff;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
}
.mentor-list-body {
    padding: 0 12px 12px 12px;
}
.mentor-row {
    background: #ffffff;
    border-radius: 12px;
    padding: 12px;
    margin-top: 10px;
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
.mentor-meta {
    min-width: 110px;
}
.mentor-actions .btn {
    min-width: 70px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>

