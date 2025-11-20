<?php
$page_title = 'Kelola Mata Pelajaran';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle delete only (add and update are handled in separate pages)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM mata_pelajaran WHERE id = ? AND sekolah_id = ?");
        $stmt->bind_param("ii", $id, $sekolah_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            $_SESSION['success_message'] = 'Mata pelajaran berhasil dihapus!';
            echo '<script>window.location.href = "mata_pelajaran.php?success=1";</script>';
            exit;
        } else {
            $message = 'error:Gagal menghapus mata pelajaran!';
        }
        $stmt->close();
    }
}

// Get all mata pelajaran with guru info
$mata_pelajaran = $conn->query("SELECT mp.*, u.nama_lengkap as nama_guru, u.spesialisasi
    FROM mata_pelajaran mp
    LEFT JOIN users u ON mp.guru_id = u.id
    WHERE mp.sekolah_id = $sekolah_id
    ORDER BY mp.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>


<?php 
// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1): 
    $success_msg = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : 'Operasi berhasil dilakukan!';
    unset($_SESSION['success_message']);
?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo strpos($message, 'success') === 0 ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php 
        $msg = explode(':', $message);
        echo htmlspecialchars($msg[1]); 
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2>Kelola Mata Pelajaran</h2>
    <p>Tambah dan kelola mata pelajaran di sekolah Anda</p>
</div>

<!-- Mata Pelajaran List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-book"></i> Daftar Mata Pelajaran</h5>
                <a href="tambah_mata_pelajaran.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Tambah Mata Pelajaran
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="mataPelajaranTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Pelajaran</th>
                                <th>Guru Pengajar</th>
                                <th>Spesialisasi</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mata_pelajaran as $mp): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($mp['kode_pelajaran'] ?: '-'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($mp['nama_pelajaran']); ?></td>
                                    <td><?php echo htmlspecialchars($mp['nama_guru'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($mp['spesialisasi']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($mp['spesialisasi']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($mp['created_at'])); ?></td>
                                    <td>
                                        <a href="edit_mata_pelajaran.php?id=<?php echo $mp['id']; ?>" class="btn btn-sm btn-warning me-1">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteMataPelajaran(<?php echo $mp['id']; ?>)">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#mataPelajaranTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[4, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    });
});

function deleteMataPelajaran(id) {
    if (confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                        '<input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
