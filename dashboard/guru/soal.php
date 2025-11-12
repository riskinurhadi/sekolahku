<?php
$page_title = 'Kelola Soal';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Check for success parameter from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'success:Soal berhasil ditambahkan!';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM soal WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("ii", $id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Soal berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus soal!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'update_status') {
            $id = $_POST['id'];
            $status = $_POST['status'];
            $stmt = $conn->prepare("UPDATE soal SET status = ? WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("sii", $status, $id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Status soal berhasil diupdate!';
            } else {
                $message = 'error:Gagal mengupdate status!';
            }
            $stmt->close();
        }
    }
}

// Get all soal
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran 
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE s.guru_id = ? 
    ORDER BY s.created_at DESC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$soal_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2>Kelola Soal & Quiz</h2>
            <p>Daftar semua soal, quiz, dan ujian yang telah dibuat</p>
        </div>
        <a href="tambah_soal.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Soal Baru
        </a>
    </div>
</div>

<!-- Soal List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Soal</h5>
            </div>
            <div class="card-body">
                <?php if (count($soal_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="soalTable">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soal_list as $soal): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($soal['judul']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($soal['nama_pelajaran']); ?></td>
                                        <td>
                                            <?php 
                                            $jenis_labels = [
                                                'quiz' => '<span class="badge bg-info">Quiz</span>',
                                                'pilihan_ganda' => '<span class="badge bg-primary">Pilihan Ganda</span>',
                                                'isian' => '<span class="badge bg-warning">Isian</span>'
                                            ];
                                            echo $jenis_labels[$soal['jenis']] ?? $soal['jenis'];
                                            ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;" class="status-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $soal['id']; ?>">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="draft" <?php echo $soal['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="aktif" <?php echo $soal['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                    <option value="selesai" <?php echo $soal['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo $soal['waktu_pengerjaan']; ?> menit</td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($soal['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('soal');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $soal['id']; ?>">
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
                    <div class="empty-state text-center py-5">
                        <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3">Belum ada soal</h5>
                        <p class="text-muted">Mulai dengan menambahkan soal baru</p>
                        <a href="tambah_soal.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Tambah Soal Baru
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if (count($soal_list) > 0): ?>
    // Initialize DataTable
    initDataTable('#soalTable', {
        order: [[5, 'desc']], // Sort by created_at descending
        columnDefs: [
            { orderable: false, targets: [6] } // Disable sorting on action column
        ]
    });
    <?php endif; ?>
    
    // Confirm delete
    function confirmDelete(type) {
        return confirm('Apakah Anda yakin ingin menghapus ' + type + ' ini?');
    }
    
    // Make confirmDelete available globally
    window.confirmDelete = confirmDelete;
});
</script>

<?php require_once '../../includes/footer.php'; ?>
