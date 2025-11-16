<?php
$page_title = 'Mulai Pelajaran';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get guru info including spesialisasi
$stmt = $conn->prepare("SELECT spesialisasi FROM users WHERE id = ?");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$guru_info = $stmt->get_result()->fetch_assoc();
$spesialisasi = $guru_info['spesialisasi'] ?? '';
$stmt->close();

// Handle form submission - Generate and copy kode presensi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'generate_kode') {
        $jadwal_id = intval($_POST['jadwal_id']);
        
        // Verify that this jadwal belongs to a mata pelajaran taught by this guru
        $stmt = $conn->prepare("SELECT jp.*, mp.id as mata_pelajaran_id, mp.nama_pelajaran
            FROM jadwal_pelajaran jp
            JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
            WHERE jp.id = ? AND mp.guru_id = ?");
        $stmt->bind_param("ii", $jadwal_id, $guru_id);
        $stmt->execute();
        $jadwal = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($jadwal) {
            // Generate unique code (6 characters alphanumeric)
            function generateKodePresensi($conn) {
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude confusing chars like 0, O, I, 1
                do {
                    $kode = '';
                    for ($i = 0; $i < 6; $i++) {
                        $kode .= $chars[rand(0, strlen($chars) - 1)];
                    }
                    // Check if code already exists
                    $check = $conn->prepare("SELECT id FROM sesi_pelajaran WHERE kode_presensi = ?");
                    $check->bind_param("s", $kode);
                    $check->execute();
                    $exists = $check->get_result()->num_rows > 0;
                    $check->close();
                } while ($exists);
                return $kode;
            }
            
            $kode_presensi = generateKodePresensi($conn);
            
            // Calculate waktu_mulai and waktu_selesai from jadwal
            $tanggal = $jadwal['tanggal'];
            $waktu_mulai = $tanggal . ' ' . $jadwal['jam_mulai'];
            $waktu_selesai = $tanggal . ' ' . $jadwal['jam_selesai'];
            
            // Create sesi_pelajaran
            $stmt = $conn->prepare("INSERT INTO sesi_pelajaran (mata_pelajaran_id, guru_id, kode_presensi, waktu_mulai, waktu_selesai, status) 
                VALUES (?, ?, ?, ?, ?, 'aktif')");
            $stmt->bind_param("iisss", $jadwal['mata_pelajaran_id'], $guru_id, $kode_presensi, $waktu_mulai, $waktu_selesai);
            
            if ($stmt->execute()) {
                $stmt->close();
                // Return JSON response for AJAX
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'kode' => $kode_presensi, 'message' => 'Kode presensi berhasil dibuat!']);
                exit();
            } else {
                $message = 'error:Gagal membuat kode presensi!';
            }
            $stmt->close();
        } else {
            $message = 'error:Jadwal tidak ditemukan atau tidak memiliki akses!';
        }
    }
}

// Get jadwal minggu ini
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT jp.*, mp.id as mata_pelajaran_id, mp.nama_pelajaran, mp.kode_pelajaran, k.nama_kelas
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN kelas k ON jp.kelas_id = k.id
    WHERE mp.guru_id = ? AND jp.tanggal BETWEEN ? AND ?
    ORDER BY jp.tanggal ASC, jp.jam_mulai ASC");
