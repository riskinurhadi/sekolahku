<?php
$page_title = 'Dashboard Siswa';
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

// Get statistics
$stats = [
    'total_soal_aktif' => 0,
    'total_soal_selesai' => 0,
    'total_nilai' => 0,
    'rata_rata_nilai' => 0
];

// Soal aktif: soal dengan status aktif dan sudah mulai (tanggal_mulai <= sekarang) dan belum selesai (tanggal_selesai >= sekarang atau null)
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

// Soal selesai: hasil ujian dengan status selesai
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai'");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$stats['total_soal_selesai'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Rata-rata nilai: dari hasil ujian yang selesai
$stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['rata_rata_nilai'] = $result['avg'] ? number_format($result['avg'], 2) : 0;
$stmt->close();

// Get jadwal minggu ini dan besok untuk ringkasan
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday next week')); // 2 weeks
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$jadwal_minggu_ini = [];
$jadwal_hari_ini = [];
$jadwal_besok = [];

if ($kelas_id) {
    // Get jadwal 2 minggu ke depan
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
    
    // Filter jadwal hari ini
    $jadwal_hari_ini = array_filter($jadwal_minggu_ini, function($j) use ($today) {
        return $j['tanggal'] == $today;
    });
    
    // Filter jadwal besok
    $jadwal_besok = array_filter($jadwal_minggu_ini, function($j) use ($tomorrow) {
        return $j['tanggal'] == $tomorrow;
    });
}

// Get presensi minggu ini
$presensi_stats = [
    'total' => 0,
    'hadir' => 0,
    'terlambat' => 0,
    'tidak_hadir' => 0,
    'persentase' => 0
];
if ($kelas_id) {
    $stmt = $conn->prepare("SELECT p.status, COUNT(*) as total
        FROM presensi p
        JOIN sesi_pelajaran sp ON p.sesi_pelajaran_id = sp.id
        WHERE p.siswa_id = ? AND DATE(sp.waktu_mulai) BETWEEN ? AND ?
        GROUP BY p.status");
    $stmt->bind_param("iss", $siswa_id, $week_start, date('Y-m-d', strtotime('sunday this week')));
    $stmt->execute();
    $presensi_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($presensi_result as $p) {
        $presensi_stats['total'] += $p['total'];
        $presensi_stats[$p['status']] = $p['total'];
    }
    
    if ($presensi_stats['total'] > 0) {
        $hadir_total = $presensi_stats['hadir'] + $presensi_stats['terlambat'];
        $presensi_stats['persentase'] = round(($hadir_total / $presensi_stats['total']) * 100, 1);
    }
}

// Get hasil ujian terbaru
$stmt = $conn->prepare("SELECT hu.*, s.judul, mp.nama_pelajaran, mp.kode_pelajaran
    FROM hasil_ujian hu
    JOIN soal s ON hu.soal_id = s.id
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
    WHERE hu.siswa_id = ? AND hu.status = 'selesai'
    ORDER BY hu.waktu_selesai DESC
    LIMIT 5");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$hasil_terbaru = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get active soal: soal aktif yang sudah mulai dan belum selesai, dan belum dikerjakan oleh siswa
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran, 
    CASE 
        WHEN EXISTS (SELECT 1 FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai') THEN 1
        ELSE 0
    END as sudah_dikerjakan
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE mp.sekolah_id = ? AND s.status = 'aktif' 
    AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?)
    AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)
    ORDER BY s.created_at DESC 
    LIMIT 5");
$stmt->bind_param("iiss", $siswa_id, $sekolah_id, $now, $now);
$stmt->execute();
$active_soal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

// Get data untuk trend charts (7 hari terakhir)
$trend_data = [
    'soal_aktif' => [],
    'soal_selesai' => [],
    'rata_nilai' => [],
    'belum_dikerjakan' => []
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_datetime = $date . ' 23:59:59';
    
    // Soal aktif (total aktif pada tanggal tersebut - soal yang sudah mulai dan belum selesai)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s 
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
        WHERE mp.sekolah_id = ? AND s.status = 'aktif' 
        AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?)
        AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)");
    $stmt->bind_param("iss", $sekolah_id, $date_datetime, $date_datetime);
    $stmt->execute();
    $trend_data['soal_aktif'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Soal selesai (selesai pada tanggal tersebut)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian 
        WHERE siswa_id = ? AND status = 'selesai' AND DATE(waktu_selesai) = ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $trend_data['soal_selesai'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Rata-rata nilai (rata-rata nilai sampai tanggal tersebut)
    $stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian 
        WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL AND DATE(waktu_selesai) <= ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $avg = $stmt->get_result()->fetch_assoc()['avg'];
    $trend_data['rata_nilai'][] = $avg ? round($avg, 1) : 0;
    $stmt->close();
    
    // Belum dikerjakan: soal aktif yang belum ada di hasil_ujian untuk siswa ini
    $aktif = $trend_data['soal_aktif'][count($trend_data['soal_aktif']) - 1];
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
        WHERE mp.sekolah_id = ? AND s.status = 'aktif'
        AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?)
        AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)
        AND NOT EXISTS (SELECT 1 FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai')");
    $stmt->bind_param("issi", $sekolah_id, $date_datetime, $date_datetime, $siswa_id);
    $stmt->execute();
    $belum_dikerjakan = $stmt->get_result()->fetch_assoc()['total'];
    $trend_data['belum_dikerjakan'][] = $belum_dikerjakan;
    $stmt->close();
}

