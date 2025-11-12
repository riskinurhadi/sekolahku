<?php
$page_title = 'Kelola Kepala Sekolah';
require_once '../../config/session.php';
requireRole(['developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $nama_lengkap = $_POST['nama_lengkap'];
            $email = $_POST['email'] ?? '';
            $sekolah_id = $_POST['sekolah_id'];
            
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
            } else {
                $message = 'error:Gagal menambahkan kepala sekolah!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            // Get sekolah_id first
            $stmt = $conn->prepare("SELECT sekolah_id FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $sekolah_id = $user['sekolah_id'];
            $stmt->close();
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Update sekolah to remove kepala_sekolah_id
                $stmt2 = $conn->prepare("UPDATE sekolah SET kepala_sekolah_id = NULL WHERE id = ?");
                $stmt2->bind_param("i", $sekolah_id);
                $stmt2->execute();
                $stmt2->close();
                
                $message = 'success:Kepala sekolah berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus kepala sekolah!';
            }
            $stmt->close();
        }
    }
}

// Get all kepala sekolah
$kepala_sekolah = $conn->query("SELECT u.*, s.nama_sekolah 
    FROM users u 
    LEFT JOIN sekolah s ON u.sekolah_id = s.id 
    WHERE u.role = 'kepala_sekolah' 
    ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get all schools for dropdown
$schools = $conn->query("SELECT * FROM sekolah ORDER BY nama_sekolah")->fetch_all(MYSQLI_ASSOC);

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
    <h2>Kelola Kepala Sekolah</h2>
    <p>Tambah dan kelola data kepala sekolah</p>
</div>

<!-- Kepala Sekolah List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Daftar Kepala Sekolah</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addKepalaSekolahModal">
                    <i class="bi bi-plus-circle"></i> Tambah Kepala Sekolah
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="kepalaSekolahTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Sekolah</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kepala_sekolah as $ks): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ks['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ks['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($ks['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($ks['nama_sekolah'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($ks['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); confirmDelete('kepala sekolah').then(result => { if(result) this.submit(); }); return false;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $ks['id']; ?>">
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

<!-- Add Kepala Sekolah Modal -->
<div class="modal fade" id="addKepalaSekolahModal" tabindex="-1" aria-labelledby="addKepalaSekolahModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addKepalaSekolahForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addKepalaSekolahModalLabel">
                        <i class="bi bi-plus-circle"></i> Tambah Kepala Sekolah Baru
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
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Sekolah <span class="text-danger">*</span></label>
                            <select class="form-select" name="sekolah_id" required>
                                <option value="">Pilih Sekolah</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['nama_sekolah']); ?></option>
                                <?php endforeach; ?>
                            </select>
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
    $('#kepalaSekolahTable').DataTable({
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
