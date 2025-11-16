<?php
$page_title = 'Jadwal Pelajaran';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get siswa info including kelas_id
$stmt = $conn->prepare("SELECT kelas_id FROM users WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa_info = $stmt->get_result()->fetch_assoc();
$kelas_id = $siswa_info['kelas_id'] ?? null;
$stmt->close();

// Show schedule for this week and next week (2 weeks ahead)
// Start from Monday this week, end on Sunday next week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday next week'));

// Get jadwal for selected week based on siswa's kelas - OPTIMIZED: Single query with JOINs
if ($kelas_id) {
    $stmt = $conn->prepare("SELECT jp.*, mp.id as mata_pelajaran_id, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru, k.nama_kelas
        FROM jadwal_pelajaran jp
        JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
        JOIN users u ON mp.guru_id = u.id
        JOIN kelas k ON jp.kelas_id = k.id
        WHERE jp.kelas_id = ? AND jp.tanggal BETWEEN ? AND ?
        ORDER BY jp.tanggal ASC, jp.jam_mulai ASC");
    $stmt->bind_param("iss", $kelas_id, $week_start, $week_end);
    $stmt->execute();
    $jadwal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // OPTIMIZED: Get all sesi and presensi in batch queries (not N+1 queries)
    $presensi_status = [];
    if (!empty($jadwal)) {
        $jadwal_ids = array_column($jadwal, 'id');
        $mata_pelajaran_ids = array_unique(array_column($jadwal, 'mata_pelajaran_id'));
        
        // Build condition for batch query sesi_pelajaran
        // Get all active sesi that match any of the jadwal (by mata_pelajaran_id, tanggal, jam_mulai)
        $sesi_map = [];
        
        // Use IN clause for batch query instead of loop
        if (!empty($mata_pelajaran_ids)) {
            $placeholders = implode(',', array_fill(0, count($mata_pelajaran_ids), '?'));
            $query = "SELECT sp.id as sesi_id, sp.mata_pelajaran_id, sp.kode_presensi, sp.status as sesi_status,
                DATE(sp.waktu_mulai) as sesi_tanggal, TIME(sp.waktu_mulai) as sesi_jam_mulai,
                CASE 
                    WHEN NOW() < sp.waktu_mulai THEN 'belum_mulai'
                    WHEN NOW() BETWEEN sp.waktu_mulai AND sp.waktu_selesai THEN 'berlangsung'
                    ELSE 'selesai'
                END as status_waktu
                FROM sesi_pelajaran sp
                WHERE sp.mata_pelajaran_id IN ($placeholders)
                AND DATE(sp.waktu_mulai) BETWEEN ? AND ?
                AND sp.status = 'aktif'
                ORDER BY sp.created_at DESC";
            
            $types = str_repeat('i', count($mata_pelajaran_ids)) . 'ss';
            $params = array_merge($mata_pelajaran_ids, [$week_start, $week_end]);
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                // Use call_user_func_array for dynamic parameter binding with references
                $bind_params = [$types];
                foreach ($params as &$param) {
                    $bind_params[] = &$param;
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_params);
                $stmt->execute();
                $all_sesi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                // Map sesi by mata_pelajaran_id + tanggal + jam_mulai for quick lookup
                foreach ($all_sesi as $sesi) {
                    $key = $sesi['mata_pelajaran_id'] . '|' . $sesi['sesi_tanggal'] . '|' . $sesi['sesi_jam_mulai'];
                    if (!isset($sesi_map[$key]) || $sesi['sesi_id'] > $sesi_map[$key]['sesi_id']) {
                        $sesi_map[$key] = $sesi;
                    }
                }
            }
        }
        
        // Get all presensi for this siswa in one query
        $presensi_sesi_ids = [];
        foreach ($sesi_map as $sesi) {
            if (isset($sesi['sesi_id'])) {
                $presensi_sesi_ids[] = $sesi['sesi_id'];
            }
        }
        $presensi_sesi_map = [];
        if (!empty($presensi_sesi_ids)) {
            $placeholders = implode(',', array_fill(0, count($presensi_sesi_ids), '?'));
            $query = "SELECT sesi_pelajaran_id FROM presensi WHERE sesi_pelajaran_id IN ($placeholders) AND siswa_id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $types = str_repeat('i', count($presensi_sesi_ids)) . 'i';
                $params = array_merge($presensi_sesi_ids, [$siswa_id]);
                // Use call_user_func_array for dynamic parameter binding with references
                $bind_params = [$types];
                foreach ($params as &$param) {
                    $bind_params[] = &$param;
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $presensi_sesi_map[$row['sesi_pelajaran_id']] = true;
                }
                $stmt->close();
            }
        }
        
        // Build presensi_status array by matching jadwal with sesi
        foreach ($jadwal as $j) {
            // Create matching key: mata_pelajaran_id|tanggal|jam_mulai (format jam_mulai from TIME() function)
            $jam_mulai_formatted = date('H:i:s', strtotime($j['jam_mulai'])); // Ensure consistent format
            $key = $j['mata_pelajaran_id'] . '|' . $j['tanggal'] . '|' . $jam_mulai_formatted;
            $sesi = $sesi_map[$key] ?? null;
            
            $sudah_presensi = false;
            if ($sesi && isset($sesi['sesi_id'])) {
                $sudah_presensi = isset($presensi_sesi_map[$sesi['sesi_id']]);
            }
            
            $presensi_status[$j['id']] = [
                'sesi' => $sesi,
                'sudah_presensi' => $sudah_presensi
            ];
        }
    }
} else {
    $jadwal = [];
    $presensi_status = [];
}

