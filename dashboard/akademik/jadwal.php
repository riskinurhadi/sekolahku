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
            $tanggal = $_POST['tanggal'];
            $jam_mulai = $_POST['jam_mulai'];
            $jam_selesai = $_POST['jam_selesai'];
            $ruangan = $_POST['ruangan'] ?? '';
            $keterangan = $_POST['keterangan'] ?? '';
            $status = $_POST['status'] ?? 'terjadwal';
            
            if ($_POST['action'] == 'add') {
                $stmt = $conn->prepare("INSERT INTO jadwal_pelajaran (mata_pelajaran_id, sekolah_id, tanggal, jam_mulai, jam_selesai, ruangan, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissssss", $mata_pelajaran_id, $sekolah_id, $tanggal, $jam_mulai, $jam_selesai, $ruangan, $status, $keterangan);
                
                if ($stmt->execute()) {
                    $message = 'success:Jadwal pelajaran berhasil ditambahkan!';
                } else {
                    $message = 'error:Gagal menambahkan jadwal pelajaran!';
                }
            } else {
                $id = $_POST['id'];
                $stmt = $conn->prepare("UPDATE jadwal_pelajaran SET mata_pelajaran_id = ?, tanggal = ?, jam_mulai = ?, jam_selesai = ?, ruangan = ?, status = ?, keterangan = ? WHERE id = ? AND sekolah_id = ?");
                $stmt->bind_param("issssssii", $mata_pelajaran_id, $tanggal, $jam_mulai, $jam_selesai, $ruangan, $status, $keterangan, $id, $sekolah_id);
                
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

// Get all mata pelajaran for dropdown
$mata_pelajaran = $conn->query("SELECT mp.*, u.nama_lengkap as nama_guru 
    FROM mata_pelajaran mp 
    JOIN users u ON mp.guru_id = u.id 
    WHERE mp.sekolah_id = $sekolah_id 
    ORDER BY mp.nama_pelajaran ASC")->fetch_all(MYSQLI_ASSOC);

// Get jadwal for selected week
$jadwal = $conn->query("SELECT jp.*, mp.nama_pelajaran, mp.kode_pelajaran, u.nama_lengkap as nama_guru
    FROM jadwal_pelajaran jp
    JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id
    JOIN users u ON mp.guru_id = u.id
    WHERE jp.sekolah_id = $sekolah_id AND jp.tanggal BETWEEN '$week_start' AND '$week_end'
    ORDER BY jp.tanggal ASC, jp.jam_mulai ASC")->fetch_all(MYSQLI_ASSOC);

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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJadwalModal">
                    <i class="bi bi-plus-circle"></i> Tambah Jadwal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Jadwal Per Hari -->
<div class="row">
    <?php
    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    for ($i = 0; $i < 7; $i++):
        $current_date = date('Y-m-d', strtotime($week_start . " +$i days"));
        $day_name = $days[$i];
        $is_today = $current_date == date('Y-m-d');
        $day_jadwal = $jadwal_by_date[$current_date] ?? [];
    ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="dashboard-card <?php echo $is_today ? 'border-primary' : ''; ?>">
                <div class="card-header <?php echo $is_today ? 'bg-primary text-white' : ''; ?>">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-day"></i> <?php echo $day_name; ?>
                        <?php if ($is_today): ?>
                            <span class="badge bg-light text-primary ms-2">Hari Ini</span>
                        <?php endif; ?>
                    </h5>
                    <small><?php echo date('d/m/Y', strtotime($current_date)); ?></small>
                </div>
                <div class="card-body" style="min-height: 300px; max-height: 500px; overflow-y: auto;">
                    <?php if (empty($day_jadwal)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">Tidak ada jadwal</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($day_jadwal as $j): ?>
                            <div class="card mb-2 border-start border-3 border-<?php 
                                echo $j['status'] == 'berlangsung' ? 'success' : 
                                    ($j['status'] == 'selesai' ? 'info' : 
                                    ($j['status'] == 'dibatalkan' ? 'warning' : 'secondary')); 
                            ?>">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?php echo date('H:i', strtotime($j['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($j['jam_selesai'])); ?></strong>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="editJadwal(<?php echo htmlspecialchars(json_encode($j)); ?>)">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus jadwal ini?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $j['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash"></i> Hapus
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($j['nama_pelajaran']); ?></h6>
                                    <small class="text-muted d-block mb-1">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($j['nama_guru']); ?>
                                    </small>
                                    <?php if ($j['ruangan']): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($j['ruangan']); ?>
                                        </small>
                                    <?php endif; ?>
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
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endfor; ?>
</div>

<!-- Modal Tambah/Edit Jadwal -->
<div class="modal fade" id="addJadwalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Jadwal Pelajaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="jadwalForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="mb-3">
                        <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select class="form-select" name="mata_pelajaran_id" id="mataPelajaranId" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mata_pelajaran as $mp): ?>
                                <option value="<?php echo $mp['id']; ?>" data-guru="<?php echo htmlspecialchars($mp['nama_guru']); ?>">
                                    <?php echo htmlspecialchars($mp['nama_pelajaran']); ?> - <?php echo htmlspecialchars($mp['nama_guru']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal" id="tanggal" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_mulai" id="jamMulai" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_selesai" id="jamSelesai" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ruangan</label>
                        <input type="text" class="form-control" name="ruangan" id="ruangan" placeholder="Contoh: A-101">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="terjadwal">Terjadwal</option>
                            <option value="berlangsung">Berlangsung</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editJadwal(jadwal) {
    document.getElementById('modalTitle').textContent = 'Edit Jadwal Pelajaran';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = jadwal.id;
    document.getElementById('mataPelajaranId').value = jadwal.mata_pelajaran_id;
    document.getElementById('tanggal').value = jadwal.tanggal;
    document.getElementById('jamMulai').value = jadwal.jam_mulai;
    document.getElementById('jamSelesai').value = jadwal.jam_selesai;
    document.getElementById('ruangan').value = jadwal.ruangan || '';
    document.getElementById('status').value = jadwal.status;
    document.getElementById('keterangan').value = jadwal.keterangan || '';
    
    var modal = new bootstrap.Modal(document.getElementById('addJadwalModal'));
    modal.show();
}

// Reset form when modal is closed
document.getElementById('addJadwalModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('jadwalForm').reset();
    document.getElementById('modalTitle').textContent = 'Tambah Jadwal Pelajaran';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
});
</script>

<?php require_once '../../includes/footer.php'; ?>

