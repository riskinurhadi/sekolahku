<?php
$page_title = 'Kelola Siswa';
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
            $kelas_id = $_POST['kelas_id'] ?? null;
            
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
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id, kelas_id) VALUES (?, ?, ?, ?, 'siswa', ?, ?)");
            $stmt->bind_param("ssssii", $username, $password, $nama_lengkap, $email, $sekolah_id, $kelas_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Siswa berhasil ditambahkan!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan siswa!']);
            }
            $stmt->close();
            $conn->close();
            exit;
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'siswa' AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Siswa berhasil dihapus!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus siswa!']);
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
            $kelas_id = $_POST['kelas_id'] ?? null;
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id, kelas_id) VALUES (?, ?, ?, ?, 'siswa', ?, ?)");
            $stmt->bind_param("ssssii", $username, $password, $nama_lengkap, $email, $sekolah_id, $kelas_id);
            
            if ($stmt->execute()) {
                $message = 'success:Siswa berhasil ditambahkan!';
            } else {
                $message = 'error:Gagal menambahkan siswa!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'siswa' AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:Siswa berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus siswa!';
            }
            $stmt->close();
        }
    }
}

// Get all classes
$kelas = $conn->query("SELECT * FROM kelas WHERE sekolah_id = $sekolah_id ORDER BY tingkat ASC, nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);

// Get all students with kelas info
$students = $conn->query("SELECT u.*, k.nama_kelas 
    FROM users u 
    LEFT JOIN kelas k ON u.kelas_id = k.id 
    WHERE u.role = 'siswa' AND u.sekolah_id = $sekolah_id 
    ORDER BY k.tingkat ASC, k.nama_kelas ASC, u.nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>


<div class="page-header">
    <h2>Kelola Siswa</h2>
    <p>Tambah dan kelola data siswa</p>
</div>

<!-- Students List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people"></i> Daftar Siswa</h5>
                <a href="tambah_siswa.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Tambah Siswa
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="studentsTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Email</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['nama_lengkap']); ?></td>
                                    <td>
                                        <?php if ($student['nama_kelas']): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($student['nama_kelas']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['email'] ?? '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteStudent(<?php echo $student['id']; ?>)">
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

<?php 
// Prepare message for JavaScript
$js_message = '';
if ($message) {
    $msg = explode(':', $message);
    if ($msg[0] == 'success') {
        $js_message = json_encode([
            'type' => 'success',
            'title' => 'Berhasil',
            'text' => $msg[1]
        ]);
    } else {
        $js_message = json_encode([
            'type' => 'error',
            'title' => 'Gagal',
            'text' => $msg[1]
        ]);
    }
}
require_once '../../includes/footer.php'; 
?>

<script>
$(document).ready(function() {
    // Show message if exists
    <?php if ($js_message): ?>
    var msg = <?php echo $js_message; ?>;
    if (msg.type === 'success') {
        Swal.fire({
            icon: 'success',
            title: msg.title,
            text: msg.text,
            timer: 2000,
            showConfirmButton: false
        }).then(function() {
            window.location.reload();
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: msg.title,
            text: msg.text,
            confirmButtonText: 'OK'
        });
    }
    <?php endif; ?>
    
    // Initialize DataTables
    $('#studentsTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    });
});

function deleteStudent(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data siswa akan dihapus secara permanen!",
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
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: response.message,
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan saat menghapus data',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
}
</script>
