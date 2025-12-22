<?php
$page_title = 'Dashboard Siswa';
require_once '../../config/session.php';
requireRole(['siswa']);
// Menggunakan header baru
require_once '../../includes/new_header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Semua logika PHP untuk mengambil data dari database tetap sama
// ... (salin semua logika PHP dari file index.php lama di sini)
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

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai'");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$stats['total_soal_selesai'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT AVG(nilai) as avg FROM hasil_ujian WHERE siswa_id = ? AND status = 'selesai' AND nilai IS NOT NULL");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stats['rata_rata_nilai'] = $result['avg'] ? number_format($result['avg'], 2) : 0;
$stmt->close();

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday next week')); // 2 weeks
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$jadwal_minggu_ini = [];
$jadwal_hari_ini = [];
$jadwal_besok = [];

if ($kelas_id) {
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
    
    $jadwal_hari_ini = array_filter($jadwal_minggu_ini, function($j) use ($today) {
        return $j['tanggal'] == $today;
    });
    
    $jadwal_besok = array_filter($jadwal_minggu_ini, function($j) use ($tomorrow) {
        return $j['tanggal'] == $tomorrow;
    });
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card bg-primary text-white border-0 mb-4" style="background: linear-gradient(45deg, rgb(var(--bs-primary-rgb)), rgb(var(--bs-secondary-rgb)));">
                <div class="card-body">
                    <h1 class="card-title h3">Hai, <?php echo htmlspecialchars(explode(' ', $user['nama_lengkap'])[0]); ?>!</h1>
                    <p class="card-text">Selamat datang kembali di dashboard Anda. Tetap semangat belajar!</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Kolom Kiri -->
        <div class="col-lg-8">
            <!-- Stat Cards -->
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-journal-check fs-1 text-primary"></i>
                            <h5 class="card-title mt-3"><?php echo $stats['total_soal_aktif']; ?></h5>
                            <p class="card-text text-muted">Soal Aktif</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-patch-check-fill fs-1 text-success"></i>
                            <h5 class="card-title mt-3"><?php echo $stats['total_soal_selesai']; ?></h5>
                            <p class="card-text text-muted">Soal Selesai</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-bar-chart-line-fill fs-1 text-info"></i>
                            <h5 class="card-title mt-3"><?php echo $stats['rata_rata_nilai']; ?></h5>
                            <p class="card-text text-muted">Rata-rata Nilai</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Jadwal Hari Ini -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">Jadwal Hari Ini</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($jadwal_hari_ini)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($jadwal['nama_guru']); ?></small>
                                    </div>
                                    <span class="badge bg-light text-dark"><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Tidak ada jadwal untuk hari ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div class="col-lg-4">
            <!-- Jadwal Besok -->
            <div class="card border-0 shadow-sm mb-4">
                 <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">Jadwal Besok</h5>
                </div>
                <div class="card-body">
                     <?php if (!empty($jadwal_besok)): ?>
                        <ul class="list-unstyled">
                            <?php foreach (array_slice($jadwal_besok, 0, 4) as $jadwal): ?>
                                <li class="mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <div class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 40px; height: 40px;">
                                                <i class="bi bi-book text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></h6>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?></small>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if(count($jadwal_besok) > 4): ?>
                            <div class="text-center">
                                <a href="jadwal.php" class="btn btn-outline-primary btn-sm">Lihat Semua</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">Tidak ada jadwal untuk besok.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Menggunakan footer standar
require_once '../../includes/footer.php';
?>
<script>
    // Script untuk toggle sidebar di mobile
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('main-content').classList.toggle('active');
    });
</script>
