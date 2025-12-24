<?php
$page_title = 'Dashboard Siswa';
require_once '../../config/session.php';
requireRole(['siswa']);
// Menggunakan header baru
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Semua logika PHP untuk mengambil data dari database tetap sama
// ... (salin semua logika PHP dari file index.php lama di sini)
$stmt = $conn->prepare("SELECT kelas_id FROM users WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa_info = $stmt->get_result()->fetch_assoc();
$kelas_id = $siswa_info['kelas_id'] ?? null;
$stmt->close();

// Get statistics
$stats = [
    'total_soal_aktif' => 0,
    'total_soal_selesai' => 0,
    'total_nilai' => 0,
    'rata_rata_nilai' => 0
];

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE mp.sekolah_id = ? AND s.status = 'aktif' 
    AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?)
    AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)");
$stmt->bind_param("iss", $sekolah_id, $now, $now);
$stmt->execute();
$stats['total_soal_aktif'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai'");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$stats['total_soal_selesai'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['rata_rata_nilai'] = $result['avg'] ? number_format($result['avg'], 2) : 0;
$stmt->close();

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday next week')); // 2 weeks
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$jadwal_minggu_ini = [];
$jadwal_hari_ini = [];
$jadwal_besok = [];

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
    $jadwal_minggu_ini = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $jadwal_hari_ini = array_filter($jadwal_minggu_ini, function($j) use ($today) {
        return $j['tanggal'] == $today;
    });
    
    $jadwal_besok = array_filter($jadwal_minggu_ini, function($j) use ($tomorrow) {
        return $j['tanggal'] == $tomorrow;
    });
}

// Get latest announcement
$stmt = $conn->prepare("SELECT * FROM informasi_akademik ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$pengumuman_terbaru = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get latest exam result
$stmt = $conn->prepare("SELECT hu.*, s.judul as judul_soal, mp.nama_pelajaran 
    FROM hasil_ujian hu 
    JOIN soal s ON hu.soal_id = s.id 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE hu.siswa_id = ? AND hu.status = 'selesai' 
    ORDER BY hu.waktu_selesai DESC LIMIT 1");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$hasil_ujian_terakhir = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent hasil ujian
$stmt = $conn->prepare("SELECT hu.*, s.judul as judul_soal, mp.nama_pelajaran 
    FROM hasil_ujian hu 
    JOIN soal s ON hu.soal_id = s.id 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE hu.siswa_id = ? AND hu.status = 'selesai' 
    ORDER BY hu.waktu_selesai DESC 
    LIMIT 5");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$recent_hasil_ujian = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get trend data untuk mini charts (7 hari terakhir)
$trend_data = [
    'soal_aktif' => [],
    'soal_selesai' => [],
    'total_soal' => [],
    'rata_nilai' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_end = date('Y-m-d H:i:s', strtotime("-$i days 23:59:59"));
    
    // Total soal aktif sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s 
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
        WHERE mp.sekolah_id = ? AND s.status = 'aktif' 
        AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?)
        AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)
        AND DATE(s.created_at) <= ?");
    $stmt->bind_param("isss", $sekolah_id, $date_end, $date_end, $date);
    $stmt->execute();
    $trend_data['soal_aktif'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total soal selesai sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian 
        WHERE siswa_id = ? AND status = 'selesai' AND DATE(waktu_selesai) <= ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $trend_data['soal_selesai'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Total soal (aktif + selesai)
    $trend_data['total_soal'][] = $trend_data['soal_aktif'][count($trend_data['soal_aktif']) - 1] + $trend_data['soal_selesai'][count($trend_data['soal_selesai']) - 1];
    
    // Rata-rata nilai sampai tanggal tersebut
    $stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian 
        WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL AND DATE(waktu_selesai) <= ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $trend_data['rata_nilai'][] = $result['avg'] ? round($result['avg'], 2) : 0;
    $stmt->close();
}

// Calculate percentage changes
$prev_soal_aktif = count($trend_data['soal_aktif']) > 1 ? $trend_data['soal_aktif'][count($trend_data['soal_aktif']) - 2] : $stats['total_soal_aktif'];
$prev_soal_selesai = count($trend_data['soal_selesai']) > 1 ? $trend_data['soal_selesai'][count($trend_data['soal_selesai']) - 2] : $stats['total_soal_selesai'];
$prev_total_soal = count($trend_data['total_soal']) > 1 ? $trend_data['total_soal'][count($trend_data['total_soal']) - 2] : ($stats['total_soal_aktif'] + $stats['total_soal_selesai']);
$prev_rata_nilai = count($trend_data['rata_nilai']) > 1 ? $trend_data['rata_nilai'][count($trend_data['rata_nilai']) - 2] : $stats['rata_rata_nilai'];

$change_soal_aktif = $prev_soal_aktif > 0 ? round((($stats['total_soal_aktif'] - $prev_soal_aktif) / $prev_soal_aktif) * 100, 1) : 0;
$change_soal_selesai = $prev_soal_selesai > 0 ? round((($stats['total_soal_selesai'] - $prev_soal_selesai) / $prev_soal_selesai) * 100, 1) : 0;
$change_total_soal = $prev_total_soal > 0 ? round((($stats['total_soal_aktif'] + $stats['total_soal_selesai'] - $prev_total_soal) / $prev_total_soal) * 100, 1) : 0;
$change_rata_nilai = $prev_rata_nilai > 0 ? round((($stats['rata_rata_nilai'] - $prev_rata_nilai) / $prev_rata_nilai) * 100, 1) : 0;

// Get soal aktif untuk tab soal
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran 
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE mp.sekolah_id = ? AND s.status = 'aktif' 
    AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?)
    AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)
    ORDER BY s.created_at DESC 
    LIMIT 10");
$stmt->bind_param("iss", $sekolah_id, $now, $now);
$stmt->execute();
$soal_aktif = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

/* Stat Cards dengan Mini Charts - Enhanced Style */
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

/* Chart Sections - Modern Health Dashboard Style */
.chart-section {
    background: #ffffff;
    border-radius: 24px;
    padding: 28px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 4px rgba(0, 0, 0, 0.04);
    border: none;
    margin-bottom: 24px;
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 400px;
    max-height: 400px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.chart-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
}

.chart-section-header {
    margin-bottom: 20px;
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.chart-section-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
    letter-spacing: -0.3px;
}

.chart-section-desc {
    font-size: 13px;
    color: #64748b;
    font-weight: 400;
}

/* List Items - Clean Modern Style */
.dashboard-list-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    cursor: pointer;
}