// Group jadwal by date
$jadwal_by_date = [];
foreach ($jadwal as $j) {
    $date = $j['tanggal'];
    if (!isset($jadwal_by_date[$date])) {
        $jadwal_by_date[$date] = [];
    }
    $jadwal_by_date[$date][] = $j;
}

// Get kelas name
$kelas_name = '';
if ($kelas_id) {
    $stmt = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $kelas_info = $stmt->get_result()->fetch_assoc();
    $kelas_name = $kelas_info['nama_kelas'] ?? '';
    $stmt->close();
}

$conn->close();

// Day names in Indonesian
$day_names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
?>

<div class="page-header">
    <h2><i class="bi bi-calendar-week"></i> Jadwal Pelajaran</h2>
    <p>Jadwal pelajaran untuk kelas <strong><?php echo htmlspecialchars($kelas_name ?: 'Belum ditentukan'); ?></strong></p>
</div>

<?php if (!$kelas_id): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Anda belum terdaftar di kelas manapun. Silakan hubungi administrator untuk mengatur kelas Anda.
    </div>
<?php else: ?>

<!-- Weekly Schedule Table -->
<div class="row">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-body"><!-- CUSTOM: Padding diatur di CSS section di bawah (line 289) -->
                <?php if (empty($jadwal)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">Tidak ada jadwal untuk periode ini</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="jadwalTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hari</th>
                                    <th>Jam</th>
                                    <th>Mapel</th>
                                    <th>Kode</th>
                                    <th>Guru</th>
                                    <th>Ruangan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $status_text = [
                                    'terjadwal' => 'Terjadwal',
                                    'berlangsung' => 'Berlangsung',
                                    'selesai' => 'Selesai',
                                    'dibatalkan' => 'Dibatalkan'
                                ];
                                
                                foreach ($jadwal as $j): 
                                    $current_date = $j['tanggal'];
                                    $day_name = $day_names[date('w', strtotime($current_date))];
                                    $is_today = $current_date == date('Y-m-d');
                                ?>
                                    <tr class="<?php echo $is_today ? 'table-primary' : ''; ?>">
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($current_date)); ?>
                                            <?php if ($is_today): ?>
                                                <span class="badge bg-primary ms-1">Hari Ini</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo $day_name; ?></strong></td>
                                        <td>
                                            <strong><?php echo date('H:i', strtotime($j['jam_mulai'])); ?></strong> - 
                                            <?php echo date('H:i', strtotime($j['jam_selesai'])); ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($j['nama_pelajaran']); ?></strong></td>
                                        <td>
                                            <?php if ($j['kode_pelajaran']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($j['kode_pelajaran']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($j['nama_guru']); ?></td>
                                        <td>
                                            <?php if ($j['ruangan']): ?>
                                                <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($j['ruangan']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $j['status'] == 'berlangsung' ? 'success' : 
                                                    ($j['status'] == 'selesai' ? 'info' : 
                                                    ($j['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                                            ?>">
                                                <?php echo $status_text[$j['status']] ?? ucfirst($j['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $jadwal_presensi = $presensi_status[$j['id']] ?? null;
                                            $sesi = $jadwal_presensi['sesi'] ?? null;
                                            $sudah_presensi = $jadwal_presensi['sudah_presensi'] ?? false;
                                            // Gunakan $current_date yang sudah didefinisikan di atas
                                            $is_today_check = $current_date == date('Y-m-d');
                                            $is_berlangsung_or_terjadwal = in_array($j['status'] ?? '', ['berlangsung', 'terjadwal']);
                                            
                                            // Tampilkan form input kode jika:
                                            // 1. Belum presensi DAN
                                            // 2. (Hari ini dengan status berlangsung/terjadwal ATAU ada sesi aktif)
                                            $show_form = !$sudah_presensi && (($is_today_check && $is_berlangsung_or_terjadwal) || ($sesi && ($sesi['status_waktu'] == 'berlangsung' || $sesi['status_waktu'] == 'belum_mulai')));
                                            
                                            if ($sudah_presensi): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Sudah Presensi
                                                </span>
                                            <?php elseif ($show_form): ?>
                                                <form class="d-inline presensi-form-inline" data-jadwal-id="<?php echo $j['id']; ?>">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" 
                                                               class="form-control form-control-sm kode-presensi-input" 
                                                               name="kode_presensi" 
                                                               placeholder="Kode" 
                                                               maxlength="10" 
                                                               required
                                                               autocomplete="off"
                                                               style="text-transform: uppercase;">
                                                        <button type="submit" class="btn btn-primary btn-sm btn-submit-presensi">
                                                            <i class="bi bi-send"></i> Kirim
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<style>
/* ============================================
   TABEL JADWAL - STYLE SAMA DENGAN PRESENSI
   ============================================ */

/* Container untuk tabel - scroll hanya jika diperlukan */
.table-responsive {
    position: relative;
    max-height: calc(100vh - 300px);
    overflow-y: auto;
    overflow-x: auto; /* Scroll horizontal hanya muncul jika diperlukan */
    width: 100%;
    -webkit-overflow-scrolling: touch;
}

/* Menggunakan style standar dari style.css (.dashboard-card .table) */
/* Sticky Header - tetap terlihat saat scroll */
.table-responsive table#jadwalTable thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-responsive table#jadwalTable thead th {
    position: sticky;
    top: 0;
    z-index: 11;
}

/* Responsif - horizontal scroll di layar kecil */
@media (max-width: 992px) {
    .table-responsive {
        overflow-x: auto;
    }
}

/* ============================================
   CUSTOM UKURAN CARD JADWAL - EDIT DI SINI
   ============================================ */
   
/* Pastikan tidak ada overflow di level page */
.dashboard-card .card-body {
    overflow-x: hidden !important;
}

.container-fluid {
    overflow-x: hidden !important;
}

.content {
    overflow-x: hidden !important;
}

.wrapper {
    overflow-x: hidden !important;
}

body {
    overflow-x: hidden !important;
}
</style>

<script>
// Wait for jQuery to be available
(function() {
    function initJadwal() {
        // Check if jQuery is loaded
        if (typeof jQuery === 'undefined') {
            setTimeout(initJadwal, 100);
            return;
        }
        
        const $ = jQuery;
        
        $(document).ready(function() {
            // DataTables removed - using native table with fixed header
            
            // Auto uppercase kode presensi input using vanilla JS
            document.querySelectorAll('.kode-presensi-input').forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                });
            });
            
            // Handle submit presensi using vanilla JS
            document.querySelectorAll('.presensi-form-inline').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const btn = form.querySelector('.btn-submit-presensi');
                    const kodeInput = form.querySelector('.kode-presensi-input');
                    const kode = kodeInput.value.trim().toUpperCase();
                    
                    // Update input value
                    kodeInput.value = kode;
                    
                    // Validation
                    if (!kode || kode.length < 3) {
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
                    
                    // Disable button and input
                    btn.disabled = true;
                    kodeInput.disabled = true;
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    
                    // Submit presensi
                    const formData = new FormData();
                    formData.append('action', 'presensi');
                    formData.append('kode_presensi', kode);
                    
                    fetch('submit_presensi.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        
                        // Check if response is OK
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Response not OK:', text);
                                throw new Error('Network response was not ok: ' + response.status);
                            });
                        }
                        
                        // Try to parse as JSON
                        return response.text().then(text => {
                            console.log('Response text:', text);
                            try {
                                const data = JSON.parse(text);
                                return data;
                            } catch (e) {
                                console.error('JSON parse error:', e);
                                console.error('Response text:', text);
                                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(data => {
                        console.log('Parsed data:', data);
                        
                        if (data && data.success) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message || 'Presensi berhasil!',
                                    timer: 2000,
                                    showConfirmButton: false,
                                    allowOutsideClick: false
                                }).then(() => {
                                    // Reload page to update status
                                    window.location.reload();
                                });
                            } else {
                                alert(data.message || 'Presensi berhasil!');
                                window.location.reload();
                            }
                        } else {
                            btn.disabled = false;
                            kodeInput.disabled = false;
                            btn.innerHTML = originalHtml;
                            
                            const errorMessage = (data && data.message) ? data.message : 'Gagal melakukan presensi';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: (data && data.expired) ? 'error' : 'error',
                                    title: (data && data.expired) ? 'Kode Kadaluarsa' : 'Error',
                                    text: errorMessage,
                                    timer: 3000
                                });
                            } else {
                                alert(errorMessage);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        btn.disabled = false;
                        kodeInput.disabled = false;
                        btn.innerHTML = originalHtml;
                        
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Terjadi kesalahan saat melakukan presensi: ' + (error.message || 'Unknown error'),
                                timer: 3000
                            });
                        } else {
                            alert('Terjadi kesalahan saat melakukan presensi: ' + (error.message || 'Unknown error'));
                        }
                    });
                    
                    return false;
                });
            });
        });
    }
    
    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJadwal);
    } else {
        initJadwal();
    }
})();
</script>

<?php require_once '../../includes/footer.php'; ?>

