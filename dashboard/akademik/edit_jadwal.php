<?php
$page_title = 'Edit Jadwal Pelajaran';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header('Location: jadwal.php');
    exit;
}

// Get jadwal data
$stmt = $conn->prepare("SELECT * FROM jadwal_pelajaran WHERE id = ? AND sekolah_id = ?");
$stmt->bind_param("ii", $id, $sekolah_id);
$stmt->execute();
$jadwal_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$jadwal_data) {
    header('Location: jadwal.php');
    exit;
}

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
    
    $stmt = $conn->prepare("UPDATE jadwal_pelajaran SET mata_pelajaran_id = ?, kelas_id = ?, tanggal = ?, jam_mulai = ?, jam_selesai = ?, ruangan = ?, status = ?, keterangan = ? WHERE id = ? AND sekolah_id = ?");
    $stmt->bind_param("iissssssii", $mata_pelajaran_id, $kelas_id, $tanggal, $jam_mulai, $jam_selesai, $ruangan, $status, $keterangan, $id, $sekolah_id);
    
    if ($stmt->execute()) {
        $message = 'success:Jadwal pelajaran berhasil diupdate!';
        header('Location: jadwal.php?success=1');
        exit;
    } else {
        $message = 'error:Gagal mengupdate jadwal pelajaran!';
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
    <h2><i class="bi bi-pencil"></i> Edit Jadwal Pelajaran</h2>
    <p>Ubah data jadwal pelajaran di bawah ini</p>
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
                                <option value="<?php echo $k['id']; ?>" <?php echo $jadwal_data['kelas_id'] == $k['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="mata_pelajaran_id" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select class="form-select" id="mata_pelajaran_id" name="mata_pelajaran_id" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mata_pelajaran as $mp): ?>
                                <option value="<?php echo $mp['id']; ?>" <?php echo $jadwal_data['mata_pelajaran_id'] == $mp['id'] ? 'selected' : ''; ?>>
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
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($jadwal_data['tanggal']); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jam_mulai" class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="jam_mulai" name="jam_mulai" value="<?php echo htmlspecialchars($jadwal_data['jam_mulai']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="jam_selesai" class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="jam_selesai" name="jam_selesai" value="<?php echo htmlspecialchars($jadwal_data['jam_selesai']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ruangan" class="form-label">Ruangan</label>
                        <input type="text" class="form-control" id="ruangan" name="ruangan" value="<?php echo htmlspecialchars($jadwal_data['ruangan'] ?? ''); ?>" placeholder="Contoh: A-101">
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="terjadwal" <?php echo $jadwal_data['status'] == 'terjadwal' ? 'selected' : ''; ?>>Terjadwal</option>
                            <option value="berlangsung" <?php echo $jadwal_data['status'] == 'berlangsung' ? 'selected' : ''; ?>>Berlangsung</option>
                            <option value="selesai" <?php echo $jadwal_data['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                            <option value="dibatalkan" <?php echo $jadwal_data['status'] == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="2"><?php echo htmlspecialchars($jadwal_data['keterangan'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="jadwal.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

