<?php
$page_title = 'Hasil Ujian';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['siswa']);

// Handle redirect BEFORE header output
$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$soal_id = $_GET['soal_id'] ?? 0;

// Get hasil ujian
$stmt = $conn->prepare("SELECT hu.*, s.judul, s.deskripsi, mp.nama_pelajaran 
    FROM hasil_ujian hu 
    JOIN soal s ON hu.soal_id = s.id 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE hu.soal_id = ? AND hu.siswa_id = ?");
$stmt->bind_param("ii", $soal_id, $siswa_id);
$stmt->execute();
$hasil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hasil) {
    $conn->close();
    header('Location: soal_saya.php');
    exit();
}

// Now include header AFTER handling redirect
require_once '../../includes/header.php';

// Pastikan koneksi database masih aktif
if (!isset($conn) || !$conn || $conn->ping() === false) {
    $conn = getConnection();
}

// Get item soal and jawaban
$stmt = $conn->prepare("SELECT * FROM item_soal WHERE soal_id = ? ORDER BY urutan");
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
    $stmt = $conn->prepare("SELECT * FROM pilihan_jawaban WHERE item_soal_id = ? ORDER BY urutan");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $pilihan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $item_soal[$key]['pilihan'] = $pilihan;
}

$conn->close();

// Show success message if redirected from submit
if (isset($_GET['success']) && $_GET['success'] == 1) {
    echo "<script>showSuccess('Soal berhasil dikerjakan!');</script>";
}
?>

<div class="page-header">
    <h2>Hasil Ujian</h2>
    <p><?php echo htmlspecialchars($hasil['judul']); ?> - <?php echo htmlspecialchars($hasil['nama_pelajaran']); ?></p>
</div>

<?php if (empty($item_soal)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Belum ada item soal untuk soal ini.
    </div>
<?php else: ?>

<!-- Summary Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Nilai</h5>
                <h2 class="text-primary"><?php echo number_format($hasil['nilai'], 2); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Poin Diperoleh</h5>
                <h2 class="text-success"><?php echo $hasil['poin_diperoleh']; ?> / <?php echo $hasil['total_poin']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Status</h5>
                <h4>
                    <span class="badge bg-<?php echo $hasil['status'] == 'selesai' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($hasil['status']); ?>
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
                                <div class="ms-4">
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
                                                    <i class="bi bi-x-circle-fill text-danger"></i> (Jawaban Anda)
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-<?php echo ($item['jawaban']['poin_diperoleh'] ?? 0) > 0 ? 'success' : 'secondary'; ?>">
                                    <strong>Jawaban Anda:</strong><br>
                                    <?php echo htmlspecialchars($item['jawaban']['jawaban'] ?? 'Tidak dijawab'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <a href="soal_saya.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Daftar Soal
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
