<?php
$page_title = 'Edit Kelas';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    echo '<script>window.location.href = "kelas.php";</script>';
    exit;
}

// Get kelas data
$stmt = $conn->prepare("SELECT * FROM kelas WHERE id = ? AND sekolah_id = ?");
$stmt->bind_param("ii", $id, $sekolah_id);
$stmt->execute();
$kelas_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kelas_data) {
    echo '<script>window.location.href = "kelas.php";</script>';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_kelas = $_POST['nama_kelas'];
    $tingkat = $_POST['tingkat'];
    
    $stmt = $conn->prepare("UPDATE kelas SET nama_kelas = ?, tingkat = ? WHERE id = ? AND sekolah_id = ?");
    $stmt->bind_param("siii", $nama_kelas, $tingkat, $id, $sekolah_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Kelas berhasil diupdate!';
        $stmt->close();
        $conn->close();
        echo '<script>window.location.href = "kelas.php?success=1";</script>';
        exit;
    } else {
        $message = 'error:Gagal mengupdate kelas!';
    }
    $stmt->close();
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
                }).then(function() {
                    window.location.href = 'kelas.php';
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
    <h2><i class="bi bi-pencil"></i> Edit Kelas</h2>
    <p>Ubah data kelas di bawah ini</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" id="editKelasForm">
                    <div class="mb-3">
                        <label for="tingkat" class="form-label">Tingkat <span class="text-danger">*</span></label>
                        <select class="form-select" id="tingkat" name="tingkat" required>
                            <option value="">Pilih Tingkat</option>
                            <option value="10" <?php echo $kelas_data['tingkat'] == 10 ? 'selected' : ''; ?>>Kelas 10</option>
                            <option value="11" <?php echo $kelas_data['tingkat'] == 11 ? 'selected' : ''; ?>>Kelas 11</option>
                            <option value="12" <?php echo $kelas_data['tingkat'] == 12 ? 'selected' : ''; ?>>Kelas 12</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama_kelas" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_kelas" name="nama_kelas" value="<?php echo htmlspecialchars($kelas_data['nama_kelas']); ?>" placeholder="Contoh: 10 D, 11 A, 12 B" required>
                        <small class="text-muted">Masukkan nama kelas lengkap (contoh: 10 D, 11 A, 12 B)</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="kelas.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
                
                <script>
                $(document).ready(function() {
                    $('#editKelasForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        Swal.fire({
                            title: 'Update Data?',
                            text: 'Apakah Anda yakin ingin mengupdate data kelas ini?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Ya, Update!',
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

