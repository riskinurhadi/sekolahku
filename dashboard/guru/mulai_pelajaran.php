<?php
$page_title = 'Mulai Pelajaran';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get guru info including spesialisasi
$stmt = $conn->prepare("SELECT spesialisasi FROM users WHERE id = ?");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$guru_info = $stmt->get_result()->fetch_assoc();
$spesialisasi = $guru_info['spesialisasi'] ?? '';
$stmt->close();

// Handle form submission - Update status jadwal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
        $jadwal_id = $_POST['jadwal_id'];
        $status = $_POST['status'];
        
        // Verify that this jadwal belongs to a mata pelajaran taught by this guru
        $stmt = $conn->prepare("UPDATE jadwal_pelajaran jp
            JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
            SET jp.status = ?
            WHERE jp.id = ? AND mp.guru_id = ?");
        $stmt->bind_param("sii", $status, $jadwal_id, $guru_id);
        
        if ($stmt->execute()) {
            $status_text = [
                'terjadwal' => 'Terjadwal',
                'berlangsung' => 'Berlangsung',
                'selesai' => 'Selesai',
                'dibatalkan' => 'Dibatalkan'
            ];
            $message = 'success:Status jadwal berhasil diubah menjadi ' . ($status_text[$status] ?? $status) . '!';
        } else {
            $message = 'error:Gagal mengubah status jadwal!';
        }
        $stmt->close();
    }
}

// Get jadwal pelajaran hari ini untuk guru ini
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, k.nama_kelas
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN kelas k ON jp.kelas_id = k.id
    WHERE mp.guru_id = ? AND jp.tanggal = ?
    ORDER BY jp.jam_mulai ASC");
$stmt->bind_param("is", $guru_id, $today);
$stmt->execute();
$jadwal_hari_ini = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get jadwal minggu ini
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

$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "Swal.fire({icon: 'success', title: 'Berhasil!', html: '" . addslashes($msg[1]) . "', timer: 3000});";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-calendar-week"></i> Jadwal Pelajaran</h2>
    <p>
        <?php if ($spesialisasi): ?>
            Jadwal mengajar untuk <strong><?php echo htmlspecialchars($spesialisasi); ?></strong>. 
        <?php endif; ?>
        Lihat dan kelola jadwal pelajaran Anda. Ubah status dari terjadwal menjadi berlangsung saat memulai pelajaran.
    </p>
</div>

<!-- Jadwal Hari Ini -->
<div class="dashboard-card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-calendar-day"></i> Jadwal Pelajaran Hari Ini (<?php echo date('d/m/Y'); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($jadwal_hari_ini)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h5>Tidak Ada Jadwal</h5>
                <p>Belum ada jadwal pelajaran untuk hari ini. Jadwal akan muncul setelah diatur oleh tim akademik.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card border-start border-3 border-<?php 
                            echo $jadwal['status'] == 'berlangsung' ? 'success' : 
                                ($jadwal['status'] == 'selesai' ? 'info' : 
                                ($jadwal['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                        ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></h6>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-people"></i> <strong>Kelas:</strong> <?php echo htmlspecialchars($jadwal['nama_kelas']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> 
                                            <?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - 
                                            <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $jadwal['status'] == 'berlangsung' ? 'success' : 
                                            ($jadwal['status'] == 'selesai' ? 'info' : 
                                            ($jadwal['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                                    ?>">
                                        <?php 
                                        $status_text = [
                                            'terjadwal' => 'Terjadwal',
                                            'berlangsung' => 'Berlangsung',
                                            'selesai' => 'Selesai',
                                            'dibatalkan' => 'Dibatalkan'
                                        ];
                                        echo $status_text[$jadwal['status']] ?? ucfirst($jadwal['status']);
                                        ?>
                                    </span>
                                </div>
                                <?php if ($jadwal['ruangan']): ?>
                                    <small class="text-muted d-block mb-2">
                                        <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($jadwal['ruangan']); ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($jadwal['keterangan']): ?>
                                    <p class="mb-2 small"><?php echo htmlspecialchars($jadwal['keterangan']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($jadwal['status'] == 'terjadwal'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Mulai pelajaran ini sekarang? Status akan berubah menjadi berlangsung.');">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="jadwal_id" value="<?php echo $jadwal['id']; ?>">
                                        <input type="hidden" name="status" value="berlangsung">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-play-circle"></i> Mulai Pelajaran
                                        </button>
                                    </form>
                                <?php elseif ($jadwal['status'] == 'berlangsung'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Selesaikan pelajaran ini? Status akan berubah menjadi selesai.');">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="jadwal_id" value="<?php echo $jadwal['id']; ?>">
                                        <input type="hidden" name="status" value="selesai">
                                        <button type="submit" class="btn btn-sm btn-info">
                                            <i class="bi bi-check-circle"></i> Selesaikan
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistik Per Mata Pelajaran -->
<?php if (!empty($stats_per_pelajaran)): ?>
<div class="row mb-4">
    <?php foreach ($stats_per_pelajaran as $pelajaran => $stats): ?>
        <div class="col-md-6 col-lg-4 mb-3">
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
<?php endif; ?>

<!-- Jadwal Minggu Ini -->
<div class="dashboard-card">
    <div class="card-header">
        <h5><i class="bi bi-calendar-range"></i> Jadwal Minggu Ini</h5>
    </div>
    <div class="card-body">
        <?php if (empty($jadwal_minggu_ini)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h5>Tidak Ada Jadwal</h5>
                <p>Belum ada jadwal pelajaran untuk minggu ini.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Ruangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jadwal_minggu_ini as $jadwal): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($jadwal['tanggal'])); ?></td>
                                <td>
                                    <strong><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?></strong> - 
                                    <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></td>
                                <td><?php echo htmlspecialchars($jadwal['ruangan'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $jadwal['status'] == 'berlangsung' ? 'success' : 
                                            ($jadwal['status'] == 'selesai' ? 'info' : 
                                            ($jadwal['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                                    ?>">
                                        <?php 
                                        $status_text = [
                                            'terjadwal' => 'Terjadwal',
                                            'berlangsung' => 'Berlangsung',
                                            'selesai' => 'Selesai',
                                            'dibatalkan' => 'Dibatalkan'
                                        ];
                                        echo $status_text[$jadwal['status']] ?? ucfirst($jadwal['status']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($jadwal['status'] == 'terjadwal' && $jadwal['tanggal'] == $today): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Mulai pelajaran ini sekarang?');">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="jadwal_id" value="<?php echo $jadwal['id']; ?>">
                                            <input type="hidden" name="status" value="berlangsung">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-play-circle"></i> Mulai
                                            </button>
                                        </form>
                                    <?php elseif ($jadwal['status'] == 'berlangsung' && $jadwal['tanggal'] == $today): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Selesaikan pelajaran ini?');">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="jadwal_id" value="<?php echo $jadwal['id']; ?>">
                                            <input type="hidden" name="status" value="selesai">
                                            <button type="submit" class="btn btn-sm btn-info">
                                                <i class="bi bi-check-circle"></i> Selesai
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
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

