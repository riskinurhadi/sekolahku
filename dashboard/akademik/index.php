<?php
$page_title = 'Dashboard Akademik';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];

// Get school info
$stmt = $conn->prepare("SELECT * FROM sekolah WHERE id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$sekolah = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get statistics
$stats = [
    'total_jadwal' => 0,
    'jadwal_hari_ini' => 0,
    'jadwal_minggu_ini' => 0,
    'total_mata_pelajaran' => 0
];

// Total jadwal
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM jadwal_pelajaran WHERE sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_jadwal'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Jadwal hari ini
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM jadwal_pelajaran WHERE sekolah_id = ? AND tanggal = ?");
$stmt->bind_param("is", $sekolah_id, $today);
$stmt->execute();
$stats['jadwal_hari_ini'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Jadwal minggu ini (7 hari ke depan)
$week_start = date('Y-m-d');
$week_end = date('Y-m-d', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM jadwal_pelajaran WHERE sekolah_id = ? AND tanggal BETWEEN ? AND ?");
$stmt->bind_param("iss", $sekolah_id, $week_start, $week_end);
$stmt->execute();
$stats['jadwal_minggu_ini'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total mata pelajaran
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM mata_pelajaran WHERE sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_mata_pelajaran'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get jadwal hari ini
$stmt = $conn->prepare("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru, k.nama_kelas
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN users u ON mp.guru_id = u.id
    JOIN kelas k ON jp.kelas_id = k.id
    WHERE jp.sekolah_id = ? AND jp.tanggal = ?
    ORDER BY jp.jam_mulai ASC
    LIMIT 10");
$stmt->bind_param("is", $sekolah_id, $today);
$stmt->execute();
$jadwal_hari_ini = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get trend data untuk mini charts (7 hari terakhir)
$trend_data = [
    'jadwal' => [],
    'jadwal_hari_ini' => [],
    'jadwal_minggu_ini' => [],
    'mata_pelajaran' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    // Total jadwal sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jadwal_pelajaran WHERE sekolah_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['jadwal'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Jadwal pada tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jadwal_pelajaran WHERE sekolah_id = ? AND tanggal = ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['jadwal_hari_ini'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Jadwal minggu ini (7 hari dari tanggal tersebut)
    $week_start_date = $date;
    $week_end_date = date('Y-m-d', strtotime($date . ' +7 days'));
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jadwal_pelajaran WHERE sekolah_id = ? AND tanggal BETWEEN ? AND ?");
    $stmt->bind_param("iss", $sekolah_id, $week_start_date, $week_end_date);
    $stmt->execute();
    $trend_data['jadwal_minggu_ini'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total mata pelajaran sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mata_pelajaran WHERE sekolah_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['mata_pelajaran'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// Calculate percentage changes
$prev_jadwal = count($trend_data['jadwal']) > 1 ? $trend_data['jadwal'][count($trend_data['jadwal']) - 2] : $stats['total_jadwal'];
$prev_jadwal_hari_ini = count($trend_data['jadwal_hari_ini']) > 1 ? $trend_data['jadwal_hari_ini'][count($trend_data['jadwal_hari_ini']) - 2] : $stats['jadwal_hari_ini'];
$prev_jadwal_minggu_ini = count($trend_data['jadwal_minggu_ini']) > 1 ? $trend_data['jadwal_minggu_ini'][count($trend_data['jadwal_minggu_ini']) - 2] : $stats['jadwal_minggu_ini'];
$prev_mata_pelajaran = count($trend_data['mata_pelajaran']) > 1 ? $trend_data['mata_pelajaran'][count($trend_data['mata_pelajaran']) - 2] : $stats['total_mata_pelajaran'];

$change_jadwal = $prev_jadwal > 0 ? round((($stats['total_jadwal'] - $prev_jadwal) / $prev_jadwal) * 100, 1) : 0;
$change_jadwal_hari_ini = $prev_jadwal_hari_ini > 0 ? round((($stats['jadwal_hari_ini'] - $prev_jadwal_hari_ini) / $prev_jadwal_hari_ini) * 100, 1) : 0;
$change_jadwal_minggu_ini = $prev_jadwal_minggu_ini > 0 ? round((($stats['jadwal_minggu_ini'] - $prev_jadwal_minggu_ini) / $prev_jadwal_minggu_ini) * 100, 1) : 0;
$change_mata_pelajaran = $prev_mata_pelajaran > 0 ? round((($stats['total_mata_pelajaran'] - $prev_mata_pelajaran) / $prev_mata_pelajaran) * 100, 1) : 0;

$conn->close();
?>

<style>
/* Dashboard Modern Style */
.dashboard-greeting {
    margin-bottom: 32px;
}

.dashboard-greeting h1 {
    font-size: 32px;
    font-weight: 700;
    color: #1e3a8a;
    margin-bottom: 8px;
}

.dashboard-greeting p {
    font-size: 16px;
    color: #64748b;
    margin-bottom: 24px;
}

.dashboard-tabs {
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 24px;
}

.dashboard-tabs .nav-link {
    color: #64748b;
    font-weight: 600;
    padding: 12px 24px;
    border: none;
    border-bottom: 3px solid transparent;
    background: transparent;
    transition: all 0.2s ease;
}

.dashboard-tabs .nav-link:hover {
    color: #3b82f6;
    border-bottom-color: rgba(59, 130, 246, 0.3);
}

.dashboard-tabs .nav-link.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
    background: transparent;
}

/* Stat Cards dengan Mini Charts */
.metric-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    border-color: #3b82f6;
}

.metric-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.metric-title {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 32px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 8px;
}

.metric-change {
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.metric-change.positive {
    color: #10b981;
}

.metric-change.negative {
    color: #ef4444;
}

.metric-chart {
    height: 60px;
    margin-top: 16px;
    position: relative;
}

/* Chart Sections */
.chart-section {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 24px;
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 400px;
    max-height: 400px;
}

.chart-section-header {
    margin-bottom: 24px;
    flex-shrink: 0;
}

.chart-section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
}

.chart-section-desc {
    font-size: 14px;
    color: #64748b;
}

.chart-container {
    position: relative;
    height: 300px;
    flex: 1;
    min-height: 0;
}

.chart-container-small {
    position: relative;
    height: 200px;
    flex: 1;
    min-height: 0;
}
</style>

<!-- Greeting Section -->
<div class="dashboard-greeting">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1>Hai, selamat datang kembali!</h1>
            <p>Dashboard monitoring jadwal dan akademik <?php echo htmlspecialchars($sekolah['nama_sekolah'] ?? 'Sekolah'); ?>.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="date-range-selector" style="display: flex; align-items: center; gap: 12px; font-size: 14px; color: #64748b;">
                <span class="date-text" style="font-weight: 600; color: #1e293b;"><?php echo date('d M Y'); ?></span>
                <span><?php echo date('l'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <ul class="nav dashboard-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                Ringkasan
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="jadwal-tab" data-bs-toggle="tab" data-bs-target="#jadwal" type="button" role="tab">
                Jadwal Hari Ini
            </button>
        </li>
    </ul>
</div>

<!-- Tab Content -->
<div class="tab-content" id="dashboardTabsContent">
    <!-- Overview Tab -->
    <div class="tab-pane fade show active" id="overview" role="tabpanel">
        <!-- Statistics Cards dengan Mini Charts -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Total Jadwal</div>
                            <div class="metric-value"><?php echo $stats['total_jadwal']; ?></div>
                            <div class="metric-change <?php echo $change_jadwal >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_jadwal >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_jadwal); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartJadwal"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Jadwal Hari Ini</div>
                            <div class="metric-value"><?php echo $stats['jadwal_hari_ini']; ?></div>
                            <div class="metric-change <?php echo $change_jadwal_hari_ini >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_jadwal_hari_ini >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_jadwal_hari_ini); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartJadwalHariIni"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Jadwal Minggu Ini</div>
                            <div class="metric-value"><?php echo $stats['jadwal_minggu_ini']; ?></div>
                            <div class="metric-change <?php echo $change_jadwal_minggu_ini >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_jadwal_minggu_ini >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_jadwal_minggu_ini); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartJadwalMingguIni"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Mata Pelajaran</div>
                            <div class="metric-value"><?php echo $stats['total_mata_pelajaran']; ?></div>
                            <div class="metric-change <?php echo $change_mata_pelajaran >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_mata_pelajaran >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_mata_pelajaran); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartMataPelajaran"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row align-items-stretch">
            <!-- Quick Stats -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Statistik Cepat</h5>
                        <p class="chart-section-desc">Ringkasan data jadwal dan akademik.</p>
                    </div>
                    <div style="flex: 1; overflow-y: auto;">
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                    Total Jadwal
                                </h6>
                                <small class="text-muted"><?php echo $stats['total_jadwal']; ?> jadwal terdaftar</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                <i class="bi bi-calendar-day"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                    Jadwal Hari Ini
                                </h6>
                                <small class="text-muted"><?php echo $stats['jadwal_hari_ini']; ?> jadwal hari ini</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                    Mata Pelajaran
                                </h6>
                                <small class="text-muted"><?php echo $stats['total_mata_pelajaran']; ?> mata pelajaran</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info Sekolah -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Info Sekolah</h5>
                        <p class="chart-section-desc">Informasi sekolah Anda.</p>
                    </div>
                    <div style="flex: 1; overflow-y: auto;">
                        <div class="mb-3">
                            <h6 class="mb-1" style="font-size: 14px; font-weight: 600; color: #1e293b;">Nama Sekolah</h6>
                            <p class="text-muted mb-0" style="font-size: 13px;"><?php echo htmlspecialchars($sekolah['nama_sekolah'] ?? '-'); ?></p>
                        </div>
                        <?php if ($sekolah['alamat']): ?>
                        <div class="mb-3">
                            <h6 class="mb-1" style="font-size: 14px; font-weight: 600; color: #1e293b;">Alamat</h6>
                            <p class="text-muted mb-0" style="font-size: 13px;"><?php echo htmlspecialchars($sekolah['alamat']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($sekolah['telepon']): ?>
                        <div class="mb-3">
                            <h6 class="mb-1" style="font-size: 14px; font-weight: 600; color: #1e293b;">Telepon</h6>
                            <p class="text-muted mb-0" style="font-size: 13px;"><?php echo htmlspecialchars($sekolah['telepon']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Jadwal Summary -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Ringkasan Jadwal</h5>
                        <p class="chart-section-desc">Distribusi jadwal pembelajaran.</p>
                    </div>
                    <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <h1 class="text-primary mb-2 fw-bold" style="font-size: 4rem;">
                            <?php echo $stats['jadwal_hari_ini']; ?>
                        </h1>
                        <p class="text-muted mb-0">Jadwal Hari Ini</p>
                        <p class="text-muted mb-4" style="font-size: 14px;">dari <?php echo $stats['total_jadwal']; ?> total jadwal</p>
                        <?php if ($stats['total_jadwal'] > 0): ?>
                            <div class="progress mx-auto" style="height: 12px; max-width: 200px; width: 100%;">
                                <div class="progress-bar bg-primary" role="progressbar" 
                                     style="width: <?php echo ($stats['jadwal_hari_ini'] / $stats['total_jadwal']) * 100; ?>%" 
                                     aria-valuenow="<?php echo $stats['jadwal_hari_ini']; ?>" 
                                     aria-valuemin="0" aria-valuemax="<?php echo $stats['total_jadwal']; ?>"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Jadwal Tab -->
    <div class="tab-pane fade" id="jadwal" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="chart-section">
                    <div class="chart-section-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="chart-section-title">Jadwal Pelajaran Hari Ini</h5>
                            <p class="chart-section-desc">Daftar jadwal pelajaran untuk hari ini.</p>
                        </div>
                        <a href="jadwal.php" class="text-decoration-none small text-primary">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (count($jadwal_hari_ini) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Jam</th>
                                        <th>Kelas</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Guru</th>
                                        <th>Ruangan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?></strong> - 
                                                <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></strong>
                                                <?php if ($jadwal['kode_pelajaran']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($jadwal['kode_pelajaran']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($jadwal['nama_guru']); ?></td>
                                            <td><?php echo htmlspecialchars($jadwal['ruangan'] ?? '-'); ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'terjadwal' => 'bg-secondary',
                                                    'berlangsung' => 'bg-success',
                                                    'selesai' => 'bg-info',
                                                    'dibatalkan' => 'bg-warning'
                                                ];
                                                $status_text = [
                                                    'terjadwal' => 'Terjadwal',
                                                    'berlangsung' => 'Berlangsung',
                                                    'selesai' => 'Selesai',
                                                    'dibatalkan' => 'Dibatalkan'
                                                ];
                                                $class = $status_class[$jadwal['status']] ?? 'bg-secondary';
                                                $text = $status_text[$jadwal['status']] ?? ucfirst($jadwal['status']);
                                                ?>
                                                <span class="badge <?php echo $class; ?>"><?php echo $text; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Tidak ada jadwal hari ini</p>
                            <a href="jadwal.php" class="btn btn-primary mt-3">
                                <i class="bi bi-plus-circle"></i> Tambah Jadwal
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
// Mini Charts untuk Stat Cards
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false }
    },
    scales: {
        x: { display: false },
        y: { display: false }
    },
    elements: {
        point: { radius: 0 },
        line: { borderWidth: 2, tension: 0.4 }
    }
};

// Chart Jadwal
new Chart(document.getElementById('chartJadwal'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['jadwal']); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Jadwal Hari Ini
new Chart(document.getElementById('chartJadwalHariIni'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['jadwal_hari_ini']); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Jadwal Minggu Ini
new Chart(document.getElementById('chartJadwalMingguIni'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['jadwal_minggu_ini']); ?>,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Mata Pelajaran
new Chart(document.getElementById('chartMataPelajaran'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['mata_pelajaran']); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});
});
</script>

<?php require_once '../../includes/footer.php'; ?>
