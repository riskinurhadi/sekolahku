<?php
$page_title = 'Dashboard Siswa';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

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

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0">Selamat Datang, <?php echo htmlspecialchars($user['nama_lengkap']); ?>!</h2>
        <p class="text-muted">Berikut adalah ringkasan aktivitas pembelajaran Anda.</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_soal_aktif']; ?></div>
            <div class="stat-label">Soal Aktif</div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_soal_selesai']; ?></div>
            <div class="stat-label">Soal Selesai</div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-value"><?php echo $stats['rata_rata_nilai']; ?></div>
            <div class="stat-label">Rata-rata Nilai</div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_soal_aktif'] - $stats['total_soal_selesai']; ?></div>
            <div class="stat-label">Belum Dikerjakan</div>
        </div>
    </div>
</div>

<!-- Active Soal -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Soal Aktif</h5>
                <a href="soal_saya.php" class="text-success text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
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
                                <?php endforeach; ?>
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

