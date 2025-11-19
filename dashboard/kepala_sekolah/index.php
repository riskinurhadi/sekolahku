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
    'total_soal' => 0
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

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE mp.sekolah_id = ?");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$stats['total_soal'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get recent teachers
$recent_teachers = $conn->query("SELECT * FROM users 
    WHERE role = 'guru' AND sekolah_id = $sekolah_id 
    ORDER BY created_at DESC 
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);

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
                <i class="bi bi-person-workspace"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_guru']; ?></div>
                <div class="stat-label">Total Guru</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
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
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="bi bi-book"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_mata_pelajaran']; ?></div>
                <div class="stat-label">Mata Pelajaran</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_soal']; ?></div>
                <div class="stat-label">Total Soal</div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Teachers -->
<div class="row">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-workspace"></i> Guru Terbaru</h5>
                <a href="guru.php" class="text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body">
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
                    <div class="empty-state">
                        <i class="bi bi-person-workspace"></i>
                        <h5>Belum ada guru</h5>
                        <p>Mulai dengan menambahkan guru baru.</p>
                        <a href="guru.php" class="btn btn-primary">Tambah Guru</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

