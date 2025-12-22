<?php
$page_title = 'Dashboard Siswa';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// --- LOGIKA DATA (TETAP SAMA) ---
$stmt = $conn->prepare("SELECT nama_lengkap, kelas_id FROM users WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa_info = $stmt->get_result()->fetch_assoc();
$nama_siswa = $siswa_info['nama_lengkap'] ?? 'Siswa';
$kelas_id = $siswa_info['kelas_id'] ?? null;
$stmt->close();

$stats = ['total_soal_aktif' => 0, 'total_soal_selesai' => 0, 'total_nilai' => 0, 'rata_rata_nilai' => 0];
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id WHERE mp.sekolah_id = ? AND s.status = 'aktif' AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?) AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)");
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
$stats['rata_rata_nilai'] = $result['avg'] ? number_format($result['avg'], 1) : 0;
$stmt->close();

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday next week'));
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$jadwal_minggu_ini = [];
$jadwal_hari_ini = [];
$jadwal_besok = [];

if ($kelas_id) {
    $stmt = $conn->prepare("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru, k.nama_kelas FROM jadwal_pelajaran jp JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id JOIN users u ON mp.guru_id = u.id JOIN kelas k ON jp.kelas_id = k.id WHERE jp.kelas_id = ? AND jp.tanggal BETWEEN ? AND ? ORDER BY jp.tanggal ASC, jp.jam_mulai ASC");
    $stmt->bind_param("iss", $kelas_id, $week_start, $week_end);
    $stmt->execute();
    $jadwal_minggu_ini = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $jadwal_hari_ini = array_filter($jadwal_minggu_ini, fn($j) => $j['tanggal'] == $today);
    $jadwal_besok = array_filter($jadwal_minggu_ini, fn($j) => $j['tanggal'] == $tomorrow);
}

