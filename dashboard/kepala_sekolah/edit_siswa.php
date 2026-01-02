<?php
$page_title = 'Edit Siswa';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    echo '<script>window.location.href = "siswa.php";</script>';
    exit;
}

// Get student data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'siswa' AND sekolah_id = ?");
$stmt->bind_param("ii", $id, $sekolah_id);
$stmt->execute();
$student_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student_data) {
    echo '<script>window.location.href = "siswa.php";</script>';
    exit;
}

// Get all classes
$kelas = $conn->query("SELECT * FROM kelas WHERE sekolah_id = $sekolah_id ORDER BY tingkat ASC, nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'] ?? '';
    $kelas_id = $_POST['kelas_id'] ?? null;
    
    // Check if username already exists (excluding current student)
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $checkStmt->bind_param("si", $username, $id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        $message = 'error:Username sudah digunakan!';
    } else {
        $checkStmt->close();
        
        // Update password only if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, nama_lengkap = ?, email = ?, kelas_id = ? WHERE id = ? AND role = 'siswa' AND sekolah_id = ?");
            $stmt->bind_param("ssssiii", $username, $password, $nama_lengkap, $email, $kelas_id, $id, $sekolah_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, nama_lengkap = ?, email = ?, kelas_id = ? WHERE id = ? AND role = 'siswa' AND sekolah_id = ?");
            $stmt->bind_param("sssiii", $username, $nama_lengkap, $email, $kelas_id, $id, $sekolah_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Siswa berhasil diupdate!';
            $stmt->close();
            $conn->close();
            echo '<script>window.location.href = "siswa.php?success=1";</script>';
            exit;
        } else {
            $message = 'error:Gagal mengupdate siswa!';
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
                    window.location.href = 'siswa.php';
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
    <h2><i class="bi bi-pencil"></i> Edit Siswa</h2>
    <p>Ubah data siswa di bawah ini</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" id="editSiswaForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($student_data['username']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah password">
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($student_data['nama_lengkap']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="kelas_id" class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" id="kelas_id" name="kelas_id" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($kelas as $k): ?>
                                    <option value="<?php echo $k['id']; ?>" <?php echo ($student_data['kelas_id'] == $k['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($k['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student_data['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="siswa.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
                
                <script>
                $(document).ready(function() {
                    $('#editSiswaForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        Swal.fire({
                            title: 'Update Data?',
                            text: 'Apakah Anda yakin ingin mengupdate data siswa ini?',
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

