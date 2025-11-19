<?php
$page_title = 'Dashboard Developer';
require_once '../../config/session.php';
requireRole(['developer']);
require_once '../../includes/header.php';

$conn = getConnection();

// Get statistics
$stats = [
    'total_sekolah' => 0,
    'total_kepala_sekolah' => 0,
    'total_guru' => 0,
    'total_siswa' => 0,
    'total_sekolah_aktif' => 0
];

$result = $conn->query("SELECT COUNT(*) as total FROM sekolah");
$stats['total_sekolah'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'kepala_sekolah'");
$stats['total_kepala_sekolah'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'guru'");
$stats['total_guru'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'");
$stats['total_siswa'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM sekolah WHERE kepala_sekolah_id IS NOT NULL");
$stats['total_sekolah_aktif'] = $result->fetch_assoc()['total'];

// Get recent schools
$recent_schools = $conn->query("SELECT s.*, u.nama_lengkap as kepala_sekolah_nama 
    FROM sekolah s 
    LEFT JOIN users u ON s.kepala_sekolah_id = u.id 
    ORDER BY s.created_at DESC 
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Get trend data untuk mini charts (7 hari terakhir)
$trend_data = [
    'sekolah' => [],
    'kepala_sekolah' => [],
    'guru' => [],
    'siswa' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_datetime = $date . ' 23:59:59';
    
    // Total sekolah sampai tanggal tersebut
    $result = $conn->query("SELECT COUNT(*) as total FROM sekolah WHERE DATE(created_at) <= '$date'");
    $trend_data['sekolah'][] = $result->fetch_assoc()['total'];
    
    // Total kepala sekolah sampai tanggal tersebut
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'kepala_sekolah' AND DATE(created_at) <= '$date'");
    $trend_data['kepala_sekolah'][] = $result->fetch_assoc()['total'];
    
    // Total guru sampai tanggal tersebut
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'guru' AND DATE(created_at) <= '$date'");
    $trend_data['guru'][] = $result->fetch_assoc()['total'];
    
    // Total siswa sampai tanggal tersebut
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa' AND DATE(created_at) <= '$date'");
    $trend_data['siswa'][] = $result->fetch_assoc()['total'];
}

// Calculate percentage changes
$prev_sekolah = count($trend_data['sekolah']) > 1 ? $trend_data['sekolah'][count($trend_data['sekolah']) - 2] : $stats['total_sekolah'];
$prev_kepala_sekolah = count($trend_data['kepala_sekolah']) > 1 ? $trend_data['kepala_sekolah'][count($trend_data['kepala_sekolah']) - 2] : $stats['total_kepala_sekolah'];
$prev_guru = count($trend_data['guru']) > 1 ? $trend_data['guru'][count($trend_data['guru']) - 2] : $stats['total_guru'];
$prev_siswa = count($trend_data['siswa']) > 1 ? $trend_data['siswa'][count($trend_data['siswa']) - 2] : $stats['total_siswa'];

$change_sekolah = $prev_sekolah > 0 ? round((($stats['total_sekolah'] - $prev_sekolah) / $prev_sekolah) * 100, 1) : 0;
$change_kepala_sekolah = $prev_kepala_sekolah > 0 ? round((($stats['total_kepala_sekolah'] - $prev_kepala_sekolah) / $prev_kepala_sekolah) * 100, 1) : 0;
$change_guru = $prev_guru > 0 ? round((($stats['total_guru'] - $prev_guru) / $prev_guru) * 100, 1) : 0;
$change_siswa = $prev_siswa > 0 ? round((($stats['total_siswa'] - $prev_siswa) / $prev_siswa) * 100, 1) : 0;

// Get data untuk chart distribusi per role
$role_distribution = [];
$result = $conn->query("SELECT role, COUNT(*) as total FROM users WHERE role IN ('kepala_sekolah', 'guru', 'siswa') GROUP BY role");
while ($row = $result->fetch_assoc()) {
    $role_distribution[$row['role']] = $row['total'];
}

// Get data untuk chart trend (30 hari terakhir)
$trend_labels = [];
$trend_sekolah = [];
$trend_users = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trend_labels[] = date('d M', strtotime($date));
    
    $result = $conn->query("SELECT COUNT(*) as total FROM sekolah WHERE DATE(created_at) = '$date'");
    $trend_sekolah[] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = '$date'");
    $trend_users[] = $result->fetch_assoc()['total'];
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
            <p>Dashboard monitoring sistem Sekolahku.</p>
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
            <button class="nav-link" id="sekolah-tab" data-bs-toggle="tab" data-bs-target="#sekolah" type="button" role="tab">
                Sekolah
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
                            <div class="metric-title">Total Sekolah</div>
                            <div class="metric-value"><?php echo $stats['total_sekolah']; ?></div>
                            <div class="metric-change <?php echo $change_sekolah >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_sekolah >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_sekolah); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartSekolah"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Kepala Sekolah</div>
                            <div class="metric-value"><?php echo $stats['total_kepala_sekolah']; ?></div>
                            <div class="metric-change <?php echo $change_kepala_sekolah >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_kepala_sekolah >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_kepala_sekolah); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartKepalaSekolah"></canvas>
                    </div>
                </div>
            </div>
            
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
        </div>
        
        <!-- Charts Row -->
        <div class="row align-items-stretch">
            <!-- Distribusi User per Role -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Distribusi User</h5>
                        <p class="chart-section-desc">Jumlah user berdasarkan role.</p>
                    </div>
                    <div class="chart-container-small">
                        <canvas id="chartDistribusiUser"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Sekolah Aktif -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Sekolah Aktif</h5>
                        <p class="chart-section-desc">Sekolah yang sudah memiliki kepala sekolah.</p>
                    </div>
                    <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <h1 class="text-primary mb-2 fw-bold" style="font-size: 4rem;">
                            <?php echo $stats['total_sekolah_aktif']; ?>
                        </h1>
                        <p class="text-muted mb-0">dari <?php echo $stats['total_sekolah']; ?> sekolah</p>
                        <?php if ($stats['total_sekolah'] > 0): ?>
                            <div class="progress mx-auto mt-3" style="height: 12px; max-width: 200px; width: 100%;">
                                <div class="progress-bar bg-primary" role="progressbar" 
                                     style="width: <?php echo ($stats['total_sekolah_aktif'] / $stats['total_sekolah']) * 100; ?>%" 
                                     aria-valuenow="<?php echo $stats['total_sekolah_aktif']; ?>" 
                                     aria-valuemin="0" aria-valuemax="<?php echo $stats['total_sekolah']; ?>"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Statistik Cepat</h5>
                        <p class="chart-section-desc">Ringkasan data sistem.</p>
                    </div>
                    <div style="flex: 1; overflow-y: auto;">
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                    Total Sekolah
                                </h6>
                                <small class="text-muted"><?php echo $stats['total_sekolah']; ?> sekolah terdaftar</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                    Total User
                                </h6>
                                <small class="text-muted"><?php echo $stats['total_kepala_sekolah'] + $stats['total_guru'] + $stats['total_siswa']; ?> user aktif</small>
                            </div>
                        </div>
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
                        <p class="chart-section-desc">Perkembangan pendaftaran sekolah dan user selama 30 hari terakhir.</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTrend"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sekolah Tab -->
    <div class="tab-pane fade" id="sekolah" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="chart-section">
                    <div class="chart-section-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="chart-section-title">Sekolah Terbaru</h5>
                            <p class="chart-section-desc">Daftar sekolah yang baru terdaftar.</p>
                        </div>
                        <a href="sekolah.php" class="text-decoration-none small text-primary">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (count($recent_schools) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Sekolah</th>
                                        <th>Alamat</th>
                                        <th>Kepala Sekolah</th>
                                        <th>Telepon</th>
                                        <th>Tanggal Dibuat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_schools as $school): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($school['nama_sekolah']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($school['alamat'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($school['kepala_sekolah_nama'] ?? 'Belum ditetapkan'); ?></td>
                                            <td><?php echo htmlspecialchars($school['telepon'] ?? '-'); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($school['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-building text-muted" style="font-size: 3.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada sekolah</p>
                            <a href="sekolah.php" class="btn btn-primary mt-3">Tambah Sekolah</a>
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

// Chart Sekolah
new Chart(document.getElementById('chartSekolah'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['sekolah']); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Kepala Sekolah
new Chart(document.getElementById('chartKepalaSekolah'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['kepala_sekolah']); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Guru
new Chart(document.getElementById('chartGuru'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['guru']); ?>,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
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
            borderColor: '#14b8a6',
            backgroundColor: 'rgba(20, 184, 166, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Distribusi User Pie Chart
<?php if (!empty($role_distribution)): ?>
new Chart(document.getElementById('chartDistribusiUser'), {
    type: 'pie',
    data: {
        labels: ['Kepala Sekolah', 'Guru', 'Siswa'],
        datasets: [{
            data: [
                <?php echo $role_distribution['kepala_sekolah'] ?? 0; ?>,
                <?php echo $role_distribution['guru'] ?? 0; ?>,
                <?php echo $role_distribution['siswa'] ?? 0; ?>
            ],
            backgroundColor: [
                '#3b82f6',
                '#10b981',
                '#f59e0b'
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
<?php endif; ?>

// Trend Chart
new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [{
            label: 'Sekolah',
            data: <?php echo json_encode($trend_sekolah); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 5
        }, {
            label: 'User',
            data: <?php echo json_encode($trend_users); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
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
