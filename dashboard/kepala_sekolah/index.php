<?php
$page_title = 'Dashboard Kepala Sekolah';
require_once '../../config/session.php';
requireRole(['kepala_sekolah']);
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
    'total_guru' => 0,
    'total_siswa' => 0,
    'total_mata_pelajaran' => 0,
    'total_soal' => 0
];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'guru' AND sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_guru'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'siswa' AND sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_siswa'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM mata_pelajaran WHERE sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_mata_pelajaran'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE mp.sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_soal'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get recent teachers
$stmt = $conn->prepare("SELECT * FROM users 
    WHERE role = 'guru' AND sekolah_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$recent_teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get trend data untuk mini charts (7 hari terakhir)
$trend_data = [
    'guru' => [],
    'siswa' => [],
    'mata_pelajaran' => [],
    'soal' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_datetime = $date . ' 23:59:59';
    
    // Total guru sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'guru' AND sekolah_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['guru'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total siswa sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'siswa' AND sekolah_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['siswa'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total mata pelajaran sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mata_pelajaran WHERE sekolah_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['mata_pelajaran'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total soal sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s 
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
        WHERE mp.sekolah_id = ? AND DATE(s.created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['soal'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// Calculate percentage changes
$prev_guru = count($trend_data['guru']) > 1 ? $trend_data['guru'][count($trend_data['guru']) - 2] : $stats['total_guru'];
$prev_siswa = count($trend_data['siswa']) > 1 ? $trend_data['siswa'][count($trend_data['siswa']) - 2] : $stats['total_siswa'];
$prev_mata_pelajaran = count($trend_data['mata_pelajaran']) > 1 ? $trend_data['mata_pelajaran'][count($trend_data['mata_pelajaran']) - 2] : $stats['total_mata_pelajaran'];
$prev_soal = count($trend_data['soal']) > 1 ? $trend_data['soal'][count($trend_data['soal']) - 2] : $stats['total_soal'];

$change_guru = $prev_guru > 0 ? round((($stats['total_guru'] - $prev_guru) / $prev_guru) * 100, 1) : 0;
$change_siswa = $prev_siswa > 0 ? round((($stats['total_siswa'] - $prev_siswa) / $prev_siswa) * 100, 1) : 0;
$change_mata_pelajaran = $prev_mata_pelajaran > 0 ? round((($stats['total_mata_pelajaran'] - $prev_mata_pelajaran) / $prev_mata_pelajaran) * 100, 1) : 0;
$change_soal = $prev_soal > 0 ? round((($stats['total_soal'] - $prev_soal) / $prev_soal) * 100, 1) : 0;

// Get data untuk chart distribusi
$role_distribution = [
    'guru' => $stats['total_guru'],
    'siswa' => $stats['total_siswa']
];

// Get data untuk chart trend (30 hari terakhir)
$trend_labels = [];
$trend_guru = [];
$trend_siswa = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trend_labels[] = date('d M', strtotime($date));
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'guru' AND sekolah_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_guru[] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'siswa' AND sekolah_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_siswa[] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

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
            <p>Dashboard monitoring <?php echo htmlspecialchars($sekolah['nama_sekolah'] ?? 'Sekolah'); ?>.</p>
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
            <button class="nav-link" id="guru-tab" data-bs-toggle="tab" data-bs-target="#guru" type="button" role="tab">
                Guru
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
                            <div class="metric-title">Total Guru</div>
                            <div class="metric-value"><?php echo $stats['total_guru']; ?></div>
                            <div class="metric-change <?php echo $change_guru >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_guru >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_guru); ?>%
                            </div>
                        </div>
            </div>
                    <div class="metric-chart">
                        <canvas id="chartGuru"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Total Siswa</div>
                            <div class="metric-value"><?php echo $stats['total_siswa']; ?></div>
                            <div class="metric-change <?php echo $change_siswa >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_siswa >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_siswa); ?>%
                            </div>
                        </div>
            </div>
                    <div class="metric-chart">
                        <canvas id="chartSiswa"></canvas>
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
    
    <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Total Soal</div>
                            <div class="metric-value"><?php echo $stats['total_soal']; ?></div>
                            <div class="metric-change <?php echo $change_soal >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_soal >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_soal); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartSoal"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row align-items-stretch">
            <!-- Distribusi User -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Distribusi User</h5>
                        <p class="chart-section-desc">Jumlah guru dan siswa di sekolah.</p>
                    </div>
                    <div class="chart-container-small">
                        <canvas id="chartDistribusiUser"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Statistik Cepat</h5>
                        <p class="chart-section-desc">Ringkasan data sekolah.</p>
                    </div>
                    <div style="flex: 1; overflow-y: auto;">
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                    Total Guru
                                </h6>
                                <small class="text-muted"><?php echo $stats['total_guru']; ?> guru terdaftar</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                    Total Siswa
                                </h6>
                                <small class="text-muted"><?php echo $stats['total_siswa']; ?> siswa terdaftar</small>
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
        </div>
        
        <!-- Charts Row 2 -->
        <div class="row">
            <!-- Trend Chart -->
            <div class="col-lg-12 mb-4">
                <div class="chart-section">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Trend Pendaftaran</h5>
                        <p class="chart-section-desc">Perkembangan pendaftaran guru dan siswa selama 30 hari terakhir.</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTrend"></canvas>
                    </div>
                </div>
        </div>
    </div>
</div>

    <!-- Guru Tab -->
    <div class="tab-pane fade" id="guru" role="tabpanel">
<div class="row">
    <div class="col-12">
                <div class="chart-section">
                    <div class="chart-section-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="chart-section-title">Guru Terbaru</h5>
                            <p class="chart-section-desc">Daftar guru yang baru terdaftar.</p>
                        </div>
                        <a href="guru.php" class="text-decoration-none small text-primary">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
                <?php if (count($recent_teachers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Email</th>
                                    <th>Tanggal Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_teachers as $teacher): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($teacher['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($teacher['nama_lengkap']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['email'] ?? '-'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($teacher['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                        <div class="text-center py-5" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-person-workspace text-muted" style="font-size: 3.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada guru</p>
                            <a href="guru.php" class="btn btn-primary mt-3">Tambah Guru</a>
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

// Chart Guru
new Chart(document.getElementById('chartGuru'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['guru']); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Siswa
new Chart(document.getElementById('chartSiswa'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['siswa']); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
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
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Soal
new Chart(document.getElementById('chartSoal'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['soal']); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Distribusi User Pie Chart
new Chart(document.getElementById('chartDistribusiUser'), {
    type: 'pie',
    data: {
        labels: ['Guru', 'Siswa'],
        datasets: [{
            data: [
                <?php echo $role_distribution['guru']; ?>,
                <?php echo $role_distribution['siswa']; ?>
            ],
            backgroundColor: [
                '#3b82f6',
                '#10b981'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: { size: 11 }
                }
            }
        }
    }
});

// Trend Chart
new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [{
            label: 'Guru',
            data: <?php echo json_encode($trend_guru); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 5
        }, {
            label: 'Siswa',
            data: <?php echo json_encode($trend_siswa); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
});
</script>

<?php require_once '../../includes/footer.php'; ?>
