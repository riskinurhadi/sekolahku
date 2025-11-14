<?php
$page_title = 'Presensi';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['siswa']);

// Handle presensi - MUST be before header output
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'presensi') {
    $kode_presensi = strtoupper(trim($_POST['kode_presensi'] ?? ''));
    
    if (empty($kode_presensi)) {
        $message = 'error:Kode presensi tidak boleh kosong!';
    } elseif (!$sekolah_id) {
        $message = 'error:Anda belum terdaftar di sekolah!';
    } else {
        // Cari sesi dengan kode tersebut yang masih aktif (lebih fleksibel - masih bisa presensi sampai waktu selesai)
        // Pastikan mengambil created_at dan updated_at untuk logika penentuan status
        $stmt = $conn->prepare("SELECT sp.*, mp.nama_pelajaran 
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
                $waktu_sekarang = new DateTime();
                
                // Gunakan updated_at karena saat regenerate kode, updated_at akan berubah
                $waktu_kode_dibuat = new DateTime($sesi['updated_at'] ?? $sesi['created_at']);
                
                // Hitung selisih dalam menit dari waktu kode dibuat/di-regenerate
                $selisih_menit = ($waktu_sekarang->getTimestamp() - $waktu_kode_dibuat->getTimestamp()) / 60;
                
                // Jika lebih dari 30 menit, kode sudah kadaluarsa
                if ($selisih_menit > 30) {
                    $error_msg = 'yahh kode sudah kadaluarsa';
                    // Jika request via AJAX, return JSON
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'expired' => true, 'message' => $error_msg]);
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

// Now include header AFTER handling POST
// Pastikan tidak ada output sebelum ini
while (ob_get_level()) {
    ob_end_clean();
}
require_once '../../includes/header.php';

// Pastikan koneksi database masih aktif (jika belum ditutup)
if (!isset($conn) || !$conn || $conn->ping() === false) {
    $conn = getConnection();
}

// Get active sessions untuk ditampilkan di halaman presensi
$active_sessions = [];
if ($sekolah_id) {
    $table_check = $conn->query("SHOW TABLES LIKE 'sesi_pelajaran'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT sp.*, mp.nama_pelajaran, u.nama_lengkap as nama_guru,
            (SELECT COUNT(*) FROM presensi WHERE sesi_pelajaran_id = sp.id) as jumlah_presensi,
            CASE 
                WHEN NOW() < sp.waktu_mulai THEN 'belum_mulai'
                WHEN NOW() BETWEEN sp.waktu_mulai AND sp.waktu_selesai THEN 'berlangsung'
                ELSE 'selesai'
            END as status_waktu,
            (SELECT COUNT(*) FROM presensi WHERE sesi_pelajaran_id = sp.id AND siswa_id = ?) as sudah_presensi
            FROM sesi_pelajaran sp 
            JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id 
            JOIN users u ON sp.guru_id = u.id
            WHERE sp.status = 'aktif' 
            AND NOW() <= sp.waktu_selesai
            AND mp.sekolah_id = ?
            ORDER BY sp.waktu_mulai DESC");
        if ($stmt) {
            $stmt->bind_param("ii", $siswa_id, $sekolah_id);
            $stmt->execute();
            $active_sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// Get my presensi history
$my_presensi = [];
$table_check = $conn->query("SHOW TABLES LIKE 'presensi'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT p.*, sp.kode_presensi, mp.nama_pelajaran, u.nama_lengkap as nama_guru
        FROM presensi p
        JOIN sesi_pelajaran sp ON p.sesi_pelajaran_id = sp.id
        JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id
        JOIN users u ON sp.guru_id = u.id
        WHERE p.siswa_id = ?
        ORDER BY p.waktu_presensi DESC
        LIMIT 10");
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
    <p>Ikuti pelajaran aktif dan lakukan presensi</p>
</div>

<!-- List Mata Pelajaran Sedang Berlangsung -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Pelajaran Sedang Berlangsung</h5>
            </div>
            <div class="card-body">
                <?php if (count($active_sessions) > 0): ?>
                    <?php foreach ($active_sessions as $session): ?>
                        <div class="presensi-lesson-item mb-3 pb-3 border-bottom">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($session['nama_pelajaran']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($session['nama_guru']); ?><br>
                                        <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($session['waktu_mulai'])); ?> - <?php echo date('H:i', strtotime($session['waktu_selesai'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <?php 
                                    $status_waktu = $session['status_waktu'] ?? 'selesai';
                                    $status_label = $status_waktu == 'berlangsung' ? 'BERLANGSUNG' : 'SELESAI';
                                    $status_class = $status_waktu == 'berlangsung' ? 'success' : 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($session['sudah_presensi'] > 0): ?>
                                        <div class="alert alert-success mb-0 py-2">
                                            <i class="bi bi-check-circle"></i> Anda sudah melakukan presensi
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" action="presensi.php" class="presensi-form-inline" id="presensiForm_<?php echo $session['id']; ?>">
                                            <input type="hidden" name="action" value="presensi">
                                            <div class="input-group">
                                                <input type="text" 
                                                    class="form-control form-control-sm" 
                                                    name="kode_presensi" 
                                                    placeholder="Masukkan kode presensi" 
                                                    maxlength="10" 
                                                    required 
                                                    autocomplete="off"
                                                    style="text-transform: uppercase;">
                                                <button type="submit" class="btn btn-primary btn-sm btn-presensi-submit">
                                                    <i class="bi bi-check-circle"></i> Presensi
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-2">Tidak ada pelajaran yang sedang berlangsung</p>
                        <?php if (!$sekolah_id): ?>
                            <small class="text-danger">Anda belum terdaftar di sekolah</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
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
// Pastikan script dijalankan setelah semua library dimuat
(function() {
    function initPresensi() {
        // Pastikan jQuery sudah dimuat
        if (typeof jQuery === 'undefined') {
            console.error('jQuery is not loaded! Retrying...');
            setTimeout(initPresensi, 100);
            return;
        }
        
        jQuery(document).ready(function($) {
            console.log('Presensi page loaded');
            
            <?php if (count($my_presensi) > 0): ?>
            if (typeof initDataTable !== 'undefined') {
                initDataTable('#presensiTable', {
                    order: [[0, 'desc']] // Sort by tanggal descending
                });
            }
            <?php endif; ?>
            
            // Auto uppercase kode presensi pada semua form inline
            $('.presensi-form-inline input[name="kode_presensi"]').on('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
            
            // Handle form submission dengan AJAX
            $('.presensi-form-inline').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Form submitted');
                
                const form = $(this);
                const kodeInput = form.find('input[name="kode_presensi"]');
                const kode = kodeInput.val().trim().toUpperCase();
                const btn = form.find('button[type="submit"]');
                
                // Update input value
                kodeInput.val(kode);
                
                console.log('Kode:', kode);
                
                // Validasi
                if (!kode || kode.length < 3) {
                    console.log('Validation failed: kode too short');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Kode presensi minimal 3 karakter!',
                            timer: 2000
                        });
                    } else {
                        alert('Kode presensi minimal 3 karakter!');
                    }
                    return false;
                }
                
                // Disable button
                btn.prop('disabled', true);
                const originalHtml = btn.html();
                btn.html('<span class="spinner-border spinner-border-sm"></span> Memproses...');
                
                console.log('Sending AJAX request...');
                
                // Submit form dengan AJAX
                $.ajax({
                    url: 'presensi.php',
                    type: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        console.log('AJAX Success:', response);
                        
                        if (response && response.success) {
                            // Tampilkan success message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: response.message || 'Presensi berhasil!',
                                    timer: 2000
                                }).then(function() {
                                    // Reload page untuk update status
                                    window.location.href = 'presensi.php';
                                });
                            } else {
                                alert(response.message || 'Presensi berhasil!');
                                window.location.href = 'presensi.php';
                            }
                        } else if (response && response.expired) {
                            // Kode kadaluarsa
                            console.log('Code expired');
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Kode Kadaluarsa',
                                    text: response.message || 'yahh kode sudah kadaluarsa',
                                    timer: 3000
                                });
                            } else {
                                alert(response.message || 'yahh kode sudah kadaluarsa');
                            }
                            // Re-enable button
                            btn.prop('disabled', false);
                            btn.html(originalHtml);
                        } else {
                            // Error lainnya
                            console.log('Error response:', response);
                            const errorMsg = response && response.message ? response.message : 'Terjadi kesalahan saat melakukan presensi.';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: errorMsg,
                                    timer: 3000
                                });
                            } else {
                                alert(errorMsg);
                            }
                            // Re-enable button
                            btn.prop('disabled', false);
                            btn.html(originalHtml);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);
                        console.error('Status:', xhr.status);
                        
                        // Re-enable button on error
                        btn.prop('disabled', false);
                        btn.html(originalHtml);
                        
                        // Cek jika response adalah redirect atau HTML (bukan JSON)
                        if (xhr.status === 200 && xhr.responseText && !xhr.responseText.trim().startsWith('{')) {
                            // Response adalah HTML (redirect terjadi), reload page
                            console.log('Redirect detected, reloading page');
                            window.location.href = 'presensi.php?success=1';
                            return;
                        }
                        
                        // Parse error message dari response jika ada
                        let errorMsg = 'Terjadi kesalahan saat melakukan presensi. Silakan coba lagi.';
                        try {
                            if (xhr.responseText) {
                                const response = JSON.parse(xhr.responseText);
                                if (response && response.message) {
                                    errorMsg = response.message;
                                }
                            }
                        } catch(e) {
                            console.error('Failed to parse JSON:', e);
                            // Bukan JSON, mungkin HTML error page
                            errorMsg = 'Terjadi kesalahan. Silakan refresh halaman dan coba lagi.';
                        }
                        
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMsg,
                                timer: 3000
                            });
                        } else {
                            alert(errorMsg);
                        }
                    }
                });
                
                return false;
            });
            
            // Reload page setelah success untuk update status
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            setTimeout(function() {
                window.location.href = 'presensi.php';
            }, 2500);
            <?php endif; ?>
        });
    }
    
    // Coba init langsung, jika gagal tunggu 100ms
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPresensi);
    } else {
        initPresensi();
    }
})();
</script>

<?php require_once '../../includes/footer.php'; ?>

