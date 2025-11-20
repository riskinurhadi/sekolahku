<?php
$page_title = 'Tambah Jadwal Pelajaran';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mata_pelajaran_id = $_POST['mata_pelajaran_id'];
    $kelas_id = $_POST['kelas_id'];
    $tanggal = $_POST['tanggal'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $ruangan = $_POST['ruangan'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $status = $_POST['status'] ?? 'terjadwal';
    
    $stmt = $conn->prepare("INSERT INTO jadwal_pelajaran (mata_pelajaran_id, sekolah_id, kelas_id, tanggal, jam_mulai, jam_selesai, ruangan, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiissssss", $mata_pelajaran_id, $sekolah_id, $kelas_id, $tanggal, $jam_mulai, $jam_selesai, $ruangan, $status, $keterangan);
    
    if ($stmt->execute()) {
        $message = 'success:Jadwal pelajaran berhasil ditambahkan!';
        header('Location: jadwal.php?success=1');
        exit;
    } else {
        $message = 'error:Gagal menambahkan jadwal pelajaran!';
    }
    $stmt->close();
}

// Get all classes
$stmt = $conn->prepare("SELECT * FROM kelas WHERE sekolah_id = ? ORDER BY tingkat ASC, nama_kelas ASC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$kelas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all mata pelajaran
$stmt = $conn->prepare("SELECT mp.*, u.nama_lengkap as nama_guru, u.spesialisasi
    FROM mata_pelajaran mp 
    JOIN users u ON mp.guru_id = u.id 
    WHERE mp.sekolah_id = ? 
    ORDER BY mp.nama_pelajaran ASC");
$stmt->bind_param("i", $sekolah_id);
$stmt->execute();
$mata_pelajaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo strpos($message, 'success') === 0 ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php 
        $msg = explode(':', $message);
        echo htmlspecialchars($msg[1]); 
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-plus-circle"></i> Tambah Jadwal Pelajaran</h2>
    <p>Isi form di bawah ini untuk menambahkan jadwal pelajaran baru</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="kelas_id" class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" id="kelas_id" name="kelas_id" required>
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($kelas as $k): ?>
                                <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="mata_pelajaran_id" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select class="form-select" id="mata_pelajaran_id" name="mata_pelajaran_id" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php if (empty($mata_pelajaran)): ?>
                                <option value="" disabled>Tidak ada mata pelajaran tersedia. Silakan tambahkan mata pelajaran terlebih dahulu.</option>
                            <?php else: ?>
                                <?php foreach ($mata_pelajaran as $mp): ?>
                                    <option value="<?php echo $mp['id']; ?>">
                                        <?php echo htmlspecialchars($mp['nama_pelajaran']); ?> 
                                        <?php if ($mp['kode_pelajaran']): ?>
                                            (<?php echo htmlspecialchars($mp['kode_pelajaran']); ?>)
                                        <?php endif; ?>
                                        - <?php echo htmlspecialchars($mp['nama_guru']); ?>
                                        <?php if ($mp['spesialisasi']): ?>
                                            [<?php echo htmlspecialchars($mp['spesialisasi']); ?>]
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($mata_pelajaran)): ?>
                            <small class="text-danger">
                                <i class="bi bi-exclamation-triangle"></i> Tidak ada mata pelajaran yang tersedia. 
                                Pastikan guru sudah membuat mata pelajaran di menu "Mata Pelajaran".
                            </small>
                        <?php else: ?>
                            <small class="text-muted">Pilih mata pelajaran yang akan dijadwalkan</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jam_mulai" class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="jam_mulai" name="jam_mulai" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="jam_selesai" class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="jam_selesai" name="jam_selesai" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ruangan" class="form-label">Ruangan</label>
                        <input type="text" class="form-control" id="ruangan" name="ruangan" placeholder="Contoh: A-101">
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="terjadwal">Terjadwal</option>
                            <option value="berlangsung">Berlangsung</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="jadwal.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

