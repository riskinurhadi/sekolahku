<?php
$page_title = 'Kelola Kepala Sekolah';
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
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $nama_lengkap = $_POST['nama_lengkap'];
            $email = $_POST['email'] ?? '';
            $sekolah_id = $_POST['sekolah_id'];
            
            // Check if username already exists
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $checkStmt->close();
                echo json_encode(['success' => false, 'message' => 'Username sudah digunakan!']);
                $conn->close();
                exit;
            }
            $checkStmt->close();
            
            // Check if sekolah already has kepala_sekolah
            $checkStmt = $conn->prepare("SELECT kepala_sekolah_id FROM sekolah WHERE id = ?");
            $checkStmt->bind_param("i", $sekolah_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $sekolah = $result->fetch_assoc();
            if ($sekolah && $sekolah['kepala_sekolah_id']) {
                $checkStmt->close();
                echo json_encode(['success' => false, 'message' => 'Sekolah ini sudah memiliki kepala sekolah!']);
                $conn->close();
                exit;
            }
            $checkStmt->close();
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id) VALUES (?, ?, ?, ?, 'kepala_sekolah', ?)");
            $stmt->bind_param("ssssi", $username, $password, $nama_lengkap, $email, $sekolah_id);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                // Update sekolah dengan kepala_sekolah_id
                $stmt2 = $conn->prepare("UPDATE sekolah SET kepala_sekolah_id = ? WHERE id = ?");
                $stmt2->bind_param("ii", $user_id, $sekolah_id);
                $stmt2->execute();
                $stmt2->close();
                
                echo json_encode(['success' => true, 'message' => 'Kepala sekolah berhasil ditambahkan!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan kepala sekolah!']);
            }
            $stmt->close();
            $conn->close();
            exit;
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
                
                echo json_encode(['success' => true, 'message' => 'Kepala sekolah berhasil dihapus!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus kepala sekolah!']);
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

// Get all schools for dropdown (only schools without kepala_sekolah)
$schools = $conn->query("SELECT * FROM sekolah WHERE kepala_sekolah_id IS NULL ORDER BY nama_sekolah")->fetch_all(MYSQLI_ASSOC);

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
    <h2>Kelola Kepala Sekolah</h2>
    <p>Tambah dan kelola data kepala sekolah</p>
</div>

<!-- Kepala Sekolah List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Daftar Kepala Sekolah</h5>
                <a href="tambah_kepala_sekolah.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Tambah Kepala Sekolah
                </a>
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
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteKepalaSekolah(<?php echo $ks['id']; ?>)">
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

function deleteKepalaSekolah(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data kepala sekolah akan dihapus secara permanen!",
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
