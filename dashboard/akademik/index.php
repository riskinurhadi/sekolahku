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
$jadwal_hari_ini = $conn->query("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru, k.nama_kelas
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN users u ON mp.guru_id = u.id
    JOIN kelas k ON jp.kelas_id = k.id
    WHERE jp.sekolah_id = $sekolah_id AND jp.tanggal = '$today'
    ORDER BY jp.jam_mulai ASC
    LIMIT 10")->fetch_all(MYSQLI_ASSOC);

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

$conn->close();
?>

<!-- Slider Section -->
<?php if (!empty($sliders)): ?>
<div class="slider-container mb-4" style="position: relative; height: 200px; border-radius: 20px; overflow: hidden; 
    background: rgba(255, 255, 255, 0.15); 
    backdrop-filter: blur(60px) saturate(200%); 
    -webkit-backdrop-filter: blur(60px) saturate(200%);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.7);">
    <div id="sliderCarousel" class="carousel slide h-100" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators">
            <?php foreach ($sliders as $index => $slider): ?>
                <button type="button" data-bs-target="#sliderCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                        class="<?php echo $index === 0 ? 'active' : ''; ?>" 
                        aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                        aria-label="Slide <?php echo $index + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner h-100">
            <?php foreach ($sliders as $index => $slider): ?>
                <div class="carousel-item h-100 <?php echo $index === 0 ? 'active' : ''; ?>" data-bs-interval="5000">
                    <?php if ($slider['link']): ?>
                        <a href="<?php echo htmlspecialchars($slider['link']); ?>" target="_blank" style="display: block; height: 100%;">
                    <?php endif; ?>
                    <img src="<?php echo getBasePath(); ?>uploads/slider/<?php echo htmlspecialchars($slider['gambar']); ?>" 
                         class="d-block w-100 h-100" 
                         style="object-fit: cover;" 
                         alt="<?php echo htmlspecialchars($slider['judul'] ?? 'Slider'); ?>">
                    <?php if ($slider['judul'] || $slider['deskripsi']): ?>
                        <div class="carousel-caption d-none d-md-block" style="background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); padding: 2rem; border-radius: 0 0 20px 20px;">
                            <?php if ($slider['judul']): ?>
                                <h5 style="font-weight: 600; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($slider['judul']); ?></h5>
                            <?php endif; ?>
                            <?php if ($slider['deskripsi']): ?>
                                <p style="margin-bottom: 0;"><?php echo htmlspecialchars($slider['deskripsi']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($slider['link']): ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#sliderCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#sliderCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>
<?php endif; ?>

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
                            <th>Kelas</th>
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
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></span>
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

