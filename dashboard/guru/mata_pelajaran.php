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
            echo "setTimeout(function(){ window.location.reload(); }, 1500);";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Kelola Mata Pelajaran</h2>
    <p>Tambah dan kelola mata pelajaran yang Anda ajarkan</p>
</div>

<!-- Mata Pelajaran List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-book"></i> Daftar Mata Pelajaran</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMataPelajaranModal">
                    <i class="bi bi-plus-circle"></i> Tambah Mata Pelajaran
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="mataPelajaranTable" class="table table-hover" style="width:100%">
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
                                        <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); confirmDelete('mata pelajaran').then(result => { if(result) this.submit(); }); return false;">
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
            </div>
        </div>
    </div>
</div>

<!-- Add Mata Pelajaran Modal -->
<div class="modal fade" id="addMataPelajaranModal" tabindex="-1" aria-labelledby="addMataPelajaranModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addMataPelajaranForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMataPelajaranModalLabel">
                        <i class="bi bi-plus-circle"></i> Tambah Mata Pelajaran Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_pelajaran" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode Pelajaran</label>
                        <input type="text" class="form-control" name="kode_pelajaran" placeholder="Contoh: MAT-001">
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
    $('#mataPelajaranTable').DataTable({
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
