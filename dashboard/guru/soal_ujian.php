<?php
$page_title = 'Kelola Soal Ujian';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../config/database.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Get tipe ujian from URL parameter
$tipe_ujian = $_GET['tipe'] ?? 'uts';
if (!in_array($tipe_ujian, ['uts', 'uas'])) {
    $tipe_ujian = 'uts';
}

$tipe_label = strtoupper($tipe_ujian);

// Handle form submission BEFORE header output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);
            $check_stmt = $conn->prepare("SELECT id FROM soal WHERE id = ? AND guru_id = ?");
            $check_stmt->bind_param("ii", $id, $guru_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Get item_soal IDs first
                $get_items = $conn->prepare("SELECT id FROM item_soal WHERE soal_id = ?");
                $get_items->bind_param("i", $id);
                $get_items->execute();
                $items_result = $get_items->get_result();
                $item_ids = [];
                while ($row = $items_result->fetch_assoc()) {
                    $item_ids[] = $row['id'];
                }
                $get_items->close();
                
                // Delete pilihan_jawaban
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
                
                // Delete jadwal_ujian (if exists)
                $check_table = $conn->query("SHOW TABLES LIKE 'jadwal_ujian'");
                if ($check_table && $check_table->num_rows > 0) {
                    $delete_jadwal = $conn->prepare("DELETE FROM jadwal_ujian WHERE soal_id = ?");
                    $delete_jadwal->bind_param("i", $id);
                    $delete_jadwal->execute();
                    $delete_jadwal->close();
                }
                
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
                    header('Location: soal_ujian.php?tipe=' . $tipe_ujian . '&success=1&msg=' . urlencode('Soal berhasil dihapus!'));
                    exit;
                } else {
                    $stmt->close();
                    $conn->close();
                    header('Location: soal_ujian.php?tipe=' . $tipe_ujian . '&error=1&msg=' . urlencode('Gagal menghapus soal!'));
                    exit;
                }
            } else {
                $check_stmt->close();
                $conn->close();
                header('Location: soal_ujian.php?tipe=' . $tipe_ujian . '&error=1&msg=' . urlencode('Soal tidak ditemukan atau tidak memiliki akses!'));
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

// Check if jadwal_ujian table exists
$check_table = $conn->query("SHOW TABLES LIKE 'jadwal_ujian'");
$jadwal_table_exists = ($check_table && $check_table->num_rows > 0);

// Check if tipe_ujian column exists
$check_column = $conn->query("SHOW COLUMNS FROM soal LIKE 'tipe_ujian'");
if ($check_column && $check_column->num_rows > 0) {
    // Get all soal with tipe_ujian filter
    if ($jadwal_table_exists) {
        $stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran,
            (SELECT COUNT(*) FROM jadwal_ujian ju WHERE ju.soal_id = s.id) as jadwal_count
            FROM soal s 
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
            WHERE s.guru_id = ? AND s.tipe_ujian = ?
            ORDER BY s.created_at DESC");
        $stmt->bind_param("is", $guru_id, $tipe_ujian);
    } else {
        $stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran, 0 as jadwal_count
            FROM soal s 
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
            WHERE s.guru_id = ? AND s.tipe_ujian = ?
            ORDER BY s.created_at DESC");
        $stmt->bind_param("is", $guru_id, $tipe_ujian);
    }
} else {
    // Fallback: get all soal (tipe_ujian column doesn't exist yet)
    if ($jadwal_table_exists) {
        $stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran,
            (SELECT COUNT(*) FROM jadwal_ujian ju WHERE ju.soal_id = s.id) as jadwal_count
            FROM soal s 
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
            WHERE s.guru_id = ?
            ORDER BY s.created_at DESC");
        $stmt->bind_param("i", $guru_id);
    } else {
        $stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran, 0 as jadwal_count
            FROM soal s 
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
            WHERE s.guru_id = ?
            ORDER BY s.created_at DESC");
        $stmt->bind_param("i", $guru_id);
    }
}
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
            <h2>Kelola Soal <?php echo $tipe_label; ?></h2>
            <p>Daftar semua soal <?php echo $tipe_label; ?> yang telah dibuat</p>
        </div>
        <div>
            <a href="tambah_soal_ujian.php?tipe=<?php echo $tipe_ujian; ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Soal <?php echo $tipe_label; ?> Baru
            </a>
        </div>
    </div>
</div>

<!-- Tabs untuk UTS/UAS -->
<div class="row mb-3">
    <div class="col-12">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $tipe_ujian == 'uts' ? 'active' : ''; ?>" href="soal_ujian.php?tipe=uts">
                    <i class="bi bi-file-earmark-medical"></i> UTS
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tipe_ujian == 'uas' ? 'active' : ''; ?>" href="soal_ujian.php?tipe=uas">
                    <i class="bi bi-file-earmark-medical-fill"></i> UAS
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Soal List -->
<div class="row">
    <div class="col-12">
        <div class="card">
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
                                    <th>Jadwal</th>
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
                                            <?php 
                                            $status_badges = [
                                                'draft' => 'bg-secondary',
                                                'aktif' => 'bg-success',
                                                'selesai' => 'bg-warning'
                                            ];
                                            $badge_class = $status_badges[$soal['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($soal['status']); ?></span>
                                        </td>
                                        <td><?php echo $soal['waktu_pengerjaan']; ?> menit</td>
                                        <td>
                                            <?php if ($soal['jadwal_count'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $soal['jadwal_count']; ?> Jadwal</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Belum Dijadwalkan</span>
                                            <?php endif; ?>
                                        </td>
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
                        <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: #cbd5e1; opacity: 0.6;"></i>
                        <h5 class="mt-3 mb-2" style="font-size: 1rem; font-weight: 600; color: #1e293b;">Belum ada soal <?php echo $tipe_label; ?></h5>
                        <p class="text-muted mb-3" style="font-size: 0.875rem; color: #64748b;">Mulai dengan menambahkan soal <?php echo $tipe_label; ?> baru</p>
                        <a href="tambah_soal_ujian.php?tipe=<?php echo $tipe_ujian; ?>" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 500; border-radius: 8px;">
                            <i class="bi bi-plus-circle me-1" style="font-size: 14px;"></i> Tambah Soal <?php echo $tipe_label; ?> Baru
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDeleteSoal(id, judul) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        html: 'Soal <strong>"' + judul + '"</strong> akan dihapus secara permanen!<br><br>Semua data terkait juga akan ikut terhapus:<br>• Item soal & pertanyaan<br>• Pilihan jawaban<br>• Jawaban siswa<br>• Hasil ujian<br>• Jadwal ujian',
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
    initDataTable('#soalTable', {
        order: [[6, 'desc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>

