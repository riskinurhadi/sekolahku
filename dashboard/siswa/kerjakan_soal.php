<?php
$page_title = 'Kerjakan Soal';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$soal_id = $_GET['id'] ?? 0;

// Get soal details
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran 
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE s.id = ? AND s.status = 'aktif'");
$stmt->bind_param("i", $soal_id);
$stmt->execute();
$soal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$soal) {
    header('Location: soal_saya.php');
    exit();
}

// Check if already done
$stmt = $conn->prepare("SELECT * FROM hasil_ujian WHERE soal_id = ? AND siswa_id = ? AND status = 'selesai'");
$stmt->bind_param("ii", $soal_id, $siswa_id);
$stmt->execute();
$hasil_exist = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($hasil_exist) {
    header('Location: hasil.php?soal_id=' . $soal_id);
    exit();
}

// Get item soal
$item_soal = $conn->query("SELECT * FROM item_soal WHERE soal_id = $soal_id ORDER BY urutan")->fetch_all(MYSQLI_ASSOC);

// Get pilihan jawaban for each item
foreach ($item_soal as &$item) {
    $item_id = $item['id'];
    $pilihan = $conn->query("SELECT * FROM pilihan_jawaban WHERE item_soal_id = $item_id ORDER BY urutan")->fetch_all(MYSQLI_ASSOC);
    $item['pilihan'] = $pilihan;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Create or update hasil_ujian
    $waktu_mulai = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO hasil_ujian (soal_id, siswa_id, waktu_mulai, status) VALUES (?, ?, ?, 'belum_selesai') 
        ON DUPLICATE KEY UPDATE waktu_mulai = ?");
    $stmt->bind_param("iiss", $soal_id, $siswa_id, $waktu_mulai, $waktu_mulai);
    $stmt->execute();
    $stmt->close();
    
    $total_poin = 0;
    $poin_diperoleh = 0;
    
    // Process answers
    foreach ($item_soal as $item) {
        $item_id = $item['id'];
        $total_poin += $item['poin'];
        
        // Delete existing answer
        $conn->query("DELETE FROM jawaban_siswa WHERE soal_id = $soal_id AND siswa_id = $siswa_id AND item_soal_id = $item_id");
        
        if ($item['jenis_jawaban'] == 'pilihan_ganda') {
            $pilihan_id = $_POST['jawaban'][$item_id] ?? 0;
            if ($pilihan_id > 0) {
                // Check if correct
                $stmt = $conn->prepare("SELECT is_benar FROM pilihan_jawaban WHERE id = ?");
                $stmt->bind_param("i", $pilihan_id);
                $stmt->execute();
                $pilihan = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $poin = $pilihan['is_benar'] ? $item['poin'] : 0;
                $poin_diperoleh += $poin;
                
                $stmt = $conn->prepare("INSERT INTO jawaban_siswa (soal_id, siswa_id, item_soal_id, pilihan_jawaban_id, poin_diperoleh) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiii", $soal_id, $siswa_id, $item_id, $pilihan_id, $poin);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $jawaban_text = $_POST['jawaban'][$item_id] ?? '';
            if (!empty($jawaban_text)) {
                // For isian/essay, give full points (can be reviewed by teacher later)
                $poin = $item['poin'];
                $poin_diperoleh += $poin;
                
                $stmt = $conn->prepare("INSERT INTO jawaban_siswa (soal_id, siswa_id, item_soal_id, jawaban, poin_diperoleh) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iissi", $soal_id, $siswa_id, $item_id, $jawaban_text, $poin);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    // Calculate nilai
    $nilai = $total_poin > 0 ? ($poin_diperoleh / $total_poin) * 100 : 0;
    $waktu_selesai = date('Y-m-d H:i:s');
    
    // Update hasil_ujian
    $stmt = $conn->prepare("UPDATE hasil_ujian SET total_poin = ?, poin_diperoleh = ?, nilai = ?, status = 'selesai', waktu_selesai = ? WHERE soal_id = ? AND siswa_id = ?");
    $stmt->bind_param("iidssii", $total_poin, $poin_diperoleh, $nilai, $waktu_selesai, $soal_id, $siswa_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->close();
    
    echo "<script>
        showSuccess('Soal berhasil dikerjakan!');
        setTimeout(function() {
            window.location.href = 'hasil.php?soal_id=$soal_id';
        }, 1500);
    </script>";
    exit();
}

$conn->close();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0"><?php echo htmlspecialchars($soal['judul']); ?></h2>
        <p class="text-muted">Mata Pelajaran: <?php echo htmlspecialchars($soal['nama_pelajaran']); ?> | Waktu: <?php echo $soal['waktu_pengerjaan']; ?> menit</p>
    </div>
</div>

<?php if (!empty($soal['deskripsi'])): ?>
    <div class="alert alert-info">
        <strong>Deskripsi:</strong> <?php echo htmlspecialchars($soal['deskripsi']); ?>
    </div>
<?php endif; ?>

<form method="POST" id="soalForm">
    <?php foreach ($item_soal as $index => $item): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Pertanyaan <?php echo $index + 1; ?> <span class="badge bg-primary"><?php echo $item['poin']; ?> Poin</span></h5>
            </div>
            <div class="card-body">
                <p class="mb-3"><strong><?php echo htmlspecialchars($item['pertanyaan']); ?></strong></p>
                
                <?php if ($item['jenis_jawaban'] == 'pilihan_ganda' && count($item['pilihan']) > 0): ?>
                    <div class="ms-4">
                        <?php foreach ($item['pilihan'] as $pilihan): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="jawaban[<?php echo $item['id']; ?>]" 
                                    id="pilihan_<?php echo $pilihan['id']; ?>" value="<?php echo $pilihan['id']; ?>" required>
                                <label class="form-check-label" for="pilihan_<?php echo $pilihan['id']; ?>">
                                    <?php echo htmlspecialchars($pilihan['pilihan']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <textarea class="form-control" name="jawaban[<?php echo $item['id']; ?>]" rows="4" 
                            placeholder="Tulis jawaban Anda di sini..." required></textarea>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="text-center mb-4">
        <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Apakah Anda yakin ingin mengirim jawaban? Pastikan semua pertanyaan sudah dijawab.');">
            <i class="bi bi-check-circle"></i> Kirim Jawaban
        </button>
        <a href="soal_saya.php" class="btn btn-secondary btn-lg">
            <i class="bi bi-x-circle"></i> Batal
        </a>
    </div>
</form>

<?php require_once '../../includes/footer.php'; ?>

