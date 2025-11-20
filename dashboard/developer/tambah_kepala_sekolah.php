<?php
$page_title = 'Tambah Kepala Sekolah';
require_once '../../config/session.php';
requireRole(['developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'] ?? '';
    $sekolah_id = $_POST['sekolah_id'];
    
    // Check if username already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        $message = 'error:Username sudah digunakan!';
    } else {
        $checkStmt->close();
        
        // Check if sekolah already has kepala_sekolah
        $checkStmt = $conn->prepare("SELECT kepala_sekolah_id FROM sekolah WHERE id = ?");
        $checkStmt->bind_param("i", $sekolah_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $sekolah = $result->fetch_assoc();
        if ($sekolah && $sekolah['kepala_sekolah_id']) {
            $checkStmt->close();
            $message = 'error:Sekolah ini sudah memiliki kepala sekolah!';
        } else {
            $checkStmt->close();
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id) VALUES (?, ?, ?, ?, 'kepala_sekolah', ?)");
            $stmt->bind_param("ssssi", $username, $password, $nama_lengkap, $email, $sekolah_id);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                // Update sekolah dengan kepala_sekolah_id
                $stmt2 = $conn->prepare("UPDATE sekolah SET kepala_sekolah_id = ? WHERE id = ?");
                $stmt2->bind_param("ii", $user_id, $sekolah_id);
                $stmt2->execute();
                $stmt2->close();
                
                $message = 'success:Kepala sekolah berhasil ditambahkan!';
                header('Location: kepala_sekolah.php?success=1');
                exit;
            } else {
                $message = 'error:Gagal menambahkan kepala sekolah!';
            }
            $stmt->close();
        }
    }
}

// Get all schools without kepala_sekolah
$schools = $conn->query("SELECT * FROM sekolah WHERE kepala_sekolah_id IS NULL ORDER BY nama_sekolah ASC")->fetch_all(MYSQLI_ASSOC);

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
    <h2><i class="bi bi-plus-circle"></i> Tambah Kepala Sekolah</h2>
    <p>Isi form di bawah ini untuk menambahkan kepala sekolah baru</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sekolah_id" class="form-label">Sekolah <span class="text-danger">*</span></label>
                            <select class="form-select" id="sekolah_id" name="sekolah_id" required>
                                <option value="">Pilih Sekolah</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['nama_sekolah']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($schools)): ?>
                                <small class="text-danger">
                                    <i class="bi bi-exclamation-triangle"></i> Tidak ada sekolah yang tersedia atau semua sekolah sudah memiliki kepala sekolah.
                                </small>
                            <?php else: ?>
                                <small class="text-muted">Pilih sekolah untuk kepala sekolah ini</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="kepala_sekolah.php" class="btn btn-secondary">
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

