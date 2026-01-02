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
            $_SESSION['success_message'] = 'Kelas berhasil ditambahkan!';
            $stmt->close();
            $conn->close();
            echo '<script>window.location.href = "kelas.php?success=1";</script>';
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
    <h2><i class="bi bi-plus-circle"></i> Tambah Kelas</h2>
    <p>Isi form di bawah ini untuk menambahkan kelas baru</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" id="tambahKelasForm">
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
                
                <script>
                $(document).ready(function() {
                    $('#tambahKelasForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        Swal.fire({
                            title: 'Simpan Data?',
                            text: 'Apakah Anda yakin ingin menambahkan kelas baru?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
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

