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
$stmt = $conn->prepare("SELECT * FROM item_soal WHERE soal_id = ? ORDER BY urutan ASC");
$stmt->bind_param("i", $soal_id);
$stmt->execute();
$item_soal = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get pilihan jawaban for each item
foreach ($item_soal as $key => $item) {
    $item_id = $item['id'];
    $stmt = $conn->prepare("SELECT * FROM pilihan_jawaban WHERE item_soal_id = ? ORDER BY urutan ASC");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $pilihan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $item_soal[$key]['pilihan'] = $pilihan;
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
    
    // Re-get item soal to ensure fresh data
    $stmt = $conn->prepare("SELECT * FROM item_soal WHERE soal_id = ? ORDER BY urutan ASC");
    $stmt->bind_param("i", $soal_id);
    $stmt->execute();
    $item_soal_fresh = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Process answers - collect all first, then process
    $jawaban_data = [];
    foreach ($item_soal_fresh as $item) {
        $item_id = $item['id'];
        $total_poin += $item['poin'];
        
        if ($item['jenis_jawaban'] == 'pilihan_ganda') {
            $pilihan_id = $_POST['jawaban'][$item_id] ?? 0;
            if ($pilihan_id > 0) {
                // Check if correct
                $stmt = $conn->prepare("SELECT is_benar FROM pilihan_jawaban WHERE id = ?");
                $stmt->bind_param("i", $pilihan_id);
                $stmt->execute();
                $pilihan = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $poin = $pilihan && $pilihan['is_benar'] ? $item['poin'] : 0;
                $poin_diperoleh += $poin;
                
                $jawaban_data[] = [
                    'item_id' => $item_id,
                    'pilihan_id' => $pilihan_id,
                    'poin' => $poin,
                    'type' => 'pilihan_ganda'
                ];
            }
        } else {
            $jawaban_text = $_POST['jawaban'][$item_id] ?? '';
            if (!empty(trim($jawaban_text))) {
                // For isian/essay, give full points (can be reviewed by teacher later)
                $poin = $item['poin'];
                $poin_diperoleh += $poin;
                
                $jawaban_data[] = [
                    'item_id' => $item_id,
                    'jawaban_text' => $jawaban_text,
                    'poin' => $poin,
                    'type' => 'text'
                ];
            }
        }
    }
    
    // Delete all existing answers for this soal and siswa first
    $stmt = $conn->prepare("DELETE FROM jawaban_siswa WHERE soal_id = ? AND siswa_id = ?");
    $stmt->bind_param("ii", $soal_id, $siswa_id);
    $stmt->execute();
    $stmt->close();
    
    // Insert all answers
    foreach ($jawaban_data as $jawaban) {
        if ($jawaban['type'] == 'pilihan_ganda') {
            $stmt = $conn->prepare("INSERT INTO jawaban_siswa (soal_id, siswa_id, item_soal_id, pilihan_jawaban_id, poin_diperoleh) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiii", $soal_id, $siswa_id, $jawaban['item_id'], $jawaban['pilihan_id'], $jawaban['poin']);
        } else {
            $stmt = $conn->prepare("INSERT INTO jawaban_siswa (soal_id, siswa_id, item_soal_id, jawaban, poin_diperoleh) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $soal_id, $siswa_id, $jawaban['item_id'], $jawaban['jawaban_text'], $jawaban['poin']);
        }
        $stmt->execute();
        $stmt->close();
    }
    
    // Calculate nilai
    $nilai = $total_poin > 0 ? ($poin_diperoleh / $total_poin) * 100 : 0;
    $waktu_selesai = date('Y-m-d H:i:s');
    
    // Update hasil_ujian
    $stmt = $conn->prepare("UPDATE hasil_ujian SET total_poin = ?, poin_diperoleh = ?, nilai = ?, status = 'selesai', waktu_selesai = ? WHERE soal_id = ? AND siswa_id = ?");
    $stmt->bind_param("iidsii", $total_poin, $poin_diperoleh, $nilai, $waktu_selesai, $soal_id, $siswa_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->close();
    
    // Redirect dengan parameter success
    header('Location: hasil.php?soal_id=' . $soal_id . '&success=1');
    exit();
}

$conn->close();
?>

<div class="page-header">
    <h2><?php echo htmlspecialchars($soal['judul']); ?></h2>
    <p>Mata Pelajaran: <?php echo htmlspecialchars($soal['nama_pelajaran']); ?> | Waktu: <?php echo $soal['waktu_pengerjaan']; ?> menit</p>
</div>

<?php if (!empty($soal['deskripsi'])): ?>
    <div class="alert alert-info">
        <strong>Deskripsi:</strong> <?php echo htmlspecialchars($soal['deskripsi']); ?>
    </div>
<?php endif; ?>

<?php if (empty($item_soal)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Belum ada pertanyaan untuk soal ini.
    </div>
<?php else: ?>
<form method="POST" id="soalForm">
    <?php foreach ($item_soal as $index => $item): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Pertanyaan <?php echo $index + 1; ?></h5>
                <span class="badge bg-primary"><?php echo $item['poin']; ?> Poin</span>
            </div>
            <div class="card-body">
                <p class="mb-3"><strong><?php echo htmlspecialchars($item['pertanyaan']); ?></strong></p>
                
                <?php if ($item['jenis_jawaban'] == 'pilihan_ganda' && !empty($item['pilihan'])): ?>
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
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>

