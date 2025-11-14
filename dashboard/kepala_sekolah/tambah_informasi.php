<?php
$page_title = 'Tambah Informasi Akademik';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru', 'kepala_sekolah']);

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$sekolah_id = $_SESSION['sekolah_id'] ?? null;

// Get sekolah_id if not set
if (!$sekolah_id) {
    $stmt = $conn->prepare("SELECT sekolah_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sekolah_id = $user['sekolah_id'] ?? null;
}

$message = '';

// Handle form submission BEFORE header output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $judul = trim($_POST['judul'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $target_role = $_POST['target_role'] ?? 'semua';
    $target_user_id = !empty($_POST['target_user_id']) ? intval($_POST['target_user_id']) : null;
    $prioritas = $_POST['prioritas'] ?? 'normal';
    $status = $_POST['status'] ?? 'draft';
    
    // Jika target_role adalah spesifik, set target_role menjadi 'semua' dan gunakan target_user_id
    // (karena target_role adalah ENUM yang tidak bisa NULL, kita gunakan 'semua' sebagai default)
    if ($target_role == 'spesifik') {
        $target_role = 'semua'; // Default, tapi akan di-filter berdasarkan target_user_id
    }
    
    // Validasi
    if (empty($judul)) {
        $conn->close();
        header('Location: tambah_informasi.php?error=1&msg=' . urlencode('Judul tidak boleh kosong!'));
        exit;
    } elseif (empty($isi)) {
        $conn->close();
        header('Location: tambah_informasi.php?error=1&msg=' . urlencode('Isi informasi tidak boleh kosong!'));
        exit;
    } elseif ($_POST['target_role'] == 'spesifik' && !$target_user_id) {
        $conn->close();
        header('Location: tambah_informasi.php?error=1&msg=' . urlencode('Silakan pilih user target!'));
        exit;
    } else {
        // Insert informasi akademik
        $stmt = $conn->prepare("INSERT INTO informasi_akademik 
            (judul, isi, pengirim_id, sekolah_id, target_role, target_user_id, prioritas, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisssss", $judul, $isi, $user_id, $sekolah_id, $target_role, $target_user_id, $prioritas, $status);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header('Location: informasi_akademik.php?success=1&msg=' . urlencode('Informasi akademik berhasil ditambahkan!'));
            exit;
        } else {
            $stmt->close();
            $conn->close();
            header('Location: tambah_informasi.php?error=1&msg=' . urlencode('Gagal menambahkan informasi akademik!'));
            exit;
        }
    }
}

// Check for error/success from redirect
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $msg = isset($_GET['msg']) ? $_GET['msg'] : 'Terjadi kesalahan!';
    $message = 'error:' . $msg;
}
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $msg = isset($_GET['msg']) ? $_GET['msg'] : 'Operasi berhasil!';
    $message = 'success:' . $msg;
}

// Now include header AFTER handling POST
require_once '../../includes/header.php';

// Get list of users for target selection (if kepala_sekolah)
$users_list = [];
if ($user_role == 'kepala_sekolah' && $sekolah_id) {
    $table_check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT id, nama_lengkap, role FROM users WHERE sekolah_id = ? AND role IN ('siswa', 'guru') ORDER BY role, nama_lengkap");
        $stmt->bind_param("i", $sekolah_id);
        $stmt->execute();
        $users_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
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

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-plus-circle"></i> Tambah Informasi Akademik</h2>
        <p>Buat dan kirim informasi akademik baru</p>
    </div>
    <a href="informasi_akademik.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<!-- Form Tambah Informasi -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Form Informasi Akademik</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="tambahInformasiForm">
                    <input type="hidden" name="action" value="tambah">
                    
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" required 
                               placeholder="Masukkan judul informasi" maxlength="200">
                        <small class="text-muted">Maksimal 200 karakter</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="isi" class="form-label">Isi Informasi <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="isi" name="isi" rows="8" required 
                                  placeholder="Masukkan isi informasi akademik"></textarea>
                        <small class="text-muted">Tuliskan informasi yang ingin disampaikan</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="target_role" class="form-label">Target Penerima <span class="text-danger">*</span></label>
                            <select class="form-select" id="target_role" name="target_role" required>
                                <option value="semua">Semua</option>
                                <option value="siswa">Siswa</option>
                                <option value="guru">Guru</option>
                                <?php if ($user_role == 'kepala_sekolah'): ?>
                                    <option value="kepala_sekolah">Kepala Sekolah</option>
                                <?php endif; ?>
                                <?php if ($user_role == 'kepala_sekolah' && !empty($users_list)): ?>
                                    <option value="spesifik">User Spesifik</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="target_user_wrapper" style="display: none;">
                            <label for="target_user_id" class="form-label">Pilih User</label>
                            <select class="form-select" id="target_user_id" name="target_user_id">
                                <option value="">Pilih User</option>
                                <?php foreach ($users_list as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['nama_lengkap']); ?> 
                                        (<?php echo ucfirst($u['role']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="prioritas" class="form-label">Prioritas</label>
                            <select class="form-select" id="prioritas" name="prioritas">
                                <option value="normal">Normal</option>
                                <option value="penting">Penting</option>
                                <option value="sangat_penting">Sangat Penting</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="draft">Draft</option>
                                <option value="terkirim" selected>Terkirim</option>
                            </select>
                            <small class="text-muted">Pilih "Terkirim" untuk langsung mengirim informasi</small>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="informasi_akademik.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Kirim Informasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Show/hide target user select based on target_role
    $('#target_role').on('change', function() {
        if ($(this).val() === 'spesifik') {
            $('#target_user_wrapper').show();
            $('#target_user_id').prop('required', true);
        } else {
            $('#target_user_wrapper').hide();
            $('#target_user_id').prop('required', false);
            $('#target_user_id').val('');
        }
    });
    
    // Form validation
    $('#tambahInformasiForm').on('submit', function(e) {
        const judul = $('#judul').val().trim();
        const isi = $('#isi').val().trim();
        
        if (!judul || !isi) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Judul dan isi informasi harus diisi!'
            });
            return false;
        }
        
        if (judul.length > 200) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Judul maksimal 200 karakter!'
            });
            return false;
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

