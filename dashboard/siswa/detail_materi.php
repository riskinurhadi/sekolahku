<?php
$page_title = 'Detail Materi';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

$materi_id = intval($_GET['id'] ?? 0);
if (!$materi_id) {
    header("Location: materi.php");
    exit;
}

// Get materi
$stmt = $conn->prepare("SELECT m.*, mp.nama_pelajaran, mp.kode_pelajaran 
    FROM materi_pelajaran m
    JOIN mata_pelajaran mp ON m.mata_pelajaran_id = mp.id
    WHERE m.id = ? AND m.status = 'aktif' AND mp.sekolah_id = ?");
$stmt->bind_param("ii", $materi_id, $sekolah_id);
$stmt->execute();
$materi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$materi) {
    header("Location: materi.php");
    exit;
}

// Update progress (mark as selesai/sudah dibaca dengan progress 100%)
$stmt = $conn->prepare("INSERT INTO progress_materi_siswa (materi_id, siswa_id, status, progress, terakhir_dibaca) 
    VALUES (?, ?, 'selesai', 100, NOW())
    ON DUPLICATE KEY UPDATE status = 'selesai', progress = 100, terakhir_dibaca = NOW()");
$stmt->bind_param("ii", $materi_id, $siswa_id);
$stmt->execute();
$stmt->close();

// Get latihan aktif
$stmt = $conn->prepare("SELECT l.*, 
    (SELECT COUNT(*) FROM submisi_tugas WHERE latihan_id = l.id AND siswa_id = ?) as sudah_submit_tugas,
    (SELECT COUNT(*) FROM submisi_latihan_soal WHERE latihan_id = l.id AND siswa_id = ?) as sudah_kerjakan_soal
    FROM latihan l
    WHERE l.materi_id = ? AND l.status = 'aktif'
    ORDER BY l.created_at ASC");
$stmt->bind_param("iii", $siswa_id, $siswa_id, $materi_id);
$stmt->execute();
$latihan_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1"><?php echo htmlspecialchars($materi['judul']); ?></h2>
        <p class="text-muted mb-0">
            <span class="badge bg-primary"><?php echo htmlspecialchars($materi['nama_pelajaran']); ?></span>
            <?php if ($materi['kode_pelajaran']): ?>
                <span class="text-muted ms-2"><?php echo htmlspecialchars($materi['kode_pelajaran']); ?></span>
            <?php endif; ?>
        </p>
    </div>
    <a href="materi.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <?php if ($materi['deskripsi']): ?>
                    <div class="mb-4">
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($materi['deskripsi'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($materi['konten']): ?>
                    <div class="materi-konten">
                        <?php echo $materi['konten']; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-text" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-3">Tidak ada konten materi</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($materi['file_attachment']): ?>
                    <div class="mt-4 pt-4 border-top">
                        <h5 class="mb-3">File Attachment</h5>
                        <a href="../../uploads/materi/<?php echo htmlspecialchars($materi['file_attachment']); ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-download me-1"></i> Download <?php echo htmlspecialchars($materi['file_name']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>Latihan</h5>
            </div>
            <div class="card-body">
                <?php if (empty($latihan_list)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-clipboard-check" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">Belum ada latihan</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($latihan_list as $latihan): ?>
                        <?php
                        $sudah_kerjakan = ($latihan['jenis'] == 'tugas_file' && $latihan['sudah_submit_tugas'] > 0) || 
                                         ($latihan['jenis'] == 'soal' && $latihan['sudah_kerjakan_soal'] > 0);
                        $deadline_passed = $latihan['deadline'] && strtotime($latihan['deadline']) < time();
                        ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($latihan['judul']); ?></h6>
                                    <span class="badge bg-<?php echo $latihan['jenis'] == 'tugas_file' ? 'primary' : 'success'; ?>">
                                        <?php echo $latihan['jenis'] == 'tugas_file' ? 'Tugas File' : 'Soal'; ?>
                                    </span>
                                    <?php if ($sudah_kerjakan): ?>
                                        <span class="badge bg-success ms-1">Selesai</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($latihan['deskripsi']): ?>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($latihan['deskripsi'], 0, 80)); ?>...</p>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-star-fill text-warning"></i> Poin: <?php echo $latihan['poin']; ?>
                                </small>
                                <?php if ($latihan['deadline']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> Deadline: <?php echo date('d/m/Y H:i', strtotime($latihan['deadline'])); ?>
                                    </small>
                                    <?php if ($deadline_passed && !$sudah_kerjakan): ?>
                                        <br><span class="badge bg-danger">Terlambat</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <a href="kerjakan_latihan.php?latihan_id=<?php echo $latihan['id']; ?>" class="btn btn-sm btn-primary w-100">
                                <?php if ($sudah_kerjakan): ?>
                                    <i class="bi bi-eye me-1"></i> Lihat Hasil
                                <?php else: ?>
                                    <i class="bi bi-pencil me-1"></i> Kerjakan
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Mark as selesai when scrolled to bottom
let progressUpdated = false;
window.addEventListener('scroll', function() {
    if (progressUpdated) return;
    
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // If scrolled to 80% of page
    if (scrollTop + windowHeight >= documentHeight * 0.8) {
        // Update progress to selesai
        fetch('update_progress_materi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'materi_id=<?php echo $materi_id; ?>&progress=100&status=selesai'
        });
        progressUpdated = true;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

