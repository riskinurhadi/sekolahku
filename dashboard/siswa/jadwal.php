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

// Always show this week's schedule
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Get jadwal for selected week based on siswa's kelas
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
    
    // Check presensi status for each jadwal
    $jadwal_ids = array_column($jadwal, 'id');
    $presensi_status = [];
    if (!empty($jadwal_ids)) {
        // Get presensi status and active sesi for each jadwal
        foreach ($jadwal as $j) {
            $tanggal = $j['tanggal'];
            $jam_mulai = $j['jam_mulai'];
            $mata_pelajaran_id = $j['mata_pelajaran_id'];
            
            // Check if there's an active sesi for this jadwal
            $stmt = $conn->prepare("SELECT sp.id as sesi_id, sp.kode_presensi, sp.status as sesi_status,
                CASE 
                    WHEN NOW() < sp.waktu_mulai THEN 'belum_mulai'
                    WHEN NOW() BETWEEN sp.waktu_mulai AND sp.waktu_selesai THEN 'berlangsung'
                    ELSE 'selesai'
                END as status_waktu
                FROM sesi_pelajaran sp
                WHERE sp.mata_pelajaran_id = ? 
                AND DATE(sp.waktu_mulai) = ? 
                AND TIME(sp.waktu_mulai) = ? 
                AND sp.status = 'aktif'
                ORDER BY sp.created_at DESC LIMIT 1");
            $stmt->bind_param("iss", $mata_pelajaran_id, $tanggal, $jam_mulai);
            $stmt->execute();
            $sesi = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Check if siswa already presensi for this sesi
            $sudah_presensi = false;
            if ($sesi) {
                $stmt = $conn->prepare("SELECT id FROM presensi WHERE sesi_pelajaran_id = ? AND siswa_id = ?");
                $stmt->bind_param("ii", $sesi['sesi_id'], $siswa_id);
                $stmt->execute();
                $sudah_presensi = $stmt->get_result()->num_rows > 0;
                $stmt->close();
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
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-week"></i> Jadwal Pelajaran Minggu Ini
                    <small class="text-muted ms-2">
                        (<?php echo date('d/m/Y', strtotime($week_start)); ?> - <?php echo date('d/m/Y', strtotime($week_end)); ?>)
                    </small>
                </h5>
            </div>
            <div class="card-body"><!-- CUSTOM: Padding diatur di CSS section di bawah (line 289) -->
                <?php if (empty($jadwal)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">Tidak ada jadwal untuk minggu ini</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="jadwalTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hari</th>
                                    <th>Jam</th>
                                    <th>Mata Pelajaran</th>
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
   TABEL JADWAL - RESPONSIVE DESIGN
   ============================================ */

/* Container untuk tabel - hanya scroll jika diperlukan */
.table-responsive {
    position: relative;
    max-height: calc(100vh - 300px);
    overflow-y: auto;
    overflow-x: auto; /* Scroll horizontal hanya muncul jika konten lebih lebar */
    width: 100%;
    -webkit-overflow-scrolling: touch;
}

/* Tabel responsif - gunakan space secara efisien */
.table-responsive table#jadwalTable {
    width: 100%;
    margin-bottom: 0;
    table-layout: auto; /* Auto layout untuk fleksibilitas - kolom menyesuaikan konten */
    border-collapse: collapse;
}

/* Di layar lebar, tabel akan otomatis menggunakan semua space */
@media (min-width: 1400px) {
    .table-responsive table#jadwalTable {
        width: 100%;
    }
    
    /* Biarkan kolom mengembang jika ada space */
    .table-responsive table#jadwalTable th:nth-child(4),
    .table-responsive table#jadwalTable td:nth-child(4) {
        max-width: none; /* Mata Pelajaran bisa lebih lebar */
    }
    
    .table-responsive table#jadwalTable th:nth-child(6),
    .table-responsive table#jadwalTable td:nth-child(6) {
        max-width: none; /* Guru bisa lebih lebar */
    }
}

/* Optimize kolom width untuk efisiensi space */
.table-responsive table#jadwalTable th,
.table-responsive table#jadwalTable td {
    padding: 0.75rem 0.5rem !important;
    vertical-align: middle;
    white-space: nowrap;
    font-size: 0.875rem;
}

/* Kolom khusus - lebih compact */
.table-responsive table#jadwalTable th:nth-child(1),
.table-responsive table#jadwalTable td:nth-child(1) {
    min-width: 120px; /* Tanggal */
}

.table-responsive table#jadwalTable th:nth-child(2),
.table-responsive table#jadwalTable td:nth-child(2) {
    min-width: 80px; /* Hari */
}

.table-responsive table#jadwalTable th:nth-child(3),
.table-responsive table#jadwalTable td:nth-child(3) {
    min-width: 110px; /* Jam */
}

.table-responsive table#jadwalTable th:nth-child(4),
.table-responsive table#jadwalTable td:nth-child(4) {
    min-width: 150px; /* Mata Pelajaran */
    max-width: 200px;
    white-space: normal;
    word-wrap: break-word;
}

