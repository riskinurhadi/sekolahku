<?php
$page_title = 'Kelola Kelas';
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
            $nama_kelas = $_POST['nama_kelas'];
            $tingkat = $_POST['tingkat'];
            
            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tingkat, sekolah_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $nama_kelas, $tingkat, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:Kelas berhasil ditambahkan!';
            } else {
                $message = 'error:Gagal menambahkan kelas! ' . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $nama_kelas = $_POST['nama_kelas'];
            $tingkat = $_POST['tingkat'];
            
            $stmt = $conn->prepare("UPDATE kelas SET nama_kelas = ?, tingkat = ? WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param("siii", $nama_kelas, $tingkat, $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:Kelas berhasil diupdate!';
            } else {
                $message = 'error:Gagal mengupdate kelas!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            
            // Cek apakah ada siswa di kelas ini
            $check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE kelas_id = ?");
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($check_result['total'] > 0) {
                $message = 'error:Kelas tidak bisa dihapus karena masih ada siswa di kelas ini!';
            } else {
                $stmt = $conn->prepare("DELETE FROM kelas WHERE id = ? AND sekolah_id = ?");
                $stmt->bind_param("ii", $id, $sekolah_id);
                
                if ($stmt->execute()) {
                    $message = 'success:Kelas berhasil dihapus!';
                } else {
                    $message = 'error:Gagal menghapus kelas!';
                }
                $stmt->close();
            }
        }
    }
}

// Get all classes
$kelas = $conn->query("SELECT k.*, 
    (SELECT COUNT(*) FROM users WHERE kelas_id = k.id) as jumlah_siswa
    FROM kelas k 
    WHERE k.sekolah_id = $sekolah_id 
    ORDER BY k.tingkat ASC, k.nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "Swal.fire({ icon: 'success', title: 'Berhasil', text: '" . addslashes($msg[1]) . "', timer: 2000, showConfirmButton: false });";
            echo "setTimeout(function(){ window.location.reload(); }, 2000);";
        } else {
            echo "Swal.fire({ icon: 'error', title: 'Error', text: '" . addslashes($msg[1]) . "' });";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-building"></i> Kelola Kelas</h2>
    <p>Tambah dan kelola data kelas (10 D, 11 D, 12 D, dll)</p>
</div>

<!-- Classes List -->
<div class="row">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-building"></i> Daftar Kelas</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addKelasModal">
                    <i class="bi bi-plus-circle"></i> Tambah Kelas
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="kelasTable" class="table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Nama Kelas</th>
                                <th>Tingkat</th>
                                <th>Jumlah Siswa</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kelas as $k): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($k['nama_kelas']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-info">Kelas <?php echo $k['tingkat']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $k['jumlah_siswa']; ?> siswa</span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($k['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="editKelas(<?php echo htmlspecialchars(json_encode($k)); ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus kelas ini? Pastikan tidak ada siswa di kelas ini.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $k['id']; ?>">
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

<!-- Add/Edit Kelas Modal -->
<div class="modal fade" id="addKelasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="kelasForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="mb-3">
                        <label class="form-label">Tingkat <span class="text-danger">*</span></label>
                        <select class="form-select" name="tingkat" id="tingkat" required>
                            <option value="">Pilih Tingkat</option>
                            <option value="10">Kelas 10</option>
                            <option value="11">Kelas 11</option>
                            <option value="12">Kelas 12</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kelas" id="namaKelas" placeholder="Contoh: 10 D, 11 A, 12 B" required>
                        <small class="text-muted">Masukkan nama kelas lengkap (contoh: 10 D, 11 A, 12 B)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editKelas(kelas) {
    document.getElementById('modalTitle').textContent = 'Edit Kelas';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = kelas.id;
    document.getElementById('tingkat').value = kelas.tingkat;
    document.getElementById('namaKelas').value = kelas.nama_kelas;
    
    var modal = new bootstrap.Modal(document.getElementById('addKelasModal'));
    modal.show();
}

// Reset form when modal is closed
document.getElementById('addKelasModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kelasForm').reset();
    document.getElementById('modalTitle').textContent = 'Tambah Kelas';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
});

$(document).ready(function() {
    $('#kelasTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[1, 'asc'], [0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

