<?php
$page_title = 'Kelola Materi';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $check_stmt = $conn->prepare("SELECT id, file_attachment FROM materi_pelajaran WHERE id = ? AND guru_id = ?");
    $check_stmt->bind_param("ii", $id, $guru_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $materi = $result->fetch_assoc();
        
        // Delete file if exists
        if ($materi['file_attachment'] && file_exists('../../uploads/materi/' . $materi['file_attachment'])) {
            unlink('../../uploads/materi/' . $materi['file_attachment']);
        }
        
        // Delete materi (cascade will delete latihan and related data)
        $delete_stmt = $conn->prepare("DELETE FROM materi_pelajaran WHERE id = ?");
        $delete_stmt->bind_param("i", $id);
        if ($delete_stmt->execute()) {
            $message = 'success:Materi berhasil dihapus.';
        } else {
            $message = 'error:Gagal menghapus materi.';
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
    header("Location: materi.php?msg=" . urlencode($message));
    exit;
}

// Get all materi
$stmt = $conn->prepare("SELECT m.*, mp.nama_pelajaran, 
    (SELECT COUNT(*) FROM latihan WHERE materi_id = m.id) as jumlah_latihan
    FROM materi_pelajaran m
    JOIN mata_pelajaran mp ON m.mata_pelajaran_id = mp.id
    WHERE m.guru_id = ?
    ORDER BY m.urutan ASC, m.created_at DESC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$materi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get mata pelajaran for filter
$mata_pelajaran_list = $conn->query("SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_pelajaran")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Kelola Materi</h2>
        <p class="text-muted mb-0">Upload dan kelola materi pembelajaran Anda</p>
    </div>
    <a href="tambah_materi.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Tambah Materi
    </a>
</div>

<?php if (isset($_GET['msg'])): 
    $msg = urldecode($_GET['msg']);
    $msg_type = strpos($msg, 'success:') === 0 ? 'success' : 'error';
    $msg_text = str_replace(['success:', 'error:'], '', $msg);
?>
    <div class="alert alert-<?php echo $msg_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($msg_text); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($materi_list)): ?>
            <div class="text-center py-5">
                <i class="bi bi-journal-text text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                <h5 class="mt-3 text-muted">Belum ada materi</h5>
                <p class="text-muted">Mulai dengan menambahkan materi pembelajaran pertama Anda</p>
                <a href="tambah_materi.php" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Materi Pertama
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">Judul</th>
                            <th width="20%">Mata Pelajaran</th>
                            <th width="10%">Latihan</th>
                            <th width="10%">Status</th>
                            <th width="10%">Urutan</th>
                            <th width="20%" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materi_list as $index => $materi): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($materi['judul']); ?></div>
                                    <?php if ($materi['file_attachment']): ?>
                                        <small class="text-muted">
                                            <i class="bi bi-paperclip"></i> <?php echo htmlspecialchars($materi['file_name']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($materi['nama_pelajaran']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $materi['jumlah_latihan']; ?> Latihan</span>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'draft' => 'bg-secondary',
                                        'aktif' => 'bg-success',
                                        'arsip' => 'bg-warning'
                                    ];
                                    $badge_class = $status_badges[$materi['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($materi['status']); ?></span>
                                </td>
                                <td><?php echo $materi['urutan']; ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="tambah_latihan.php?materi_id=<?php echo $materi['id']; ?>" class="btn btn-outline-primary" title="Tambah Latihan">
                                            <i class="bi bi-plus-circle"></i>
                                        </a>
                                        <a href="edit_materi.php?id=<?php echo $materi['id']; ?>" class="btn btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="submisi_latihan.php?materi_id=<?php echo $materi['id']; ?>" class="btn btn-outline-info" title="Lihat Submisi">
                                            <i class="bi bi-clipboard-check"></i>
                                        </a>
                                        <a href="?delete=<?php echo $materi['id']; ?>" class="btn btn-outline-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus materi ini? Semua latihan terkait juga akan dihapus.');">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<?php require_once '../../includes/footer.php'; ?>

