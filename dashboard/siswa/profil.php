<?php
$page_title = 'Profil Saya';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'] ?? '';
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nama_lengkap, $email, $password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nama_lengkap, $email, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['user_nama'] = $nama_lengkap;
        $message = 'success:Profil berhasil diupdate!';
    } else {
        $message = 'error:Gagal mengupdate profil!';
    }
    $stmt->close();
}

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "showSuccess('" . addslashes($msg[1]) . "');";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0">Profil Saya</h2>
        <p class="text-muted">Kelola data pribadi Anda</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Informasi Profil</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="profilForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
                            <small class="text-muted">Username tidak dapat diubah</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password Baru</label>
                            <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Siswa" disabled>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informasi</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Anda dapat mengubah informasi profil Anda di sini. Pastikan data yang Anda masukkan akurat.</p>
                <hr>
                <p><strong>Catatan:</strong></p>
                <ul class="small">
                    <li>Username tidak dapat diubah</li>
                    <li>Password hanya diubah jika diisi</li>
                    <li>Pastikan email valid jika diisi</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

