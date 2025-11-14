<?php
$page_title = 'Kelola Soal';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../config/database.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Handle form submission BEFORE header output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);
            // First check if soal exists and belongs to this teacher
            $check_stmt = $conn->prepare("SELECT id FROM soal WHERE id = ? AND guru_id = ?");
            $check_stmt->bind_param("ii", $id, $guru_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Get item_soal IDs first to delete pilihan_jawaban
                $get_items = $conn->prepare("SELECT id FROM item_soal WHERE soal_id = ?");
                $get_items->bind_param("i", $id);
                $get_items->execute();
                $items_result = $get_items->get_result();
                $item_ids = [];
                while ($row = $items_result->fetch_assoc()) {
                    $item_ids[] = $row['id'];
                }
                $get_items->close();
                
                // Delete pilihan_jawaban (related to item_soal)
                if (!empty($item_ids)) {
                    if (count($item_ids) == 1) {
                        $delete_pilihan = $conn->prepare("DELETE FROM pilihan_jawaban WHERE item_soal_id = ?");
                        $delete_pilihan->bind_param("i", $item_ids[0]);
                    } else {
                        $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
                        $delete_pilihan = $conn->prepare("DELETE FROM pilihan_jawaban WHERE item_soal_id IN ($placeholders)");
                        $delete_pilihan->bind_param(str_repeat('i', count($item_ids)), ...$item_ids);
                    }
                    $delete_pilihan->execute();
                    $delete_pilihan->close();
                }
                
                // Delete jawaban siswa
                $delete_jawaban = $conn->prepare("DELETE FROM jawaban_siswa WHERE soal_id = ?");
                $delete_jawaban->bind_param("i", $id);
                $delete_jawaban->execute();
                $delete_jawaban->close();
                
                // Delete hasil ujian
                $delete_hasil = $conn->prepare("DELETE FROM hasil_ujian WHERE soal_id = ?");
                $delete_hasil->bind_param("i", $id);
                $delete_hasil->execute();
                $delete_hasil->close();
                
                // Delete item_soal
                $delete_item = $conn->prepare("DELETE FROM item_soal WHERE soal_id = ?");
                $delete_item->bind_param("i", $id);
                $delete_item->execute();
                $delete_item->close();
                
                // Finally delete soal
                $stmt = $conn->prepare("DELETE FROM soal WHERE id = ? AND guru_id = ?");
                $stmt->bind_param("ii", $id, $guru_id);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    $conn->close();
                    header('Location: soal.php?success=1&msg=' . urlencode('Soal berhasil dihapus!'));
                    exit;
                } else {
                    $stmt->close();
                    $conn->close();
                    header('Location: soal.php?error=1&msg=' . urlencode('Gagal menghapus soal!'));
                    exit;
                }
            } else {
                $check_stmt->close();
                $conn->close();
                header('Location: soal.php?error=1&msg=' . urlencode('Soal tidak ditemukan atau tidak memiliki akses!'));
                exit;
            }
        } elseif ($_POST['action'] == 'update_status') {
            $id = intval($_POST['id']);
            $status = $_POST['status'];
            $stmt = $conn->prepare("UPDATE soal SET status = ? WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("sii", $status, $id, $guru_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header('Location: soal.php?success=1&msg=' . urlencode('Status soal berhasil diupdate!'));
                exit;
            } else {
                $stmt->close();
                $conn->close();
                header('Location: soal.php?error=1&msg=' . urlencode('Gagal mengupdate status!'));
                exit;
            }
        }
    }
}

// Now include header after POST handling
require_once '../../includes/header.php';

// Check for success/error from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $msg = isset($_GET['msg']) ? $_GET['msg'] : 'Operasi berhasil!';
    $message = 'success:' . $msg;
}
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $msg = isset($_GET['msg']) ? $_GET['msg'] : 'Terjadi kesalahan!';
    $message = 'error:' . $msg;
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
                                            <form method="POST" style="display: inline;" class="status-form" data-original-status="<?php echo htmlspecialchars($soal['status']); ?>">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $soal['id']; ?>">
                                                <select name="status" class="form-select form-select-sm status-select">
                                                    <option value="draft" <?php echo $soal['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="aktif" <?php echo $soal['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                    <option value="selesai" <?php echo $soal['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo $soal['waktu_pengerjaan']; ?> menit</td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($soal['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="nilai_ujian.php?soal_id=<?php echo $soal['id']; ?>" class="btn btn-sm btn-info" title="Lihat & Nilai Hasil Ujian">
                                                    <i class="bi bi-clipboard-check"></i> Nilai
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteSoal(<?php echo $soal['id']; ?>, '<?php echo htmlspecialchars($soal['judul'], ENT_QUOTES); ?>')">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <i class="bi bi-file-earmark-text" style="font-size: 3.5rem; color: #cbd5e1; opacity: 0.6;"></i>
                        <h5 class="mt-3 mb-2" style="font-size: 1.125rem; font-weight: 600; color: #1e293b;">Belum ada soal</h5>
                        <p class="text-muted mb-4" style="font-size: 0.875rem; color: #64748b;">Mulai dengan menambahkan soal baru</p>
                        <a href="tambah_soal.php" class="btn btn-primary btn-sm" style="padding: 10px 20px; font-size: 14px; font-weight: 600;">
                            <i class="bi bi-plus-circle me-2"></i> Tambah Soal Baru
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Global function untuk konfirmasi hapus soal dengan SweetAlert2
function confirmDeleteSoal(id, judul) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        html: 'Soal <strong>"' + judul + '"</strong> akan dihapus secara permanen!<br><br>Semua data terkait juga akan ikut terhapus:<br>• Item soal & pertanyaan<br>• Pilihan jawaban<br>• Jawaban siswa<br>• Hasil ujian',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="bi bi-trash"></i> Ya, hapus!',
        cancelButtonText: '<i class="bi bi-x-circle"></i> Batal',
        reverseButtons: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: () => {
            return new Promise((resolve) => {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            });
        }
    });
}

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
    
    // Handle status update dengan SweetAlert2
    $('.status-select').on('change', function(e) {
        e.preventDefault();
        const select = $(this);
        const form = select.closest('.status-form');
        const originalStatus = form.data('original-status');
        const newStatus = select.val();
        
        // If status hasn't changed, do nothing
        if (newStatus === originalStatus) {
            return;
        }
        
        const statusText = newStatus === 'aktif' ? 'Aktif' : newStatus === 'draft' ? 'Draft' : 'Selesai';
        
        Swal.fire({
            title: 'Update Status?',
            html: 'Ubah status soal menjadi <strong>"' + statusText + '"</strong>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, update!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            } else {
                // Reset to original value
                select.val(originalStatus);
            }
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