// Get data untuk distribusi per mata pelajaran (top 5)
$stmt = $conn->prepare("SELECT mp.nama_pelajaran, COUNT(hu.id) as total_soal, AVG(hu.nilai) as avg_nilai
    FROM hasil_ujian hu
    JOIN soal s ON hu.soal_id = s.id
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
    WHERE hu.siswa_id = ? AND hu.status = 'selesai' AND hu.nilai IS NOT NULL
    GROUP BY mp.id, mp.nama_pelajaran
    ORDER BY total_soal DESC
    LIMIT 5");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$top_pelajaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get data untuk line graph (nilai over time - 30 hari terakhir)
$nilai_trend = [];
$nilai_labels = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $nilai_labels[] = date('d M', strtotime($date));
    
    $stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian 
        WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL AND DATE(waktu_selesai) = ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $avg = $stmt->get_result()->fetch_assoc()['avg'];
    $nilai_trend[] = $avg ? round($avg, 1) : null;
    $stmt->close();
}

// Get data untuk stacked bar (soal per mata pelajaran - 30 hari terakhir)
$soal_per_pelajaran = [];
$pelajaran_list = [];
$stmt = $conn->prepare("SELECT DISTINCT mp.id, mp.nama_pelajaran 
    FROM hasil_ujian hu
    JOIN soal s ON hu.soal_id = s.id
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
    WHERE hu.siswa_id = ? AND hu.status = 'selesai' AND DATE(hu.waktu_selesai) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY mp.nama_pelajaran");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$pelajaran_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($pelajaran_result as $p) {
    $pelajaran_list[] = $p['nama_pelajaran'];
    $soal_data = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian hu
            JOIN soal s ON hu.soal_id = s.id
            WHERE hu.siswa_id = ? AND s.mata_pelajaran_id = ? AND DATE(hu.waktu_selesai) = ?");
        $stmt->bind_param("iis", $siswa_id, $p['id'], $date);
        $stmt->execute();
        $soal_data[] = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    }
    $soal_per_pelajaran[] = $soal_data;
}

