<?php
$page_title = 'Kelola Mata Pelajaran';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nama_pelajaran = $_POST['nama_pelajaran'];
            $kode_pelajaran = $_POST['kode_pelajaran'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO mata_pelajaran (nama_pelajaran, kode_pelajaran, sekolah_id, guru_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $nama_pelajaran, $kode_pelajaran, $sekolah_id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Mata pelajaran berhasil ditambahkan!';
            } else {
                $message = 'error:Gagal menambahkan mata pelajaran!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM mata_pelajaran WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("ii", $id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Mata pelajaran berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus mata pelajaran!';
            }
            $stmt->close();
        }
    }
}

// Get all mata pelajaran
$stmt = $conn->prepare("SELECT * FROM mata_pelajaran WHERE guru_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$mata_pelajaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
        <h2 class="mb-0">Kelola Mata Pelajaran</h2>
        <p class="text-muted">Tambah dan kelola mata pelajaran yang Anda ajarkan</p>
    </div>
</div>

<!-- Add Mata Pelajaran Form -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Tambah Mata Pelajaran Baru</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addMataPelajaranForm">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_pelajaran" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kode Pelajaran</label>
                            <input type="text" class="form-control" name="kode_pelajaran" placeholder="Contoh: MAT-001">
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

<!-- Mata Pelajaran List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Mata Pelajaran</h5>
            </div>
            <div class="card-body">
                <?php if (count($mata_pelajaran) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Pelajaran</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mata_pelajaran as $mp): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($mp['kode_pelajaran'] ?: '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mp['nama_pelajaran']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($mp['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $mp['id']; ?>">
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
                        <i class="bi bi-book"></i>
                        <h5>Belum ada mata pelajaran</h5>
                        <p>Mulai dengan menambahkan mata pelajaran baru di atas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

