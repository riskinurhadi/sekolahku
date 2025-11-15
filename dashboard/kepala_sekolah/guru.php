<?php
$page_title = 'Kelola Guru';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
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
            $role = $_POST['role'] ?? 'guru';
            
            // Validasi role hanya boleh guru atau akademik
            if (!in_array($role, ['guru', 'akademik'])) {
                $role = 'guru';
            }
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $username, $password, $nama_lengkap, $email, $role, $sekolah_id);
            
            if ($stmt->execute()) {
                $role_text = $role == 'akademik' ? 'Akademik' : 'Guru';
                $message = 'success:' . $role_text . ' berhasil ditambahkan!';
            } else {
                $message = 'error:Gagal menambahkan user!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('guru', 'akademik') AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:User berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus user!';
            }
            $stmt->close();
        }
    }
}

// Get all teachers and akademik staff
$stmt = $conn->prepare("SELECT * FROM users WHERE role IN ('guru', 'akademik') AND sekolah_id = ? ORDER BY role ASC, created_at DESC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
    <h2>Kelola Guru & Staf</h2>
    <p>Tambah dan kelola data guru dan staf akademik</p>
</div>

<!-- Teachers List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-workspace"></i> Daftar Guru</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="bi bi-plus-circle"></i> Tambah Guru
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="teachersTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($teacher['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($teacher['nama_lengkap']); ?></td>
                                    <td>
                                        <?php
                                        $role_badge = [
                                            'guru' => 'bg-primary',
                                            'akademik' => 'bg-info'
                                        ];
                                        $role_text = [
                                            'guru' => 'Guru',
                                            'akademik' => 'Akademik'
                                        ];
                                        $badge_class = $role_badge[$teacher['role']] ?? 'bg-secondary';
                                        $badge_text = $role_text[$teacher['role']] ?? ucfirst($teacher['role']);
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($teacher['email'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($teacher['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); confirmDelete('user').then(result => { if(result) this.submit(); }); return false;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $teacher['id']; ?>">
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

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addTeacherForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTeacherModalLabel">
                        <i class="bi bi-plus-circle"></i> Tambah Guru/Staf Baru
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
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <option value="guru">Guru</option>
                                <option value="akademik">Akademik</option>
                            </select>
                            <small class="text-muted">Pilih role untuk user ini</small>
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
    $('#teachersTable').DataTable({
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
