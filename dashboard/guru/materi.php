<?php
$page_title = 'Kelola Materi';
require_once '../../config/session.php';
require_once '../../config/database.php';
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

// Get all materi dengan info kelas
$stmt = $conn->prepare("SELECT m.*, mp.nama_pelajaran, k.nama_kelas, k.tingkat,
    (SELECT COUNT(*) FROM latihan WHERE materi_id = m.id) as jumlah_latihan
    FROM materi_pelajaran m
    JOIN mata_pelajaran mp ON m.mata_pelajaran_id = mp.id
    LEFT JOIN kelas k ON m.kelas_id = k.id
    WHERE m.guru_id = ?
    ORDER BY m.id ASC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$materi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
// Group by kelas
$materi_by_kelas = [];
foreach ($materi_list as $materi) {
    $kelas_key = $materi['kelas_id'] ?? 'no_class';
    $kelas_name = $materi['nama_kelas'] ?? 'Belum ada kelas';
    if (!isset($materi_by_kelas[$kelas_key])) {
        $materi_by_kelas[$kelas_key] = [
            'nama_kelas' => $kelas_name,
            'tingkat' => $materi['tingkat'] ?? null,
            'items' => []
        ];
    }
    $materi_by_kelas[$kelas_key]['items'][] = $materi;
}
// Sort by kelas name
uasort($materi_by_kelas, function($a, $b) {
    if ($a['tingkat'] != $b['tingkat']) {
        return ($a['tingkat'] ?? 0) - ($b['tingkat'] ?? 0);
    }
    return strcmp($a['nama_kelas'], $b['nama_kelas']);
});
?>

<div class="accordion" id="kelasAccordion">
    <?php foreach ($materi_by_kelas as $kelas_id => $kelas_data): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $kelas_id; ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $kelas_id; ?>" aria-expanded="false" aria-controls="collapse<?php echo $kelas_id; ?>">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($kelas_data['nama_kelas']); ?></strong>
                            <?php if ($kelas_data['tingkat']): ?>
                                <span class="text-muted ms-2">(Kelas <?php echo $kelas_data['tingkat']; ?>)</span>
                            <?php endif; ?>
                            <span class="text-muted ms-2">- Total <?php echo count($kelas_data['items']); ?> materi</span>
                        </div>
                        <div>
                            <a href="tambah_materi.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Upload Materi
                            </a>
                        </div>
                    </div>
                </button>
            </h2>
            <div id="collapse<?php echo $kelas_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $kelas_id; ?>" data-bs-parent="#kelasAccordion">
                <div class="accordion-body">
                    <?php foreach ($kelas_data['items'] as $index => $materi): ?>
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

