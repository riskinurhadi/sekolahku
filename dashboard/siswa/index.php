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

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE mp.sekolah_id = ? AND s.status = 'aktif'");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_soal_aktif'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai'");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$stats['total_soal_selesai'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai'");
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
$hasil_terbaru = $conn->query("SELECT hu.*, s.judul, mp.nama_pelajaran, mp.kode_pelajaran
    FROM hasil_ujian hu
    JOIN soal s ON hu.soal_id = s.id
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
    WHERE hu.siswa_id = $siswa_id AND hu.status = 'selesai'
    ORDER BY hu.waktu_selesai DESC
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Get active soal
$active_soal = $conn->query("SELECT s.*, mp.nama_pelajaran, 
    (SELECT COUNT(*) FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = $siswa_id) as sudah_dikerjakan
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE mp.sekolah_id = $sekolah_id AND s.status = 'aktif' 
    ORDER BY s.created_at DESC 
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!-- Statistics Cards -->
<div class="row statistics-row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_soal_aktif']; ?></div>
                <div class="stat-label">Soal<br>Aktif</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_soal_selesai']; ?></div>
                <div class="stat-label">Soal<br>Selesai</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['rata_rata_nilai']; ?></div>
                <div class="stat-label">Rata-rata Nilai</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_soal_aktif'] - $stats['total_soal_selesai']; ?></div>
                <div class="stat-label">Belum Dikerjakan</div>
            </div>
        </div>
    </div>
</div>

<!-- Jadwal Besok & Ringkasan Jadwal Minggu Ini -->
<?php if ($kelas_id): ?>
<div class="row mt-4 align-items-stretch">
    <!-- Jadwal Besok -->
    <?php if (!empty($jadwal_besok)): ?>
        <div class="col-lg-6 mb-4 d-flex">
            <div class="dashboard-card w-100 d-flex flex-column" style="max-height: 400px;">
                <div class="card-header bg-info text-white flex-shrink-0">
                    <h6 class="mb-0"><i class="bi bi-calendar-check"></i> Jadwal Besok</h6>
                </div>
                <div class="card-body flex-grow-1 d-flex flex-column" style="overflow: hidden;">
                    <div class="flex-grow-1" style="overflow-y: auto; min-height: 0;">
                        <?php 
                        $icon_styles = [
                            ['gradient' => 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)', 'icon' => 'book'],
                            ['gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)', 'icon' => 'journal-bookmark'],
                            ['gradient' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)', 'icon' => 'book-half'],
                            ['gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', 'icon' => 'journal-text']
                        ];
                        $index = 0;
                        foreach ($jadwal_besok as $j): 
                            $style = $icon_styles[$index % count($icon_styles)];
                            $index++;
                        ?>
                            <div class="history-task-item mb-2 p-2 bg-white rounded border" style="border-color: #e2e8f0 !important; transition: all 0.2s ease;">
                                <div class="d-flex align-items-start">
                                    <div class="history-icon-wrapper me-3 flex-shrink-0" style="width: 42px; height: 42px; border-radius: 10px; background: <?php echo $style['gradient']; ?>; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                        <i class="bi bi-<?php echo $style['icon']; ?> text-white" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-semibold" style="color: #1e293b; font-size: 14px; line-height: 1.3;">
                                            <?php echo htmlspecialchars($j['nama_pelajaran']); ?>
                                        </h6>
                                        <p class="mb-0 text-muted" style="font-size: 12px; line-height: 1.4; color: #64748b;">
                                            <?php echo date('H:i', strtotime($j['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($j['jam_selesai'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Presensi Minggu Ini -->
    <div class="col-lg-<?php echo !empty($jadwal_besok) ? '6' : '12'; ?> mb-4 d-flex">
        <div class="dashboard-card w-100 d-flex flex-column" style="max-height: 400px;">
            <div class="card-header d-flex justify-content-between align-items-center flex-shrink-0">
                <h6 class="mb-0"><i class="bi bi-clipboard-check"></i> Presensi Minggu Ini</h6>
                <a href="presensi.php" class="text-decoration-none small">Lihat Detail <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body flex-grow-1 d-flex flex-column justify-content-center">
                <?php if ($presensi_stats['total'] > 0): ?>
                    <div class="text-center">
                        <h1 class="text-primary mb-1 fw-bold" style="font-size: 3.5rem; line-height: 1;">
                            <?php echo $presensi_stats['persentase']; ?>%
                        </h1>
                        <p class="text-muted mb-3" style="font-size: 0.9rem;">Kehadiran</p>
                        <div class="progress mx-auto mb-3" style="height: 10px; max-width: 280px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $presensi_stats['persentase']; ?>%" 
                                 aria-valuenow="<?php echo $presensi_stats['persentase']; ?>" 
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="row text-center mt-2 pt-2 border-top">
                            <div class="col-4">
                                <div class="py-1">
                                    <h3 class="text-success mb-1 fw-bold" style="font-size: 2rem;"><?php echo $presensi_stats['hadir']; ?></h3>
                                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Hadir</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="py-1">
                                    <h3 class="text-warning mb-1 fw-bold" style="font-size: 2rem;"><?php echo $presensi_stats['terlambat']; ?></h3>
                                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Terlambat</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="py-1">
                                    <h3 class="text-danger mb-1 fw-bold" style="font-size: 2rem;"><?php echo $presensi_stats['tidak_hadir']; ?></h3>
                                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Tidak Hadir</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-top">
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">Total: <?php echo $presensi_stats['total']; ?> sesi</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-clipboard-check text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="text-muted mt-3 mb-0">Belum ada data presensi minggu ini</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Ringkasan Jadwal & Hasil Terbaru -->
<?php if ($kelas_id): ?>
<div class="row mt-4 align-items-stretch">
    <!-- Ringkasan Jadwal Minggu Ini -->
    <div class="col-lg-4 mb-4 d-flex">
        <div class="dashboard-card w-100 d-flex flex-column h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-shrink-0">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Ringkasan Jadwal Minggu Ini</h5>
                <a href="jadwal.php" class="text-decoration-none small">Lihat Detail <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body flex-grow-1 d-flex flex-column justify-content-center">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="p-2">
                            <h4 class="text-primary mb-0"><?php echo count($jadwal_minggu_ini); ?></h4>
                            <small class="text-muted">Total Jadwal</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="p-2">
                            <h4 class="text-success mb-0">
                                <?php echo count(array_filter($jadwal_minggu_ini, function($j) { return $j['status'] == 'berlangsung'; })); ?>
                            </h4>
                            <small class="text-muted">Berlangsung</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2">
                            <h4 class="text-info mb-0">
                                <?php echo count(array_filter($jadwal_minggu_ini, function($j) { return $j['status'] == 'selesai'; })); ?>
                            </h4>
                            <small class="text-muted">Selesai</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2">
                            <h4 class="text-secondary mb-0">
                                <?php echo count(array_filter($jadwal_minggu_ini, function($j) { return $j['status'] == 'terjadwal'; })); ?>
                            </h4>
                            <small class="text-muted">Terjadwal</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hasil Terbaru -->
    <div class="col-lg-8 mb-4 d-flex">
        <div class="dashboard-card w-100 d-flex flex-column h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-shrink-0">
                <h5 class="mb-0"><i class="bi bi-trophy"></i> Hasil Terbaru</h5>
                <a href="hasil.php" class="text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body flex-grow-1 d-flex flex-column">
                <?php if (count($hasil_terbaru) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
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
                                        <td>
                                            <strong><?php echo htmlspecialchars($hasil['nama_pelajaran']); ?></strong>
                                        </td>
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
                                        <td>
                                            <small><?php echo date('d/m/Y', strtotime($hasil['waktu_selesai'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="hasil.php?soal_id=<?php echo $hasil['soal_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-trophy text-muted" style="font-size: 2.5rem; opacity: 0.3;"></i>
                        <p class="text-muted mt-2 mb-0">Belum ada hasil ujian</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Active Soal -->
<div class="row mt-4">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Soal Aktif</h5>
                <a href="soal_saya.php" class="text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body">
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
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-text"></i>
                        <h5>Tidak ada soal aktif</h5>
                        <p>Belum ada soal yang tersedia untuk dikerjakan saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

