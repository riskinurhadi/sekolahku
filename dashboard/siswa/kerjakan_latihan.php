<?php
$page_title = 'Kerjakan Latihan';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

$latihan_id = intval($_GET['latihan_id'] ?? 0);
if (!$latihan_id) {
    header("Location: materi.php");
    exit;
}

// Get latihan
$stmt = $conn->prepare("SELECT l.*, m.judul as materi_judul, m.id as materi_id 
    FROM latihan l
    JOIN materi_pelajaran m ON l.materi_id = m.id
    WHERE l.id = ? AND l.status = 'aktif'");
$stmt->bind_param("i", $latihan_id);
$stmt->execute();
$latihan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$latihan) {
    header("Location: materi.php");
    exit;
}

// Create uploads/tugas directory if not exists
$upload_dir = __DIR__ . '/../../uploads/tugas/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$message = '';

// Handle tugas file submission
if ($latihan['jenis'] == 'tugas_file' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_tugas'])) {
    // Check if already submitted
    $check_stmt = $conn->prepare("SELECT id FROM submisi_tugas WHERE latihan_id = ? AND siswa_id = ?");
    $check_stmt->bind_param("ii", $latihan_id, $siswa_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        $message = 'error:Anda sudah mengumpulkan tugas ini.';
    } else {
        if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] == 0) {
            $file = $_FILES['file_tugas'];
            $allowed_types = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png',
                'image/jpg'
            ];
            $max_size = 50 * 1024 * 1024; // 50MB
            
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'tugas_' . $latihan_id . '_' . $siswa_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $catatan = trim($_POST['catatan'] ?? '');
                    $stmt = $conn->prepare("INSERT INTO submisi_tugas (latihan_id, siswa_id, file_path, file_name, file_size, catatan, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'menunggu')");
                    $stmt->bind_param("iissis", $latihan_id, $siswa_id, $filename, $file['name'], $file['size'], $catatan);
                    
                    if ($stmt->execute()) {
                        $message = 'success:Tugas berhasil dikumpulkan.';
                    } else {
                        unlink($filepath);
                        $message = 'error:Gagal menyimpan submisi.';
                    }
                    $stmt->close();
                } else {
                    $message = 'error:Gagal mengupload file.';
                }
            } else {
                $message = 'error:Format file tidak didukung atau ukuran file terlalu besar (maks 50MB).';
            }
        } else {
            $message = 'error:File harus diupload.';
        }
    }
}

