<?php
$page_title = 'Kelola Materi';
require_once '../../config/session.php';
requireRole(['guru']);

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Handle delete - MUST be before header.php to prevent "headers already sent" error
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

require_once '../../includes/header.php';

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

<?php if (empty($materi_list)): ?>
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-journal-text text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
        <h5 class="mt-3 text-muted">Belum ada materi</h5>
        <p class="text-muted">Mulai dengan menambahkan materi pembelajaran pertama Anda</p>
        <a href="tambah_materi.php" class="btn btn-primary mt-2">
            <i class="bi bi-plus-circle me-1"></i> Tambah Materi Pertama
        </a>
    </div>
</div>
<?php else: ?>
<?php
// Group by pertemuan (urutan). Pertemuan = urutan+1
$pertemuan_list = [];
foreach ($materi_list as $materi) {
    $pert = intval($materi['urutan']);
    if (!isset($pertemuan_list[$pert])) {
        $pertemuan_list[$pert] = [];
    }
    $pertemuan_list[$pert][] = $materi;
}
ksort($pertemuan_list);
?>

<div class="accordion" id="pertemuanAccordion">
    <?php foreach ($pertemuan_list as $pert => $items): ?>
        <?php $pertemuan_ke = $pert + 1; ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $pert; ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $pert; ?>" aria-expanded="false" aria-controls="collapse<?php echo $pert; ?>">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <div>
                            <strong>Pertemuan <?php echo $pertemuan_ke; ?></strong>
                            <span class="text-muted ms-2">Total <?php echo count($items); ?> materi</span>
                        </div>
                        <div>
                            <a href="tambah_materi.php?urutan=<?php echo $pert; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Upload Materi
                            </a>
                        </div>
                    </div>
                </button>
            </h2>
            <div id="collapse<?php echo $pert; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $pert; ?>" data-bs-parent="#pertemuanAccordion">
                <div class="accordion-body">
                    <?php foreach ($items as $index => $materi): ?>
                        <div class="card shadow-sm mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($materi['judul']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($materi['nama_pelajaran']); ?></div>
                                        <?php if ($materi['file_attachment']): ?>
                                            <small class="text-muted d-block mt-1"><i class="bi bi-paperclip"></i> <?php echo htmlspecialchars($materi['file_name']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="tambah_latihan.php?materi_id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-outline-primary" title="Tambah Latihan">
                                            <i class="bi bi-plus-circle"></i>
                                        </a>
                                        <a href="edit_materi.php?id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="submisi_latihan.php?materi_id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-outline-info" title="Lihat Submisi">
                                            <i class="bi bi-clipboard-check"></i>
                                        </a>
                                        <a href="?delete=<?php echo $materi['id']; ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus materi ini? Semua latihan terkait juga akan dihapus.');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="badge bg-info"><?php echo $materi['jumlah_latihan']; ?> Latihan</span>
                                    <?php
                                    $status_badges = [
                                        'draft' => 'bg-secondary',
                                        'aktif' => 'bg-success',
                                        'arsip' => 'bg-warning'
                                    ];
                                    $badge_class = $status_badges[$materi['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($materi['status']); ?></span>
                                    <span class="badge bg-light text-dark">Urutan: <?php echo $materi['urutan']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>

