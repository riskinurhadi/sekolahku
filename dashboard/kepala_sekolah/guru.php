<?php
$page_title = 'Kelola Guru';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
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
            $role = $_POST['role'] ?? 'guru';
            $spesialisasi = $_POST['spesialisasi'] ?? '';
            
            // Validasi role hanya boleh guru atau akademik
            if (!in_array($role, ['guru', 'akademik'])) {
                $role = 'guru';
            }
            
            // Jika role adalah guru, spesialisasi wajib diisi
            if ($role == 'guru' && empty($spesialisasi)) {
                echo json_encode(['success' => false, 'message' => 'Spesialisasi wajib diisi untuk guru!']);
                $conn->close();
                exit;
            }
            
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
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id, spesialisasi) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssis", $username, $password, $nama_lengkap, $email, $role, $sekolah_id, $spesialisasi);
            
            if ($stmt->execute()) {
                $role_text = $role == 'akademik' ? 'Akademik' : 'Guru';
                echo json_encode(['success' => true, 'message' => $role_text . ' berhasil ditambahkan!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan user!']);
            }
            $stmt->close();
            $conn->close();
            exit;
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('guru', 'akademik') AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User berhasil dihapus!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus user!']);
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
            $role = $_POST['role'] ?? 'guru';
            $spesialisasi = $_POST['spesialisasi'] ?? '';
            
            // Validasi role hanya boleh guru atau akademik
            if (!in_array($role, ['guru', 'akademik'])) {
                $role = 'guru';
            }
            
            // Jika role adalah guru, spesialisasi wajib diisi
            if ($role == 'guru' && empty($spesialisasi)) {
                $message = 'error:Spesialisasi wajib diisi untuk guru!';
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id, spesialisasi) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $username, $password, $nama_lengkap, $email, $role, $sekolah_id, $spesialisasi);
                
                if ($stmt->execute()) {
                    $role_text = $role == 'akademik' ? 'Akademik' : 'Guru';
                    $message = 'success:' . $role_text . ' berhasil ditambahkan!';
                } else {
                    $message = 'error:Gagal menambahkan user!';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('guru', 'akademik') AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:User berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus user!';
            }
            $stmt->close();
        }
    }
}

// Get all teachers and akademik staff
$stmt = $conn->prepare("SELECT * FROM users WHERE role IN ('guru', 'akademik') AND sekolah_id = ? ORDER BY role ASC, created_at DESC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
    <h2>Kelola Guru & Staf</h2>
    <p>Tambah dan kelola data guru dan staf akademik</p>
</div>

<!-- Teachers List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-workspace"></i> Daftar Guru</h5>
                <a href="tambah_guru.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Tambah Guru
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="teachersTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Role</th>
                                <th>Spesialisasi</th>
                                <th>Email</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($teacher['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($teacher['nama_lengkap']); ?></td>
                                    <td>
                                        <?php
                                        $role_badge = [
                                            'guru' => 'bg-primary',
                                            'akademik' => 'bg-info'
                                        ];
                                        $role_text = [
                                            'guru' => 'Guru',
                                            'akademik' => 'Akademik'
                                        ];
                                        $badge_class = $role_badge[$teacher['role']] ?? 'bg-secondary';
                                        $badge_text = $role_text[$teacher['role']] ?? ucfirst($teacher['role']);
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($teacher['spesialisasi']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($teacher['spesialisasi']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($teacher['email'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($teacher['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteTeacher(<?php echo $teacher['id']; ?>)">
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
    $('#teachersTable').DataTable({
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

function deleteTeacher(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data user akan dihapus secara permanen!",
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
