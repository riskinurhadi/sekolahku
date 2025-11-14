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
                $message = 'error:Anda sudah melakukan presensi untuk sesi ini!';
            } else {
                // Cek apakah kode masih valid (dalam 30 menit)
                $waktu_sekarang = new DateTime();
                
                // Gunakan updated_at karena saat regenerate kode, updated_at akan berubah
                $waktu_kode_dibuat = new DateTime($sesi['updated_at'] ?? $sesi['created_at']);
                
                // Hitung selisih dalam menit dari waktu kode dibuat/di-regenerate
                $selisih_menit = ($waktu_sekarang->getTimestamp() - $waktu_kode_dibuat->getTimestamp()) / 60;
                
                // Jika lebih dari 30 menit, kode sudah kadaluarsa
                if ($selisih_menit > 30) {
                    $message = 'expired:yahh kode sudah kadaluarsa';
                } else {
                    // Jika dalam 30 menit, presensi berhasil dengan status hadir
                    $status = 'hadir';
                    
                    // Insert presensi
                    $stmt = $conn->prepare("INSERT INTO presensi (sesi_pelajaran_id, siswa_id, waktu_presensi, status) VALUES (?, ?, NOW(), ?)");
                    $stmt->bind_param("iis", $sesi['id'], $siswa_id, $status);
                    
                    if ($stmt->execute()) {
                        $stmt->close();
                        // Redirect dengan parameter success
                        header('Location: presensi.php?success=1');
                        $conn->close();
                        exit();
                    } else {
                        $message = 'error:Gagal melakukan presensi! Error: ' . $conn->error;
                    }
                    $stmt->close();
                }
            }
        } else {
            $message = 'error:Kode presensi tidak valid atau sesi sudah berakhir!';
        }
    }
}

// Check for success parameter
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'success:Presensi berhasil!';
}

// Now include header AFTER handling POST
require_once '../../includes/header.php';

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
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "showSuccess('" . addslashes($msg[1]) . "');";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Presensi</h2>
    <p>Ikuti pelajaran aktif dan lakukan presensi</p>
</div>

<!-- Form Input Kode Presensi -->
<div class="row mb-4">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key"></i> Input Kode Presensi</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="presensiForm" action="presensi.php" onsubmit="return validatePresensi()">
                    <input type="hidden" name="action" value="presensi">
                    <div class="mb-3">
                        <label class="form-label">Masukkan Kode Presensi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-center fs-4 fw-bold" name="kode_presensi" id="kode_presensi"
                            placeholder="ABCD12" maxlength="10" style="letter-spacing: 0.5em;" required autocomplete="off" value="<?php echo isset($_POST['kode_presensi']) ? htmlspecialchars($_POST['kode_presensi']) : ''; ?>">
                        <small class="text-muted">Masukkan kode yang diberikan oleh guru (minimal 3 karakter)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="btnPresensi">
                        <i class="bi bi-check-circle"></i> Presensi
                    </button>
                </form>
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
                                                    'hadir' => 'Hadir',
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
function validatePresensi() {
    const kode = document.getElementById('kode_presensi').value.trim().toUpperCase();
    if (!kode || kode.length < 3) {
        showError('Kode presensi minimal 3 karakter!');
        return false;
    }
    
    // Disable button to prevent double submit
    const btn = document.getElementById('btnPresensi');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...';
    
    // Allow form to submit normally
    return true;
}

$(document).ready(function() {
    <?php if (count($my_presensi) > 0): ?>
    initDataTable('#presensiTable', {
        order: [[0, 'desc']] // Sort by tanggal descending
    });
    <?php endif; ?>
    
    // Auto uppercase kode presensi
    $('#kode_presensi').on('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
    
    // Clear form after successful presensi
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    $('#kode_presensi').val('');
    // Re-enable button in case of redirect
    $('#btnPresensi').prop('disabled', false).html('<i class="bi bi-check-circle"></i> Presensi');
    <?php endif; ?>
    
    // Prevent double submit
    $('#presensiForm').on('submit', function(e) {
        const btn = $('#btnPresensi');
        if (btn.prop('disabled')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