$presensi_stats = ['total' => 0, 'hadir' => 0, 'terlambat' => 0, 'tidak_hadir' => 0, 'persentase' => 0];
if ($kelas_id) {
    $stmt = $conn->prepare("SELECT p.status, COUNT(*) as total FROM presensi p JOIN sesi_pelajaran sp ON p.sesi_pelajaran_id = sp.id WHERE p.siswa_id = ? AND DATE(sp.waktu_mulai) BETWEEN ? AND ? GROUP BY p.status");
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

$stmt = $conn->prepare("SELECT hu.*, s.judul, mp.nama_pelajaran, mp.kode_pelajaran FROM hasil_ujian hu JOIN soal s ON hu.soal_id = s.id JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id WHERE hu.siswa_id = ? AND hu.status = 'selesai' ORDER BY hu.waktu_selesai DESC LIMIT 5");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$hasil_terbaru = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran, CASE WHEN EXISTS (SELECT 1 FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai') THEN 1 ELSE 0 END as sudah_dikerjakan FROM soal s JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id WHERE mp.sekolah_id = ? AND s.status = 'aktif' AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?) AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?) ORDER BY s.created_at DESC LIMIT 5");
$stmt->bind_param("iiss", $siswa_id, $sekolah_id, $now, $now);
$stmt->execute();
$active_soal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$trend_data = ['soal_aktif' => [], 'soal_selesai' => [], 'rata_nilai' => [], 'belum_dikerjakan' => []];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_dt = $date . ' 23:59:59';
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id WHERE mp.sekolah_id = ? AND s.status = 'aktif' AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?) AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?)");
    $stmt->bind_param("iss", $sekolah_id, $date_dt, $date_dt);
    $stmt->execute();
    $trend_data['soal_aktif'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai' AND DATE(waktu_selesai) = ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $trend_data['soal_selesai'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL AND DATE(waktu_selesai) <= ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $avg = $stmt->get_result()->fetch_assoc()['avg'];
    $trend_data['rata_nilai'][] = $avg ? round($avg, 1) : 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id WHERE mp.sekolah_id = ? AND s.status = 'aktif' AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?) AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?) AND NOT EXISTS (SELECT 1 FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai')");
    $stmt->bind_param("issi", $sekolah_id, $date_dt, $date_dt, $siswa_id);
    $stmt->execute();
    $trend_data['belum_dikerjakan'][] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT mp.nama_pelajaran, COUNT(hu.id) as total_soal, AVG(hu.nilai) as avg_nilai FROM hasil_ujian hu JOIN soal s ON hu.soal_id = s.id JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id WHERE hu.siswa_id = ? AND hu.status = 'selesai' AND hu.nilai IS NOT NULL GROUP BY mp.id, mp.nama_pelajaran ORDER BY total_soal DESC LIMIT 5");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$top_pelajaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$nilai_trend = []; $nilai_labels = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $nilai_labels[] = date('d M', strtotime($date));
    $stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL AND DATE(waktu_selesai) = ?");
    $stmt->bind_param("is", $siswa_id, $date);
    $stmt->execute();
    $avg = $stmt->get_result()->fetch_assoc()['avg'];
    $nilai_trend[] = $avg ? round($avg, 1) : null;
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id WHERE mp.sekolah_id = ? AND s.status = 'aktif' AND (s.tanggal_mulai IS NULL OR s.tanggal_mulai <= ?) AND (s.tanggal_selesai IS NULL OR s.tanggal_selesai >= ?) AND NOT EXISTS (SELECT 1 FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai')");
$stmt->bind_param("issi", $sekolah_id, $now, $now, $siswa_id);
$stmt->execute();
$stats['total_belum_dikerjakan'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$conn->close();
?>

<style>
:root {
    --primary-blue: #0f172a;
    --accent-blue: #3b82f6;
    --bg-light: #f8fafc;
    --text-muted: #64748b;
}

body {
    background-color: var(--bg-light);
    color: var(--primary-blue);
    font-family: 'Inter', sans-serif;
}

/* Greeting Section */
.greeting-box {
    padding: 2rem 0;
}
.greeting-box h1 {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary-blue);
}
.greeting-box p {
    color: var(--text-muted);
}

/* Modern Tabs */
.nav-pills-modern {
    background: #fff;
    padding: 5px;
    border-radius: 12px;
    display: inline-flex;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}
.nav-pills-modern .nav-link {
    border-radius: 10px;
    color: var(--text-muted);
    font-weight: 600;
    padding: 8px 20px;
}
.nav-pills-modern .nav-link.active {
    background-color: var(--accent-blue);
    color: #fff;
}

/* Stat Cards */
.card-stat {
    border: none;
    border-radius: 16px;
    padding: 1.5rem;
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    transition: transform 0.2s;
}
.card-stat:hover {
    transform: translateY(-5px);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}
.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    display: block;
}
.stat-label {
    color: var(--text-muted);
    font-size: 0.9rem;
    font-weight: 500;
}

/* Main Content Card */
.content-card {
    border: none;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 4px 25px rgba(0,0,0,0.03);
    margin-bottom: 1.5rem;
}
.content-card .card-header {
    background: transparent;
    border-bottom: 1px solid #f1f5f9;
    padding: 1.25rem 1.5rem;
}
.content-card .card-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
}

/* Custom Table */
.table thead th {
    background: #f8fafc;
    border: none;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}
.table td {
    vertical-align: middle;
    border-color: #f1f5f9;
    padding: 1rem 0.75rem;
}

/* Schedule List */
.schedule-item {
    padding: 1rem;
    border-left: 4px solid var(--accent-blue);
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 1rem;
}
</style>

