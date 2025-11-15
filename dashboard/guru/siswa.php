<?php
$page_title = 'Kelola Siswa';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $nama_lengkap = $_POST['nama_lengkap'];
            $email = $_POST['email'] ?? '';
            $kelas_id = $_POST['kelas_id'] ?? null;
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id, kelas_id) VALUES (?, ?, ?, ?, 'siswa', ?, ?)");
            $stmt->bind_param("ssssii", $username, $password, $nama_lengkap, $email, $sekolah_id, $kelas_id);
            
            if ($stmt->execute()) {
                $message = 'success:Siswa berhasil ditambahkan!';
            } else {
                $message = 'error:Gagal menambahkan siswa!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'siswa' AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:Siswa berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus siswa!';
            }
            $stmt->close();
        }
    }
}

// Get all classes
$kelas = $conn->query("SELECT * FROM kelas WHERE sekolah_id = $sekolah_id ORDER BY tingkat ASC, nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);

// Get all students with kelas info
$students = $conn->query("SELECT u.*, k.nama_kelas 
    FROM users u 
    LEFT JOIN kelas k ON u.kelas_id = k.id 
    WHERE u.role = 'siswa' AND u.sekolah_id = $sekolah_id 
    ORDER BY k.tingkat ASC, k.nama_kelas ASC, u.nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "showSuccess('" . addslashes($msg[1]) . "');";
            echo "setTimeout(function(){ window.location.reload(); }, 1500);";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Kelola Siswa</h2>
    <p>Tambah dan kelola data siswa</p>
</div>

<!-- Students List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> Daftar Siswa</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="bi bi-plus-circle"></i> Tambah Siswa
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="studentsTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Email</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['nama_lengkap']); ?></td>
                                    <td>
                                        <?php if ($student['nama_kelas']): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($student['nama_kelas']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['email'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); confirmDelete('siswa').then(result => { if(result) this.submit(); }); return false;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addStudentForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentModalLabel">
                        <i class="bi bi-plus-circle"></i> Tambah Siswa Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_lengkap" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas_id" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($kelas as $k): ?>
                                    <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#studentsTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
