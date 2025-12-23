<?php
$page_title = 'Tambah Materi';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Get mata pelajaran
$mata_pelajaran = $conn->query("SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_pelajaran")->fetch_all(MYSQLI_ASSOC);

// Create uploads/materi directory if not exists
$upload_dir = __DIR__ . '/../../uploads/materi/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    if (empty($_POST['mata_pelajaran_id'])) {
        $errors[] = 'Mata pelajaran harus dipilih';
    }
    if (empty(trim($_POST['judul']))) {
        $errors[] = 'Judul materi harus diisi';
    }
    
    if (empty($errors)) {
        $mata_pelajaran_id = intval($_POST['mata_pelajaran_id']);
        $judul = trim($_POST['judul']);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $konten = $_POST['konten'] ?? '';
        $urutan = intval($_POST['urutan'] ?? 0);
        $status = $_POST['status'] ?? 'draft';
        
        $file_attachment = null;
        $file_name = null;
        $file_size = null;
        
        // Handle file upload
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
        
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO materi_pelajaran (mata_pelajaran_id, guru_id, judul, deskripsi, konten, file_attachment, file_name, file_size, urutan, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssiis", $mata_pelajaran_id, $guru_id, $judul, $deskripsi, $konten, $file_attachment, $file_name, $file_size, $urutan, $status);
            
            if ($stmt->execute()) {
                $message = 'success:Materi berhasil ditambahkan.';
                header("Location: materi.php?msg=" . urlencode($message));
                exit;
            } else {
                // Delete file if insert failed
                if ($file_attachment && file_exists($filepath)) {
                    unlink($filepath);
                }
                $message = 'error:Gagal menyimpan materi.';
            }
            $stmt->close();
        } else {
            $message = 'error:' . implode(', ', $errors);
        }
    } else {
        $message = 'error:' . implode(', ', $errors);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Tambah Materi Baru</h2>
        <p class="text-muted mb-0">Upload materi pembelajaran untuk siswa</p>
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
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select name="mata_pelajaran_id" class="form-select" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($mata_pelajaran as $mp): ?>
                            <option value="<?php echo $mp['id']; ?>" <?php echo (isset($_POST['mata_pelajaran_id']) && $_POST['mata_pelajaran_id'] == $mp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mp['nama_pelajaran']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="aktif" <?php echo (isset($_POST['status']) && $_POST['status'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                        <option value="arsip" <?php echo (isset($_POST['status']) && $_POST['status'] == 'arsip') ? 'selected' : ''; ?>>Arsip</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Judul Materi <span class="text-danger">*</span></label>
                <input type="text" name="judul" class="form-control" value="<?php echo htmlspecialchars($_POST['judul'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['deskripsi'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Konten Materi</label>
                <textarea name="konten" class="form-control" rows="10" id="konten"><?php echo htmlspecialchars($_POST['konten'] ?? ''); ?></textarea>
                <small class="text-muted">Anda dapat menggunakan HTML untuk format teks</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">File Attachment</label>
                <input type="file" name="file_attachment" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                <small class="text-muted">Format: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG (Maks 50MB)</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Urutan</label>
                <input type="number" name="urutan" class="form-control" value="<?php echo htmlspecialchars($_POST['urutan'] ?? '0'); ?>" min="0">
                <small class="text-muted">Urutan tampil materi (0 = paling atas)</small>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="materi.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan Materi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Simple textarea editor (bisa diganti dengan rich text editor seperti TinyMCE)
document.getElementById('konten').addEventListener('input', function() {
    // Auto-resize textarea
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});
</script>

<?php require_once '../../includes/footer.php'; ?>

