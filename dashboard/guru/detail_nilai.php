<?php
$page_title = 'Detail & Nilai Ujian';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$soal_id = $_GET['soal_id'] ?? 0;
$siswa_id = $_GET['siswa_id'] ?? 0;
$message = '';

// Verify soal belongs to this guru
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE s.id = ? AND s.guru_id = ?");
$stmt->bind_param("ii", $soal_id, $guru_id);
$stmt->execute();
$soal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$soal) {
    header('Location: soal.php');
    exit();
}

// Get siswa info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'siswa'");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$siswa) {
    header('Location: nilai_ujian.php?soal_id=' . $soal_id);
    exit();
}

// Get hasil ujian
$stmt = $conn->prepare("SELECT * FROM hasil_ujian WHERE soal_id = ? AND siswa_id = ?");
$stmt->bind_param("ii", $soal_id, $siswa_id);
$stmt->execute();
$hasil = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle update nilai
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_nilai') {
    $jawaban_id = $_POST['jawaban_id'];
    $poin_baru = (int)$_POST['poin'];
    
    // Get item soal untuk validasi poin maksimal
    $stmt = $conn->prepare("SELECT poin FROM item_soal WHERE id = (SELECT item_soal_id FROM jawaban_siswa WHERE id = ?)");
    $stmt->bind_param("i", $jawaban_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($item && $poin_baru >= 0 && $poin_baru <= $item['poin']) {
        // Update poin di jawaban_siswa
        $stmt = $conn->prepare("UPDATE jawaban_siswa SET poin_diperoleh = ? WHERE id = ?");
        $stmt->bind_param("ii", $poin_baru, $jawaban_id);
        $stmt->execute();
        $stmt->close();
        
        // Recalculate total poin and nilai
        $stmt = $conn->prepare("SELECT 
            SUM(is.poin) as total_poin,
            COALESCE(SUM(js.poin_diperoleh), 0) as poin_diperoleh
            FROM item_soal is
            LEFT JOIN jawaban_siswa js ON is.id = js.item_soal_id AND js.siswa_id = ?
            WHERE is.soal_id = ?");
        $stmt->bind_param("ii", $siswa_id, $soal_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $total_poin = $result['total_poin'] ?? 0;
        $poin_diperoleh = $result['poin_diperoleh'] ?? 0;
        $nilai = $total_poin > 0 ? ($poin_diperoleh / $total_poin) * 100 : 0;
        
        // Update hasil_ujian
        $stmt = $conn->prepare("UPDATE hasil_ujian SET total_poin = ?, poin_diperoleh = ?, nilai = ? WHERE soal_id = ? AND siswa_id = ?");
        $stmt->bind_param("iiddi", $total_poin, $poin_diperoleh, $nilai, $soal_id, $siswa_id);
        $stmt->execute();
        $stmt->close();
        
        $message = 'success:Nilai berhasil diupdate!';
        // Refresh hasil
        $stmt = $conn->prepare("SELECT * FROM hasil_ujian WHERE soal_id = ? AND siswa_id = ?");
        $stmt->bind_param("ii", $soal_id, $siswa_id);
        $stmt->execute();
        $hasil = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $message = 'error:Poin tidak valid!';
    }
}

// Get item soal and jawaban
$stmt = $conn->prepare("SELECT * FROM item_soal WHERE soal_id = ? ORDER BY urutan ASC");
$stmt->bind_param("i", $soal_id);
$stmt->execute();
$item_soal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get jawaban dan pilihan untuk setiap item
foreach ($item_soal as $key => $item) {
    $item_id = $item['id'];
    
    // Get jawaban siswa
    $stmt = $conn->prepare("SELECT js.*, pj.pilihan as pilihan_text, pj.is_benar 
        FROM jawaban_siswa js 
        LEFT JOIN pilihan_jawaban pj ON js.pilihan_jawaban_id = pj.id 
        WHERE js.soal_id = ? AND js.siswa_id = ? AND js.item_soal_id = ?");
    $stmt->bind_param("iii", $soal_id, $siswa_id, $item_id);
    $stmt->execute();
    $jawaban = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $item_soal[$key]['jawaban'] = $jawaban;
    
    // Get all pilihan for display
    $stmt = $conn->prepare("SELECT * FROM pilihan_jawaban WHERE item_soal_id = ? ORDER BY urutan ASC");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $pilihan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $item_soal[$key]['pilihan'] = $pilihan;
}

$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "showSuccess('" . addslashes($msg[1]) . "');";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2>Detail & Nilai Ujian</h2>
            <p><?php echo htmlspecialchars($soal['judul']); ?> - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></p>
        </div>
        <a href="nilai_ujian.php?soal_id=<?php echo $soal_id; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<!-- Summary Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Nilai</h5>
                <h2 class="text-primary"><?php echo number_format($hasil['nilai'] ?? 0, 2); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Poin Diperoleh</h5>
                <h2 class="text-success"><?php echo $hasil['poin_diperoleh'] ?? 0; ?> / <?php echo $hasil['total_poin'] ?? 0; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Status</h5>
                <h4>
                    <span class="badge bg-<?php echo ($hasil['status'] ?? '') == 'selesai' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($hasil['status'] ?? 'belum_selesai'); ?>
                    </span>
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Detail Jawaban -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Detail Jawaban</h5>
            </div>
            <div class="card-body">
                <?php foreach ($item_soal as $index => $item): ?>
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Pertanyaan <?php echo $index + 1; ?></h6>
                            <span class="badge bg-<?php echo ($item['jawaban']['poin_diperoleh'] ?? 0) > 0 ? 'success' : 'danger'; ?>">
                                <?php echo $item['jawaban']['poin_diperoleh'] ?? 0; ?> / <?php echo $item['poin']; ?> Poin
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="mb-3"><strong><?php echo htmlspecialchars($item['pertanyaan']); ?></strong></p>
                            
                            <?php if ($item['jenis_jawaban'] == 'pilihan_ganda' && !empty($item['pilihan'])): ?>
                                <div class="ms-4 mb-3">
                                    <?php foreach ($item['pilihan'] as $pilihan): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" disabled
                                                <?php echo (isset($item['jawaban']['pilihan_jawaban_id']) && $item['jawaban']['pilihan_jawaban_id'] == $pilihan['id']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label <?php 
                                                if ($pilihan['is_benar']) echo 'text-success fw-bold';
                                                if (isset($item['jawaban']['pilihan_jawaban_id']) && $item['jawaban']['pilihan_jawaban_id'] == $pilihan['id'] && !$pilihan['is_benar']) echo 'text-danger';
                                            ?>">
                                                <?php echo htmlspecialchars($pilihan['pilihan']); ?>
                                                <?php if ($pilihan['is_benar']): ?>
                                                    <i class="bi bi-check-circle-fill text-success"></i> (Benar)
                                                <?php endif; ?>
                                                <?php if (isset($item['jawaban']['pilihan_jawaban_id']) && $item['jawaban']['pilihan_jawaban_id'] == $pilihan['id'] && !$pilihan['is_benar']): ?>
                                                    <i class="bi bi-x-circle-fill text-danger"></i> (Jawaban Siswa)
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if ($item['jawaban'] && $item['jawaban']['poin_diperoleh'] > 0): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle"></i> Jawaban benar! Poin: <?php echo $item['jawaban']['poin_diperoleh']; ?>/<?php echo $item['poin']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-danger">
                                        <i class="bi bi-x-circle"></i> Jawaban salah! Poin: 0/<?php echo $item['poin']; ?>
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <!-- Isian/Essay - bisa dinilai manual -->
                                <div class="alert alert-<?php echo ($item['jawaban']['poin_diperoleh'] ?? 0) > 0 ? 'success' : 'secondary'; ?> mb-3">
                                    <strong>Jawaban Siswa:</strong><br>
                                    <div class="mt-2 p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($item['jawaban']['jawaban'] ?? 'Tidak dijawab')); ?>
                                    </div>
                                </div>
                                
                                <?php if ($item['jawaban']): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_nilai">
                                        <input type="hidden" name="jawaban_id" value="<?php echo $item['jawaban']['id']; ?>">
                                        <div class="row align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Berikan Poin (0 - <?php echo $item['poin']; ?>)</label>
                                                <input type="number" class="form-control" name="poin" 
                                                    value="<?php echo $item['jawaban']['poin_diperoleh'] ?? 0; ?>" 
                                                    min="0" max="<?php echo $item['poin']; ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save"></i> Simpan Poin
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <a href="nilai_ujian.php?soal_id=<?php echo $soal_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Daftar Hasil Ujian
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

