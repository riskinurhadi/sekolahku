<?php
$page_title = 'Submisi Latihan';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
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

// Get all latihan for this materi
$latihan_list = $conn->prepare("SELECT * FROM latihan WHERE materi_id = ? ORDER BY created_at DESC");
$latihan_list->bind_param("i", $materi_id);
$latihan_list->execute();
$latihan_list = $latihan_list->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Submisi Latihan</h2>
        <p class="text-muted mb-0">Materi: <?php echo htmlspecialchars($materi['judul']); ?></p>
    </div>
    <a href="materi.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($latihan_list)): ?>
            <div class="text-center py-4">
                <i class="bi bi-clipboard-check text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="text-muted mt-3 mb-0">Belum ada latihan untuk materi ini</p>
            </div>
        <?php else: ?>
            <div class="accordion" id="latihanAccordion">
                <?php foreach ($latihan_list as $index => $latihan): ?>
                    <?php
                    if ($latihan['jenis'] == 'tugas_file') {
                        // Get submisi tugas
                        $stmt = $conn->prepare("SELECT st.*, u.nama_lengkap, u.nis FROM submisi_tugas st 
                            JOIN users u ON st.siswa_id = u.id 
                            WHERE st.latihan_id = ? ORDER BY st.submitted_at DESC");
                        $stmt->bind_param("i", $latihan['id']);
                        $stmt->execute();
                        $submisi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                    } else {
                        // Get submisi soal
                        $stmt = $conn->prepare("SELECT sls.*, u.nama_lengkap, u.nis FROM submisi_latihan_soal sls 
                            JOIN users u ON sls.siswa_id = u.id 
                            WHERE sls.latihan_id = ? ORDER BY sls.waktu_selesai DESC");
                        $stmt->bind_param("i", $latihan['id']);
                        $stmt->execute();
                        $submisi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                    }
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo $index == 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#latihan<?php echo $latihan['id']; ?>">
                                <div class="w-100 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($latihan['judul']); ?></strong>
                                        <span class="badge bg-<?php echo $latihan['jenis'] == 'tugas_file' ? 'primary' : 'success'; ?> ms-2">
                                            <?php echo $latihan['jenis'] == 'tugas_file' ? 'Tugas File' : 'Soal'; ?>
                                        </span>
                                    </div>
                                    <span class="badge bg-info"><?php echo count($submisi); ?> Submisi</span>
                                </div>
                            </button>
                        </h2>
                        <div id="latihan<?php echo $latihan['id']; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" data-bs-parent="#latihanAccordion">
                            <div class="accordion-body">
                                <?php if (empty($submisi)): ?>
                                    <p class="text-muted text-center py-3">Belum ada submisi</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>NIS</th>
                                                    <th>Nama Siswa</th>
                                                    <?php if ($latihan['jenis'] == 'tugas_file'): ?>
                                                        <th>File</th>
                                                        <th>Tanggal Submit</th>
                                                        <th>Nilai</th>
                                                        <th>Status</th>
                                                        <th>Aksi</th>
                                                    <?php else: ?>
                                                        <th>Nilai</th>
                                                        <th>Soal Benar</th>
                                                        <th>Waktu Selesai</th>
                                                        <th>Status</th>
                                                        <th>Aksi</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($submisi as $s): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($s['nis']); ?></td>
                                                        <td><?php echo htmlspecialchars($s['nama_lengkap']); ?></td>
                                                        <?php if ($latihan['jenis'] == 'tugas_file'): ?>
                                                            <td>
                                                                <a href="../../uploads/tugas/<?php echo htmlspecialchars($s['file_path']); ?>" target="_blank">
                                                                    <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($s['file_name']); ?>
                                                                </a>
                                                            </td>
                                                            <td><?php echo date('d/m/Y H:i', strtotime($s['submitted_at'])); ?></td>
                                                            <td>
                                                                <?php if ($s['nilai'] !== null): ?>
                                                                    <strong><?php echo number_format($s['nilai'], 2); ?></strong>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $status_badges = [
                                                                    'menunggu' => 'bg-warning',
                                                                    'dinilai' => 'bg-info',
                                                                    'selesai' => 'bg-success',
                                                                    'ditolak' => 'bg-danger'
                                                                ];
                                                                $badge_class = $status_badges[$s['status']] ?? 'bg-secondary';
                                                                ?>
                                                                <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($s['status']); ?></span>
                                                            </td>
                                                            <td>
                                                                <a href="nilai_submisi.php?id=<?php echo $s['id']; ?>&type=tugas" class="btn btn-sm btn-primary">
                                                                    <i class="bi bi-pencil"></i> Nilai
                                                                </a>
                                                            </td>
                                                        <?php else: ?>
                                                            <td>
                                                                <strong><?php echo $s['nilai'] ? number_format($s['nilai'], 2) : '-'; ?></strong>
                                                            </td>
                                                            <td><?php echo $s['soal_benar']; ?> / <?php echo $s['total_soal']; ?></td>
                                                            <td><?php echo $s['waktu_selesai'] ? date('d/m/Y H:i', strtotime($s['waktu_selesai'])) : '-'; ?></td>
                                                            <td>
                                                                <?php
                                                                $status_badges = [
                                                                    'belum_mulai' => 'bg-secondary',
                                                                    'sedang_mengerjakan' => 'bg-warning',
                                                                    'selesai' => 'bg-success',
                                                                    'terlambat' => 'bg-danger'
                                                                ];
                                                                $badge_class = $status_badges[$s['status']] ?? 'bg-secondary';
                                                                ?>
                                                                <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $s['status'])); ?></span>
                                                            </td>
                                                            <td>
                                                                <a href="detail_submisi_soal.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-info">
                                                                    <i class="bi bi-eye"></i> Detail
                                                                </a>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

