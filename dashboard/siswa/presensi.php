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

// Removed presensi handling - now handled via submit_presensi.php endpoint
if (false && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'presensi') {
    $kode_presensi = strtoupper(trim($_POST['kode_presensi'] ?? ''));
    
    if (empty($kode_presensi)) {
        $message = 'error:Kode presensi tidak boleh kosong!';
    } elseif (!$sekolah_id) {
        $message = 'error:Anda belum terdaftar di sekolah!';
    } else {
        // Cari sesi dengan kode tersebut yang masih aktif (lebih fleksibel - masih bisa presensi sampai waktu selesai)
        // Pastikan mengambil created_at dan updated_at untuk logika penentuan status
        // Explicitly select updated_at untuk memastikan nilainya terambil
        $stmt = $conn->prepare("SELECT sp.id, sp.mata_pelajaran_id, sp.guru_id, sp.kode_presensi, 
            sp.waktu_mulai, sp.waktu_selesai, sp.status, 
            sp.created_at, sp.updated_at, 
            mp.nama_pelajaran 
            FROM sesi_pelajaran sp 
            JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id 
            WHERE sp.kode_presensi = ? AND sp.status = 'aktif' 
            AND NOW() <= sp.waktu_selesai
            AND mp.sekolah_id = ?");
        $stmt->bind_param("si", $kode_presensi, $sekolah_id);
        $stmt->execute();
        $sesi = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($sesi) {
            // Cek apakah sudah presensi
            $stmt = $conn->prepare("SELECT * FROM presensi WHERE sesi_pelajaran_id = ? AND siswa_id = ?");
            $stmt->bind_param("ii", $sesi['id'], $siswa_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                $error_msg = 'Anda sudah melakukan presensi untuk sesi ini!';
                // Jika request via AJAX, return JSON
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $error_msg]);
                    exit();
                }
                $message = 'error:' . $error_msg;
            } else {
                // Cek apakah kode masih valid (dalam 30 menit)
                // Gunakan waktu dari database untuk konsistensi timezone
                $waktu_sekarang_db = $conn->query("SELECT NOW() as waktu_sekarang")->fetch_assoc()['waktu_sekarang'];
                
                // Gunakan updated_at karena saat regenerate kode, updated_at akan berubah
                // Jika updated_at sama dengan created_at, berarti belum pernah di-regenerate
                $waktu_kode_dibuat = $sesi['updated_at'] ?? $sesi['created_at'];
                
                // Hitung selisih dalam menit menggunakan query SQL untuk akurasi
                $stmt_selisih = $conn->prepare("SELECT TIMESTAMPDIFF(MINUTE, ?, ?) as selisih_menit");
                $stmt_selisih->bind_param("ss", $waktu_kode_dibuat, $waktu_sekarang_db);
                $stmt_selisih->execute();
                $result_selisih = $stmt_selisih->get_result()->fetch_assoc();
                $selisih_menit = $result_selisih['selisih_menit'] ?? 0;
                $stmt_selisih->close();
                
                // Debug: log untuk troubleshooting
                error_log("Presensi Debug - Kode: $kode_presensi, Waktu kode: $waktu_kode_dibuat, Waktu sekarang: $waktu_sekarang_db, Selisih: $selisih_menit menit");
                
                // Jika selisih negatif (waktu kode di masa depan), berarti ada masalah timezone, anggap valid
                // Jika lebih dari 30 menit, kode sudah kadaluarsa
                if ($selisih_menit < 0) {
                    // Waktu kode di masa depan, mungkin masalah timezone, anggap valid
                    $selisih_menit = 0;
                }
                
                if ($selisih_menit > 30) {
                    $error_msg = 'yahh kode sudah kadaluarsa (selisih: ' . round($selisih_menit, 2) . ' menit)';
                    // Jika request via AJAX, return JSON
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'expired' => true, 'message' => $error_msg, 'debug' => [
                            'waktu_kode' => $waktu_kode_dibuat,
                            'waktu_sekarang' => $waktu_sekarang_db,
                            'selisih_menit' => $selisih_menit
                        ]]);
                        exit();
                    }
                    $message = 'expired:' . $error_msg;
                } else {
                    // Jika dalam 30 menit, presensi berhasil dengan status hadir
                    $status = 'hadir';
                    
                    // Insert presensi
                    $stmt = $conn->prepare("INSERT INTO presensi (sesi_pelajaran_id, siswa_id, waktu_presensi, status) VALUES (?, ?, NOW(), ?)");
                    $stmt->bind_param("iis", $sesi['id'], $siswa_id, $status);
                    
                    if ($stmt->execute()) {
                        $stmt->close();
                        $conn->close();
                        
                        // Jika request via AJAX, return JSON
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'message' => 'Presensi berhasil!']);
                            exit();
                        }
                        
                        // Redirect dengan parameter success untuk normal form submission
                        header('Location: presensi.php?success=1');
                        exit();
                    } else {
                        $error_msg = 'Gagal melakukan presensi! Error: ' . $conn->error;
                        // Jika request via AJAX, return JSON
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => $error_msg]);
                            exit();
                        }
                        $message = 'error:' . $error_msg;
                    }
                    $stmt->close();
                }
            }
        } else {
            $error_msg = 'Kode presensi tidak valid atau sesi sudah berakhir!';
            // Jika request via AJAX, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error_msg]);
                exit();
            }
            $message = 'error:' . $error_msg;
        }
    }
}

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
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Riwayat Presensi Saya</h5>
            </div>
            <div class="card-body">
                <?php if (count($my_presensi) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="presensiTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Guru</th>
                                    <th>Kode</th>
                                    <th>Waktu Presensi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
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

