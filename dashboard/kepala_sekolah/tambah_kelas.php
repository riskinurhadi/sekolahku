<?php
$page_title = 'Tambah Kelas';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_kelas = $_POST['nama_kelas'];
    $tingkat = $_POST['tingkat'];
    
    // Check if kelas already exists
    $checkStmt = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ? AND sekolah_id = ?");
    $checkStmt->bind_param("si", $nama_kelas, $sekolah_id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        $message = 'error:Kelas dengan nama ini sudah ada!';
    } else {
        $checkStmt->close();
        
        $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tingkat, sekolah_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama_kelas, $tingkat, $sekolah_id);
        
        if ($stmt->execute()) {
            $message = 'success:Kelas berhasil ditambahkan!';
            header('Location: kelas.php?success=1');
            exit;
        } else {
            $message = 'error:Gagal menambahkan kelas!';
        }
        $stmt->close();
    }
}

$conn->close();
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo strpos($message, 'success') === 0 ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php 
        $msg = explode(':', $message);
        echo htmlspecialchars($msg[1]); 
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-plus-circle"></i> Tambah Kelas</h2>
    <p>Isi form di bawah ini untuk menambahkan kelas baru</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="tingkat" class="form-label">Tingkat <span class="text-danger">*</span></label>
                        <select class="form-select" id="tingkat" name="tingkat" required>
                            <option value="">Pilih Tingkat</option>
                            <option value="10">Kelas 10</option>
                            <option value="11">Kelas 11</option>
                            <option value="12">Kelas 12</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_kelas" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" placeholder="Contoh: 10 D, 11 A, 12 B" required>
                        <small class="text-muted">Masukkan nama kelas lengkap (contoh: 10 D, 11 A, 12 B)</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="kelas.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

