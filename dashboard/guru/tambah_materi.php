<?php
$page_title = 'Tambah Materi';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../config/database.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get mata pelajaran pertama dari guru (karena guru sudah di role mata pelajarannya masing-masing)
$stmt = $conn->prepare("SELECT * FROM mata_pelajaran WHERE guru_id = ? ORDER BY nama_pelajaran LIMIT 1");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$mata_pelajaran_result = $stmt->get_result();
$mata_pelajaran_first = $mata_pelajaran_result->fetch_assoc();
$stmt->close();

if (!$mata_pelajaran_first) {
    $message = 'error:Anda belum memiliki mata pelajaran. Silakan hubungi admin untuk menambahkan mata pelajaran.';
}

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
    
    if (!$mata_pelajaran_first) {
        $errors[] = 'Anda belum memiliki mata pelajaran';
    }
    if (empty($_POST['kelas_id'])) {
        $errors[] = 'Kelas harus dipilih';
    }
    if (empty(trim($_POST['judul']))) {
        $errors[] = 'Judul materi harus diisi';
    }
    
    if (empty($errors)) {
        $mata_pelajaran_id = $mata_pelajaran_first['id'];
        $kelas_id = intval($_POST['kelas_id']);
        $judul = trim($_POST['judul']);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $konten = $_POST['konten'] ?? '';
        $status = 'aktif'; // Auto set ke aktif
        
        $file_attachment = null;
        $file_name = null;
        $file_size = null;
        $filepath = null;
        
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
            $stmt = $conn->prepare("INSERT INTO materi_pelajaran (mata_pelajaran_id, guru_id, kelas_id, judul, deskripsi, konten, file_attachment, file_name, file_size, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisssssis", $mata_pelajaran_id, $guru_id, $kelas_id, $judul, $deskripsi, $konten, $file_attachment, $file_name, $file_size, $status);
            
            if ($stmt->execute()) {
                $message = 'success:Materi berhasil ditambahkan.';
                header("Location: materi.php?msg=" . urlencode($message));
                exit;
            } else {
                // Delete file if insert failed
                if ($file_attachment && $filepath && file_exists($filepath)) {
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

// Setelah selesai proses POST, baru tampilkan header
require_once '../../includes/header.php';
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
                        <option value="<?php echo $kelas['id']; ?>" <?php echo (isset($_POST['kelas_id']) && $_POST['kelas_id'] == $kelas['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?> (Kelas <?php echo $kelas['tingkat']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Pilih kelas yang akan menerima materi ini</small>
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