$stmt->bind_param("iss", $guru_id, $week_start, $week_end);
$stmt->execute();
$jadwal_minggu_ini = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get existing sesi_pelajaran for each jadwal to check if code already exists
$kode_presensi_map = [];
foreach ($jadwal_minggu_ini as $jadwal) {
    $tanggal = $jadwal['tanggal'];
    $jam_mulai = $jadwal['jam_mulai'];
    $mata_pelajaran_id = $jadwal['mata_pelajaran_id'] ?? null;
    
    if ($mata_pelajaran_id) {
        // Try to find existing sesi_pelajaran that matches this jadwal
        $stmt = $conn->prepare("SELECT kode_presensi FROM sesi_pelajaran 
            WHERE mata_pelajaran_id = ? 
            AND guru_id = ? 
            AND DATE(waktu_mulai) = ? 
            AND TIME(waktu_mulai) = ? 
            AND status = 'aktif'
            ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("iiss", $mata_pelajaran_id, $guru_id, $tanggal, $jam_mulai);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $kode_presensi_map[$jadwal['id']] = $row['kode_presensi'];
        }
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
            echo "Swal.fire({icon: 'success', title: 'Berhasil!', html: '" . addslashes($msg[1]) . "', timer: 3000});";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-calendar-week"></i> Jadwal Pelajaran</h2>
    <p>
        <?php if ($spesialisasi): ?>
            Jadwal mengajar untuk <strong><?php echo htmlspecialchars($spesialisasi); ?></strong>. 
        <?php endif; ?>
        Lihat jadwal pelajaran Anda dan generate kode presensi untuk setiap jadwal.
    </p>
</div>

<!-- Jadwal Minggu Ini -->
<div class="dashboard-card">
    <div class="card-header">
        <h5><i class="bi bi-calendar-range"></i> Jadwal Minggu Ini</h5>
    </div>
    <div class="card-body">
        <?php if (empty($jadwal_minggu_ini)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h5>Tidak Ada Jadwal</h5>
                <p>Belum ada jadwal pelajaran untuk minggu ini.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Ruangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jadwal_minggu_ini as $jadwal): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($jadwal['tanggal'])); ?></td>
                                <td>
                                    <strong><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?></strong> - 
                                    <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></td>
                                <td><?php echo htmlspecialchars($jadwal['ruangan'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $jadwal['status'] == 'berlangsung' ? 'success' : 
                                            ($jadwal['status'] == 'selesai' ? 'info' : 
                                            ($jadwal['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                                    ?>">
                                        <?php 
                                        $status_text = [
                                            'terjadwal' => 'Terjadwal',
                                            'berlangsung' => 'Berlangsung',
                                            'selesai' => 'Selesai',
                                            'dibatalkan' => 'Dibatalkan'
                                        ];
                                        echo $status_text[$jadwal['status']] ?? ucfirst($jadwal['status']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $existing_kode = isset($kode_presensi_map[$jadwal['id']]) ? $kode_presensi_map[$jadwal['id']] : null;
                                    ?>
                                    <button type="button" class="btn btn-sm btn-primary generate-kode-btn" 
                                            data-jadwal-id="<?php echo $jadwal['id']; ?>"
                                            data-kode="<?php echo $existing_kode ? htmlspecialchars($existing_kode) : ''; ?>">
                                        <i class="bi bi-<?php echo $existing_kode ? 'clipboard-check' : 'key'; ?>"></i> 
                                        <?php echo $existing_kode ? 'Copy Kode' : 'Generate & Copy'; ?>
                                    </button>
                                    <?php if ($existing_kode): ?>
                                        <small class="d-block text-muted mt-1">Kode: <strong><?php echo htmlspecialchars($existing_kode); ?></strong></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>


<script>
// Handle generate and copy kode presensi
document.addEventListener('DOMContentLoaded', function() {
    const generateButtons = document.querySelectorAll('.generate-kode-btn');
    
    generateButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const jadwalId = this.getAttribute('data-jadwal-id');
            const existingKode = this.getAttribute('data-kode');
            
            if (existingKode) {
                // Just copy existing code
                copyToClipboard(existingKode);
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Kode presensi berhasil disalin: ' + existingKode,
                    timer: 2000
                });
            } else {
                // Generate new code
                const formData = new FormData();
                formData.append('action', 'generate_kode');
                formData.append('jadwal_id', jadwalId);
                
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        copyToClipboard(data.kode);
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Kode presensi berhasil dibuat dan disalin: ' + data.kode,
                            timer: 3000
                        });
                        // Update button and show code
                        this.innerHTML = '<i class="bi bi-clipboard-check"></i> Copy Kode';
                        this.setAttribute('data-kode', data.kode);
                        // Add small text below button
                        const small = document.createElement('small');
                        small.className = 'd-block text-muted mt-1';
                        small.innerHTML = 'Kode: <strong>' + data.kode + '</strong>';
                        this.parentElement.appendChild(small);
                        // Reload page after a short delay to update all buttons
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message || 'Gagal membuat kode presensi'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat membuat kode presensi'
                    });
                });
            }
        });
    });
    
    function copyToClipboard(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
        } catch (err) {
            console.error('Failed to copy:', err);
        }
        document.body.removeChild(textarea);
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

