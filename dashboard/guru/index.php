<?php
$page_title = 'Dashboard Guru';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get statistics
$stats = [
    'total_siswa' => 0,
    'total_mata_pelajaran' => 0,
    'total_soal' => 0,
    'total_soal_aktif' => 0
];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'siswa' AND sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_siswa'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM mata_pelajaran WHERE guru_id = ?");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$stats['total_mata_pelajaran'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal WHERE guru_id = ?");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$stats['total_soal'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal WHERE guru_id = ? AND status = 'aktif'");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$stats['total_soal_aktif'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get recent soal
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran 
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE s.guru_id = ? 
    ORDER BY s.created_at DESC 
    LIMIT 5");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$recent_soal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get jadwal minggu ini untuk statistik per mata pelajaran
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, k.nama_kelas
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN kelas k ON jp.kelas_id = k.id
    WHERE mp.guru_id = ? AND jp.tanggal BETWEEN ? AND ?
    ORDER BY jp.tanggal ASC, jp.jam_mulai ASC");
$stmt->bind_param("iss", $guru_id, $week_start, $week_end);
$stmt->execute();
$jadwal_minggu_ini = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics per mata pelajaran
$stats_per_pelajaran = [];
foreach ($jadwal_minggu_ini as $j) {
    $pelajaran = $j['nama_pelajaran'];
    if (!isset($stats_per_pelajaran[$pelajaran])) {
        $stats_per_pelajaran[$pelajaran] = [
            'total' => 0,
            'terjadwal' => 0,
            'berlangsung' => 0,
            'selesai' => 0,
            'dibatalkan' => 0,
            'kelas' => []
        ];
    }
    $stats_per_pelajaran[$pelajaran]['total']++;
    $stats_per_pelajaran[$pelajaran][$j['status']]++;
    if (!in_array($j['nama_kelas'], $stats_per_pelajaran[$pelajaran]['kelas'])) {
        $stats_per_pelajaran[$pelajaran]['kelas'][] = $j['nama_kelas'];
    }
}

