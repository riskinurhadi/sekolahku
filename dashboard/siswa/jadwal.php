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
            <div class="card-body">
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
                                                <form class="d-inline presensi-form-inline" data-jadwal-id="<?php echo $j['id']; ?>" style="min-width: 180px;">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" 
                                                               class="form-control form-control-sm kode-presensi-input" 
                                                               name="kode_presensi" 
                                                               placeholder="Kode" 
                                                               maxlength="10" 
                                                               required
                                                               autocomplete="off"
                                                               style="text-transform: uppercase; font-size: 0.8rem; width: 100px;">
                                                        <button type="submit" class="btn btn-primary btn-sm btn-submit-presensi" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                            <i class="bi bi-send"></i>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
$(document).ready(function() {
    $('#jadwalTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[0, 'asc'], [2, 'asc']], // Sort by date, then time
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        columnDefs: [
            { orderable: true, targets: [0, 1, 2, 3, 4, 5, 6] },
            { orderable: false, targets: [7] } // Aksi tidak bisa di-sort
        ]
    });
    
    // Auto uppercase kode presensi input
    $('.kode-presensi-input').on('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
    
    // Handle submit presensi
    $('.presensi-form-inline').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btn = form.find('.btn-submit-presensi');
        const kodeInput = form.find('.kode-presensi-input');
        const kode = kodeInput.val().trim().toUpperCase();
        
        // Update input value
        kodeInput.val(kode);
        
        // Validation
        if (!kode || kode.length < 3) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Kode presensi minimal 3 karakter!',
                timer: 2000
            });
            return false;
        }
        
        // Disable button and input
        btn.prop('disabled', true);
        kodeInput.prop('disabled', true);
        const originalHtml = btn.html();
        btn.html('<span class="spinner-border spinner-border-sm"></span>');
        
        // Submit presensi
        const formData = new FormData();
        formData.append('action', 'presensi');
        formData.append('kode_presensi', kode);
        
        fetch('submit_presensi.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is OK
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Response is not JSON:', text);
                    throw new Error('Response is not JSON');
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message || 'Presensi berhasil!',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Reload page to update status
                    window.location.reload();
                });
            } else {
                btn.prop('disabled', false);
                kodeInput.prop('disabled', false);
                btn.html(originalHtml);
                
                Swal.fire({
                    icon: data.expired ? 'error' : 'error',
                    title: data.expired ? 'Kode Kadaluarsa' : 'Error',
                    text: data.message || 'Gagal melakukan presensi',
                    timer: 3000
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            console.error('Error details:', error.message);
            btn.prop('disabled', false);
            kodeInput.prop('disabled', false);
            btn.html(originalHtml);
            
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat melakukan presensi: ' + error.message,
                timer: 3000
            });
        });
        
        return false;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

