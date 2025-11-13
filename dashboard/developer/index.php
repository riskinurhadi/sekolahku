<?php
$page_title = 'Dashboard Developer';
require_once '../../config/session.php';
requireRole(['developer']);
require_once '../../includes/header.php';

$conn = getConnection();

// Get statistics
$stats = [
    'total_sekolah' => 0,
    'total_kepala_sekolah' => 0,
    'total_guru' => 0,
    'total_siswa' => 0,
    'total_sekolah_aktif' => 0
];

$result = $conn->query("SELECT COUNT(*) as total FROM sekolah");
$stats['total_sekolah'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'kepala_sekolah'");
$stats['total_kepala_sekolah'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'guru'");
$stats['total_guru'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'");
$stats['total_siswa'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM sekolah WHERE kepala_sekolah_id IS NOT NULL");
$stats['total_sekolah_aktif'] = $result->fetch_assoc()['total'];

// Get recent schools
$recent_schools = $conn->query("SELECT s.*, u.nama_lengkap as kepala_sekolah_nama 
    FROM sekolah s 
    LEFT JOIN users u ON s.kepala_sekolah_id = u.id 
    ORDER BY s.created_at DESC 
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!-- Statistics Cards -->
<div class="row statistics-row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="bi bi-building"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_sekolah']; ?></div>
                <div class="stat-label">Total Sekolah</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="bi bi-person-badge"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_kepala_sekolah']; ?></div>
                <div class="stat-label">Kepala Sekolah</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info">
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
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_siswa']; ?></div>
                <div class="stat-label">Total Siswa</div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Schools -->
<div class="row">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building"></i> Sekolah Terbaru</h5>
                <a href="sekolah.php" class="text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <?php if (count($recent_schools) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Sekolah</th>
                                    <th>Alamat</th>
                                    <th>Kepala Sekolah</th>
                                    <th>Telepon</th>
                                    <th>Tanggal Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_schools as $school): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($school['nama_sekolah']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($school['alamat'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($school['kepala_sekolah_nama'] ?? 'Belum ditetapkan'); ?></td>
                                        <td><?php echo htmlspecialchars($school['telepon'] ?? '-'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($school['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-building"></i>
                        <h5>Belum ada sekolah</h5>
                        <p>Mulai dengan menambahkan sekolah baru.</p>
                        <a href="sekolah.php" class="btn btn-primary">Tambah Sekolah</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

