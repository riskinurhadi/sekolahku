<?php
$page_title = 'Tambah Sekolah';
require_once '../../config/session.php';
requireRole(['developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_sekolah = $_POST['nama_sekolah'];
    $alamat = $_POST['alamat'] ?? '';
    $telepon = $_POST['telepon'] ?? '';
    $email = $_POST['email'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO sekolah (nama_sekolah, alamat, telepon, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nama_sekolah, $alamat, $telepon, $email);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Sekolah berhasil ditambahkan!';
        $stmt->close();
        $conn->close();
        echo '<script>window.location.href = "sekolah.php?success=1";</script>';
        exit;
    } else {
        $message = 'error:Gagal menambahkan sekolah!';
    }
    $stmt->close();
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
    <h2><i class="bi bi-plus-circle"></i> Tambah Sekolah Baru</h2>
    <p>Isi form di bawah ini untuk menambahkan sekolah baru</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_sekolah" name="nama_sekolah" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telepon" class="form-label">Telepon</label>
                            <input type="text" class="form-control" id="telepon" name="telepon">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="sekolah.php" class="btn btn-secondary">
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

