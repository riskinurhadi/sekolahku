<?php
$page_title = 'Kelola Kelas';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['kepala_sekolah']);

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle AJAX request FIRST (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    // Set header first before any output
    header('Content-Type: application/json; charset=utf-8');
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nama_kelas = $_POST['nama_kelas'];
            $tingkat = $_POST['tingkat'];
            
            // Check if kelas already exists
            $checkStmt = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ? AND sekolah_id = ?");
            $checkStmt->bind_param("si", $nama_kelas, $sekolah_id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $checkStmt->close();
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'Kelas dengan nama ini sudah ada!']);
                exit;
            }
            $checkStmt->close();
            
            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tingkat, sekolah_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $nama_kelas, $tingkat, $sekolah_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                echo json_encode(['success' => true, 'message' => 'Kelas berhasil ditambahkan!']);
            } else {
                $error = $conn->error;
                $stmt->close();
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan kelas: ' . $error]);
            }
            exit;
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $nama_kelas = $_POST['nama_kelas'];
            $tingkat = intval($_POST['tingkat']);
            
            if ($id <= 0) {
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'ID tidak valid!']);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE kelas SET nama_kelas = ?, tingkat = ? WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param("siii", $nama_kelas, $tingkat, $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                echo json_encode(['success' => true, 'message' => 'Kelas berhasil diupdate!']);
            } else {
                $error = $conn->error;
                $stmt->close();
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'Gagal mengupdate kelas: ' . $error]);
            }
            exit;
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'ID tidak valid!']);
                exit;
            }
            
            // Cek apakah ada siswa di kelas ini
            $check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE kelas_id = ?");
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($check_result['total'] > 0) {
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'Kelas tidak bisa dihapus karena masih ada siswa di kelas ini!']);
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM kelas WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                $conn->close();
                
                if ($affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Kelas berhasil dihapus!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Kelas tidak ditemukan atau tidak memiliki akses!']);
                }
            } else {
                $error = $conn->error;
                $stmt->close();
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus kelas: ' . $error]);
            }
            exit;
        }
    }
    
    // If no action matched
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid!']);
    exit;
}

// Now include header for non-AJAX requests
require_once '../../includes/header.php';

// Handle form submission (non-AJAX fallback)
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

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-building"></i> Kelola Kelas</h2>
        <p>Tambah dan kelola data kelas (10 D, 11 D, 12 D, dll)</p>
    </div>
    <a href="tambah_kelas.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> Tambah Kelas
    </a>
</div>

<!-- Classes List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="kelasTable" class="table table-hover" style="width:100%">
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
                                        <a href="edit_kelas.php?id=<?php echo $k['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteKelas(<?php echo $k['id']; ?>)">
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
} elseif (isset($_GET['success']) && $_GET['success'] == 1) {
    $js_message = json_encode([
        'type' => 'success',
        'title' => 'Berhasil',
        'text' => isset($_SESSION['success_message']) ? $_SESSION['success_message'] : 'Operasi berhasil!'
    ]);
    unset($_SESSION['success_message']);
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
            window.location.href = 'kelas.php';
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

function deleteKelas(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Kelas akan dihapus secara permanen! Pastikan tidak ada siswa di kelas ini.",
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
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', status, error);
                    console.log('Response:', xhr.responseText);
                    
                    // Try to parse response as JSON
                    var response = null;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        // If not JSON, show generic error
                    }
                    
                    if (response && response.message) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: response.message,
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat menghapus data. Status: ' + status,
                            confirmButtonText: 'OK'
                        });
                    }
                }
            });
        }
    });
}
</script>
