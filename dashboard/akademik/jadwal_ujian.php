<?php
$page_title = 'Kelola Jadwal Ujian';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$akademik_id = $_SESSION['user_id'];
$message = '';

// Get tipe ujian from URL parameter
$tipe_ujian = $_GET['tipe'] ?? 'uts';
if (!in_array($tipe_ujian, ['uts', 'uas'])) {
    $tipe_ujian = 'uts';
}

$tipe_label = strtoupper($tipe_ujian);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $soal_id = intval($_POST['soal_id']);
            $kelas_id = intval($_POST['kelas_id']);
            $tanggal_ujian = $_POST['tanggal_ujian'];
            $jam_mulai = $_POST['jam_mulai'];
            $jam_selesai = $_POST['jam_selesai'];
            $ruangan = trim($_POST['ruangan'] ?? '');
            $pengawas_id = !empty($_POST['pengawas_id']) ? intval($_POST['pengawas_id']) : null;
            $status = $_POST['status'] ?? 'terjadwal';
            
            // Check if jadwal_ujian table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'jadwal_ujian'");
            if ($check_table && $check_table->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO jadwal_ujian (soal_id, kelas_id, tanggal_ujian, jam_mulai, jam_selesai, ruangan, pengawas_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissssisi", $soal_id, $kelas_id, $tanggal_ujian, $jam_mulai, $jam_selesai, $ruangan, $pengawas_id, $status, $akademik_id);
                
                if ($stmt->execute()) {
                    // Update soal status to aktif
                    $update_soal = $conn->prepare("UPDATE soal SET status = 'aktif' WHERE id = ?");
                    $update_soal->bind_param("i", $soal_id);
                    $update_soal->execute();
                    $update_soal->close();
                    
                    $message = 'success:Jadwal ujian berhasil ditambahkan!';
                } else {
                    $message = 'error:Gagal menambahkan jadwal ujian!';
                }
                $stmt->close();
            } else {
                $message = 'error:Tabel jadwal_ujian belum ada. Silakan jalankan migration database terlebih dahulu.';
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);
            
            $check_table = $conn->query("SHOW TABLES LIKE 'jadwal_ujian'");
            if ($check_table && $check_table->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM jadwal_ujian WHERE id = ? AND created_by = ?");
                $stmt->bind_param("ii", $id, $akademik_id);
                
                if ($stmt->execute()) {
                    $message = 'success:Jadwal ujian berhasil dihapus!';
                } else {
                    $message = 'error:Gagal menghapus jadwal ujian!';
                }
                $stmt->close();
            }
        }
    }
}

// Check for success/error messages
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $msg = isset($_GET['msg']) ? $_GET['msg'] : 'Operasi berhasil!';
    $message = 'success:' . $msg;
}
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $msg = isset($_GET['msg']) ? $_GET['msg'] : 'Terjadi kesalahan!';
    $message = 'error:' . $msg;
}