// Calculate total soal belum dikerjakan (soal aktif yang belum ada di hasil_ujian untuk siswa ini)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
    WHERE mp.sekolah_id = ? AND s.status = 'aktif'
    AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?)
    AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)
    AND NOT EXISTS (SELECT 1 FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai')");
$stmt->bind_param("issi", $sekolah_id, $now, $now, $siswa_id);
$stmt->execute();
$stats['total_belum_dikerjakan'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Calculate percentage changes untuk stat cards
$prev_soal_aktif = count($trend_data['soal_aktif']) > 1 ? $trend_data['soal_aktif'][count($trend_data['soal_aktif']) - 2] : $stats['total_soal_aktif'];
$prev_soal_selesai = count($trend_data['soal_selesai']) > 1 ? $trend_data['soal_selesai'][count($trend_data['soal_selesai']) - 2] : $stats['total_soal_selesai'];
$prev_rata_nilai = count($trend_data['rata_nilai']) > 1 ? $trend_data['rata_nilai'][count($trend_data['rata_nilai']) - 2] : $stats['rata_rata_nilai'];
$prev_belum_dikerjakan = count($trend_data['belum_dikerjakan']) > 1 ? $trend_data['belum_dikerjakan'][count($trend_data['belum_dikerjakan']) - 2] : $stats['total_belum_dikerjakan'];

$change_soal_aktif = $prev_soal_aktif > 0 ? round((($stats['total_soal_aktif'] - $prev_soal_aktif) / $prev_soal_aktif) * 100, 1) : 0;
$change_soal_selesai = $prev_soal_selesai > 0 ? round((($stats['total_soal_selesai'] - $prev_soal_selesai) / $prev_soal_selesai) * 100, 1) : 0;
$change_rata_nilai = $prev_rata_nilai > 0 ? round((($stats['rata_rata_nilai'] - $prev_rata_nilai) / $prev_rata_nilai) * 100, 1) : 0;
$change_belum_dikerjakan = $prev_belum_dikerjakan > 0 ? round((($stats['total_belum_dikerjakan'] - $prev_belum_dikerjakan) / $prev_belum_dikerjakan) * 100, 1) : 0;

$conn->close();
?>

<style>
:root {
    --primary-color: #8E2DE2;
    --secondary-color: #4A00E0;
    --accent-color: #00F260;
    --bg-color: #f0f2f5;
    --card-bg-color: #ffffff;
    --text-color: #333;
    --text-color-light: #777;
    --shadow-color: rgba(0, 0, 0, 0.1);
    --shadow-color-hover: rgba(0, 0, 0, 0.2);
}

.dashboard-greeting h1 {
    font-size: 1.8rem;
    font-weight: 700;
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.4rem;
}

.dashboard-greeting p {
    font-size: 0.9rem;
    color: var(--text-color-light);
}

.dashboard-tabs .nav-link {
    color: var(--text-color-light);
    font-weight: 600;
    padding: 0.4rem 0.8rem;
    border: none;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.dashboard-tabs .nav-link:hover {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.dashboard-tabs .nav-link.active {
    color: var(--secondary-color);
    border-bottom-color: var(--secondary-color);
}

.metric-card, .chart-section {
    background: var(--card-bg-color);
    border: none;
    border-radius: 15px;
    padding: 1.2rem;
    box-shadow: 0 15px 40px var(--shadow-color);
    transition: all 0.3s ease-in-out;
    height: 100%;
}

.metric-card:hover, .chart-section:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 50px var(--shadow-color-hover);
}

.metric-card .bi {
    font-size: 2.2rem;
    background: -webkit-linear-gradient(45deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 5px 15px var(--shadow-color);
}

.metric-card-header .metric-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-color-light);
}

.metric-card-header .metric-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-color);
    line-height: 1;
}

.metric-change {
    font-size: 0.8rem;
    font-weight: 600;
}

.metric-change.positive {
    color: #00F260;
}

.metric-change.negative {
    color: #ff4d4d;
}

.metric-chart {
    height: 40px;
    margin-top: 0.8rem;
}

.chart-section-header .chart-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-color);
}

.chart-section-header .chart-section-desc {
    font-size: 0.8rem;
    color: var(--text-color-light);
}

.chart-container {
    height: 220px;
}

.top-list-item-name {
    font-weight: 600;
    font-size: 0.9rem;
}

.top-list-item-value {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--primary-color);
}

.chart-container-small {
    height: 150px;
}

.small-chart-section {
    padding: 1rem;
    min-height: 300px;
    max-height: 300px;
}

.small-chart-section .chart-section-title {
    font-size: 1rem;
}

