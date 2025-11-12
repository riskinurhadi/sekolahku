<?php
$page_title = 'Mulai Pelajaran';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Get mata pelajaran untuk dropdown
$stmt = $conn->prepare("SELECT * FROM mata_pelajaran WHERE guru_id = ? ORDER BY nama_pelajaran");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$mata_pelajaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'start') {
            $mata_pelajaran_id = $_POST['mata_pelajaran_id'];
            $waktu_mulai = $_POST['waktu_mulai'];
            $waktu_selesai = $_POST['waktu_selesai'];
            
            // Generate kode presensi unik (6 karakter alphanumeric)
            do {
                $kode_presensi = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                $check_stmt = $conn->prepare("SELECT id FROM sesi_pelajaran WHERE kode_presensi = ?");
                $check_stmt->bind_param("s", $kode_presensi);
                $check_stmt->execute();
                $check = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();
            } while ($check);
            
            $stmt = $conn->prepare("INSERT INTO sesi_pelajaran (mata_pelajaran_id, guru_id, kode_presensi, waktu_mulai, waktu_selesai, status) VALUES (?, ?, ?, ?, ?, 'aktif')");
            $stmt->bind_param("iisss", $mata_pelajaran_id, $guru_id, $kode_presensi, $waktu_mulai, $waktu_selesai);
            
            if ($stmt->execute()) {
                $message = 'success:Sesi pelajaran berhasil dimulai! Kode presensi: <strong>' . $kode_presensi . '</strong>';
            } else {
                $message = 'error:Gagal memulai sesi pelajaran!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'end') {
            $sesi_id = $_POST['sesi_id'];
            $stmt = $conn->prepare("UPDATE sesi_pelajaran SET status = 'selesai' WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("ii", $sesi_id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Sesi pelajaran berhasil diakhiri!';
            } else {
                $message = 'error:Gagal mengakhiri sesi pelajaran!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'regenerate') {
            $sesi_id = $_POST['sesi_id'];
            
            // Generate kode baru
            do {
                $kode_presensi = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                $check_stmt = $conn->prepare("SELECT id FROM sesi_pelajaran WHERE kode_presensi = ? AND id != ?");
                $check_stmt->bind_param("si", $kode_presensi, $sesi_id);
                $check_stmt->execute();
                $check = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();
            } while ($check);
            
            $stmt = $conn->prepare("UPDATE sesi_pelajaran SET kode_presensi = ? WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("sii", $kode_presensi, $sesi_id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Kode presensi berhasil di-generate ulang! Kode baru: <strong>' . $kode_presensi . '</strong>';
            } else {
                $message = 'error:Gagal generate kode presensi!';
            }
            $stmt->close();
        }
    }
}

// Get active sessions
$stmt = $conn->prepare("SELECT sp.*, mp.nama_pelajaran 
    FROM sesi_pelajaran sp 
    JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id 
    WHERE sp.guru_id = ? AND sp.status = 'aktif' 
    ORDER BY sp.waktu_mulai DESC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$active_sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "Swal.fire({icon: 'success', title: 'Berhasil!', html: '" . addslashes($msg[1]) . "', timer: 3000});";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Mulai Pelajaran</h2>
    <p>Mulai sesi pelajaran dan generate kode presensi untuk siswa</p>
</div>

<!-- Form Mulai Pelajaran -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-play-circle"></i> Mulai Sesi Pelajaran Baru</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="startSessionForm">
                    <input type="hidden" name="action" value="start">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select class="form-select" name="mata_pelajaran_id" required>
                                <option value="">Pilih Mata Pelajaran</option>
                                <?php foreach ($mata_pelajaran as $mp): ?>
                                    <option value="<?php echo $mp['id']; ?>"><?php echo htmlspecialchars($mp['nama_pelajaran']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="waktu_mulai" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="waktu_selesai" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-circle"></i> Mulai Pelajaran
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Sesi Aktif -->
<?php if (count($active_sessions) > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Sesi Pelajaran Aktif</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mata Pelajaran</th>
                                <th>Kode Presensi</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Selesai</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_sessions as $session): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($session['nama_pelajaran']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($session['kode_presensi']); ?></span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($session['waktu_mulai'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($session['waktu_selesai'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-info" onclick="copyKode('<?php echo $session['kode_presensi']; ?>')">
                                                <i class="bi bi-copy"></i> Copy Kode
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Generate kode baru? Kode lama tidak akan bisa digunakan lagi.');">
                                                <input type="hidden" name="action" value="regenerate">
                                                <input type="hidden" name="sesi_id" value="<?php echo $session['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-arrow-clockwise"></i> Generate Ulang
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Akhiri sesi pelajaran ini?');">
                                                <input type="hidden" name="action" value="end">
                                                <input type="hidden" name="sesi_id" value="<?php echo $session['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-stop-circle"></i> Akhiri
                                                </button>
                                            </form>
                                        </div>
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
<?php endif; ?>

<script>
function copyKode(kode) {
    navigator.clipboard.writeText(kode).then(function() {
        showSuccess('Kode presensi berhasil disalin: ' + kode);
    }, function() {
        // Fallback untuk browser lama
        const textarea = document.createElement('textarea');
        textarea.value = kode;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showSuccess('Kode presensi berhasil disalin: ' + kode);
    });
}

// Set default waktu mulai dan selesai
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const startTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const endTime = new Date(now.getTime() + 2 * 60 * 60 * 1000 - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    
    document.querySelector('input[name="waktu_mulai"]').value = startTime;
    document.querySelector('input[name="waktu_selesai"]').value = endTime;
});
</script>

<?php require_once '../../includes/footer.php'; ?>

