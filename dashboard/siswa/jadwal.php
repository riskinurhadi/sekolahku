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

// Get filter date (default: today)
$filter_date = $_GET['tanggal'] ?? date('Y-m-d');
// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $filter_date = date('Y-m-d');
}
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));

// Get jadwal for selected week based on siswa's kelas
if ($kelas_id) {
    $stmt = $conn->prepare("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru, k.nama_kelas
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
} else {
    $jadwal = [];
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

<!-- Filter Week -->
<div class="row mb-4">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Pilih Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" value="<?php echo htmlspecialchars($filter_date); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Tampilkan Minggu Ini
                        </button>
                        <a href="?tanggal=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary">
                            <i class="bi bi-calendar-day"></i> Minggu Ini
                        </a>
                    </div>
                    <div class="col-md-4 text-end">
                        <small class="text-muted">
                            <i class="bi bi-calendar-range"></i> 
                            <?php echo date('d/m/Y', strtotime($week_start)); ?> - <?php echo date('d/m/Y', strtotime($week_end)); ?>
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Schedule -->
<div class="row">
    <?php
    // Loop through each day of the week
    for ($i = 0; $i < 7; $i++) {
        $current_date = date('Y-m-d', strtotime($week_start . " +$i days"));
        $day_name = $day_names[date('w', strtotime($current_date))];
        $is_today = $current_date == date('Y-m-d');
        $day_jadwal = $jadwal_by_date[$current_date] ?? [];
    ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="dashboard-card <?php echo $is_today ? 'border-primary shadow-sm' : ''; ?>">
                <div class="card-header <?php echo $is_today ? 'bg-primary text-white' : ''; ?>">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-day"></i> <?php echo $day_name; ?>
                        <?php if ($is_today): ?>
                            <span class="badge bg-light text-primary ms-2">Hari Ini</span>
                        <?php endif; ?>
                    </h5>
                    <small><?php echo date('d/m/Y', strtotime($current_date)); ?></small>
                </div>
                <div class="card-body" style="min-height: 300px; max-height: 500px; overflow-y: auto;">
                    <?php if (empty($day_jadwal)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">Tidak ada jadwal</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($day_jadwal as $j): ?>
                            <div class="card mb-2 border-start border-3 border-<?php 
                                echo $j['status'] == 'berlangsung' ? 'success' : 
                                    ($j['status'] == 'selesai' ? 'info' : 
                                    ($j['status'] == 'dibatalkan' ? 'warning' : 'primary')); 
                            ?>">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong class="text-primary"><?php echo date('H:i', strtotime($j['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($j['jam_selesai'])); ?></strong>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo $j['status'] == 'berlangsung' ? 'success' : 
                                                ($j['status'] == 'selesai' ? 'info' : 
                                                ($j['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                                        ?>">
                                            <?php 
                                            $status_text = [
                                                'terjadwal' => 'Terjadwal',
                                                'berlangsung' => 'Berlangsung',
                                                'selesai' => 'Selesai',
                                                'dibatalkan' => 'Dibatalkan'
                                            ];
                                            echo $status_text[$j['status']] ?? ucfirst($j['status']);
                                            ?>
                                        </span>
                                    </div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($j['nama_pelajaran']); ?></h6>
                                    <?php if ($j['kode_pelajaran']): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($j['kode_pelajaran']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mb-1">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($j['nama_guru']); ?>
                                    </small>
                                    <?php if ($j['ruangan']): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($j['ruangan']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($j['keterangan']): ?>
                                        <small class="text-muted d-block mt-2">
                                            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($j['keterangan']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endfor; ?>
</div>

<!-- Summary Card -->
<div class="row mt-4">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Ringkasan Minggu Ini</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-primary mb-0"><?php echo count($jadwal); ?></h3>
                            <small class="text-muted">Total Jadwal</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-success mb-0">
                                <?php echo count(array_filter($jadwal, function($j) { return $j['status'] == 'berlangsung'; })); ?>
                            </h3>
                            <small class="text-muted">Berlangsung</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-info mb-0">
                                <?php echo count(array_filter($jadwal, function($j) { return $j['status'] == 'selesai'; })); ?>
                            </h3>
                            <small class="text-muted">Selesai</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-secondary mb-0">
                                <?php echo count(array_filter($jadwal, function($j) { return $j['status'] == 'terjadwal'; })); ?>
                            </h3>
                            <small class="text-muted">Terjadwal</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>