// Get existing submission
if ($latihan['jenis'] == 'tugas_file') {
    $stmt = $conn->prepare("SELECT * FROM submisi_tugas WHERE latihan_id = ? AND siswa_id = ?");
    $stmt->bind_param("ii", $latihan_id, $siswa_id);
    $stmt->execute();
    $submisi = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM submisi_latihan_soal WHERE latihan_id = ? AND siswa_id = ?");
    $stmt->bind_param("ii", $latihan_id, $siswa_id);
    $stmt->execute();
    $submisi = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1"><?php echo htmlspecialchars($latihan['judul']); ?></h2>
        <p class="text-muted mb-0">Materi: <?php echo htmlspecialchars($latihan['materi_judul']); ?></p>
    </div>
    <a href="detail_materi.php?id=<?php echo $latihan['materi_id']; ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<?php if ($message): 
    $msg_type = strpos($message, 'success:') === 0 ? 'success' : 'error';
    $msg_text = str_replace(['success:', 'error:'], '', $message);
?>
    <div class="alert alert-<?php echo $msg_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg_text); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($latihan['deskripsi']): ?>
            <div class="mb-4">
                <p><?php echo nl2br(htmlspecialchars($latihan['deskripsi'])); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Jenis:</strong> 
                <span class="badge bg-<?php echo $latihan['jenis'] == 'tugas_file' ? 'primary' : 'success'; ?>">
                    <?php echo $latihan['jenis'] == 'tugas_file' ? 'Tugas Submit File' : 'Soal'; ?>
                </span>
            </div>
            <div class="col-md-6">
                <strong>Poin:</strong> <?php echo $latihan['poin']; ?>
            </div>
            <?php if ($latihan['deadline']): ?>
                <div class="col-md-6 mt-2">
                    <strong>Deadline:</strong> <?php echo date('d/m/Y H:i', strtotime($latihan['deadline'])); ?>
                    <?php if (strtotime($latihan['deadline']) < time()): ?>
                        <span class="badge bg-danger">Terlambat</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($latihan['jenis'] == 'tugas_file'): ?>
            <!-- Tugas File -->
            <?php if ($submisi): ?>
                <div class="alert alert-info">
                    <h5><i class="bi bi-check-circle me-2"></i>Anda sudah mengumpulkan tugas ini</h5>
                    <p class="mb-2"><strong>File:</strong> 
                        <a href="../../uploads/tugas/<?php echo htmlspecialchars($submisi['file_path']); ?>" target="_blank">
                            <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($submisi['file_name']); ?>
                        </a>
                    </p>
                    <p class="mb-2"><strong>Tanggal Submit:</strong> <?php echo date('d/m/Y H:i', strtotime($submisi['submitted_at'])); ?></p>
                    <p class="mb-2"><strong>Status:</strong> 
                        <span class="badge bg-<?php echo $submisi['status'] == 'selesai' ? 'success' : ($submisi['status'] == 'dinilai' ? 'info' : 'warning'); ?>">
                            <?php echo ucfirst($submisi['status']); ?>
                        </span>
                    </p>
                    <?php if ($submisi['nilai'] !== null): ?>
                        <p class="mb-0"><strong>Nilai:</strong> <span class="h5 text-primary"><?php echo number_format($submisi['nilai'], 2); ?></span></p>
                    <?php endif; ?>
                    <?php if ($submisi['feedback']): ?>
                        <div class="mt-2">
                            <strong>Feedback:</strong>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($submisi['feedback'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Upload File Tugas <span class="text-danger">*</span></label>
                        <input type="file" name="file_tugas" class="form-control" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <small class="text-muted">Format: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Maks 50MB)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Catatan (Opsional)</label>
                        <textarea name="catatan" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" name="submit_tugas" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Kumpulkan Tugas
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <!-- Soal -->
            <?php if ($submisi && $submisi['status'] == 'selesai'): ?>
                <div class="alert alert-success">
                    <h5><i class="bi bi-check-circle me-2"></i>Anda sudah menyelesaikan latihan ini</h5>
                    <p class="mb-2"><strong>Nilai:</strong> <span class="h4 text-primary"><?php echo number_format($submisi['nilai'], 2); ?></span></p>
                    <p class="mb-2"><strong>Soal Benar:</strong> <?php echo $submisi['soal_benar']; ?> / <?php echo $submisi['total_soal']; ?></p>
                    <p class="mb-2"><strong>Poin:</strong> <?php echo number_format($submisi['poin_diperoleh'], 2); ?> / <?php echo number_format($submisi['total_poin'], 2); ?></p>
                    <p class="mb-0"><strong>Waktu Selesai:</strong> <?php echo date('d/m/Y H:i', strtotime($submisi['waktu_selesai'])); ?></p>
                </div>
                <a href="hasil_latihan_detail.php?id=<?php echo $submisi['id']; ?>" class="btn btn-info">
                    <i class="bi bi-eye me-1"></i> Lihat Detail Jawaban
                </a>
            <?php else: ?>
                <?php
                // Get soal for this latihan
                if ($latihan['random_soal']) {
                    // Random soal
                    $jumlah_soal = $latihan['jumlah_soal'] ?? 10;
                    $stmt = $conn->prepare("SELECT ls.soal_id FROM latihan_soal ls WHERE ls.latihan_id = ? ORDER BY RAND() LIMIT ?");
                    $stmt->bind_param("ii", $latihan_id, $jumlah_soal);
                } else {
                    // Urutan tetap
                    $stmt = $conn->prepare("SELECT ls.soal_id FROM latihan_soal ls WHERE ls.latihan_id = ? ORDER BY ls.urutan ASC");
                    $stmt->bind_param("i", $latihan_id);
                }
                $stmt->execute();
                $soal_ids_result = $stmt->get_result();
                $soal_ids = [];
                while ($row = $soal_ids_result->fetch_assoc()) {
                    $soal_ids[] = $row['soal_id'];
                }
                $stmt->close();
                
                if (empty($soal_ids)) {
                    echo '<div class="alert alert-warning">Belum ada soal untuk latihan ini.</div>';
                } else {
                    // Redirect to kerjakan_soal.php with latihan context
                    header("Location: kerjakan_soal.php?latihan_id=" . $latihan_id);
                    exit;
                }
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

