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
$recent_soal = $conn->query("SELECT s.*, mp.nama_pelajaran 
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE s.guru_id = $guru_id 
    ORDER BY s.created_at DESC 
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);

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

$conn->close();
?>

<!-- Statistics Cards -->
<div class="row statistics-row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_siswa']; ?></div>
                <div class="stat-label">Total Siswa</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="bi bi-book"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_mata_pelajaran']; ?></div>
                <div class="stat-label">Mapel</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_soal']; ?></div>
                <div class="stat-label">Total Soal</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_soal_aktif']; ?></div>
                <div class="stat-label">Soal Aktif</div>
            </div>
        </div>
    </div>
</div>

<!-- Statistik Per Mata Pelajaran dan History -->
<div class="row mb-4">
    <!-- Statistik Per Mata Pelajaran -->
    <?php if (!empty($stats_per_pelajaran)): ?>
        <div class="col-lg-8 mb-3">
            <div class="row">
                <?php foreach ($stats_per_pelajaran as $pelajaran => $stats): ?>
                    <div class="col-md-6 mb-3">
                        <div class="dashboard-card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-book"></i> <?php echo htmlspecialchars($pelajaran); ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-2">
                                        <div class="p-2">
                                            <h4 class="text-primary mb-0"><?php echo $stats['total']; ?></h4>
                                            <small class="text-muted">Total</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="p-2">
                                            <h4 class="text-info mb-0"><?php echo count($stats['kelas']); ?></h4>
                                            <small class="text-muted">Kelas</small>
                                        </div>
                                    </div>
                                </div>
                                <hr class="my-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <small class="text-muted d-block">Terjadwal</small>
                                        <strong class="text-secondary"><?php echo $stats['terjadwal']; ?></strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Berlangsung</small>
                                        <strong class="text-success"><?php echo $stats['berlangsung']; ?></strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Selesai</small>
                                        <strong class="text-info"><?php echo $stats['selesai']; ?></strong>
                                    </div>
                                </div>
                                <?php if (!empty($stats['kelas'])): ?>
                                    <hr class="my-2">
                                    <div>
                                        <small class="text-muted d-block mb-1">Kelas yang diajar:</small>
                                        <?php foreach ($stats['kelas'] as $kelas): ?>
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($kelas); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- History Pembelajaran -->
    <div class="col-lg-4 mb-3">
        <div class="dashboard-card">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-clock-history"></i> History Pembelajaran</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($history_pelajaran)): ?>
                    <div class="history-list" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($history_pelajaran as $history): ?>
                            <div class="history-item mb-2 pb-2 border-bottom">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-book text-primary me-2"></i>
                                    <div class="flex-grow-1">
                                        <span class="text-primary fw-semibold"><?php echo htmlspecialchars($history['nama_pelajaran']); ?></span>
                                        <span class="text-muted ms-2">- <?php echo htmlspecialchars($history['nama_kelas']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Belum ada history pembelajaran</p>
                        <small class="text-muted">History akan muncul setelah Anda mengakhiri pembelajaran</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Soal -->
<div class="row">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Soal Terbaru</h5>
                <a href="soal.php" class="text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body">
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
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-text"></i>
                        <h5>Belum ada soal</h5>
                        <p>Mulai dengan menambahkan mata pelajaran dan soal baru.</p>
                        <a href="mata_pelajaran.php" class="btn btn-primary">Tambah Mata Pelajaran</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