.small-chart-section .chart-section-desc {
    font-size: 0.8rem;
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
            <div class="date-range-selector">
                <span class="date-text"><?php echo date('d M Y'); ?></span>
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
            <button class="nav-link" id="nilai-tab" data-bs-toggle="tab" data-bs-target="#nilai" type="button" role="tab">
                Nilai
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="presensi-tab" data-bs-toggle="tab" data-bs-target="#presensi" type="button" role="tab">
                Presensi
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notifikasi-tab" data-bs-toggle="tab" data-bs-target="#notifikasi" type="button" role="tab">
                Notifikasi
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
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Soal Selesai</div>
                            <div class="metric-value"><?php echo $stats['total_soal_selesai']; ?></div>
                            <div class="metric-change <?php echo $change_soal_selesai >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_soal_selesai >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_soal_selesai); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartSoalSelesai"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Rata-rata Nilai</div>
                            <div class="metric-value"><?php echo $stats['rata_rata_nilai']; ?></div>
                            <div class="metric-change <?php echo $change_rata_nilai >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_rata_nilai >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_rata_nilai); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartRataNilai"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <div>
                            <div class="metric-title">Belum Dikerjakan</div>
                            <div class="metric-value"><?php echo $stats['total_belum_dikerjakan']; ?></div>
                            <div class="metric-change <?php echo $change_belum_dikerjakan >= 0 ? 'negative' : 'positive'; ?>">
                                <i class="bi bi-arrow-<?php echo $change_belum_dikerjakan >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($change_belum_dikerjakan); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="metric-chart">
                        <canvas id="chartBelumDikerjakan"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row align-items-stretch">
            <!-- Top Mata Pelajaran -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100 small-chart-section">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Top Mata Pelajaran</h5>
                        <p class="chart-section-desc">Mata pelajaran dengan soal terbanyak yang telah Anda kerjakan.</p>
                    </div>
                    <?php if (!empty($top_pelajaran)): ?>
                        <ul class="top-list" style="flex: 1; overflow-y: auto;">
                            <?php foreach ($top_pelajaran as $index => $pel): ?>
                                <li class="top-list-item">
                                    <span class="top-list-item-name">
                                        <span style="display: inline-block; width: 24px; height: 24px; border-radius: 6px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; text-align: center; line-height: 24px; font-size: 12px; font-weight: 700; margin-right: 12px;">
                                            <?php echo $index + 1; ?>
                                        </span>
                                        <?php echo htmlspecialchars($pel['nama_pelajaran']); ?>
                                    </span>
                                    <span class="top-list-item-value"><?php echo $pel['total_soal']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-book text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada data</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Presensi Donut Chart -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100 small-chart-section d-flex flex-column justify-content-center">
                    <div class="chart-section-header text-center">
                        <h5 class="chart-section-title">Presensi Minggu Ini</h5>
                        <p class="chart-section-desc">Persentase kehadiran Anda.</p>
                    </div>
                    <?php if ($presensi_stats['total'] > 0): ?>
                        <div class="row text-center my-auto">
                            <div class="col-4">
                                <div class="py-2">
                                    <h4 class="text-success mb-0" style="font-size: 2rem;"><?php echo $presensi_stats['hadir']; ?></h4>
                                    <small class="text-muted">Hadir</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="py-2">
                                    <h4 class="text-warning mb-0" style="font-size: 2rem;"><?php echo $presensi_stats['terlambat']; ?></h4>
                                    <small class="text-muted">Terlambat</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="py-2">
                                    <h4 class="text-danger mb-0" style="font-size: 2rem;"><?php echo $presensi_stats['tidak_hadir']; ?></h4>
                                    <small class="text-muted">Tidak Hadir</small>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 my-auto">
                            <i class="bi bi-clipboard-check text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada data presensi</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Jadwal Besok -->
            <div class="col-lg-4 mb-4 d-flex">
                <div class="chart-section w-100 small-chart-section">
                    <div class="chart-section-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="chart-section-title">Jadwal Besok</h5>
                            <p class="chart-section-desc">Pelajaran yang akan berlangsung besok.</p>
                        </div>
                        <a href="jadwal.php" class="text-decoration-none small text-primary">Lihat <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!empty($jadwal_besok)): ?>
                        <div style="flex: 1; overflow-y: auto; min-height: 0;">
                            <?php foreach (array_slice($jadwal_besok, 0, 5) as $j): ?>
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; margin-right: 12px;">
                                        <i class="bi bi-book"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0" style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                            <?php echo htmlspecialchars($j['nama_pelajaran']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($j['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($j['jam_selesai'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Tidak ada jadwal besok</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 2 -->
        <div class="row">
            <!-- Trend Nilai -->
            <div class="col-lg-8 mb-4">
                <div class="chart-section">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Trend Nilai</h5>
                        <p class="chart-section-desc">Perkembangan nilai rata-rata Anda selama 30 hari terakhir.</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartTrendNilai"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Distribusi Soal per Mata Pelajaran -->
            <div class="col-lg-4 mb-4">
                <div class="chart-section">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Distribusi Soal</h5>
                        <p class="chart-section-desc">Jumlah soal yang telah dikerjakan per mata pelajaran.</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartDistribusiSoal"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 3 -->
        <div class="row">
            <!-- Soal per Mata Pelajaran (Stacked Bar) -->
            <div class="col-lg-12 mb-4">
                <div class="chart-section">
                    <div class="chart-section-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="chart-section-title">Aktivitas Soal per Mata Pelajaran</h5>
                            <p class="chart-section-desc">Distribusi soal yang dikerjakan per mata pelajaran selama 30 hari terakhir.</p>
                        </div>
                        <a href="soal_saya.php" class="text-decoration-none small text-primary">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartSoalPerPelajaran"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Nilai Tab -->
    <div class="tab-pane fade" id="nilai" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="chart-section">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Hasil Ujian Terbaru</h5>
                        <p class="chart-section-desc">Daftar hasil ujian yang telah Anda selesaikan.</p>
                    </div>
                    <?php if (count($hasil_terbaru) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mata Pelajaran</th>
                                        <th>Judul Soal</th>
                                        <th>Nilai</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hasil_terbaru as $hasil): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($hasil['nama_pelajaran']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($hasil['judul']); ?></td>
                                            <td>
                                                <?php 
                                                $nilai = number_format($hasil['nilai'], 1);
                                                $badge_class = $nilai >= 75 ? 'success' : ($nilai >= 60 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?> fs-6">
                                                    <?php echo $nilai; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($hasil['waktu_selesai'])); ?></td>
                                            <td>
                                                <a href="hasil.php?soal_id=<?php echo $hasil['soal_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-trophy text-muted" style="font-size: 3.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada hasil ujian</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Presensi Tab -->
    <div class="tab-pane fade" id="presensi" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="chart-section">
                    <div class="chart-section-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="chart-section-title">Presensi Minggu Ini</h5>
                            <p class="chart-section-desc">Ringkasan kehadiran Anda dalam pembelajaran minggu ini.</p>
                        </div>
                        <a href="presensi.php" class="text-decoration-none small text-primary">Lihat Detail <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if ($presensi_stats['total'] > 0): ?>
                        <div class="text-center py-4" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                            <h1 class="text-primary mb-2 fw-bold" style="font-size: 4rem;">
                                <?php echo $presensi_stats['persentase']; ?>%
                            </h1>
                            <p class="text-muted mb-4">Kehadiran</p>
                            <div class="progress mx-auto mb-4" style="height: 12px; max-width: 400px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $presensi_stats['persentase']; ?>%" 
                                     aria-valuenow="<?php echo $presensi_stats['persentase']; ?>" 
                                     aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="row text-center mt-4">
                                <div class="col-4">
                                    <h3 class="text-success mb-1 fw-bold"><?php echo $presensi_stats['hadir']; ?></h3>
                                    <p class="text-muted mb-0 small">Hadir</p>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-warning mb-1 fw-bold"><?php echo $presensi_stats['terlambat']; ?></h3>
                                    <p class="text-muted mb-0 small">Terlambat</p>
                                </div>
                                <div class="col-4">
                                    <h3 class="text-danger mb-1 fw-bold"><?php echo $presensi_stats['tidak_hadir']; ?></h3>
                                    <p class="text-muted mb-0 small">Tidak Hadir</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <i class="bi bi-clipboard-check text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Belum ada data presensi minggu ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifikasi Tab -->
    <div class="tab-pane fade" id="notifikasi" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="chart-section">
                    <div class="chart-section-header">
                        <h5 class="chart-section-title">Soal Aktif</h5>
                        <p class="chart-section-desc">Daftar soal yang tersedia untuk dikerjakan.</p>
                    </div>
                    <?php if (count($active_soal) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Judul</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Jenis</th>
                                        <th>Waktu</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_soal as $soal): ?>
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
                                            <td><?php echo $soal['waktu_pengerjaan']; ?> menit</td>
                                            <td>
                                                <?php if ($soal['sudah_dikerjakan'] > 0): ?>
                                                    <span class="badge bg-success">Selesai</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Belum Dikerjakan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($soal['sudah_dikerjakan'] > 0): ?>
                                                    <a href="hasil.php?soal_id=<?php echo $soal['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Lihat Hasil
                                                    </a>
                                                <?php else: ?>
                                                    <a href="kerjakan_soal.php?id=<?php echo $soal['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i> Kerjakan
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach;?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3.5rem; opacity: 0.3;"></i>
                            <p class="text-muted mt-3 mb-0">Tidak ada soal aktif</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rootStyles = getComputedStyle(document.documentElement);
    const primaryColor = rootStyles.getPropertyValue('--primary-color').trim();
    const secondaryColor = rootStyles.getPropertyValue('--secondary-color').trim();
    const accentColor = rootStyles.getPropertyValue('--accent-color').trim();

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

    new Chart(document.getElementById('chartSoalAktif'), {
        type: 'line',
        data: {
            labels: ['', '', '', '', '', '', ''],
            datasets: [{
                data: <?php echo json_encode($trend_data['soal_aktif']); ?>,
                borderColor: primaryColor,
                backgroundColor: 'rgba(142, 45, 226, 0.1)',
                fill: true
            }]
        },
        options: chartOptions
    });

    new Chart(document.getElementById('chartSoalSelesai'), {
        type: 'line',
        data: {
            labels: ['', '', '', '', '', '', ''],
            datasets: [{
                data: <?php echo json_encode($trend_data['soal_selesai']); ?>,
                borderColor: secondaryColor,
                backgroundColor: 'rgba(74, 0, 224, 0.1)',
                fill: true
            }]
        },
        options: chartOptions
    });

    new Chart(document.getElementById('chartRataNilai'), {
        type: 'line',
        data: {
            labels: ['', '', '', '', '', '', ''],
            datasets: [{
                data: <?php echo json_encode($trend_data['rata_nilai']); ?>,
                borderColor: accentColor,
                backgroundColor: 'rgba(0, 242, 96, 0.1)',
                fill: true
            }]
        },
        options: chartOptions
    });

    new Chart(document.getElementById('chartBelumDikerjakan'), {
        type: 'line',
        data: {
            labels: ['', '', '', '', '', '', ''],
            datasets: [{
                data: <?php echo json_encode($trend_data['belum_dikerjakan']); ?>,
                borderColor: '#FFC371',
                backgroundColor: 'rgba(255, 195, 113, 0.1)',
                fill: true
            }]
        },
        options: chartOptions
    });

    

    new Chart(document.getElementById('chartTrendNilai'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($nilai_labels); ?>,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: <?php echo json_encode($nilai_trend); ?>,
                borderColor: primaryColor,
                backgroundColor: 'rgba(142, 45, 226, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
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
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    <?php if (!empty($top_pelajaran)): ?>
    new Chart(document.getElementById('chartDistribusiSoal'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($top_pelajaran, 'nama_pelajaran')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($top_pelajaran, 'total_soal')); ?>,
                backgroundColor: [primaryColor, secondaryColor, accentColor, '#FFC371', '#ff4d4d'],
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

    <?php if (!empty($pelajaran_list)): ?>
    const colors = [primaryColor, secondaryColor, accentColor, '#FFC371', '#ff4d4d', '#2980B9'];
    const datasets = <?php echo json_encode($pelajaran_list); ?>.map((pelajaran, index) => ({
        label: pelajaran,
        data: <?php echo json_encode($soal_per_pelajaran); ?>[index] || [],
        backgroundColor: colors[index % colors.length]
    }));

    new Chart(document.getElementById('chartSoalPerPelajaran'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_slice($nilai_labels, -7)); ?>,
            datasets: datasets
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
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>
