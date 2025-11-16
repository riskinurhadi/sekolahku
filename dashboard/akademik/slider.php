<?php
$page_title = 'Kelola Slider';
require_once '../../config/session.php';
requireRole(['akademik', 'developer']);
require_once '../../includes/header.php';

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'] ?? null;
$user_id = $_SESSION['user_id'];
$message = '';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['gambar'])) {
    $upload_dir = '../../uploads/slider/';
    
    // Buat folder jika belum ada
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['gambar'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        $message = 'error:Format file tidak didukung. Hanya JPG, PNG, GIF, dan WEBP yang diperbolehkan.';
    } elseif ($file['size'] > $max_size) {
        $message = 'error:Ukuran file maksimal 5MB.';
    } else {
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'slider_' . time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $judul = $_POST['judul'] ?? null;
            $deskripsi = $_POST['deskripsi'] ?? null;
            $link = $_POST['link'] ?? null;
            $urutan = intval($_POST['urutan'] ?? 0);
            $status = $_POST['status'] ?? 'aktif';
            
            $stmt = $conn->prepare("INSERT INTO slider (gambar, judul, deskripsi, link, urutan, status, sekolah_id, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssissi", $file_name, $judul, $deskripsi, $link, $urutan, $status, $sekolah_id, $user_id);
            
            if ($stmt->execute()) {
                $message = 'success:Slider berhasil ditambahkan.';
            } else {
                unlink($file_path); // Hapus file jika gagal insert
                $message = 'error:Gagal menyimpan data slider.';
            }
            $stmt->close();
        } else {
            $message = 'error:Gagal mengunggah file.';
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get gambar name
    $stmt = $conn->prepare("SELECT gambar FROM slider WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result) {
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM slider WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Delete file
            $file_path = '../../uploads/slider/' . $result['gambar'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $message = 'success:Slider berhasil dihapus.';
        }
        $stmt->close();
    }
}

// Handle update status
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $stmt = $conn->prepare("UPDATE slider SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'success:Status slider berhasil diubah.';
    }
    $stmt->close();
}

// Get all sliders
$query = "SELECT s.*, u.nama_lengkap as created_by_name, sk.nama_sekolah 
    FROM slider s 
    LEFT JOIN users u ON s.created_by = u.id 
    LEFT JOIN sekolah sk ON s.sekolah_id = sk.id 
    WHERE 1=1";
    
if ($sekolah_id) {
    $query .= " AND (s.sekolah_id = ? OR s.sekolah_id IS NULL)";
}

$query .= " ORDER BY s.urutan ASC, s.created_at DESC";

$stmt = $conn->prepare($query);
if ($sekolah_id) {
    $stmt->bind_param("i", $sekolah_id);
}
$stmt->execute();
$sliders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<?php if ($message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php 
            $msg = explode(':', $message);
            if ($msg[0] == 'success') {
                echo "if (typeof Swal !== 'undefined') {";
                echo "    Swal.fire({icon: 'success', title: 'Berhasil!', text: '" . addslashes($msg[1]) . "', timer: 3000});";
                echo "} else {";
                echo "    alert('" . addslashes($msg[1]) . "');";
                echo "}";
            } else {
                echo "if (typeof Swal !== 'undefined') {";
                echo "    Swal.fire({icon: 'error', title: 'Error', text: '" . addslashes($msg[1]) . "', timer: 3000});";
                echo "} else {";
                echo "    alert('" . addslashes($msg[1]) . "');";
                echo "}";
            }
            ?>
        });
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Kelola Slider Dashboard</h2>
    <p>Upload dan kelola gambar slider untuk ditampilkan di dashboard siswa</p>
</div>

<!-- Form Upload -->
<div class="dashboard-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Tambah Slider Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="gambar" class="form-label">Gambar Slider <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="gambar" name="gambar" accept="image/*" required>
                    <small class="text-muted">Format: JPG, PNG, GIF, WEBP. Maksimal 5MB</small>
                    <div id="preview" class="mt-2"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="judul" class="form-label">Judul (Opsional)</label>
                    <input type="text" class="form-control" id="judul" name="judul" placeholder="Judul slider">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi (Opsional)</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="2" placeholder="Deskripsi slider"></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="link" class="form-label">Link (Opsional)</label>
                    <input type="url" class="form-control" id="link" name="link" placeholder="https://example.com">
                    <small class="text-muted">Link yang akan dibuka saat slider diklik</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="urutan" class="form-label">Urutan</label>
                    <input type="number" class="form-control" id="urutan" name="urutan" value="0" min="0">
                    <small class="text-muted">Angka kecil = tampil lebih dulu</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="aktif" selected>Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-upload"></i> Upload Slider
            </button>
        </form>
    </div>
</div>

<!-- List Sliders -->
<div class="dashboard-card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-images"></i> Daftar Slider</h5>
    </div>
    <div class="card-body">
        <?php if (empty($sliders)): ?>
            <div class="empty-state text-center py-5">
                <i class="bi bi-image text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="text-muted mt-3">Belum ada slider. Upload slider baru di atas.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($sliders as $slider): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="position-relative">
                                <img src="<?php echo getBasePath(); ?>uploads/slider/<?php echo htmlspecialchars($slider['gambar']); ?>" 
                                     class="card-img-top" style="height: 200px; object-fit: cover;" 
                                     alt="<?php echo htmlspecialchars($slider['judul'] ?? 'Slider'); ?>">
                                <span class="badge bg-<?php echo $slider['status'] == 'aktif' ? 'success' : 'secondary'; ?> position-absolute top-0 end-0 m-2">
                                    <?php echo $slider['status'] == 'aktif' ? 'Aktif' : 'Nonaktif'; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if ($slider['judul']): ?>
                                    <h6 class="card-title"><?php echo htmlspecialchars($slider['judul']); ?></h6>
                                <?php endif; ?>
                                <?php if ($slider['deskripsi']): ?>
                                    <p class="card-text small text-muted"><?php echo htmlspecialchars($slider['deskripsi']); ?></p>
                                <?php endif; ?>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-sort-numeric-down"></i> Urutan: <?php echo $slider['urutan']; ?><br>
                                    <?php if ($slider['nama_sekolah']): ?>
                                        <i class="bi bi-building"></i> <?php echo htmlspecialchars($slider['nama_sekolah']); ?><br>
                                    <?php else: ?>
                                        <i class="bi bi-globe"></i> Semua Sekolah<br>
                                    <?php endif; ?>
                                    <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($slider['created_at'])); ?>
                                </div>
                                <div class="btn-group w-100" role="group">
                                    <a href="?toggle_status=<?php echo $slider['id']; ?>" 
                                       class="btn btn-sm btn-<?php echo $slider['status'] == 'aktif' ? 'warning' : 'success'; ?>"
                                       onclick="return confirm('Yakin ingin mengubah status slider ini?')">
                                        <i class="bi bi-<?php echo $slider['status'] == 'aktif' ? 'eye-slash' : 'eye'; ?>"></i>
                                    </a>
                                    <a href="?delete=<?php echo $slider['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin ingin menghapus slider ini?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Preview image before upload
document.getElementById('gambar').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">';
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

