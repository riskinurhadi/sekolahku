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
$jadwal_hari_ini = $conn->query("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN users u ON mp.guru_id = u.id
    WHERE jp.sekolah_id = $sekolah_id AND jp.tanggal = '$today'
    ORDER BY jp.jam_mulai ASC
    LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!-- Statistics Cards -->
<div class="row statistics-row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="bi bi-calendar-week"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_jadwal']; ?></div>
                <div class="stat-label">Total Jadwal</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['jadwal_hari_ini']; ?></div>
                <div class="stat-label">Jadwal Hari Ini</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="bi bi-calendar-range"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['jadwal_minggu_ini']; ?></div>
                <div class="stat-label">Jadwal Minggu Ini</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="bi bi-book"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_mata_pelajaran']; ?></div>
                <div class="stat-label">Mata Pelajaran</div>
            </div>
        </div>
    </div>
</div>

<!-- Jadwal Hari Ini -->
<div class="dashboard-card">
    <div class="card-header">
        <h5><i class="bi bi-calendar-day"></i> Jadwal Pelajaran Hari Ini</h5>
        <a href="jadwal.php">Lihat Semua <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="card-body">
        <?php if (empty($jadwal_hari_ini)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h5>Tidak Ada Jadwal</h5>
                <p>Belum ada jadwal pelajaran untuk hari ini.</p>
                <a href="jadwal.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Jadwal
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Jam</th>
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
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

