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

$conn->close();
?>

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

