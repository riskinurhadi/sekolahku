<?php
$page_title = 'Hasil Presensi';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get filter mata pelajaran
$filter_mata_pelajaran = isset($_GET['mata_pelajaran_id']) ? intval($_GET['mata_pelajaran_id']) : 0;

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5; // Jumlah sesi per halaman
$offset = ($page - 1) * $per_page;

// Get all mata pelajaran untuk filter
$stmt = $conn->prepare("SELECT id, nama_pelajaran FROM mata_pelajaran WHERE guru_id = ? ORDER BY nama_pelajaran ASC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$mata_pelajaran_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build query untuk get sesi pelajaran (aktif dan selesai untuk realtime)
$query = "SELECT sp.*, mp.nama_pelajaran, mp.kode_pelajaran,
    (SELECT GROUP_CONCAT(DISTINCT k.nama_kelas SEPARATOR ', ')
     FROM jadwal_pelajaran jp
     JOIN kelas k ON jp.kelas_id = k.id
     WHERE jp.mata_pelajaran_id = sp.mata_pelajaran_id 
     AND DATE(jp.tanggal) = DATE(sp.waktu_mulai) 
     AND TIME(jp.jam_mulai) = TIME(sp.waktu_mulai)
     AND mp.guru_id = sp.guru_id) as nama_kelas_list,
    (SELECT COUNT(*) FROM presensi WHERE sesi_pelajaran_id = sp.id AND status = 'hadir') as total_hadir,
    (SELECT COUNT(*) FROM presensi WHERE sesi_pelajaran_id = sp.id AND status = 'terlambat') as total_terlambat,
    (SELECT COUNT(*) FROM presensi WHERE sesi_pelajaran_id = sp.id AND status = 'tidak_hadir') as total_tidak_hadir
    FROM sesi_pelajaran sp
    JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id
    WHERE sp.guru_id = ? AND (sp.status = 'selesai' OR sp.status = 'aktif')";

$params = [$guru_id];
$types = "i";

if ($filter_mata_pelajaran > 0) {
    $query .= " AND sp.mata_pelajaran_id = ?";
    $params[] = $filter_mata_pelajaran;
    $types .= "i";
}

$query .= " ORDER BY sp.waktu_mulai DESC";

// Get total count untuk pagination
$count_query = "SELECT COUNT(*) as total
    FROM sesi_pelajaran sp
    JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id
    WHERE sp.guru_id = ? AND (sp.status = 'selesai' OR sp.status = 'aktif')";

$count_params = [$guru_id];
$count_types = "i";

if ($filter_mata_pelajaran > 0) {
    $count_query .= " AND sp.mata_pelajaran_id = ?";
    $count_params[] = $filter_mata_pelajaran;
    $count_types .= "i";
}

$count_stmt = $conn->prepare($count_query);
if (count($count_params) > 1) {
    $count_stmt->bind_param($count_types, ...$count_params);
} else {
    $count_stmt->bind_param($count_types, $count_params[0]);
}
$count_stmt->execute();
$total_sesi = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_sesi / $per_page);