// Get history pembelajaran selesai (dari jadwal_pelajaran dan sesi_pelajaran)
$history_pelajaran = [];
// Get dari jadwal_pelajaran yang selesai
$stmt = $conn->prepare("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, k.nama_kelas,
    sp.id as sesi_id,
    sp.kode_presensi, sp.waktu_mulai as sesi_waktu_mulai, sp.waktu_selesai as sesi_waktu_selesai,
    CASE 
        WHEN sp.id IS NOT NULL THEN (SELECT COUNT(*) FROM presensi WHERE sesi_pelajaran_id = sp.id)
        ELSE 0
    END as total_presensi
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN kelas k ON jp.kelas_id = k.id
    LEFT JOIN sesi_pelajaran sp ON sp.mata_pelajaran_id = mp.id 
        AND sp.guru_id = ? 
        AND DATE(sp.waktu_mulai) = jp.tanggal 
        AND TIME(sp.waktu_mulai) = jp.jam_mulai
        AND sp.status = 'selesai'
    WHERE mp.guru_id = ? AND jp.status = 'selesai'
    ORDER BY jp.tanggal DESC, jp.jam_mulai DESC
    LIMIT 10");
$stmt->bind_param("ii", $guru_id, $guru_id);
$stmt->execute();
$history_pelajaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get slider images
$sliders = [];
if ($sekolah_id) {
    $stmt = $conn->prepare("SELECT * FROM slider 
        WHERE status = 'aktif' AND (sekolah_id = ? OR sekolah_id IS NULL) 
        ORDER BY urutan ASC, created_at DESC");
    $stmt->bind_param("i", $sekolah_id);
    $stmt->execute();
    $sliders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT * FROM slider 
        WHERE status = 'aktif' AND sekolah_id IS NULL 
        ORDER BY urutan ASC, created_at DESC");
    $stmt->execute();
    $sliders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get trend data untuk mini charts (7 hari terakhir)
$trend_data = [
    'siswa' => [],
    'mata_pelajaran' => [],
    'soal' => [],
    'soal_aktif' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    // Total siswa sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'siswa' AND sekolah_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['siswa'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total mata pelajaran sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mata_pelajaran WHERE guru_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $guru_id, $date);
    $stmt->execute();
    $trend_data['mata_pelajaran'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total soal sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal WHERE guru_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $guru_id, $date);
    $stmt->execute();
    $trend_data['soal'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total soal aktif sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal WHERE guru_id = ? AND status = 'aktif' AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $guru_id, $date);
    $stmt->execute();
    $trend_data['soal_aktif'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// Calculate percentage changes
$prev_siswa = count($trend_data['siswa']) > 1 ? $trend_data['siswa'][count($trend_data['siswa']) - 2] : $stats['total_siswa'];
$prev_mata_pelajaran = count($trend_data['mata_pelajaran']) > 1 ? $trend_data['mata_pelajaran'][count($trend_data['mata_pelajaran']) - 2] : $stats['total_mata_pelajaran'];
$prev_soal = count($trend_data['soal']) > 1 ? $trend_data['soal'][count($trend_data['soal']) - 2] : $stats['total_soal'];
$prev_soal_aktif = count($trend_data['soal_aktif']) > 1 ? $trend_data['soal_aktif'][count($trend_data['soal_aktif']) - 2] : $stats['total_soal_aktif'];

$change_siswa = $prev_siswa > 0 ? round((($stats['total_siswa'] - $prev_siswa) / $prev_siswa) * 100, 1) : 0;
$change_mata_pelajaran = $prev_mata_pelajaran > 0 ? round((($stats['total_mata_pelajaran'] - $prev_mata_pelajaran) / $prev_mata_pelajaran) * 100, 1) : 0;
$change_soal = $prev_soal > 0 ? round((($stats['total_soal'] - $prev_soal) / $prev_soal) * 100, 1) : 0;
$change_soal_aktif = $prev_soal_aktif > 0 ? round((($stats['total_soal_aktif'] - $prev_soal_aktif) / $prev_soal_aktif) * 100, 1) : 0;

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
            <p>Dashboard monitoring pembelajaran Anda.</p>
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
            <button class="nav-link" id="soal-tab" data-bs-toggle="tab" data-bs-target="#soal" type="button" role="tab">
                Soal
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
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Soal Aktif</div>
                            <div class="metric-value"><?php echo $stats['total_soal_aktif']; ?></div>
                            <div class="metric-change <?php echo $change_soal_aktif >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_soal_aktif >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_soal_aktif); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartSoalAktif"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row align-items-stretch mb-4">
            <!-- Statistik Per Mata Pelajaran -->
            <?php if (!empty($stats_per_pelajaran)): ?>
                <div class="col-lg-6 mb-4 d-flex">
                    <div class="chart-section w-100">
                        <div class="chart-section-header">
                            <h5 class="chart-section-title">Statistik Per Mata Pelajaran</h5>
                            <p class="chart-section-desc">Ringkasan jadwal pembelajaran minggu ini.</p>
                        </div>
                        <div style="flex: 1; overflow-y: auto;">
                            <?php foreach ($stats_per_pelajaran as $pelajaran => $stat): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <h6 class="mb-2" style="font-size: 15px; font-weight: 600; color: #1e293b;">
                                        <i class="bi bi-book text-primary"></i> <?php echo htmlspecialchars($pelajaran); ?>
                                    </h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="py-2">
                                                <h4 class="text-primary mb-0" style="font-size: 1.5rem;"><?php echo $stat['total']; ?></h4>
                                                <small class="text-muted">Total</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="py-2">
                                                <h4 class="text-info mb-0" style="font-size: 1.5rem;"><?php echo count($stat['kelas']); ?></h4>
                                                <small class="text-muted">Kelas</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="py-2">
                                                <h4 class="text-success mb-0" style="font-size: 1.5rem;"><?php echo $stat['selesai']; ?></h4>
                                                <small class="text-muted">Selesai</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- History Pembelajaran -->
            <div class="col-lg-6 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">History Pembelajaran</h5>
                        <p class="chart-section-desc">Riwayat pembelajaran yang telah selesai.</p>
                    </div>
                    <?php if (!empty($history_pelajaran)): ?>
                        <div style="flex: 1; overflow-y: auto;">
                            <?php 
                            $icon_styles = [
                                ['gradient' => 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)', 'icon' => 'book'],
                                ['gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)', 'icon' => 'journal-bookmark'],
                                ['gradient' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)', 'icon' => 'book-half'],
                                ['gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', 'icon' => 'journal-text']
                            ];
                            $index = 0;
                            foreach ($history_pelajaran as $history): 
                                $style = $icon_styles[$index % count($icon_styles)];
                                $index++;
                            ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3 flex-shrink-0" style="width: 40px; height: 40px; border-radius: 10px; background: <?php echo $style['gradient']; ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                            <i class="bi bi-<?php echo $style['icon']; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                                <?php echo htmlspecialchars($history['nama_pelajaran']); ?>
                                            </h6>
                                            <p class="mb-0 text-muted" style="font-size: 13px;">
                                                Kelas <?php echo htmlspecialchars($history['nama_kelas']); ?> - Selesai
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada history pembelajaran</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Soal Tab -->
    <div class="tab-pane fade" id="soal" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="chart-section">
                    <div class="chart-section-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="chart-section-title">Soal Terbaru</h5>
                            <p class="chart-section-desc">Daftar soal yang baru dibuat.</p>
                        </div>
                        <a href="soal.php" class="text-decoration-none small text-primary">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (count($recent_soal) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Judul</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Jenis</th>
                                        <th>Status</th>
                                        <th>Tanggal Dibuat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_soal as $soal): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($soal['judul']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($soal['nama_pelajaran']); ?></td>
                                            <td>
                                                <?php 
                                                $jenis_labels = [
                                                    'quiz' => 'Quiz',
                                                    'pilihan_ganda' => 'Pilihan Ganda',
                                                    'isian' => 'Isian'
                                                ];
                                                echo $jenis_labels[$soal['jenis']] ?? $soal['jenis'];
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_badges = [
                                                    'draft' => 'bg-secondary',
                                                    'aktif' => 'bg-success',
                                                    'selesai' => 'bg-warning'
                                                ];
                                                $badge_class = $status_badges[$soal['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($soal['status']); ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($soal['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada soal</p>
                            <a href="mata_pelajaran.php" class="btn btn-primary mt-3">Tambah Mata Pelajaran</a>
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

// Chart Siswa
new Chart(document.getElementById('chartSiswa'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['siswa']); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
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
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
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
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            fill: true
        }]
    },
    options: chartOptions
});

// Chart Soal Aktif
new Chart(document.getElementById('chartSoalAktif'), {
    type: 'line',
    data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
            data: <?php echo json_encode($trend_data['soal_aktif']); ?>,
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


