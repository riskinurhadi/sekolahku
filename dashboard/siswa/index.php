<?php
$page_title = 'Dashboard Siswa';
require_once '../../config/session.php';
requireRole(['siswa']);
// Menggunakan header baru
require_once '../../includes/header.php';

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

// Get latest announcement
$stmt = $conn->prepare("SELECT * FROM informasi_akademik ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$pengumuman_terbaru = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get latest exam result
$stmt = $conn->prepare("SELECT hu.*, s.judul as judul_soal, mp.nama_pelajaran 
    FROM hasil_ujian hu 
    JOIN soal s ON hu.soal_id = s.id 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE hu.siswa_id = ? AND hu.status = 'selesai' 
    ORDER BY hu.waktu_selesai DESC LIMIT 1");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$hasil_ujian_terakhir = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white border-0 shadow-sm" style="background: linear-gradient(45deg, #4361ee, #3f37c9) !important;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h2 class="fw-bold mb-1">Hai, <?php echo htmlspecialchars(explode(' ', $user['nama_lengkap'])[0]); ?>! 👋</h2>
                            <p class="mb-0 opacity-75">Selamat datang kembali di dashboard Anda. Tetap semangat belajar!</p>
                        </div>
                        <div class="d-none d-md-block">
                            <i class="bi bi-mortarboard-fill opacity-25" style="font-size: 4rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12 col-sm-6 col-xl-4 mb-3 mb-xl-0">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="bi bi-journal-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_soal_aktif']; ?></div>
                    <div class="stat-label">Soal Aktif</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4 mb-3 mb-xl-0">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_soal_selesai']; ?></div>
                    <div class="stat-label">Soal Selesai</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-12 col-xl-4">
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="bi bi-bar-chart-line-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['rata_rata_nilai']; ?></div>
                    <div class="stat-label">Rata-rata Nilai</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Kolom Kiri -->
        <div class="col-lg-8">
            <!-- Jadwal Hari Ini -->
            <div class="dashboard-card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-calendar-event"></i> Jadwal Hari Ini</h5>
                </div>
                <div class="card-body p-0" style="height: 350px; overflow-y: auto;">
                    <?php if (!empty($jadwal_hari_ini)): ?>
                        <div class="table-responsive">
                            <table class="table mb-0 table-sticky-header">
                                <thead>
                                    <tr>
                                        <th>Mata Pelajaran</th>
                                        <th>Guru</th>
                                        <th class="text-end">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($jadwal['kode_pelajaran']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($jadwal['nama_guru']); ?></td>
                                            <td class="text-end">
                                                <span class="badge bg-light text-dark">
                                                    <?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <h5>Tidak Ada Jadwal</h5>
                            <p>Tidak ada jadwal pelajaran untuk hari ini.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hasil Ujian Terakhir -->
            <div class="dashboard-card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-award"></i> Hasil Ujian Terakhir</h5>
                    <a href="hasil_latihan.php" class="btn btn-sm btn-link text-primary p-0">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if ($hasil_ujian_terakhir): ?>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($hasil_ujian_terakhir['judul_soal']); ?></h6>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($hasil_ujian_terakhir['nama_pelajaran']); ?></p>
                            </div>
                            <div class="text-end">
                                <div class="h4 fw-bold mb-0 text-primary"><?php echo number_format($hasil_ujian_terakhir['nilai'], 0); ?></div>
                                <span class="badge <?php echo $hasil_ujian_terakhir['nilai'] >= 75 ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo $hasil_ujian_terakhir['nilai'] >= 75 ? 'Lulus' : 'Remedial'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-3 bg-light rounded-3">
                            <div class="row g-2 text-center">
                                <div class="col-6 border-end">
                                    <div class="small text-muted">Poin</div>
                                    <div class="fw-bold text-success"><?php echo number_format($hasil_ujian_terakhir['poin_diperoleh'], 0); ?> / <?php echo number_format($hasil_ujian_terakhir['total_poin'], 0); ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Tanggal</div>
                                    <div class="fw-bold small"><?php echo date('d/m/y', strtotime($hasil_ujian_terakhir['waktu_selesai'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-clipboard2-data fs-2 text-muted opacity-50"></i>
                            <p class="text-muted mt-2 mb-0">Belum ada hasil ujian</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div class="col-lg-4">
            <!-- Jadwal Besok -->
            <div class="dashboard-card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-calendar-plus"></i> Jadwal Besok</h5>
                </div>
                <div class="card-body" style="height: 350px; overflow-y: auto;">
                    <?php if (!empty($jadwal_besok)): ?>
                        <div class="list-group list-group-flush mx-n3 mt-n3 mb-n3">
                            <?php foreach ($jadwal_besok as $jadwal): ?>
                                <div class="list-group-item border-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="d-flex align-items-center justify-content-center bg-light text-primary rounded-circle" style="width: 40px; height: 40px;">
                                                <i class="bi bi-book"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></h6>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if(count($jadwal_besok) > 4): ?>
                            <div class="mt-3 text-center">
                                <a href="jadwal.php" class="btn btn-outline-primary btn-sm w-100">
                                    Lihat Semua Jadwal <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <i class="bi bi-calendar2-x fs-2"></i>
                            <h6 class="mt-2">Tidak ada jadwal besok</h6>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pengumuman Terbaru -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h5><i class="bi bi-megaphone"></i> Pengumuman</h5>
                    <a href="informasi_akademik.php" class="btn btn-sm btn-link text-primary p-0">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if ($pengumuman_terbaru): ?>
                        <div class="mb-3">
                            <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($pengumuman_terbaru['kategori'] ?? 'Umum'); ?></span>
                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($pengumuman_terbaru['judul']); ?></h6>
                            <p class="text-muted small mb-2">
                                <i class="bi bi-clock me-1"></i> <?php echo date('d M Y', strtotime($pengumuman_terbaru['created_at'])); ?>
                            </p>
                            <div class="text-muted small mb-3 text-truncate-2">
                                <?php echo strip_tags($pengumuman_terbaru['isi']); ?>
                            </div>
                            <a href="detail_informasi.php?id=<?php echo $pengumuman_terbaru['id']; ?>" class="btn btn-sm btn-outline-primary w-100">Baca Selengkapnya</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-megaphone fs-2 text-muted opacity-50"></i>
                            <p class="text-muted mt-2 mb-0">Belum ada pengumuman</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
// Menggunakan footer standar
require_once '../../includes/footer.php';
?>