// Get all classes for dropdown
$stmt = $conn->prepare("SELECT * FROM kelas WHERE sekolah_id = ? ORDER BY tingkat ASC, nama_kelas ASC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$kelas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all teachers for pengawas dropdown
$stmt = $conn->prepare("SELECT id, nama_lengkap FROM users WHERE sekolah_id = ? AND role = 'guru' ORDER BY nama_lengkap ASC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$guru_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if tipe_ujian column exists
$check_column = $conn->query("SHOW COLUMNS FROM soal LIKE 'tipe_ujian'");
if ($check_column && $check_column->num_rows > 0) {
    // Get soal UTS/UAS yang sudah dibuat oleh guru (status draft)
    $stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran, u.nama_lengkap as nama_guru
        FROM soal s
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
        JOIN users u ON s.guru_id = u.id
        WHERE mp.sekolah_id = ? AND s.tipe_ujian = ? AND s.status = 'draft'
        ORDER BY s.created_at DESC");
    $stmt->bind_param("is", $sekolah_id, $tipe_ujian);
} else {
    // Fallback: get all soal (tipe_ujian column doesn't exist yet)
    $stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran, u.nama_lengkap as nama_guru
        FROM soal s
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
        JOIN users u ON s.guru_id = u.id
        WHERE mp.sekolah_id = ? AND s.status = 'draft'
        ORDER BY s.created_at DESC");
    $stmt->bind_param("i", $sekolah_id);
}
$stmt->execute();
$soal_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get jadwal ujian yang sudah dibuat
$check_table = $conn->query("SHOW TABLES LIKE 'jadwal_ujian'");
$jadwal_list = [];
if ($check_table && $check_table->num_rows > 0) {
    if ($check_column && $check_column->num_rows > 0) {
        $stmt = $conn->prepare("SELECT ju.*, s.judul as judul_soal, s.tipe_ujian, mp.nama_pelajaran, k.nama_kelas, u.nama_lengkap as nama_pengawas
            FROM jadwal_ujian ju
            JOIN soal s ON ju.soal_id = s.id
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
            JOIN kelas k ON ju.kelas_id = k.id
            LEFT JOIN users u ON ju.pengawas_id = u.id
            WHERE s.tipe_ujian = ?
            ORDER BY ju.tanggal_ujian DESC, ju.jam_mulai ASC");
        $stmt->bind_param("s", $tipe_ujian);
    } else {
        $stmt = $conn->prepare("SELECT ju.*, s.judul as judul_soal, mp.nama_pelajaran, k.nama_kelas, u.nama_lengkap as nama_pengawas
            FROM jadwal_ujian ju
            JOIN soal s ON ju.soal_id = s.id
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
            JOIN kelas k ON ju.kelas_id = k.id
            LEFT JOIN users u ON ju.pengawas_id = u.id
            ORDER BY ju.tanggal_ujian DESC, ju.jam_mulai ASC");
    }
    $stmt->execute();
    $jadwal_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>

<?php if ($message): ?>
    <script>
        $(document).ready(function() {
            <?php 
            $msg = explode(':', $message);
            if ($msg[0] == 'success') {
                echo "Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: '" . addslashes($msg[1]) . "',
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });";
            } else {
                echo "Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '" . addslashes($msg[1]) . "',
                    confirmButtonText: 'OK'
                });";
            }
            ?>
        });
    </script>
<?php endif; ?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2>Kelola Jadwal <?php echo $tipe_label; ?></h2>
            <p>Susun jadwal <?php echo $tipe_label; ?> untuk kelas-kelas</p>
        </div>
    </div>
</div>

<!-- Tabs untuk UTS/UAS -->
<div class="row mb-3">
    <div class="col-12">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $tipe_ujian == 'uts' ? 'active' : ''; ?>" href="jadwal_ujian.php?tipe=uts">
                    <i class="bi bi-file-earmark-medical"></i> UTS
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tipe_ujian == 'uas' ? 'active' : ''; ?>" href="jadwal_ujian.php?tipe=uas">
                    <i class="bi bi-file-earmark-medical-fill"></i> UAS
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Form Tambah Jadwal -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Tambah Jadwal <?php echo $tipe_label; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addJadwalForm">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Soal <?php echo $tipe_label; ?> <span class="text-danger">*</span></label>
                            <select class="form-select" name="soal_id" required>
                                <option value="">Pilih Soal</option>
                                <?php foreach ($soal_list as $soal): ?>
                                    <option value="<?php echo $soal['id']; ?>">
                                        <?php echo htmlspecialchars($soal['judul']); ?> - <?php echo htmlspecialchars($soal['nama_pelajaran']); ?> (<?php echo htmlspecialchars($soal['nama_guru']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($soal_list)): ?>
                                <small class="text-muted">Belum ada soal <?php echo $tipe_label; ?> yang dibuat oleh guru</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas_id" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($kelas as $k): ?>
                                    <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Ujian <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal_ujian" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_mulai" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_selesai" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ruangan</label>
                            <input type="text" class="form-control" name="ruangan" placeholder="Contoh: A101, Lab Komputer">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pengawas</label>
                            <select class="form-select" name="pengawas_id">
                                <option value="">Pilih Pengawas (Opsional)</option>
                                <?php foreach ($guru_list as $guru): ?>
                                    <option value="<?php echo $guru['id']; ?>"><?php echo htmlspecialchars($guru['nama_lengkap']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <input type="hidden" name="status" value="terjadwal">
                            <button type="submit" class="btn btn-primary" <?php echo empty($soal_list) ? 'disabled' : ''; ?>>
                                <i class="bi bi-save"></i> Simpan Jadwal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Jadwal -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Jadwal <?php echo $tipe_label; ?></h5>
            </div>
            <div class="card-body">
                <?php if (count($jadwal_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="jadwalTable">
                            <thead>
                                <tr>
                                    <th>Soal</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Kelas</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Ruangan</th>
                                    <th>Pengawas</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($jadwal['judul_soal']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($jadwal['tanggal_ujian'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['ruangan'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['nama_pengawas'] ?: '-'); ?></td>
                                        <td>
                                            <?php 
                                            $status_badges = [
                                                'draft' => 'bg-secondary',
                                                'terjadwal' => 'bg-info',
                                                'berlangsung' => 'bg-warning',
                                                'selesai' => 'bg-success'
                                            ];
                                            $badge_class = $status_badges[$jadwal['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($jadwal['status']); ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteJadwal(<?php echo $jadwal['id']; ?>, '<?php echo htmlspecialchars($jadwal['judul_soal'], ENT_QUOTES); ?>')">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <i class="bi bi-calendar-event" style="font-size: 3rem; color: #cbd5e1; opacity: 0.6;"></i>
                        <h5 class="mt-3 mb-2" style="font-size: 1rem; font-weight: 600; color: #1e293b;">Belum ada jadwal <?php echo $tipe_label; ?></h5>
                        <p class="text-muted mb-3" style="font-size: 0.875rem; color: #64748b;">Mulai dengan menambahkan jadwal ujian baru</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDeleteJadwal(id, judul) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        html: 'Jadwal ujian <strong>"' + judul + '"</strong> akan dihapus!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="bi bi-trash"></i> Ya, hapus!',
        cancelButtonText: '<i class="bi bi-x-circle"></i> Batal',
        reverseButtons: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: () => {
            return new Promise((resolve) => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            });
        }
    });
}

$(document).ready(function() {
    <?php if (count($jadwal_list) > 0): ?>
    initDataTable('#jadwalTable', {
        order: [[3, 'desc']],
        columnDefs: [
            { orderable: false, targets: [8] }
        ]
    });
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>

