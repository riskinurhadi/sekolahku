<?php
$page_title = 'Edit Mata Pelajaran';
require_once '../../config/session.php';
requireRole(['akademik']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';

// Get ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    echo '<script>window.location.href = "mata_pelajaran.php";</script>';
    exit;
}

// Get mata pelajaran data
$stmt = $conn->prepare("SELECT * FROM mata_pelajaran WHERE id = ? AND sekolah_id = ?");
$stmt->bind_param("ii", $id, $sekolah_id);
$stmt->execute();
$mata_pelajaran = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$mata_pelajaran) {
    echo '<script>window.location.href = "mata_pelajaran.php";</script>';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_pelajaran = $_POST['nama_pelajaran'];
    $kode_pelajaran = $_POST['kode_pelajaran'] ?? '';
    $guru_id = $_POST['guru_id'] ?? null;
    
    if (empty($guru_id)) {
        $message = 'error:Guru wajib dipilih!';
    } else {
        $stmt = $conn->prepare("UPDATE mata_pelajaran SET nama_pelajaran = ?, kode_pelajaran = ?, guru_id = ? WHERE id = ? AND sekolah_id = ?");
        $stmt->bind_param("ssiii", $nama_pelajaran, $kode_pelajaran, $guru_id, $id, $sekolah_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Mata pelajaran berhasil diupdate!';
            $stmt->close();
            $conn->close();
            echo '<script>window.location.href = "mata_pelajaran.php?success=1";</script>';
            exit;
        } else {
            $message = 'error:Gagal mengupdate mata pelajaran!';
        }
        $stmt->close();
    }
}

// Get all teachers
$guru_list = $conn->query("SELECT id, nama_lengkap, spesialisasi FROM users WHERE role = 'guru' AND sekolah_id = $sekolah_id ORDER BY nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);

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
    <h2><i class="bi bi-pencil"></i> Edit Mata Pelajaran</h2>
    <p>Ubah data mata pelajaran di bawah ini</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="nama_pelajaran" class="form-label">Nama Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_pelajaran" name="nama_pelajaran" value="<?php echo htmlspecialchars($mata_pelajaran['nama_pelajaran']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="kode_pelajaran" class="form-label">Kode Pelajaran</label>
                        <input type="text" class="form-control" id="kode_pelajaran" name="kode_pelajaran" value="<?php echo htmlspecialchars($mata_pelajaran['kode_pelajaran'] ?? ''); ?>" placeholder="Contoh: MAT-001">
                    </div>
                    <div class="mb-3">
                        <label for="guru_id" class="form-label">Guru Pengajar <span class="text-danger">*</span></label>
                        <select class="form-select" id="guru_id" name="guru_id" required>
                            <option value="">Pilih Guru</option>
                            <?php foreach ($guru_list as $guru): ?>
                                <option value="<?php echo $guru['id']; ?>" <?php echo $mata_pelajaran['guru_id'] == $guru['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($guru['nama_lengkap']); ?>
                                    <?php if ($guru['spesialisasi']): ?>
                                        - <?php echo htmlspecialchars($guru['spesialisasi']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="mata_pelajaran.php" class="btn btn-secondary">
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