.table-responsive table#jadwalTable th:nth-child(5),
.table-responsive table#jadwalTable td:nth-child(5) {
    min-width: 70px; /* Kode */
}

.table-responsive table#jadwalTable th:nth-child(6),
.table-responsive table#jadwalTable td:nth-child(6) {
    min-width: 100px; /* Guru */
    max-width: 150px;
}

.table-responsive table#jadwalTable th:nth-child(7),
.table-responsive table#jadwalTable td:nth-child(7) {
    min-width: 100px; /* Ruangan */
}

.table-responsive table#jadwalTable th:nth-child(8),
.table-responsive table#jadwalTable td:nth-child(8) {
    min-width: 100px; /* Status */
}

.table-responsive table#jadwalTable th:nth-child(9),
.table-responsive table#jadwalTable td:nth-child(9) {
    min-width: 140px; /* Aksi */
    white-space: nowrap;
}

/* Fixed/Sticky Header */
.table-responsive table#jadwalTable thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #ffffff;
}

.table-responsive table#jadwalTable thead th {
    background: #f8f9fa !important;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 11;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    font-size: 0.875rem;
}

/* Badge lebih compact */
.table-responsive table#jadwalTable .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    font-weight: 500;
    white-space: nowrap;
}

/* Form presensi lebih compact */
.table-responsive table#jadwalTable .presensi-form-inline {
    display: inline-block;
    min-width: 0;
}

.table-responsive table#jadwalTable .input-group-sm {
    font-size: 0.875rem;
}

.table-responsive table#jadwalTable .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

/* Responsive breakpoints */
@media (max-width: 1400px) {
    .table-responsive table#jadwalTable th,
    .table-responsive table#jadwalTable td {
        padding: 0.65rem 0.4rem !important;
        font-size: 0.8rem;
    }
    
    .table-responsive table#jadwalTable th:nth-child(4),
    .table-responsive table#jadwalTable td:nth-child(4) {
        min-width: 130px;
        max-width: 180px;
    }
}

@media (max-width: 1200px) {
    .table-responsive table#jadwalTable th,
    .table-responsive table#jadwalTable td {
        padding: 0.6rem 0.35rem !important;
        font-size: 0.775rem;
    }
    
    .table-responsive table#jadwalTable th:nth-child(1),
    .table-responsive table#jadwalTable td:nth-child(1) {
        min-width: 100px;
    }
    
    .table-responsive table#jadwalTable th:nth-child(2),
    .table-responsive table#jadwalTable td:nth-child(2) {
        min-width: 70px;
    }
    
    .table-responsive table#jadwalTable th:nth-child(4),
    .table-responsive table#jadwalTable td:nth-child(4) {
        min-width: 120px;
        max-width: 160px;
    }
}

@media (max-width: 992px) {
    .table-responsive {
        overflow-x: auto; /* Enable horizontal scroll di tablet */
    }
    
    .table-responsive table#jadwalTable {
        min-width: 900px; /* Force minimum width untuk tablet */
    }
}

/* ============================================
   CUSTOM UKURAN CARD JADWAL - EDIT DI SINI
   ============================================ */
   
/* Ukuran padding di dalam card */
.dashboard-card .card-body {
    overflow-x: hidden !important;
    overflow-y: visible;
    width: 100%;
    max-width: 100%;
    /* CUSTOM: Ubah padding di sini untuk mengatur ruang dalam card */
    padding: 20px !important;  /* Default: 20px. Ubah ke: 16px, 24px, 32px, dll */
}

.dashboard-card {
    overflow: hidden !important;
    width: 100%;
    max-width: 100%;
    /* CUSTOM: Bisa tambahkan margin-bottom untuk jarak antar card */
    /* margin-bottom: 24px; */
}

/* ============================================
   CUSTOM UKURAN CARD JADWAL - EDIT DI SINI
   ============================================ */

/* Ukuran padding kiri-kanan container card */
.row > .col-12 {
    padding-left: 15px !important;
    padding-right: 15px !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

/* Ukuran padding kiri-kanan halaman */
.container-fluid {
    overflow-x: hidden !important;
    max-width: 100% !important;
    width: 100% !important;
    padding-left: 24px !important;
    padding-right: 24px !important;
    box-sizing: border-box !important;
}

/* Pastikan row tidak melebihi container */
.row {
    margin-left: 0 !important;
    margin-right: 0 !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
    width: 100% !important;
}

/* Pastikan content area tidak overflow */
.content {
    overflow-x: hidden !important;
    max-width: calc(100vw - var(--sidebar-width)) !important;
    width: calc(100% - var(--sidebar-width)) !important;
    margin-left: var(--sidebar-width) !important;
    box-sizing: border-box !important;
}

/* Pastikan page-content tidak overflow */
.page-content {
    overflow-x: hidden !important;
    max-width: 100% !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

/* Pastikan wrapper utama tidak overflow */
.wrapper {
    overflow-x: hidden !important;
    max-width: 100vw !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

/* Pastikan body tidak overflow */
body {
    overflow-x: hidden !important;
    max-width: 100vw !important;
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

