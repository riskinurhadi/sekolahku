<?php
$page_title = 'Kelola Sekolah';
require_once '../../config/session.php';
requireRole(['developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nama_sekolah = $_POST['nama_sekolah'];
            $alamat = $_POST['alamat'] ?? '';
            $telepon = $_POST['telepon'] ?? '';
            $email = $_POST['email'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO sekolah (nama_sekolah, alamat, telepon, email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nama_sekolah, $alamat, $telepon, $email);
            
            if ($stmt->execute()) {
                $message = 'success:Sekolah berhasil ditambahkan!';
            } else {
                $message = 'error:Gagal menambahkan sekolah!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM sekolah WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'success:Sekolah berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus sekolah!';
            }
            $stmt->close();
        }
    }
}

// Get all schools
$schools = $conn->query("SELECT s.*, u.nama_lengkap as kepala_sekolah_nama 
    FROM sekolah s 
    LEFT JOIN users u ON s.kepala_sekolah_id = u.id 
    ORDER BY s.created_at DESC")->fetch_all(MYSQLI_ASSOC);

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
        <h2 class="mb-0">Kelola Sekolah</h2>
        <p class="text-muted">Tambah dan kelola data sekolah</p>
    </div>
</div>

<!-- Add School Form -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Tambah Sekolah Baru</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addSchoolForm">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_sekolah" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telepon</label>
                            <input type="text" class="form-control" name="telepon">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Simpan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Schools List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Sekolah</h5>
            </div>
            <div class="card-body">
                <?php if (count($schools) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Sekolah</th>
                                    <th>Alamat</th>
                                    <th>Kepala Sekolah</th>
                                    <th>Telepon</th>
                                    <th>Email</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schools as $school): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($school['nama_sekolah']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($school['alamat'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($school['kepala_sekolah_nama']): ?>
                                                <span class="badge bg-success"><?php echo htmlspecialchars($school['kepala_sekolah_nama']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Belum ditetapkan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($school['telepon'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($school['email'] ?? '-'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus sekolah ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $school['id']; ?>">
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
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-building"></i>
                        <h5>Belum ada sekolah</h5>
                        <p>Mulai dengan menambahkan sekolah baru di atas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

