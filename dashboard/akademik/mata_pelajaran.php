<?php
$page_title = 'Kelola Mata Pelajaran';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nama_pelajaran = $_POST['nama_pelajaran'];
            $kode_pelajaran = $_POST['kode_pelajaran'] ?? '';
            $guru_id = $_POST['guru_id'] ?? null;
            
            if (empty($guru_id)) {
                echo json_encode(['success' => false, 'message' => 'Guru wajib dipilih!']);
                $conn->close();
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO mata_pelajaran (nama_pelajaran, kode_pelajaran, sekolah_id, guru_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $nama_pelajaran, $kode_pelajaran, $sekolah_id, $guru_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Mata pelajaran berhasil ditambahkan!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan mata pelajaran!']);
            }
            $stmt->close();
            $conn->close();
            exit;
        } elseif ($_POST['action'] == 'update') {
            $id = $_POST['id'];
            $nama_pelajaran = $_POST['nama_pelajaran'];
            $kode_pelajaran = $_POST['kode_pelajaran'] ?? '';
            $guru_id = $_POST['guru_id'] ?? null;
            
            if (empty($guru_id)) {
                echo json_encode(['success' => false, 'message' => 'Guru wajib dipilih!']);
                $conn->close();
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE mata_pelajaran SET nama_pelajaran = ?, kode_pelajaran = ?, guru_id = ? WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param("ssiii", $nama_pelajaran, $kode_pelajaran, $guru_id, $id, $sekolah_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Mata pelajaran berhasil diupdate!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengupdate mata pelajaran!']);
            }
            $stmt->close();
            $conn->close();
            exit;
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM mata_pelajaran WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Mata pelajaran berhasil dihapus!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus mata pelajaran!']);
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
            $nama_pelajaran = $_POST['nama_pelajaran'];
            $kode_pelajaran = $_POST['kode_pelajaran'] ?? '';
            $guru_id = $_POST['guru_id'] ?? null;
            
            if (empty($guru_id)) {
                $message = 'error:Guru wajib dipilih!';
            } else {
                $stmt = $conn->prepare("INSERT INTO mata_pelajaran (nama_pelajaran, kode_pelajaran, sekolah_id, guru_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $nama_pelajaran, $kode_pelajaran, $sekolah_id, $guru_id);
                
                if ($stmt->execute()) {
                    $message = 'success:Mata pelajaran berhasil ditambahkan!';
                } else {
                    $message = 'error:Gagal menambahkan mata pelajaran!';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] == 'update') {
            $id = $_POST['id'];
            $nama_pelajaran = $_POST['nama_pelajaran'];
            $kode_pelajaran = $_POST['kode_pelajaran'] ?? '';
            $guru_id = $_POST['guru_id'] ?? null;
            
            if (empty($guru_id)) {
                $message = 'error:Guru wajib dipilih!';
            } else {
                $stmt = $conn->prepare("UPDATE mata_pelajaran SET nama_pelajaran = ?, kode_pelajaran = ?, guru_id = ? WHERE id = ? AND sekolah_id = ?");
                $stmt->bind_param("ssiii", $nama_pelajaran, $kode_pelajaran, $guru_id, $id, $sekolah_id);
                
                if ($stmt->execute()) {
                    $message = 'success:Mata pelajaran berhasil diupdate!';
                } else {
                    $message = 'error:Gagal mengupdate mata pelajaran!';
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM mata_pelajaran WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:Mata pelajaran berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus mata pelajaran!';
            }
            $stmt->close();
        }
    }
}

// Get all teachers
$guru_list = $conn->query("SELECT id, nama_lengkap, spesialisasi FROM users WHERE role = 'guru' AND sekolah_id = $sekolah_id ORDER BY nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);

// Get all mata pelajaran with guru info
$mata_pelajaran = $conn->query("SELECT mp.*, u.nama_lengkap as nama_guru, u.spesialisasi
    FROM mata_pelajaran mp
    LEFT JOIN users u ON mp.guru_id = u.id
    WHERE mp.sekolah_id = $sekolah_id
    ORDER BY mp.created_at DESC")->fetch_all(MYSQLI_ASSOC);

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
    <h2>Kelola Mata Pelajaran</h2>
    <p>Tambah dan kelola mata pelajaran di sekolah Anda</p>
</div>

<!-- Mata Pelajaran List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
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
                                        <button type="button" class="btn btn-sm btn-warning me-1" onclick="editMataPelajaran(<?php echo htmlspecialchars(json_encode($mp)); ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
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

<!-- Add Mata Pelajaran Modal -->
<div class="modal fade" id="addMataPelajaranModal" tabindex="-1" aria-labelledby="addMataPelajaranModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="addMataPelajaranForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="ajax" value="1">
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
                    <div class="mb-3">
                        <label class="form-label">Guru Pengajar <span class="text-danger">*</span></label>
                        <select class="form-select" name="guru_id" required>
                            <option value="">Pilih Guru</option>
                            <?php foreach ($guru_list as $guru): ?>
                                <option value="<?php echo $guru['id']; ?>">
                                    <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                                    <?php if ($guru['spesialisasi']): ?>
                                        - <?php echo htmlspecialchars($guru['spesialisasi']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Edit Mata Pelajaran Modal -->
<div class="modal fade" id="editMataPelajaranModal" tabindex="-1" aria-labelledby="editMataPelajaranModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="editMataPelajaranForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="ajax" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMataPelajaranModalLabel">
                        <i class="bi bi-pencil"></i> Edit Mata Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_pelajaran" id="edit_nama_pelajaran" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode Pelajaran</label>
                        <input type="text" class="form-control" name="kode_pelajaran" id="edit_kode_pelajaran" placeholder="Contoh: MAT-001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Guru Pengajar <span class="text-danger">*</span></label>
                        <select class="form-select" name="guru_id" id="edit_guru_id" required>
                            <option value="">Pilih Guru</option>
                            <?php foreach ($guru_list as $guru): ?>
                                <option value="<?php echo $guru['id']; ?>">
                                    <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                                    <?php if ($guru['spesialisasi']): ?>
                                        - <?php echo htmlspecialchars($guru['spesialisasi']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Pastikan semua modal yang tersembunyi benar-benar tidak terlihat dan tidak bisa diklik
    $('.modal:not(.show)').css({
        'display': 'none',
        'pointer-events': 'none',
        'visibility': 'hidden'
    });
    
    // Hapus backdrop yang tersisa
    $('.modal-backdrop:not(.show)').remove();
    
    // Pastikan body tidak memiliki class modal-open jika tidak ada modal yang terbuka
    if ($('.modal.show').length === 0) {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    }
    
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
    
    // Reset form when modal is closed dan pastikan backdrop dihapus
    $('#addMataPelajaranModal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
        $(this).css({
            'display': 'none',
            'pointer-events': 'none',
            'visibility': 'hidden'
        });
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    });
    
    $('#editMataPelajaranModal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
        $(this).css({
            'display': 'none',
            'pointer-events': 'none',
            'visibility': 'hidden'
        });
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    });
    
    // Pastikan saat modal ditutup, semua backdrop dihapus
    $(document).on('hidden.bs.modal', '.modal', function() {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $(this).css({
            'display': 'none',
            'pointer-events': 'none',
            'visibility': 'hidden'
        });
    });
    
    // Handle add form submission with AJAX
    $('#addMataPelajaranForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        
        // Disable submit button
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
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
                        $('#addMataPelajaranModal').modal('hide');
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: response.message
                    });
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan saat menyimpan data'
                });
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Handle edit form submission with AJAX
    $('#editMataPelajaranForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        
        // Disable submit button
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
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
                        $('#editMataPelajaranModal').modal('hide');
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: response.message
                    });
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan saat menyimpan data'
                });
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});

function editMataPelajaran(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_nama_pelajaran').value = data.nama_pelajaran;
    document.getElementById('edit_kode_pelajaran').value = data.kode_pelajaran || '';
    document.getElementById('edit_guru_id').value = data.guru_id;
    
    var modal = new bootstrap.Modal(document.getElementById('editMataPelajaranModal'));
    modal.show();
}

function deleteMataPelajaran(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data mata pelajaran akan dihapus secara permanen!",
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
