<?php
$page_title = 'Edit Materi';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: materi.php");
    exit;
}

// Get materi
$stmt = $conn->prepare("SELECT * FROM materi_pelajaran WHERE id = ? AND guru_id = ?");
$stmt->bind_param("ii", $id, $guru_id);
$stmt->execute();
$materi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$materi) {
    header("Location: materi.php");
    exit;
}

// Get mata pelajaran pertama dari guru
$stmt = $conn->prepare("SELECT * FROM mata_pelajaran WHERE guru_id = ? ORDER BY nama_pelajaran LIMIT 1");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$mata_pelajaran_result = $stmt->get_result();
$mata_pelajaran_first = $mata_pelajaran_result->fetch_assoc();
$stmt->close();

// Get semua kelas di sekolah untuk form pilihan kelas
$kelas_list = $conn->query("SELECT * FROM kelas WHERE sekolah_id = $sekolah_id ORDER BY tingkat ASC, nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);

// Create uploads/materi directory if not exists
$upload_dir = __DIR__ . '/../../uploads/materi/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submission BEFORE sending any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    if (empty($_POST['kelas_id'])) {
        $errors[] = 'Kelas harus dipilih';
    }
    if (empty(trim($_POST['judul']))) {
        $errors[] = 'Judul materi harus diisi';
    }
    
    if (empty($errors)) {
        $kelas_id = intval($_POST['kelas_id']);
        $judul = trim($_POST['judul']);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $konten = $_POST['konten'] ?? '';
        $status = 'aktif'; // Auto set ke aktif
        
        $file_attachment = $materi['file_attachment'];
        $file_name = $materi['file_name'];
        $file_size = $materi['file_size'];
        
        // Handle file upload (if new file uploaded)
        if (isset($_FILES['file_attachment']) && $_FILES['file_attachment']['error'] == 0) {
            $file = $_FILES['file_attachment'];
            $allowed_types = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'image/jpeg',
                'image/png',
                'image/jpg'
            ];
            $max_size = 50 * 1024 * 1024; // 50MB
            
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                // Delete old file
                if ($file_attachment && file_exists($upload_dir . $file_attachment)) {
                    unlink($upload_dir . $file_attachment);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'materi_' . time() . '_' . uniqid() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $file_attachment = $filename;
                    $file_name = $file['name'];
                    $file_size = $file['size'];
                } else {
                    $errors[] = 'Gagal mengupload file';
                }
            } else {
                $errors[] = 'Format file tidak didukung atau ukuran file terlalu besar (maks 50MB)';
            }
        }
        
        // Handle delete file
        if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1') {
            if ($file_attachment && file_exists($upload_dir . $file_attachment)) {
                unlink($upload_dir . $file_attachment);
            }
            $file_attachment = null;
            $file_name = null;
            $file_size = null;
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE materi_pelajaran SET kelas_id = ?, judul = ?, deskripsi = ?, konten = ?, file_attachment = ?, file_name = ?, file_size = ?, status = ? WHERE id = ?");
            $stmt->bind_param("isssssisi", $kelas_id, $judul, $deskripsi, $konten, $file_attachment, $file_name, $file_size, $status, $id);
            
            if ($stmt->execute()) {
                $message = 'success:Materi berhasil diupdate.';
                header("Location: materi.php?msg=" . urlencode($message));
                exit;
            } else {
                $message = 'error:Gagal mengupdate materi.';
            }
            $stmt->close();
        } else {
            $message = 'error:' . implode(', ', $errors);
        }
    } else {
        $message = 'error:' . implode(', ', $errors);
    }
}

require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Edit Materi</h2>
        <p class="text-muted mb-0">Edit materi pembelajaran</p>
    </div>
    <a href="materi.php" class="btn btn-outline-secondary">
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
        <form method="POST" enctype="multipart/form-data">
            <?php if ($mata_pelajaran_first): ?>
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($mata_pelajaran_first['nama_pelajaran']); ?>
                    <?php if ($mata_pelajaran_first['kode_pelajaran']): ?>
                        <span class="text-muted">(<?php echo htmlspecialchars($mata_pelajaran_first['kode_pelajaran']); ?>)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                <select name="kelas_id" class="form-select" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo ($materi['kelas_id'] == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?> (Kelas <?php echo $kelas['tingkat']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Pilih kelas yang akan menerima materi ini</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Judul Materi <span class="text-danger">*</span></label>
                <input type="text" name="judul" class="form-control" value="<?php echo htmlspecialchars($materi['judul']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3"><?php echo htmlspecialchars($materi['deskripsi']); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Konten Materi</label>
                <textarea name="konten" class="form-control" rows="10" id="konten"><?php echo htmlspecialchars($materi['konten']); ?></textarea>
                <small class="text-muted">Anda dapat menggunakan HTML untuk format teks</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">File Attachment</label>
                <?php if ($materi['file_attachment']): ?>
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-paperclip"></i> 
                            <a href="../../uploads/materi/<?php echo htmlspecialchars($materi['file_attachment']); ?>" target="_blank">
                                <?php echo htmlspecialchars($materi['file_name']); ?>
                            </a>
                            <small class="text-muted">(<?php echo number_format($materi['file_size'] / 1024, 2); ?> KB)</small>
                        </div>
                        <label class="form-check-label">
                            <input type="checkbox" name="delete_file" value="1" class="form-check-input"> Hapus file
                        </label>
                    </div>
                <?php endif; ?>
                <input type="file" name="file_attachment" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                <small class="text-muted">Format: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG (Maks 50MB)</small>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="materi.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Update Materi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('konten').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});
</script>

<?php require_once '../../includes/footer.php'; ?>

