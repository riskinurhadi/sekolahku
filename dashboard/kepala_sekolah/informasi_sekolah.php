<?php
$page_title = 'Informasi Sekolah';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get sekolah data
$stmt = $conn->prepare("SELECT * FROM sekolah WHERE id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$sekolah_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sekolah_data) {
    $_SESSION['error_message'] = 'Data sekolah tidak ditemukan!';
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_sekolah = trim($_POST['nama_sekolah']);
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    $stmt = $conn->prepare("UPDATE sekolah SET nama_sekolah = ?, alamat = ?, telepon = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $nama_sekolah, $alamat, $telepon, $email, $sekolah_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Informasi sekolah berhasil diupdate!';
        $stmt->close();
        
        // Refresh data
        $stmt = $conn->prepare("SELECT * FROM sekolah WHERE id = ?");
        $stmt->bind_param("i", $sekolah_id);
        $stmt->execute();
        $sekolah_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $message = 'success:Informasi sekolah berhasil diupdate!';
    } else {
        $message = 'error:Gagal mengupdate informasi sekolah: ' . $conn->error;
        $stmt->close();
    }
} else {
    // If message from session (redirect)
    if (isset($_SESSION['success_message'])) {
        $message = 'success:' . $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
}

$conn->close();
?>

<?php if ($message): ?>
    <script>
        $(document).ready(function() {
            <?php 
            $msg = explode(':', $message);
            if ($msg[0] == 'success') {
                echo "Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: '" . addslashes($msg[1]) . "',
                    timer: 2000,
                    showConfirmButton: false
                });";
            } else {
                echo "Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '" . addslashes($msg[1]) . "',
                    confirmButtonText: 'OK'
                });";
            }
            ?>
        });
    </script>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-building"></i> Informasi Sekolah</h2>
    <p>Kelola informasi dan data sekolah Anda</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" id="informasiSekolahForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_sekolah" name="nama_sekolah" 
                                   value="<?php echo htmlspecialchars($sekolah_data['nama_sekolah'] ?? ''); ?>" 
                                   placeholder="Masukkan nama sekolah" required>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" 
                                      placeholder="Masukkan alamat sekolah"><?php echo htmlspecialchars($sekolah_data['alamat'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="telepon" class="form-label">Telepon</label>
                            <input type="text" class="form-control" id="telepon" name="telepon" 
                                   value="<?php echo htmlspecialchars($sekolah_data['telepon'] ?? ''); ?>" 
                                   placeholder="Masukkan nomor telepon">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($sekolah_data['email'] ?? ''); ?>" 
                                   placeholder="Masukkan email sekolah">
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
                
                <script>
                $(document).ready(function() {
                    $('#informasiSekolahForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        Swal.fire({
                            title: 'Simpan Perubahan?',
                            text: 'Apakah Anda yakin ingin menyimpan perubahan informasi sekolah ini?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Ya, Simpan!',
                            cancelButtonText: 'Batal'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    });
                });
                </script>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

