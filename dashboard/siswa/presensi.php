<?php
$page_title = 'Presensi';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['siswa']);

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'] ?? null;
$message = '';

if (!$sekolah_id) {
    // Get sekolah_id from user
    $stmt = $conn->prepare("SELECT sekolah_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sekolah_id = $user['sekolah_id'] ?? null;
}

// Presensi handling moved to submit_presensi.php endpoint

// Check for success parameter
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'success:Presensi berhasil!';
}

require_once '../../includes/header.php';

// Get my presensi history - get all, not limited
$my_presensi = [];
$table_check = $conn->query("SHOW TABLES LIKE 'presensi'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT p.*, sp.kode_presensi, mp.nama_pelajaran, u.nama_lengkap as nama_guru
        FROM presensi p
        JOIN sesi_pelajaran sp ON p.sesi_pelajaran_id = sp.id
        JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id
        JOIN users u ON sp.guru_id = u.id
        WHERE p.siswa_id = ?
        ORDER BY p.waktu_presensi DESC");
    if ($stmt) {
        $stmt->bind_param("i", $siswa_id);
        $stmt->execute();
        $my_presensi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$conn->close();
?>

<?php if ($message): ?>
    <script>
        // Pastikan SweetAlert2 sudah dimuat sebelum menggunakan
        (function() {
            function showMessage() {
                <?php 
                $msg = explode(':', $message);
                if ($msg[0] == 'success') {
                    echo "if (typeof Swal !== 'undefined') {";
                    echo "    Swal.fire({icon: 'success', title: 'Berhasil!', text: '" . addslashes($msg[1]) . "', timer: 3000});";
                    echo "} else {";
                    echo "    alert('" . addslashes($msg[1]) . "');";
                    echo "}";
                } elseif ($msg[0] == 'expired') {
                    echo "if (typeof Swal !== 'undefined') {";
                    echo "    Swal.fire({icon: 'error', title: 'Kode Kadaluarsa', text: '" . addslashes($msg[1]) . "', timer: 3000});";
                    echo "} else {";
                    echo "    alert('" . addslashes($msg[1]) . "');";
                    echo "}";
                } else {
                    echo "if (typeof showError !== 'undefined') {";
                    echo "    showError('" . addslashes($msg[1]) . "');";
                    echo "} else if (typeof Swal !== 'undefined') {";
                    echo "    Swal.fire({icon: 'error', title: 'Error', text: '" . addslashes($msg[1]) . "', timer: 3000});";
                    echo "} else {";
                    echo "    alert('" . addslashes($msg[1]) . "');";
                    echo "}";
                }
                ?>
            }
            
            // Tunggu sampai semua library dimuat
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(showMessage, 500); // Tunggu 500ms untuk memastikan library dimuat
                });
            } else {
                setTimeout(showMessage, 500);
            }
        })();
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Presensi</h2>
    <p>Lihat riwayat presensi Anda</p>
</div>

<!-- Riwayat Presensi -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (count($my_presensi) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="presensiTable">
                            <tbody>
                                <?php foreach ($my_presensi as $p): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($p['waktu_presensi'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($p['nama_pelajaran']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['nama_guru']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['kode_presensi']); ?></span></td>
                                        <td><?php echo date('H:i:s', strtotime($p['waktu_presensi'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $p['status'] == 'hadir' ? 'success' : ($p['status'] == 'terlambat' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php 
                                                $status_labels = [
                                                    'hadir' => 'Sukses',
                                                    'terlambat' => 'Terlambat',
                                                    'tidak_hadir' => 'Tidak Hadir'
                                                ];
                                                echo $status_labels[$p['status']] ?? $p['status'];
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <i class="bi bi-clipboard-check" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3">Belum ada riwayat presensi</h5>
                        <p class="text-muted">Presensi Anda akan muncul di sini</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if (count($my_presensi) > 0): ?>
    if (typeof initDataTable !== 'undefined') {
        initDataTable('#presensiTable', {
            order: [[0, 'desc']] // Sort by tanggal descending
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>

