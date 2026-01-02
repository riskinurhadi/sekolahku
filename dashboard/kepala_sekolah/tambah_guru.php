<?php
$page_title = 'Tambah Guru & Staf';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'guru';
    $spesialisasi = $_POST['spesialisasi'] ?? '';
    
    // Validasi role hanya boleh guru atau akademik
    if (!in_array($role, ['guru', 'akademik'])) {
        $role = 'guru';
    }
    
    // Jika role adalah guru, spesialisasi wajib diisi
    if ($role == 'guru' && empty($spesialisasi)) {
        $message = 'error:Spesialisasi wajib diisi untuk guru!';
    } else {
        // Check if username already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            $message = 'error:Username sudah digunakan!';
        } else {
            $checkStmt->close();
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id, spesialisasi) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssis", $username, $password, $nama_lengkap, $email, $role, $sekolah_id, $spesialisasi);
            
            if ($stmt->execute()) {
                $role_text = $role == 'akademik' ? 'Akademik' : 'Guru';
                $_SESSION['success_message'] = $role_text . ' berhasil ditambahkan!';
                $stmt->close();
                $conn->close();
                echo '<script>window.location.href = "guru.php?success=1";</script>';
                exit;
            } else {
                $message = 'error:Gagal menambahkan user!';
            }
            $stmt->close();
        }
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
                    window.location.href = 'guru.php';
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
    <h2><i class="bi bi-plus-circle"></i> Tambah Guru/Staf Baru</h2>
    <p>Isi form di bawah ini untuk menambahkan guru atau staf akademik baru</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" id="tambahGuruForm">
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
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required onchange="toggleSpesialisasi()">
                                <option value="guru">Guru</option>
                                <option value="akademik">Akademik</option>
                            </select>
                            <small class="text-muted">Pilih role untuk user ini</small>
                        </div>
                        <div class="col-md-6 mb-3" id="spesialisasiField">
                            <label for="spesialisasi" class="form-label">Spesialisasi <span class="text-danger">*</span></label>
                            <select class="form-select" id="spesialisasi" name="spesialisasi">
                                <option value="">Pilih Spesialisasi</option>
                                <option value="Guru Matematika">Guru Matematika</option>
                                <option value="Guru Bahasa Indonesia">Guru Bahasa Indonesia</option>
                                <option value="Guru Bahasa Inggris">Guru Bahasa Inggris</option>
                                <option value="Guru Fisika">Guru Fisika</option>
                                <option value="Guru Kimia">Guru Kimia</option>
                                <option value="Guru Biologi">Guru Biologi</option>
                                <option value="Guru Sejarah">Guru Sejarah</option>
                                <option value="Guru Geografi">Guru Geografi</option>
                                <option value="Guru Ekonomi">Guru Ekonomi</option>
                                <option value="Guru Sosiologi">Guru Sosiologi</option>
                                <option value="Guru Pendidikan Agama">Guru Pendidikan Agama</option>
                                <option value="Guru Pendidikan Jasmani">Guru Pendidikan Jasmani</option>
                                <option value="Guru Seni Budaya">Guru Seni Budaya</option>
                                <option value="Guru Teknologi Informasi">Guru Teknologi Informasi</option>
                                <option value="Guru Lainnya">Guru Lainnya</option>
                            </select>
                            <small class="text-muted">Pilih spesialisasi mata pelajaran</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="guru.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </div>
                </form>
                
                <script>
                $(document).ready(function() {
                    $('#tambahGuruForm').on('submit', function(e) {
                        e.preventDefault();
                        
                        // Validasi spesialisasi jika role adalah guru
                        const role = $('#role').val();
                        const spesialisasi = $('#spesialisasi').val();
                        
                        if (role === 'guru' && !spesialisasi) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Spesialisasi wajib diisi untuk guru!'
                            });
                            return false;
                        }
                        
                        Swal.fire({
                            title: 'Simpan Data?',
                            text: 'Apakah Anda yakin ingin menambahkan ' + (role === 'guru' ? 'guru' : 'staf akademik') + ' baru?',
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

<script>
function toggleSpesialisasi() {
    var role = document.getElementById('role').value;
    var spesialisasiField = document.getElementById('spesialisasiField');
    var spesialisasiSelect = document.getElementById('spesialisasi');
    
    if (role === 'guru') {
        spesialisasiField.style.display = 'block';
        spesialisasiSelect.required = true;
    } else {
        spesialisasiField.style.display = 'none';
        spesialisasiSelect.required = false;
        spesialisasiSelect.value = '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleSpesialisasi();
});
</script>

<?php require_once '../../includes/footer.php'; ?>

