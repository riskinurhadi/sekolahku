<?php
$page_title = 'Kelola Jadwal Pelajaran';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
            $mata_pelajaran_id = $_POST['mata_pelajaran_id'];
            $kelas_id = $_POST['kelas_id'];
            $tanggal = $_POST['tanggal'];
            $jam_mulai = $_POST['jam_mulai'];
            $jam_selesai = $_POST['jam_selesai'];
            $ruangan = $_POST['ruangan'] ?? '';
            $keterangan = $_POST['keterangan'] ?? '';
            $status = $_POST['status'] ?? 'terjadwal';
            
            if ($_POST['action'] == 'add') {
                $stmt = $conn->prepare("INSERT INTO jadwal_pelajaran (mata_pelajaran_id, sekolah_id, kelas_id, tanggal, jam_mulai, jam_selesai, ruangan, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiissssss", $mata_pelajaran_id, $sekolah_id, $kelas_id, $tanggal, $jam_mulai, $jam_selesai, $ruangan, $status, $keterangan);
                
                if ($stmt->execute()) {
                    $message = 'success:Jadwal pelajaran berhasil ditambahkan!';
                } else {
                    $message = 'error:Gagal menambahkan jadwal pelajaran!';
                }
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE jadwal_pelajaran SET mata_pelajaran_id = ?, kelas_id = ?, tanggal = ?, jam_mulai = ?, jam_selesai = ?, ruangan = ?, status = ?, keterangan = ? WHERE id = ? AND sekolah_id = ?");
                $stmt->bind_param("iissssssii", $mata_pelajaran_id, $kelas_id, $tanggal, $jam_mulai, $jam_selesai, $ruangan, $status, $keterangan, $id, $sekolah_id);
                
                if ($stmt->execute()) {
                    $message = 'success:Jadwal pelajaran berhasil diupdate!';
                } else {
                    $message = 'error:Gagal mengupdate jadwal pelajaran!';
                }
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM jadwal_pelajaran WHERE id = ? AND sekolah_id = ?");
            $stmt->bind_param("ii", $id, $sekolah_id);
            
            if ($stmt->execute()) {
                $message = 'success:Jadwal pelajaran berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus jadwal pelajaran!';
            }
            $stmt->close();
        }
    }
}

// Get filter date (default: today)
$filter_date = $_GET['tanggal'] ?? date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));

// Get all classes for dropdown
$stmt = $conn->prepare("SELECT * FROM kelas WHERE sekolah_id = ? ORDER BY tingkat ASC, nama_kelas ASC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$kelas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all mata pelajaran for dropdown
$stmt = $conn->prepare("SELECT mp.*, u.nama_lengkap as nama_guru, u.spesialisasi
    FROM mata_pelajaran mp 
    JOIN users u ON mp.guru_id = u.id 
    WHERE mp.sekolah_id = ? 
    ORDER BY mp.nama_pelajaran ASC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$mata_pelajaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get jadwal for selected week
$stmt = $conn->prepare("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru, k.nama_kelas
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN users u ON mp.guru_id = u.id
    JOIN kelas k ON jp.kelas_id = k.id
    WHERE jp.sekolah_id = ? AND jp.tanggal BETWEEN ? AND ?
    ORDER BY jp.tanggal ASC, jp.jam_mulai ASC");
$stmt->bind_param("iss", $sekolah_id, $week_start, $week_end);
$stmt->execute();
$jadwal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group jadwal by date
$jadwal_by_date = [];
foreach ($jadwal as $j) {
    $date = $j['tanggal'];
    if (!isset($jadwal_by_date[$date])) {
        $jadwal_by_date[$date] = [];
    }
    $jadwal_by_date[$date][] = $j;
}

$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "Swal.fire({ icon: 'success', title: 'Berhasil', text: '" . addslashes($msg[1]) . "', timer: 2000, showConfirmButton: false });";
            echo "setTimeout(function(){ window.location.reload(); }, 2000);";
        } else {
            echo "Swal.fire({ icon: 'error', title: 'Error', text: '" . addslashes($msg[1]) . "' });";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-calendar-week"></i> Kelola Jadwal Pelajaran</h2>
    <p>Atur jadwal pelajaran untuk 1 minggu ke depan</p>
</div>

<!-- Filter & Actions -->
<div class="dashboard-card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4">
                <label class="form-label">Pilih Minggu</label>
                <input type="date" id="filterDate" class="form-control" value="<?php echo $filter_date; ?>" onchange="window.location.href='?tanggal=' + this.value">
            </div>
            <div class="col-md-8 text-end">
                <a href="tambah_jadwal.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Jadwal
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Jadwal Per Hari -->
<?php
$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
for ($i = 0; $i < 7; $i++):
    $current_date = date('Y-m-d', strtotime($week_start . " +$i days"));
    $day_name = $days[$i];
    $is_today = $current_date == date('Y-m-d');
    $day_jadwal = $jadwal_by_date[$current_date] ?? [];
?>
    <div class="dashboard-card mb-4">
        <div class="card-header <?php echo $is_today ? 'bg-primary text-white' : 'bg-light'; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-day"></i> <?php echo $day_name; ?>
                    <span class="ms-2" style="font-size: 0.9rem; font-weight: normal;"><?php echo date('d/m/Y', strtotime($current_date)); ?></span>
                    <?php if ($is_today): ?>
                        <span class="badge bg-light text-primary ms-2">Hari Ini</span>
                    <?php endif; ?>
                </h5>
                <span class="badge <?php echo $is_today ? 'bg-light text-primary' : 'bg-secondary'; ?>">
                    <?php echo count($day_jadwal); ?> Jadwal
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($day_jadwal)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-calendar-x" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3 mb-0">Tidak ada jadwal untuk hari ini</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Waktu</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Guru</th>
                                <th>Ruangan</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($day_jadwal as $j): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('H:i', strtotime($j['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($j['jam_selesai'])); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($j['nama_pelajaran']); ?></strong>
                                        <?php if ($j['kode_pelajaran']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($j['kode_pelajaran']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($j['nama_kelas']); ?></td>
                                    <td><?php echo htmlspecialchars($j['nama_guru']); ?></td>
                                    <td><?php echo $j['ruangan'] ? htmlspecialchars($j['ruangan']) : '-'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $j['status'] == 'berlangsung' ? 'success' : 
                                                ($j['status'] == 'selesai' ? 'info' : 
                                                ($j['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                                        ?>">
                                            <?php 
                                            $status_text = [
                                                'terjadwal' => 'Terjadwal',
                                                'berlangsung' => 'Berlangsung',
                                                'selesai' => 'Selesai',
                                                'dibatalkan' => 'Dibatalkan'
                                            ];
                                            echo $status_text[$j['status']] ?? ucfirst($j['status']);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_jadwal.php?id=<?php echo $j['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); confirmDelete('jadwal ini').then(result => { if(result) this.submit(); }); return false;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endfor; ?>


<?php require_once '../../includes/footer.php'; ?>