<div class="container-fluid px-4">
    <!-- Header Greeting -->
    <div class="greeting-box d-flex justify-content-between align-items-center">
        <div>
            <h1>Halo, <?php echo htmlspecialchars(explode(' ', $nama_siswa)[0]); ?>! 👋</h1>
            <p>Ayo selesaikan tugasmu hari ini. Semangat belajar!</p>
        </div>
        <div class="text-end d-none d-md-block">
            <h5 class="mb-0 fw-bold"><?php echo date('d M Y'); ?></h5>
            <small class="text-muted"><?php echo date('l'); ?></small>
        </div>
    </div>

    <!-- Modern Tabs Navigation -->
    <ul class="nav nav-pills nav-pills-modern" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-overview-tab" data-bs-toggle="pill" data-bs-target="#pills-overview" type="button" role="tab">Ringkasan</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-nilai-tab" data-bs-toggle="pill" data-bs-target="#pills-nilai" type="button" role="tab">Hasil Belajar</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-presensi-tab" data-bs-toggle="pill" data-bs-target="#pills-presensi" type="button" role="tab">Kehadiran</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-notifikasi-tab" data-bs-toggle="pill" data-bs-target="#pills-notifikasi" type="button" role="tab">Tugas Aktif</button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <!-- TAB OVERVIEW -->
        <div class="tab-pane fade show active" id="pills-overview" role="tabpanel">
            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card-stat">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <span class="stat-value"><?php echo $stats['total_soal_selesai']; ?></span>
                        <span class="stat-label">Tugas Selesai</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-stat">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <span class="stat-value"><?php echo $stats['rata_rata_nilai']; ?></span>
                        <span class="stat-label">Rata-rata Nilai</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-stat">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <span class="stat-value"><?php echo $presensi_stats['persentase']; ?>%</span>
                        <span class="stat-label">Kehadiran</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-stat">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <span class="stat-value"><?php echo $stats['total_belum_dikerjakan']; ?></span>
                        <span class="stat-label">Tugas Menunggu</span>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column: Chart and Latest Results -->
                <div class="col-lg-8">
                    <div class="content-card card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Grafik Progres Nilai</h5>
                            <small class="text-muted">30 Hari Terakhir</small>
                        </div>
                        <div class="card-body">
                            <canvas id="mainTrendChart" style="height: 300px;"></canvas>
                        </div>
                    </div>

                    <div class="content-card card">
                        <div class="card-header">
                            <h5 class="card-title">Riwayat Ujian Terbaru</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Mata Pelajaran</th>
                                            <th>Nilai</th>
                                            <th>Tanggal</th>
                                            <th class="text-end pe-4">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($hasil_terbaru)): foreach ($hasil_terbaru as $h): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="fw-bold d-block"><?php echo htmlspecialchars($h['nama_pelajaran']); ?></span>
                                                <small class="text-muted"><?php echo htmlspecialchars($h['judul']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo $h['nilai'] >= 75 ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo number_format($h['nilai'], 0); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($h['waktu_selesai'])); ?></td>
                                            <td class="text-end pe-4">
                                                <a href="hasil.php?soal_id=<?php echo $h['soal_id']; ?>" class="btn btn-sm btn-light border">Detail</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data riwayat.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Schedule and Attendance -->
                <div class="col-lg-4">
                    <div class="content-card card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Jadwal Besok</h5>
                            <i class="bi bi-calendar-event text-primary"></i>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($jadwal_besok)): foreach (array_slice($jadwal_besok, 0, 3) as $j): ?>
                            <div class="schedule-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($j['nama_pelajaran']); ?></h6>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($j['nama_guru']); ?></small>
                                    </div>
                                    <span class="badge bg-white text-dark border"><?php echo date('H:i', strtotime($j['jam_mulai'])); ?></span>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                            <div class="text-center py-3">
                                <p class="text-muted small mb-0">Tidak ada jadwal untuk besok.</p>
                            </div>
                            <?php endif; ?>
                            <a href="jadwal.php" class="btn btn-outline-primary btn-sm w-100 mt-2">Lihat Jadwal Lengkap</a>
                        </div>
                    </div>

                    <div class="content-card card">
                        <div class="card-header">
                            <h5 class="card-title">Distribusi Nilai</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="distributionPieChart" style="max-height: 200px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB NILAI (Konten aslinya tetap ada) -->
        <div class="tab-pane fade" id="pills-nilai" role="tabpanel">
            <div class="content-card card">
                <div class="card-body">
                    <!-- Gunakan tabel atau list hasil yang lebih lengkap di sini -->
                    <h5 class="fw-bold mb-4">Semua Hasil Ujian</h5>
                    <div class="table-responsive">
                         <!-- [Table content as provided in original code] -->
                         <table class="table">
                            <thead><tr><th>Mata Pelajaran</th><th>Judul</th><th>Nilai</th><th>Waktu Selesai</th></tr></thead>
                            <tbody>
                                <?php foreach($hasil_terbaru as $hasil): ?>
                                <tr>
                                    <td><?php echo $hasil['nama_pelajaran']; ?></td>
                                    <td><?php echo $hasil['judul']; ?></td>
                                    <td><span class="badge bg-primary"><?php echo number_format($hasil['nilai'], 1); ?></span></td>
                                    <td><?php echo $hasil['waktu_selesai']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                         </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB PRESENSI -->
        <div class="tab-pane fade" id="pills-presensi" role="tabpanel">
            <div class="row">
                <div class="col-md-6 mx-auto text-center">
                    <div class="content-card card p-4">
                        <h1 class="display-3 fw-bold text-primary"><?php echo $presensi_stats['persentase']; ?>%</h1>
                        <p class="text-muted">Total Kehadiran Minggu Ini</p>
                        <div class="progress mb-4" style="height: 10px;">
                            <div class="progress-bar bg-primary" style="width: <?php echo $presensi_stats['persentase']; ?>%"></div>
                        </div>
                        <div class="row g-2">
                            <div class="col-4"><div class="p-2 border rounded"><strong><?php echo $presensi_stats['hadir']; ?></strong><br><small>Hadir</small></div></div>
                            <div class="col-4"><div class="p-2 border rounded"><strong><?php echo $presensi_stats['terlambat']; ?></strong><br><small>Telat</small></div></div>
                            <div class="col-4"><div class="p-2 border rounded"><strong><?php echo $presensi_stats['tidak_hadir']; ?></strong><br><small>Izin/Alpha</small></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB NOTIFIKASI / TUGAS AKTIF -->
        <div class="tab-pane fade" id="pills-notifikasi" role="tabpanel">
             <div class="content-card card">
                <div class="card-header"><h5 class="card-title">Daftar Tugas & Soal Aktif</h5></div>
                <div class="card-body">
                    <?php if(!empty($active_soal)): foreach($active_soal as $s): ?>
                    <div class="d-flex align-items-center justify-content-between p-3 border rounded mb-3">
                        <div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($s['judul']); ?></h6>
                            <span class="badge bg-light text-dark me-2"><?php echo htmlspecialchars($s['nama_pelajaran']); ?></span>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo $s['waktu_pengerjaan']; ?> Menit</small>
                        </div>
                        <?php if($s['sudah_dikerjakan']): ?>
                            <span class="text-success small fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Selesai</span>
                        <?php else: ?>
                            <a href="kerjakan_soal.php?id=<?php echo $s['id']; ?>" class="btn btn-primary btn-sm">Kerjakan</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; else: ?>
                    <p class="text-center text-muted py-4">Tidak ada tugas aktif saat ini.</p>
                    <?php endif; ?>
                </div>
             </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Styling Global Chart
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Main Trend Chart (Nilai)
    const ctxTrend = document.getElementById('mainTrendChart').getContext('2d');
    const gradient = ctxTrend.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($nilai_labels); ?>,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: <?php echo json_encode($nilai_trend); ?>,
                borderColor: '#3b82f6',
                borderWidth: 3,
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { drawBorder: false, color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Distribution Pie Chart
    <?php if (!empty($top_pelajaran)): ?>
    new Chart(document.getElementById('distributionPieChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($top_pelajaran, 'nama_pelajaran')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($top_pelajaran, 'total_soal')); ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'],
                hoverOffset: 4,
                borderWidth: 0
            }]
        },
        options: {
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 10 } } }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>