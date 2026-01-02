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
    'total_kelas' => 0
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

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM kelas WHERE sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_kelas'] = $stmt->get_result()->fetch_assoc()['total'];
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
    'kelas' => []
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
    
    // Total kelas sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM kelas WHERE sekolah_id = ? AND DATE(created_at) <= ?");
    $stmt->bind_param("is", $sekolah_id, $date);
    $stmt->execute();
    $trend_data['kelas'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// Calculate percentage changes
$prev_guru = count($trend_data['guru']) > 1 ? $trend_data['guru'][count($trend_data['guru']) - 2] : $stats['total_guru'];
$prev_siswa = count($trend_data['siswa']) > 1 ? $trend_data['siswa'][count($trend_data['siswa']) - 2] : $stats['total_siswa'];
$prev_mata_pelajaran = count($trend_data['mata_pelajaran']) > 1 ? $trend_data['mata_pelajaran'][count($trend_data['mata_pelajaran']) - 2] : $stats['total_mata_pelajaran'];
$prev_kelas = count($trend_data['kelas']) > 1 ? $trend_data['kelas'][count($trend_data['kelas']) - 2] : $stats['total_kelas'];

$change_guru = $prev_guru > 0 ? round((($stats['total_guru'] - $prev_guru) / $prev_guru) * 100, 1) : 0;
$change_siswa = $prev_siswa > 0 ? round((($stats['total_siswa'] - $prev_siswa) / $prev_siswa) * 100, 1) : 0;
$change_mata_pelajaran = $prev_mata_pelajaran > 0 ? round((($stats['total_mata_pelajaran'] - $prev_mata_pelajaran) / $prev_mata_pelajaran) * 100, 1) : 0;
$change_kelas = $prev_kelas > 0 ? round((($stats['total_kelas'] - $prev_kelas) / $prev_kelas) * 100, 1) : 0;

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

/* Stat Cards - Modern Design */
.metric-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), 0 4px 12px rgba(0, 0, 0, 0.08);
    border: none;
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.metric-card.metric-card-blue {
    background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
}

.metric-card.metric-card-green {
    background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
}

.metric-card.metric-card-orange {
    background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);
}

.metric-card.metric-card-purple {
    background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%);
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 6px;
    height: 100%;
    background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
    z-index: 1;
    border-radius: 0 16px 16px 0;
}

.metric-card.metric-card-blue::before {
    background: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%);
    box-shadow: -4px 0 20px rgba(99, 102, 241, 0.5), -2px 0 10px rgba(99, 102, 241, 0.3);
}

.metric-card.metric-card-green::before {
    background: linear-gradient(180deg, #10b981 0%, #059669 100%);
    box-shadow: -4px 0 20px rgba(16, 185, 129, 0.5), -2px 0 10px rgba(16, 185, 129, 0.3);
}

.metric-card.metric-card-orange::before {
    background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
    box-shadow: -4px 0 20px rgba(245, 158, 11, 0.5), -2px 0 10px rgba(245, 158, 11, 0.3);
}

.metric-card.metric-card-purple::before {
    background: linear-gradient(180deg, #8b5cf6 0%, #7c3aed 100%);
    box-shadow: -4px 0 20px rgba(139, 92, 246, 0.5), -2px 0 10px rgba(139, 92, 246, 0.3);
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16), 0 5px 14px rgba(0, 0, 0, 0.12);
}

.metric-card.metric-card-blue:hover {
    box-shadow: 0 10px 28px rgba(99, 102, 241, 0.25), 0 5px 14px rgba(99, 102, 241, 0.15);
}

.metric-card.metric-card-green:hover {
    box-shadow: 0 10px 28px rgba(16, 185, 129, 0.25), 0 5px 14px rgba(16, 185, 129, 0.15);
}

.metric-card.metric-card-orange:hover {
    box-shadow: 0 10px 28px rgba(245, 158, 11, 0.25), 0 5px 14px rgba(245, 158, 11, 0.15);
}

.metric-card.metric-card-purple:hover {
    box-shadow: 0 10px 28px rgba(139, 92, 246, 0.25), 0 5px 14px rgba(139, 92, 246, 0.15);
}

.metric-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    position: relative;
    z-index: 2;
}

.metric-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), 0 2px 6px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.metric-card:hover .metric-icon {
    transform: scale(1.05) rotate(5deg);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25), 0 4px 12px rgba(0, 0, 0, 0.2);
}

.metric-card-blue .metric-icon {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
}

.metric-card-green .metric-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
}

.metric-card-orange .metric-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #ffffff;
}

.metric-card-purple .metric-icon {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: #ffffff;
}

.metric-title {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.metric-value {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}

.metric-change {
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 6px 10px;
    border-radius: 16px;
    min-width: 55px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.metric-change.positive {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.15) 100%);
    color: #059669;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3), 0 2px 6px rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.metric-change.negative {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.15) 100%);
    color: #dc2626;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3), 0 2px 6px rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.metric-card:hover .metric-change {
    transform: scale(1.05);
}

.metric-change i {
    font-size: 9px;
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
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card metric-card-blue">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <div class="metric-title">Total Guru</div>
                            <div class="metric-value"><?php echo $stats['total_guru']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_guru >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_guru >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_guru); ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card metric-card-green">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="metric-title">Total Siswa</div>
                            <div class="metric-value"><?php echo $stats['total_siswa']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_siswa >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_siswa >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_siswa); ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card metric-card-orange">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-book"></i>
                            </div>
                            <div class="metric-title">Mata Pelajaran</div>
                            <div class="metric-value"><?php echo $stats['total_mata_pelajaran']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_mata_pelajaran >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_mata_pelajaran >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_mata_pelajaran); ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card metric-card-purple">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="metric-title">Total Kelas</div>
                            <div class="metric-value"><?php echo $stats['total_kelas']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_kelas >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_kelas >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_kelas); ?>%
                        </div>
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
