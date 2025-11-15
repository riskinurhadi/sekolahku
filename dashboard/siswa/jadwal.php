<?php
$page_title = 'Jadwal Pelajaran';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get siswa info including kelas_id
$stmt = $conn->prepare("SELECT kelas_id FROM users WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa_info = $stmt->get_result()->fetch_assoc();
$kelas_id = $siswa_info['kelas_id'] ?? null;
$stmt->close();

// Always show this week's schedule
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

// Get jadwal for selected week based on siswa's kelas
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
    $jadwal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $jadwal = [];
}

// Group jadwal by date
$jadwal_by_date = [];
foreach ($jadwal as $j) {
    $date = $j['tanggal'];
    if (!isset($jadwal_by_date[$date])) {
        $jadwal_by_date[$date] = [];
    }
    $jadwal_by_date[$date][] = $j;
}

// Get kelas name
$kelas_name = '';
if ($kelas_id) {
    $stmt = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $kelas_info = $stmt->get_result()->fetch_assoc();
    $kelas_name = $kelas_info['nama_kelas'] ?? '';
    $stmt->close();
}

$conn->close();

// Day names in Indonesian
$day_names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
?>

<div class="page-header">
    <h2><i class="bi bi-calendar-week"></i> Jadwal Pelajaran</h2>
    <p>Jadwal pelajaran untuk kelas <strong><?php echo htmlspecialchars($kelas_name ?: 'Belum ditentukan'); ?></strong></p>
</div>

<?php if (!$kelas_id): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Anda belum terdaftar di kelas manapun. Silakan hubungi administrator untuk mengatur kelas Anda.
    </div>
<?php else: ?>

<!-- Weekly Schedule Table -->
<div class="row">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-week"></i> Jadwal Pelajaran Minggu Ini
                    <small class="text-muted ms-2">
                        (<?php echo date('d/m/Y', strtotime($week_start)); ?> - <?php echo date('d/m/Y', strtotime($week_end)); ?>)
                    </small>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($jadwal)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">Tidak ada jadwal untuk minggu ini</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="jadwalTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hari</th>
                                    <th>Jam</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Kode</th>
                                    <th>Guru</th>
                                    <th>Ruangan</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $status_text = [
                                    'terjadwal' => 'Terjadwal',
                                    'berlangsung' => 'Berlangsung',
                                    'selesai' => 'Selesai',
                                    'dibatalkan' => 'Dibatalkan'
                                ];
                                
                                foreach ($jadwal as $j): 
                                    $current_date = $j['tanggal'];
                                    $day_name = $day_names[date('w', strtotime($current_date))];
                                    $is_today = $current_date == date('Y-m-d');
                                ?>
                                    <tr class="<?php echo $is_today ? 'table-primary' : ''; ?>">
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($current_date)); ?>
                                            <?php if ($is_today): ?>
                                                <span class="badge bg-primary ms-1">Hari Ini</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo $day_name; ?></strong></td>
                                        <td>
                                            <strong><?php echo date('H:i', strtotime($j['jam_mulai'])); ?></strong> - 
                                            <?php echo date('H:i', strtotime($j['jam_selesai'])); ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($j['nama_pelajaran']); ?></strong></td>
                                        <td>
                                            <?php if ($j['kode_pelajaran']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($j['kode_pelajaran']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($j['nama_guru']); ?></td>
                                        <td>
                                            <?php if ($j['ruangan']): ?>
                                                <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($j['ruangan']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $j['status'] == 'berlangsung' ? 'success' : 
                                                    ($j['status'] == 'selesai' ? 'info' : 
                                                    ($j['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                                            ?>">
                                                <?php echo $status_text[$j['status']] ?? ucfirst($j['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($j['keterangan']): ?>
                                                <small><?php echo htmlspecialchars($j['keterangan']); ?></small>
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
    </div>
</div>

<?php endif; ?>

<script>
$(document).ready(function() {
    $('#jadwalTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[0, 'asc'], [2, 'asc']], // Sort by date, then time
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        columnDefs: [
            { orderable: true, targets: [0, 1, 2, 3, 4, 5, 6, 7] },
            { orderable: false, targets: [8] } // Keterangan tidak bisa di-sort
        ]
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

