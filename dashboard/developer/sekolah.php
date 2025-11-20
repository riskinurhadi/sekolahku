<?php
$page_title = 'Kelola Sekolah';
require_once '../../config/session.php';
requireRole(['developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$message = '';

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nama_sekolah = $_POST['nama_sekolah'];
            $alamat = $_POST['alamat'] ?? '';
            $telepon = $_POST['telepon'] ?? '';
            $email = $_POST['email'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO sekolah (nama_sekolah, alamat, telepon, email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nama_sekolah, $alamat, $telepon, $email);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Sekolah berhasil ditambahkan!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan sekolah!']);
            }
            $stmt->close();
            $conn->close();
            exit;
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM sekolah WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Sekolah berhasil dihapus!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus sekolah!']);
            }
            $stmt->close();
            $conn->close();
            exit;
        }
    }
}

// Handle form submission (non-AJAX fallback)
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
            echo "Swal.fire({ icon: 'success', title: 'Berhasil', text: '" . addslashes($msg[1]) . "', timer: 1500, showConfirmButton: false });";
            echo "setTimeout(function(){ window.location.reload(); }, 1500);";
        } else {
            echo "Swal.fire({ icon: 'error', title: 'Gagal', text: '" . addslashes($msg[1]) . "' });";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Kelola Sekolah</h2>
    <p>Tambah dan kelola data sekolah</p>
</div>

<!-- Schools List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building"></i> Daftar Sekolah</h5>
                <a href="tambah_sekolah.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Tambah Sekolah
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="schoolsTable" class="table table-hover" style="width:100%">
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
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteSchool(<?php echo $school['id']; ?>)">
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
    $('#schoolsTable').DataTable({
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

function deleteSchool(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data sekolah akan dihapus secara permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'delete',
                    id: id,
                    ajax: 1
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan saat menghapus data'
                    });
                }
            });
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
