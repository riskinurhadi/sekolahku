<?php
$page_title = 'Tambah Latihan';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

$materi_id = intval($_GET['materi_id'] ?? 0);
if (!$materi_id) {
    header("Location: materi.php");
    exit;
}

// Verify materi belongs to this teacher
$stmt = $conn->prepare("SELECT m.*, mp.nama_pelajaran FROM materi_pelajaran m 
    JOIN mata_pelajaran mp ON m.mata_pelajaran_id = mp.id 
    WHERE m.id = ? AND m.guru_id = ?");
$stmt->bind_param("ii", $materi_id, $guru_id);
$stmt->execute();
$materi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$materi) {
    header("Location: materi.php");
    exit;
}

// Get soal for this teacher (for jenis soal)
$soal_list = $conn->query("SELECT id, judul, jenis FROM soal WHERE guru_id = $guru_id AND status = 'aktif' ORDER BY judul")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    if (empty(trim($_POST['judul']))) {
        $errors[] = 'Judul latihan harus diisi';
    }
    if (empty($_POST['jenis'])) {
        $errors[] = 'Jenis latihan harus dipilih';
    }
    
    if ($_POST['jenis'] == 'soal') {
        if (empty($_POST['soal_ids']) || !is_array($_POST['soal_ids']) || count($_POST['soal_ids']) == 0) {
            $errors[] = 'Minimal harus memilih 1 soal';
        }
    }
    
    if (empty($errors)) {
        $judul = trim($_POST['judul']);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $jenis = $_POST['jenis'];
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $poin = intval($_POST['poin'] ?? 100);
        $random_soal = isset($_POST['random_soal']) ? 1 : 0;
        $jumlah_soal = ($jenis == 'soal' && $random_soal) ? intval($_POST['jumlah_soal'] ?? 0) : null;
        $waktu_pengerjaan = ($jenis == 'soal') ? intval($_POST['waktu_pengerjaan'] ?? 0) : null;
        $status = $_POST['status'] ?? 'draft';
        
        $stmt = $conn->prepare("INSERT INTO latihan (materi_id, judul, deskripsi, jenis, deadline, poin, random_soal, jumlah_soal, waktu_pengerjaan, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssiiiii", $materi_id, $judul, $deskripsi, $jenis, $deadline, $poin, $random_soal, $jumlah_soal, $waktu_pengerjaan, $status);
        
        if ($stmt->execute()) {
            $latihan_id = $stmt->insert_id;
            $stmt->close();
            
            // Insert soal jika jenis = soal
            if ($jenis == 'soal' && !empty($_POST['soal_ids'])) {
                $urutan = 1;
                foreach ($_POST['soal_ids'] as $soal_id) {
                    $soal_id = intval($soal_id);
                    $stmt = $conn->prepare("INSERT INTO latihan_soal (latihan_id, soal_id, urutan) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $latihan_id, $soal_id, $urutan);
                    $stmt->execute();
                    $stmt->close();
                    $urutan++;
                }
            }
            
            $message = 'success:Latihan berhasil ditambahkan.';
            header("Location: materi.php?msg=" . urlencode($message));
            exit;
        } else {
            $message = 'error:Gagal menyimpan latihan.';
        }
    } else {
        $message = 'error:' . implode(', ', $errors);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Tambah Latihan</h2>
        <p class="text-muted mb-0">Materi: <?php echo htmlspecialchars($materi['judul']); ?></p>
    </div>
    <a href="materi.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<?php if ($message): 
    $msg_type = strpos($message, 'success:') === 0 ? 'success' : 'error';
    $msg_text = str_replace(['success:', 'error:'], '', $message);
?>
    <div class="alert alert-<?php echo $msg_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg_text); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" id="latihanForm">
            <div class="mb-3">
                <label class="form-label">Judul Latihan <span class="text-danger">*</span></label>
                <input type="text" name="judul" class="form-control" value="<?php echo htmlspecialchars($_POST['judul'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['deskripsi'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jenis Latihan <span class="text-danger">*</span></label>
                    <select name="jenis" class="form-select" id="jenisLatihan" required>
                        <option value="">Pilih Jenis</option>
                        <option value="tugas_file" <?php echo (isset($_POST['jenis']) && $_POST['jenis'] == 'tugas_file') ? 'selected' : ''; ?>>Tugas Submit File</option>
                        <option value="soal" <?php echo (isset($_POST['jenis']) && $_POST['jenis'] == 'soal') ? 'selected' : ''; ?>>Soal</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="aktif" <?php echo (isset($_POST['status']) && $_POST['status'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Deadline</label>
                    <input type="datetime-local" name="deadline" class="form-control" value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Poin</label>
                    <input type="number" name="poin" class="form-control" value="<?php echo htmlspecialchars($_POST['poin'] ?? '100'); ?>" min="1">
                </div>
            </div>
            
            <!-- Options for jenis soal -->
            <div id="soalOptions" style="display: none;">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="random_soal" id="randomSoal" value="1" <?php echo (isset($_POST['random_soal'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="randomSoal">
                            Acak Soal (Random)
                        </label>
                    </div>
                </div>
                
                <div id="jumlahSoalDiv" style="display: none;" class="mb-3">
                    <label class="form-label">Jumlah Soal (Jika Random)</label>
                    <input type="number" name="jumlah_soal" class="form-control" value="<?php echo htmlspecialchars($_POST['jumlah_soal'] ?? ''); ?>" min="1">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Waktu Pengerjaan (Menit)</label>
                    <input type="number" name="waktu_pengerjaan" class="form-control" value="<?php echo htmlspecialchars($_POST['waktu_pengerjaan'] ?? '60'); ?>" min="1">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Pilih Soal <span class="text-danger">*</span></label>
                    <?php if (empty($soal_list)): ?>
                        <div class="alert alert-warning">
                            Belum ada soal aktif. <a href="tambah_soal.php">Tambah soal terlebih dahulu</a>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 10px;">
                            <?php foreach ($soal_list as $soal): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="soal_ids[]" value="<?php echo $soal['id']; ?>" id="soal_<?php echo $soal['id']; ?>">
                                    <label class="form-check-label" for="soal_<?php echo $soal['id']; ?>">
                                        <?php echo htmlspecialchars($soal['judul']); ?> 
                                        <small class="text-muted">(<?php echo $soal['jenis']; ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="materi.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan Latihan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('jenisLatihan').addEventListener('change', function() {
    const soalOptions = document.getElementById('soalOptions');
    if (this.value === 'soal') {
        soalOptions.style.display = 'block';
    } else {
        soalOptions.style.display = 'none';
    }
});

document.getElementById('randomSoal').addEventListener('change', function() {
    const jumlahSoalDiv = document.getElementById('jumlahSoalDiv');
    if (this.checked) {
        jumlahSoalDiv.style.display = 'block';
    } else {
        jumlahSoalDiv.style.display = 'none';
    }
});

// Trigger on load
if (document.getElementById('jenisLatihan').value === 'soal') {
    document.getElementById('soalOptions').style.display = 'block';
}
if (document.getElementById('randomSoal').checked) {
    document.getElementById('jumlahSoalDiv').style.display = 'block';
}
</script>

<?php require_once '../../includes/footer.php'; ?>

