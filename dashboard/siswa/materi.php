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
    $query = "SELECT m.*, mp.id as mapel_id, mp.nama_pelajaran, mp.kode_pelajaran,
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
        <h2 class="h4 mb-1 fw-bold text-primary">Materi Pembelajaran</h2>
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
        $key = $materi['mapel_id'];
        if (!isset($materi_by_mapel[$key])) {
            $materi_by_mapel[$key] = [
                'mapel_id' => $materi['mapel_id'],
                'nama' => $materi['nama_pelajaran'],
                'kode' => $materi['kode_pelajaran'] ?? '',
                'items' => []
            ];
        }
        $materi_by_mapel[$key]['items'][] = $materi;
    }
    
    // Warna gradient bergantian bernuansa biru
    $gradient_classes = ['mapel-card-blue-1', 'mapel-card-blue-2', 'mapel-card-blue-3', 'mapel-card-blue-4'];
    $gindex = 0;
    ?>
    <div class="row">
        <?php foreach ($materi_by_mapel as $mapelId => $data): ?>
            <?php
                $card_class = $gradient_classes[$gindex % count($gradient_classes)];
                $gindex++;
                $total_progress = 0; $count_items = count($data['items']);
                foreach ($data['items'] as $m) {
                    $total_progress += ($m['progress_percent'] ?? 0);
                }
                $avg_progress = $count_items > 0 ? round($total_progress / $count_items) : 0;
                $kode = $data['kode'] ?: '-';
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="mapel-card <?php echo $card_class; ?> shadow-sm h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small">Kode: <?php echo htmlspecialchars($kode); ?></div>
                            <h5 class="mb-1 fw-semibold text-dark"><?php echo htmlspecialchars($data['nama']); ?></h5>
                            <div class="text-muted small">Start: <?php echo date('d M Y'); ?></div>
                        </div>
                        <div class="icon-badge text-primary">
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
                        <a href="materi_mapel.php?mapel_id=<?php echo $data['mapel_id']; ?>" class="btn btn-sm btn-primary shadow-sm">
                            Lihat Bab <i class="bi bi-chevron-right ms-1"></i>
                        </a>
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
    border-radius: 20px;
    padding: 18px;
    color: #0f172a;
    position: relative;
    overflow: hidden;
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12);
}
.mapel-card::after {
    content: '';
    position: absolute;
    top: 10px;
    right: 10px;
    width: 10px;
    height: 90%;
    border-radius: 999px;
    background: rgba(0,0,0,0.12);
    opacity: 0.25;
}
.mapel-card .progress-thin {
    height: 6px;
    background: rgba(255,255,255,0.35);
    border-radius: 10px;
}
.mapel-card .progress-bar {
    background: rgba(255,255,255,0.95);
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.mapel-card .icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.38);
    color: #0f172a;
    font-size: 1.2rem;
    box-shadow: 0 8px 18px rgba(0,0,0,0.14);
}
.mapel-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
}

/* Gradient variants bernuansa biru (inspirasi stats card) */
.mapel-card-blue-1 {
    background: linear-gradient(135deg, #eef4ff 0%, #dfe9ff 100%);
}
.mapel-card-blue-2 {
    background: linear-gradient(135deg, #e8f7ff 0%, #cfefff 100%);
}
.mapel-card-blue-3 {
    background: linear-gradient(135deg, #ecf5ff 0%, #d9eafe 100%);
}
.mapel-card-blue-4 {
    background: linear-gradient(135deg, #eaf8ff 0%, #d5f1ff 100%);
}

/* Mentor-style list for bab/materi */
</style>

<?php require_once '../../includes/footer.php'; ?>