.dashboard-list-item:last-child {
    border-bottom: none;
}

.dashboard-list-item:hover {
    padding-left: 4px;
}

.dashboard-list-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-right: 12px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.dashboard-list-item:hover .dashboard-list-item-icon {
    transform: scale(1.05);
}

.dashboard-list-item-content {
    flex: 1;
    min-width: 0;
}

.dashboard-list-item-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 2px;
    line-height: 1.4;
}

.dashboard-list-item-subtitle {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.dashboard-list-item-date {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
}

/* Icon Color Variants */
.icon-blue {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    color: #0284c7;
}

.icon-purple {
    background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
    color: #9333ea;
}

.icon-teal {
    background: linear-gradient(135deg, #e0fdfa 0%, #b2f5ea 100%);
    color: #14b8a6;
}

.icon-green {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #16a34a;
}

.icon-orange {
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
    color: #ea580c;
}

/* Jadwal Items - Updated Style */
.jadwal-item {
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    cursor: pointer;
}

.jadwal-item:last-child {
    border-bottom: none;
}

.jadwal-item:hover {
    padding-left: 4px;
}

.jadwal-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-right: 12px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.jadwal-item:hover .jadwal-icon {
    transform: scale(1.05);
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
                <div class="metric-card metric-card-blue">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-journal-check"></i>
                            </div>
                            <div class="metric-title">Soal Aktif</div>
                            <div class="metric-value"><?php echo $stats['total_soal_aktif']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_soal_aktif >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_soal_aktif >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_soal_aktif); ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card metric-card-green">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-patch-check-fill"></i>
                            </div>
                            <div class="metric-title">Soal Selesai</div>
                            <div class="metric-value"><?php echo $stats['total_soal_selesai']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_soal_selesai >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_soal_selesai >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_soal_selesai); ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card metric-card-orange">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="metric-title">Total Soal</div>
                            <div class="metric-value"><?php echo $stats['total_soal_aktif'] + $stats['total_soal_selesai']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_total_soal >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_total_soal >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_total_soal); ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card metric-card-purple">
                    <div class="metric-card-header">
                        <div class="flex-grow-1">
                            <div class="metric-icon">
                                <i class="bi bi-bar-chart-line-fill"></i>
                            </div>
                            <div class="metric-title">Rata-rata Nilai</div>
                            <div class="metric-value"><?php echo $stats['rata_rata_nilai']; ?></div>
                        </div>
                        <div class="metric-change <?php echo $change_rata_nilai >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $change_rata_nilai >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($change_rata_nilai); ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Charts Row -->
        <div class="row align-items-stretch mb-4">
            <!-- Jadwal Hari Ini -->
            <div class="col-lg-6 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Jadwal Hari Ini</h5>
                        <p class="chart-section-desc">Daftar jadwal pelajaran untuk hari ini.</p>
                </div>
                    <?php if (!empty($jadwal_hari_ini)): ?>
                        <div style="flex: 1; overflow-y: auto;">
                            <?php 
                            $icon_classes = ['icon-blue', 'icon-purple', 'icon-teal', 'icon-green'];
                            $icon_names = ['book', 'journal-bookmark', 'book-half', 'journal-text'];
                            $index = 0;
                            foreach ($jadwal_hari_ini as $jadwal): 
                                $icon_class = $icon_classes[$index % count($icon_classes)];
                                $icon_name = $icon_names[$index % count($icon_names)];
                                $index++;
                            ?>
                                <div class="jadwal-item">
                                    <div class="d-flex align-items-center">
                                        <div class="jadwal-icon <?php echo $icon_class; ?>">
                                            <i class="bi bi-<?php echo $icon_name; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="dashboard-list-item-title">
                                                <?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?>
                                            </div>
                                            <div class="dashboard-list-item-subtitle">
                                                <?php echo htmlspecialchars($jadwal['nama_guru']); ?>
                                            </div>
                                            <div class="dashboard-list-item-date">
                                                <?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Tidak ada jadwal untuk hari ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hasil Ujian Terakhir & Jadwal Besok -->
            <div class="col-lg-6 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Hasil Ujian Terakhir</h5>
                        <p class="chart-section-desc">Nilai ujian terakhir yang telah dikerjakan.</p>
                </div>
                    <?php if ($hasil_ujian_terakhir): ?>
                        <div style="flex: 1; overflow-y: auto;">
                            <div class="dashboard-list-item">
                                <div class="dashboard-list-item-icon icon-green">
                                    <i class="bi bi-clipboard-check"></i>
                                </div>
                                <div class="dashboard-list-item-content">
                                    <div class="dashboard-list-item-title">
                                        <?php echo htmlspecialchars($hasil_ujian_terakhir['judul_soal']); ?>
                                    </div>
                                    <div class="dashboard-list-item-subtitle">
                                        <?php echo htmlspecialchars($hasil_ujian_terakhir['nama_pelajaran']); ?>
                                    </div>
                                    <div class="dashboard-list-item-date">
                                        <?php echo date('d M Y', strtotime($hasil_ujian_terakhir['waktu_selesai'])); ?>
                                    </div>
                                </div>
                                <div class="text-end ms-3">
                                    <div class="h5 fw-bold mb-0" style="color: #3b82f6;"><?php echo number_format($hasil_ujian_terakhir['nilai'], 0); ?></div>
                                    <span class="badge" style="background: <?php echo $hasil_ujian_terakhir['nilai'] >= 75 ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $hasil_ujian_terakhir['nilai'] >= 75 ? '#16a34a' : '#d97706'; ?>; font-size: 11px; padding: 4px 8px;">
                                        <?php echo $hasil_ujian_terakhir['nilai'] >= 75 ? 'Lulus' : 'Remedial'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-center mt-3 pt-3 border-top">
                                <a href="hasil_latihan.php" class="text-decoration-none" style="color: #3b82f6; font-size: 13px; font-weight: 500;">
                                    Lihat Semua Hasil <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-clipboard2-data text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada hasil ujian</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Jadwal Besok & Pengumuman -->
        <div class="row align-items-stretch mb-4">
            <!-- Jadwal Besok -->
            <div class="col-lg-6 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Jadwal Besok</h5>
                        <p class="chart-section-desc">Persiapan jadwal pelajaran untuk besok.</p>
                </div>
                    <?php if (!empty($jadwal_besok)): ?>
                        <div style="flex: 1; overflow-y: auto;">
                            <?php 
                            $icon_classes = ['icon-blue', 'icon-purple', 'icon-teal', 'icon-green'];
                            $icon_names = ['book', 'journal-bookmark', 'book-half', 'journal-text'];
                            $index = 0;
                            foreach ($jadwal_besok as $jadwal): 
                                $icon_class = $icon_classes[$index % count($icon_classes)];
                                $icon_name = $icon_names[$index % count($icon_names)];
                                $index++;
                            ?>
                                <div class="dashboard-list-item">
                                    <div class="dashboard-list-item-icon <?php echo $icon_class; ?>">
                                        <i class="bi bi-<?php echo $icon_name; ?>"></i>
                                    </div>
                                    <div class="dashboard-list-item-content">
                                        <div class="dashboard-list-item-title">
                                            <?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?>
                                        </div>
                                        <div class="dashboard-list-item-date">
                                            <?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-calendar2-x text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Tidak ada jadwal untuk besok</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pengumuman Terbaru -->
            <div class="col-lg-6 mb-4 d-flex">
                <div class="chart-section w-100">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Pengumuman</h5>
                        <p class="chart-section-desc">Informasi terbaru dari sekolah.</p>
                </div>
                    <?php if ($pengumuman_terbaru): ?>
                        <div style="flex: 1; overflow-y: auto;">
                            <div class="dashboard-list-item">
                                <div class="dashboard-list-item-icon icon-orange">
                                    <i class="bi bi-megaphone"></i>
                                </div>
                                <div class="dashboard-list-item-content">
                                    <div class="dashboard-list-item-title">
                                        <?php echo htmlspecialchars($pengumuman_terbaru['judul']); ?>
                                    </div>
                                    <div class="dashboard-list-item-subtitle">
                                        <?php echo htmlspecialchars($pengumuman_terbaru['kategori'] ?? 'Umum'); ?>
                                    </div>
                                    <div class="dashboard-list-item-date">
                                        <?php echo date('d M Y', strtotime($pengumuman_terbaru['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3 pt-3 border-top">
                                <a href="informasi_akademik.php" class="text-decoration-none" style="color: #3b82f6; font-size: 13px; font-weight: 500;">
                                    Lihat Semua Pengumuman <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-megaphone text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada pengumuman</p>
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
                            <h5 class="chart-section-title">Soal Aktif</h5>
                            <p class="chart-section-desc">Daftar soal yang dapat dikerjakan sekarang.</p>
                        </div>
                        <a href="soal.php" class="text-decoration-none small text-primary">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (count($soal_aktif) > 0): ?>
                        <div style="flex: 1; overflow-y: auto;">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Judul</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Jenis</th>
                                            <th>Tanggal Mulai</th>
                                            <th>Tanggal Selesai</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($soal_aktif as $soal): ?>
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
                                                <td><?php echo $soal['tanggal_mulai'] ? date('d/m/Y H:i', strtotime($soal['tanggal_mulai'])) : '-'; ?></td>
                                                <td><?php echo $soal['tanggal_selesai'] ? date('d/m/Y H:i', strtotime($soal['tanggal_selesai'])) : '-'; ?></td>
                                                <td>
                                                    <a href="kerjakan_soal.php?id=<?php echo $soal['id']; ?>" class="btn btn-sm btn-primary">Kerjakan</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Tidak ada soal aktif</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Menggunakan footer standar
require_once '../../includes/footer.php';
?>