// Add limit and offset
$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sesi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get detail presensi untuk setiap sesi
$presensi_details = [];
foreach ($sesi_list as $sesi) {
    $sesi_id = $sesi['id'];
    
    // Get siswa yang hadir
    $stmt = $conn->prepare("SELECT p.*, u.nama_lengkap, u.username, k.nama_kelas
        FROM presensi p
        JOIN users u ON p.siswa_id = u.id
        LEFT JOIN kelas k ON u.kelas_id = k.id
        WHERE p.sesi_pelajaran_id = ? AND p.status = 'hadir'
        ORDER BY p.waktu_presensi ASC");
    $stmt->bind_param("i", $sesi_id);
    $stmt->execute();
    $hadir = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get siswa yang terlambat
    $stmt = $conn->prepare("SELECT p.*, u.nama_lengkap, u.username, k.nama_kelas
        FROM presensi p
        JOIN users u ON p.siswa_id = u.id
        LEFT JOIN kelas k ON u.kelas_id = k.id
        WHERE p.sesi_pelajaran_id = ? AND p.status = 'terlambat'
        ORDER BY p.waktu_presensi ASC");
    $stmt->bind_param("i", $sesi_id);
    $stmt->execute();
    $terlambat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get siswa yang tidak hadir (harus cari dari kelas yang terkait dengan jadwal)
    // Untuk ini, kita perlu tahu kelas mana yang terkait dengan sesi ini
    // Kita bisa ambil dari jadwal_pelajaran yang sesuai dengan waktu_mulai sesi
    $tidak_hadir = [];
    $stmt = $conn->prepare("SELECT DISTINCT jp.kelas_id 
        FROM jadwal_pelajaran jp
        JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
        WHERE mp.id = ? AND DATE(jp.tanggal) = DATE(?) AND TIME(jp.jam_mulai) = TIME(?)
        AND mp.guru_id = ?");
    $stmt->bind_param("issi", $sesi['mata_pelajaran_id'], $sesi['waktu_mulai'], $sesi['waktu_mulai'], $guru_id);
    $stmt->execute();
    $kelas_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get siswa yang sudah presensi (hadir atau terlambat)
    $siswa_yang_sudah_presensi = [];
    foreach ($hadir as $h) {
        $siswa_yang_sudah_presensi[] = $h['siswa_id'];
    }
    foreach ($terlambat as $t) {
        $siswa_yang_sudah_presensi[] = $t['siswa_id'];
    }
    
    // Get semua siswa dari semua kelas yang terkait dengan jadwal ini
    if (!empty($kelas_results)) {
        $kelas_ids = array_column($kelas_results, 'kelas_id');
        $placeholders = implode(',', array_fill(0, count($kelas_ids), '?'));
        $types = str_repeat('i', count($kelas_ids)) . 'i';
        $params = array_merge($kelas_ids, [$sekolah_id]);
        
        $stmt = $conn->prepare("SELECT u.id, u.nama_lengkap, u.username, k.nama_kelas
            FROM users u
            LEFT JOIN kelas k ON u.kelas_id = k.id
            WHERE u.role = 'siswa' AND u.kelas_id IN ($placeholders) AND u.sekolah_id = ?
            ORDER BY u.nama_lengkap ASC");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $all_siswa = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Siswa yang tidak hadir adalah yang tidak ada di list presensi
        foreach ($all_siswa as $siswa) {
            if (!in_array($siswa['id'], $siswa_yang_sudah_presensi)) {
                $tidak_hadir[] = $siswa;
            }
        }
    }
    
    $presensi_details[$sesi_id] = [
        'hadir' => $hadir,
        'terlambat' => $terlambat,
        'tidak_hadir' => $tidak_hadir
    ];
}

$conn->close();
?>

<div class="page-header">
    <h2><i class="bi bi-clipboard-check"></i> Hasil Presensi</h2>
    <p>Lihat hasil presensi siswa secara realtime berdasarkan mata pelajaran</p>
</div>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <label for="mata_pelajaran_id" class="form-label">Filter Mata Pelajaran</label>
                        <select class="form-select" id="mata_pelajaran_id" name="mata_pelajaran_id" onchange="this.form.submit()">
                            <option value="0">Semua Mata Pelajaran</option>
                            <?php foreach ($mata_pelajaran_list as $mp): ?>
                                <option value="<?php echo $mp['id']; ?>" <?php echo $filter_mata_pelajaran == $mp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mp['nama_pelajaran']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 text-md-end">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Sesi List -->
<?php if (empty($sesi_list)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="text-muted mt-3 mb-0">Belum ada hasil presensi</p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($sesi_list as $sesi): 
        $presensi = $presensi_details[$sesi['id']] ?? ['hadir' => [], 'terlambat' => [], 'tidak_hadir' => []];
        $total_siswa = count($presensi['hadir']) + count($presensi['terlambat']) + count($presensi['tidak_hadir']);
    ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="bi bi-book"></i> <?php echo htmlspecialchars($sesi['nama_pelajaran']); ?>
                                    <?php if ($sesi['kode_pelajaran']): ?>
                                        <small class="ms-2">(<?php echo htmlspecialchars($sesi['kode_pelajaran']); ?>)</small>
                                    <?php endif; ?>
                                </h5>
                                <small class="d-block mt-1">
                                    <i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($sesi['waktu_mulai'])); ?> - 
                                    <?php echo date('H:i', strtotime($sesi['waktu_selesai'])); ?>
                                    <?php if ($sesi['nama_kelas_list']): ?>
                                        | <i class="bi bi-people"></i> Kelas: <?php echo htmlspecialchars($sesi['nama_kelas_list']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-white text-dark px-3 py-2" style="border-radius: 8px; font-weight: 600;">KODE: <?php echo htmlspecialchars($sesi['kode_presensi']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Summary Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-center p-4 rounded" style="background: #d1fae5;">
                                    <h3 class="mb-0" style="color: #10b981; font-size: 2.5rem; font-weight: 700;"><?php echo count($presensi['hadir']); ?></h3>
                                    <small class="text-muted" style="font-weight: 600; font-size: 0.9rem;">Hadir</small>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-center p-4 rounded" style="background: #fed7aa;">
                                    <h3 class="mb-0" style="color: #f97316; font-size: 2.5rem; font-weight: 700;"><?php echo count($presensi['terlambat']); ?></h3>
                                    <small class="text-muted" style="font-weight: 600; font-size: 0.9rem;">Terlambat</small>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-center p-4 rounded" style="background: #fecdd3;">
                                    <h3 class="mb-0" style="color: #f43f5e; font-size: 2.5rem; font-weight: 700;"><?php echo count($presensi['tidak_hadir']); ?></h3>
                                    <small class="text-muted" style="font-weight: 600; font-size: 0.9rem;">Tidak Hadir</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-4 rounded" style="background: #dbeafe;">
                                    <h3 class="mb-0" style="color: #3b82f6; font-size: 2.5rem; font-weight: 700;"><?php echo $total_siswa; ?></h3>
                                    <small class="text-muted" style="font-weight: 600; font-size: 0.9rem;">Total Siswa</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detail Presensi -->
                        <div class="row">
                            <!-- Hadir -->
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100" style="border-color: #e5e7eb !important; background: #ffffff;">
                                    <h6 class="mb-3" style="color: #10b981; font-weight: 600; font-size: 1rem;">
                                        <i class="bi bi-check-circle-fill" style="color: #10b981;"></i> Hadir (<?php echo count($presensi['hadir']); ?>)
                                    </h6>
                                    <?php if (!empty($presensi['hadir'])): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($presensi['hadir'] as $h): ?>
                                                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($h['nama_lengkap']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($h['username']); ?>
                                                                <?php if ($h['nama_kelas']): ?>
                                                                    - <?php echo htmlspecialchars($h['nama_kelas']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo date('H:i', strtotime($h['waktu_presensi'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Tidak ada siswa yang hadir</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Terlambat -->
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100" style="border-color: #e5e7eb !important; background: #ffffff;">
                                    <h6 class="mb-3" style="color: #f97316; font-weight: 600; font-size: 1rem;">
                                        <i class="bi bi-clock-history" style="color: #f97316;"></i> Terlambat (<?php echo count($presensi['terlambat']); ?>)
                                    </h6>
                                    <?php if (!empty($presensi['terlambat'])): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($presensi['terlambat'] as $t): ?>
                                                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($t['nama_lengkap']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($t['username']); ?>
                                                                <?php if ($t['nama_kelas']): ?>
                                                                    - <?php echo htmlspecialchars($t['nama_kelas']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo date('H:i', strtotime($t['waktu_presensi'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Tidak ada siswa yang terlambat</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Tidak Hadir -->
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100" style="border-color: #e5e7eb !important; background: #ffffff;">
                                    <h6 class="mb-3" style="color: #f43f5e; font-weight: 600; font-size: 1rem;">
                                        <i class="bi bi-x-circle-fill" style="color: #f43f5e;"></i> Tidak Hadir (<?php echo count($presensi['tidak_hadir']); ?>)
                                    </h6>
                                    <?php if (!empty($presensi['tidak_hadir'])): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($presensi['tidak_hadir'] as $th): ?>
                                                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($th['nama_lengkap']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($th['username']); ?>
                                                            <?php if ($th['nama_kelas']): ?>
                                                                - <?php echo htmlspecialchars($th['nama_kelas']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0" style="font-size: 0.9rem;">Semua siswa hadir</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="row mt-4">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filter_mata_pelajaran > 0 ? '&mata_pelajaran_id=' . $filter_mata_pelajaran : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo $filter_mata_pelajaran > 0 ? '&mata_pelajaran_id=' . $filter_mata_pelajaran : ''; ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filter_mata_pelajaran > 0 ? '&mata_pelajaran_id=' . $filter_mata_pelajaran : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $filter_mata_pelajaran > 0 ? '&mata_pelajaran_id=' . $filter_mata_pelajaran : ''; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Next Button -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filter_mata_pelajaran > 0 ? '&mata_pelajaran_id=' . $filter_mata_pelajaran : ''; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Menampilkan <?php echo count($sesi_list); ?> dari <?php echo $total_sesi; ?> sesi (Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>)
                    </small>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>

